<?php
$pageTitle = 'Admin Settings';
require_once 'config/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

$pdo = getDBConnection();
$adminId = (int)$_SESSION['user_id'];
$success = '';
$error = '';

try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS app_settings (
            setting_key VARCHAR(120) PRIMARY KEY,
            setting_value TEXT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
} catch (Throwable $e) {
    // ignore table creation failure
}

function getSetting(PDO $pdo, string $key, string $default = ''): string
{
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM app_settings WHERE setting_key = ? LIMIT 1");
        $stmt->execute([$key]);
        $value = $stmt->fetchColumn();
        return $value !== false ? (string)$value : $default;
    } catch (Throwable $e) {
        return $default;
    }
}

function setSetting(PDO $pdo, string $key, string $value): void
{
    $stmt = $pdo->prepare("
        INSERT INTO app_settings (setting_key, setting_value)
        VALUES (?, ?)
        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
    ");
    $stmt->execute([$key, $value]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');
    try {
        if ($action === 'save_profile') {
            $fullName = trim((string)($_POST['full_name'] ?? ''));
            $email = trim((string)($_POST['email'] ?? ''));
            $phone = trim((string)($_POST['phone'] ?? ''));
            if ($fullName === '' || $email === '') {
                throw new RuntimeException('Name and email are required.');
            }
            $dup = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1");
            $dup->execute([$email, $adminId]);
            if ($dup->fetch()) {
                throw new RuntimeException('Email is already used by another account.');
            }
            $upd = $pdo->prepare("UPDATE users SET full_name = ?, email = ?, phone = ? WHERE id = ? AND role = 'admin'");
            $upd->execute([$fullName, $email, $phone, $adminId]);
            $_SESSION['full_name'] = $fullName;
            $success = 'Admin profile updated.';
        } elseif ($action === 'save_website') {
            setSetting($pdo, 'site_name', trim((string)($_POST['site_name'] ?? 'ServiceLink')));
            setSetting($pdo, 'site_tagline', trim((string)($_POST['site_tagline'] ?? '')));
            setSetting($pdo, 'support_email', trim((string)($_POST['support_email'] ?? '')));
            setSetting($pdo, 'maintenance_mode', !empty($_POST['maintenance_mode']) ? '1' : '0');
            $success = 'Website settings updated.';
        } elseif ($action === 'save_theme') {
            $theme = trim((string)($_POST['admin_theme'] ?? 'light'));
            $accent = trim((string)($_POST['admin_accent'] ?? '#3A86FF'));
            if (!in_array($theme, ['light', 'dark', 'auto'], true)) {
                $theme = 'light';
            }
            if (!preg_match('/^#[0-9a-fA-F]{6}$/', $accent)) {
                $accent = '#3A86FF';
            }
            setSetting($pdo, 'admin_theme', $theme);
            setSetting($pdo, 'admin_accent', strtoupper($accent));
            $success = 'Theme settings updated.';
        } elseif ($action === 'save_security') {
            $sessionTimeout = max(5, min(240, (int)($_POST['admin_session_timeout'] ?? 30)));
            $maxAttempts = max(3, min(20, (int)($_POST['max_login_attempts'] ?? 8)));
            $lockoutMins = max(5, min(180, (int)($_POST['lockout_minutes'] ?? 15)));
            setSetting($pdo, 'admin_session_timeout', (string)$sessionTimeout);
            setSetting($pdo, 'max_login_attempts', (string)$maxAttempts);
            setSetting($pdo, 'lockout_minutes', (string)$lockoutMins);
            setSetting($pdo, 'security_alerts_email', !empty($_POST['security_alerts_email']) ? '1' : '0');
            $success = 'Security settings updated.';
        } elseif ($action === 'change_password') {
            $currentPassword = (string)($_POST['current_password'] ?? '');
            $newPassword = (string)($_POST['new_password'] ?? '');
            $confirmPassword = (string)($_POST['confirm_password'] ?? '');
            if ($newPassword === '' || strlen($newPassword) < 8) {
                throw new RuntimeException('New password must be at least 8 characters.');
            }
            if ($newPassword !== $confirmPassword) {
                throw new RuntimeException('New password and confirmation do not match.');
            }
            $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ? AND role = 'admin' LIMIT 1");
            $stmt->execute([$adminId]);
            $hash = (string)$stmt->fetchColumn();
            if ($hash === '' || !password_verify($currentPassword, $hash)) {
                throw new RuntimeException('Current password is incorrect.');
            }
            $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ? AND role = 'admin'")->execute([$newHash, $adminId]);
            $success = 'Password changed successfully.';
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$adminStmt = $pdo->prepare("SELECT id, full_name, email, phone, created_at FROM users WHERE id = ? AND role = 'admin' LIMIT 1");
$adminStmt->execute([$adminId]);
$admin = $adminStmt->fetch() ?: ['full_name' => '', 'email' => '', 'phone' => '', 'created_at' => null];

$settings = [
    'site_name' => getSetting($pdo, 'site_name', 'ServiceLink'),
    'site_tagline' => getSetting($pdo, 'site_tagline', ''),
    'support_email' => getSetting($pdo, 'support_email', ''),
    'maintenance_mode' => getSetting($pdo, 'maintenance_mode', '0'),
    'admin_theme' => getSetting($pdo, 'admin_theme', 'light'),
    'admin_accent' => getSetting($pdo, 'admin_accent', '#3A86FF'),
    'admin_session_timeout' => getSetting($pdo, 'admin_session_timeout', '30'),
    'max_login_attempts' => getSetting($pdo, 'max_login_attempts', '8'),
    'lockout_minutes' => getSetting($pdo, 'lockout_minutes', '15'),
    'security_alerts_email' => getSetting($pdo, 'security_alerts_email', '0'),
];

require_once 'includes/header.php';
?>
<section class="admin-shell">
    <aside class="admin-side">
        <div class="admin-side-brand">ServiceLink</div>
        <a href="admin_dashboard.php" class="admin-nav-link">Dashboard</a>
        <a href="admin_providers.php" class="admin-nav-link">Providers</a>
        <a href="admin_users.php" class="admin-nav-link">Users</a>
        <a href="admin_bookings.php" class="admin-nav-link">Bookings</a>
        <a href="admin_reports.php" class="admin-nav-link">Reports</a>
        <a href="admin_reviews.php" class="admin-nav-link">Reviews</a>
        <a href="admin_transactions.php" class="admin-nav-link">Transactions</a>
        <a href="admin_settings.php" class="admin-nav-link active">Settings</a>
        <a href="logout.php" class="admin-nav-link">Log out</a>
        <div class="admin-side-user">Admin</div>
    </aside>

    <div class="admin-main">
        <div class="admin-topbar">
            <h1>Settings</h1>
            <div class="admin-user-chip">Admin</div>
        </div>

        <style>
            /* Admin Settings UI Improvements */
            .admin-panel-card {
                background: #ffffff;
                border-radius: 12px;
                box-shadow: 0 4px 20px rgba(0,0,0,0.03);
                border: 1px solid #f1f5f9;
                padding: 32px;
                margin-bottom: 24px;
            }
            .admin-card-head {
                margin-bottom: 24px;
                display: flex;
                align-items: center;
                gap: 12px;
                border-bottom: 1px solid #f1f5f9;
                padding-bottom: 16px;
            }
            .admin-card-head h2 {
                font-size: 1.25rem;
                font-weight: 600;
                color: #0f172a;
                margin: 0;
            }
            
            .admin-settings-form {
                display: flex;
                flex-direction: column;
                gap: 20px;
            }
            .form-row {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
                gap: 20px;
            }
            .form-group {
                display: flex;
                flex-direction: column;
                gap: 8px;
            }
            .form-group label {
                font-size: 0.9rem;
                font-weight: 600;
                color: #475569;
            }
            .form-control {
                width: 100%;
                padding: 10px 16px;
                border: 1px solid #e2e8f0;
                border-radius: 8px;
                font-size: 0.95rem;
                color: #1e293b;
                outline: none;
                transition: all 0.2s;
                background: #f8fafc;
            }
            .form-control:focus {
                background: #fff;
                border-color: #3b82f6;
                box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
            }
            select.form-control {
                appearance: none;
                background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%2364748b'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E");
                background-repeat: no-repeat;
                background-position: right 16px center;
                background-size: 16px;
            }
            input[type="color"].form-control {
                padding: 4px;
                height: 42px;
                cursor: pointer;
            }
            
            .checkbox-label {
                display: flex;
                align-items: center;
                gap: 10px;
                font-size: 0.9rem;
                font-weight: 500;
                color: #475569;
                cursor: pointer;
                margin-top: 4px;
            }
            .checkbox-label input[type="checkbox"] {
                width: 18px;
                height: 18px;
                border-radius: 4px;
                border: 1px solid #cbd5e1;
                accent-color: #3b82f6;
                cursor: pointer;
            }

            .btn-primary {
                background: #3b82f6;
                color: #fff;
                padding: 10px 24px;
                border-radius: 8px;
                font-weight: 500;
                font-size: 0.95rem;
                border: none;
                cursor: pointer;
                transition: all 0.2s;
                align-self: flex-start;
                display: inline-flex;
                align-items: center;
                gap: 8px;
                margin-top: 8px;
            }
            .btn-primary:hover {
                background: #2563eb;
                box-shadow: 0 4px 12px rgba(37, 99, 235, 0.2);
            }
            .btn-primary svg {
                width: 18px;
                height: 18px;
            }
            
            .alert-success, .alert-error {
                padding: 16px 20px;
                border-radius: 8px;
                margin-bottom: 24px;
                font-size: 0.95rem;
                display: flex;
                align-items: center;
                gap: 10px;
                font-weight: 500;
            }
            .alert-success { background: #ecfdf5; color: #059669; border: 1px solid #a7f3d0; }
            .alert-error { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }
        </style>

        <?php if ($success): ?>
            <div class="alert-success">
                <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert-error">
                <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <div class="admin-dashboard-insights">
            <div class="admin-panel-card">
                <div class="admin-card-head">
                    <h2>Admin Profile Settings</h2>
                </div>
                <form method="POST" class="admin-settings-form">
                    <input type="hidden" name="action" value="save_profile">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Full Name</label>
                            <input type="text" name="full_name" class="form-control" value="<?= htmlspecialchars((string)$admin['full_name']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Email Address</label>
                            <input type="email" name="email" class="form-control" value="<?= htmlspecialchars((string)$admin['email']) ?>" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Phone Number</label>
                            <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars((string)$admin['phone']) ?>">
                        </div>
                        <div class="form-group" style="justify-content: center;">
                            <p class="small-muted" style="margin: 0;">Admin since <?= !empty($admin['created_at']) ? htmlspecialchars(date('M d, Y', strtotime((string)$admin['created_at']))) : 'N/A' ?></p>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn-primary">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"></path></svg>
                        Save Profile
                    </button>
                </form>
            </div>

            <div class="admin-panel-card">
                <div class="admin-card-head">
                    <h2>Security Settings</h2>
                </div>
                <form method="POST" class="admin-settings-form">
                    <input type="hidden" name="action" value="save_security">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Session Timeout (minutes)</label>
                            <input type="number" name="admin_session_timeout" class="form-control" min="5" max="240" value="<?= (int)$settings['admin_session_timeout'] ?>">
                        </div>
                        <div class="form-group">
                            <label>Max Login Attempts</label>
                            <input type="number" name="max_login_attempts" class="form-control" min="3" max="20" value="<?= (int)$settings['max_login_attempts'] ?>">
                        </div>
                        <div class="form-group">
                            <label>Lockout Duration (minutes)</label>
                            <input type="number" name="lockout_minutes" class="form-control" min="5" max="180" value="<?= (int)$settings['lockout_minutes'] ?>">
                        </div>
                    </div>
                    
                    <label class="checkbox-label">
                        <input type="checkbox" name="security_alerts_email" value="1" <?= $settings['security_alerts_email'] === '1' ? 'checked' : '' ?>>
                        Email me about unusual security events
                    </label>
                    
                    <button type="submit" class="btn-primary">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                        Save Security Settings
                    </button>
                </form>
            </div>
        </div>

        <div class="admin-grid">
            <div class="admin-panel-card">
                <div class="admin-card-head">
                    <h2>Website Settings</h2>
                </div>
                <form method="POST" class="admin-settings-form">
                    <input type="hidden" name="action" value="save_website">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Website Name</label>
                            <input type="text" name="site_name" class="form-control" value="<?= htmlspecialchars($settings['site_name']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Tagline</label>
                            <input type="text" name="site_tagline" class="form-control" value="<?= htmlspecialchars($settings['site_tagline']) ?>">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Support Email</label>
                        <input type="email" name="support_email" class="form-control" value="<?= htmlspecialchars($settings['support_email']) ?>">
                    </div>
                    
                    <label class="checkbox-label">
                        <input type="checkbox" name="maintenance_mode" value="1" <?= $settings['maintenance_mode'] === '1' ? 'checked' : '' ?>>
                        Enable Maintenance Mode (Restricts public access)
                    </label>
                    
                    <button type="submit" class="btn-primary">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"></path></svg>
                        Save Website Settings
                    </button>
                </form>
            </div>

            <div class="admin-panel-card">
                <div class="admin-card-head">
                    <h2>Theme Settings</h2>
                </div>
                <form method="POST" class="admin-settings-form">
                    <input type="hidden" name="action" value="save_theme">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Admin Theme</label>
                            <select name="admin_theme" class="form-control">
                                <option value="light" <?= $settings['admin_theme'] === 'light' ? 'selected' : '' ?>>Light Mode</option>
                                <option value="dark" <?= $settings['admin_theme'] === 'dark' ? 'selected' : '' ?>>Dark Mode</option>
                                <option value="auto" <?= $settings['admin_theme'] === 'auto' ? 'selected' : '' ?>>System Default</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Accent Color</label>
                            <input type="color" name="admin_accent" class="form-control" value="<?= htmlspecialchars($settings['admin_accent']) ?>">
                        </div>
                    </div>
                    
                    <button type="submit" class="btn-primary">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01"></path></svg>
                        Save Theme Settings
                    </button>
                </form>
                
                <div class="admin-card-head" style="margin-top: 32px;">
                    <h2>Change Password</h2>
                </div>
                <form method="POST" class="admin-settings-form">
                    <input type="hidden" name="action" value="change_password">
                    
                    <div class="form-group">
                        <label>Current Password</label>
                        <input type="password" name="current_password" class="form-control" required>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>New Password</label>
                            <input type="password" name="new_password" class="form-control" required minlength="8">
                        </div>
                        <div class="form-group">
                            <label>Confirm Password</label>
                            <input type="password" name="confirm_password" class="form-control" required minlength="8">
                        </div>
                    </div>
                    
                    <button type="submit" class="btn-primary">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                        Update Password
                    </button>
                </form>
            </div>
        </div>
    </div>
</section>
<?php require_once 'includes/footer.php'; ?>
