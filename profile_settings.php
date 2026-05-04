<?php
$pageTitle = 'Profile Settings';
require_once 'config/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$pdo = getDBConnection();
$userId = (int)$_SESSION['user_id'];
$role = (string)($_SESSION['role'] ?? 'customer');
$success = '';
$error = '';

try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS user_preferences (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            pref_key VARCHAR(100) NOT NULL,
            pref_value TEXT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uk_user_pref (user_id, pref_key),
            INDEX (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
} catch (Throwable $e) {
    // ignore
}

$userStmt = $pdo->prepare("
    SELECT id, full_name, username, email, phone, city, barangay, address_line, profile_image_path, cover_image_path
    FROM users
    WHERE id = ?
    LIMIT 1
");
$userStmt->execute([$userId]);
$user = $userStmt->fetch();
if (!$user) {
    header('Location: logout.php');
    exit;
}

$provider = null;
if ($role === 'provider' && !empty($_SESSION['provider_id'])) {
    $provStmt = $pdo->prepare("SELECT id, city, barangay, profile_image_path, cover_image_path FROM providers WHERE id = ? AND user_id = ? LIMIT 1");
    $provStmt->execute([(int)$_SESSION['provider_id'], $userId]);
    $provider = $provStmt->fetch();
}

function setPref(PDO $pdo, int $userId, string $key, string $val): void
{
    $stmt = $pdo->prepare("
        INSERT INTO user_preferences (user_id, pref_key, pref_value)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE pref_value = VALUES(pref_value)
    ");
    $stmt->execute([$userId, $key, $val]);
}

function getPref(PDO $pdo, int $userId, string $key, string $default = ''): string
{
    $stmt = $pdo->prepare("SELECT pref_value FROM user_preferences WHERE user_id = ? AND pref_key = ? LIMIT 1");
    $stmt->execute([$userId, $key]);
    $value = $stmt->fetchColumn();
    return $value !== false ? (string)$value : $default;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');
    try {
        if ($action === 'save_profile') {
            $fullName = trim((string)($_POST['full_name'] ?? ''));
            $username = trim((string)($_POST['username'] ?? ''));
            $city = trim((string)($_POST['city'] ?? ''));
            $barangay = trim((string)($_POST['barangay'] ?? ''));
            $addressLine = trim((string)($_POST['address_line'] ?? ''));
            
            // Email and phone are now unchangeable via profile settings
            $email = $user['email'];
            $phone = $user['phone'];

            $profileImage = (string)($user['profile_image_path'] ?? '');
            $coverImage = (string)($user['cover_image_path'] ?? '');

            if (!empty($_FILES['profile_image']['name'])) {
                $ext = strtolower(pathinfo((string)$_FILES['profile_image']['name'], PATHINFO_EXTENSION));
                if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
                    throw new RuntimeException('Profile picture must be JPG, PNG, or WEBP.');
                }
                $fileName = $userId . '_' . time() . '_profile.' . $ext;
                $dest = 'uploads/profiles/' . $fileName;
                if (!move_uploaded_file((string)$_FILES['profile_image']['tmp_name'], $dest)) {
                    throw new RuntimeException('Failed to upload profile picture.');
                }
                $profileImage = $dest;
            }

            if (!empty($_FILES['cover_image']['name'])) {
                $ext = strtolower(pathinfo((string)$_FILES['cover_image']['name'], PATHINFO_EXTENSION));
                if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
                    throw new RuntimeException('Cover photo must be JPG, PNG, or WEBP.');
                }
                $fileName = $userId . '_' . time() . '_cover.' . $ext;
                $dest = 'uploads/profiles/' . $fileName;
                if (!move_uploaded_file((string)$_FILES['cover_image']['tmp_name'], $dest)) {
                    throw new RuntimeException('Failed to upload cover photo.');
                }
                $coverImage = $dest;
            }

            if ($fullName === '') throw new RuntimeException('Full name is required.');
            if ($username === '') throw new RuntimeException('Username is required.');
            if (!preg_match('/^[a-zA-Z0-9_.]{3,30}$/', $username)) {
                throw new RuntimeException('Username must be 3-30 chars and use letters, numbers, underscore, or dot.');
            }
            // Email validation and duplicate check removed since it's no longer changeable here
            /*
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('Please enter a valid email address.');
            }

            $dupEmail = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1");
            $dupEmail->execute([$email, $userId]);
            if ($dupEmail->fetch()) throw new RuntimeException('Email is already in use by another account.');
            */

            $dupUser = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id <> ? LIMIT 1");
            $dupUser->execute([$username, $userId]);
            if ($dupUser->fetch()) throw new RuntimeException('Username is already taken.');

            $pdo->prepare("
                UPDATE users
                SET full_name = ?, username = ?, email = ?, phone = ?, city = ?, barangay = ?, address_line = ?, profile_image_path = ?, cover_image_path = ?
                WHERE id = ?
            ")->execute([$fullName, $username, $email, $phone, $city, $barangay, $addressLine, $profileImage, $coverImage, $userId]);

            if ($provider) {
                $pdo->prepare("UPDATE providers SET city = ?, barangay = ?, profile_image_path = ?, cover_image_path = ? WHERE id = ?")
                    ->execute([$city, $barangay, $profileImage, $coverImage, (int)$provider['id']]);
            }

            $_SESSION['full_name'] = $fullName;
            $success = 'Profile updated successfully.';
        } elseif ($action === 'save_security') {
            $currentPassword = (string)($_POST['current_password'] ?? '');
            $newPassword = (string)($_POST['new_password'] ?? '');
            $confirmPassword = (string)($_POST['confirm_password'] ?? '');
            if ($newPassword !== '' || $confirmPassword !== '' || $currentPassword !== '') {
                if (strlen($newPassword) < 8) throw new RuntimeException('New password must be at least 8 characters.');
                if ($newPassword !== $confirmPassword) throw new RuntimeException('Passwords do not match.');
                $hashStmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ? LIMIT 1");
                $hashStmt->execute([$userId]);
                $hash = (string)$hashStmt->fetchColumn();
                if (!password_verify($currentPassword, $hash)) throw new RuntimeException('Current password is incorrect.');
                $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
                $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?")->execute([$newHash, $userId]);
            }
            setPref($pdo, $userId, 'security_2fa_enabled', !empty($_POST['security_2fa_enabled']) ? '1' : '0');
            $success = 'Security settings updated.';
        } elseif ($action === 'save_privacy') {
            $profileVisibility = (string)($_POST['profile_visibility'] ?? 'public');
            $contactScope = (string)($_POST['contact_scope'] ?? 'anyone');
            if (!in_array($profileVisibility, ['public', 'hidden'], true)) {
                $profileVisibility = 'public';
            }
            if (!in_array($contactScope, ['anyone', 'verified_only', 'no_one'], true)) {
                $contactScope = 'anyone';
            }
            setPref($pdo, $userId, 'privacy_profile_visibility', $profileVisibility);
            setPref($pdo, $userId, 'privacy_show_phone', !empty($_POST['privacy_show_phone']) ? '1' : '0');
            setPref($pdo, $userId, 'privacy_show_email', !empty($_POST['privacy_show_email']) ? '1' : '0');
            setPref($pdo, $userId, 'privacy_contact_scope', $contactScope);
            $success = 'Privacy settings updated.';
        } elseif ($action === 'block_user') {
            $targetEmail = trim((string)($_POST['block_email'] ?? ''));
            if ($targetEmail === '' || !filter_var($targetEmail, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('Enter a valid email to block.');
            }
            $targetStmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
            $targetStmt->execute([$targetEmail]);
            $targetId = (int)($targetStmt->fetchColumn() ?: 0);
            if ($targetId <= 0) {
                throw new RuntimeException('User not found.');
            }
            if ($targetId === $userId) {
                throw new RuntimeException('You cannot block your own account.');
            }
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS user_blocks (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    blocker_user_id INT NOT NULL,
                    blocked_user_id INT NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY uk_block_pair (blocker_user_id, blocked_user_id),
                    INDEX (blocker_user_id),
                    INDEX (blocked_user_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
            $blockStmt = $pdo->prepare("
                INSERT INTO user_blocks (blocker_user_id, blocked_user_id)
                VALUES (?, ?)
                ON DUPLICATE KEY UPDATE created_at = CURRENT_TIMESTAMP
            ");
            $blockStmt->execute([$userId, $targetId]);
            $success = 'User blocked successfully.';
        } elseif ($action === 'unblock_user') {
            $blockedUserId = (int)($_POST['blocked_user_id'] ?? 0);
            if ($blockedUserId > 0) {
                $pdo->prepare("DELETE FROM user_blocks WHERE blocker_user_id = ? AND blocked_user_id = ?")
                    ->execute([$userId, $blockedUserId]);
                $success = 'User unblocked.';
            }
        } elseif ($action === 'save_notifications') {
            setPref($pdo, $userId, 'notif_booking', !empty($_POST['notif_booking']) ? '1' : '0');
            setPref($pdo, $userId, 'notif_verification', !empty($_POST['notif_verification']) ? '1' : '0');
            setPref($pdo, $userId, 'notif_report', !empty($_POST['notif_report']) ? '1' : '0');
            setPref($pdo, $userId, 'notif_marketing', !empty($_POST['notif_marketing']) ? '1' : '0');
            $success = 'Notification settings updated.';
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
    $userStmt->execute([$userId]);
    $user = $userStmt->fetch();
    if ($provider) {
        $provStmt->execute([(int)$_SESSION['provider_id'], $userId]);
        $provider = $provStmt->fetch();
    }
}

$prefs = [
    'security_2fa_enabled' => getPref($pdo, $userId, 'security_2fa_enabled', '0'),
    'notif_booking' => getPref($pdo, $userId, 'notif_booking', '1'),
    'notif_verification' => getPref($pdo, $userId, 'notif_verification', '1'),
    'notif_report' => getPref($pdo, $userId, 'notif_report', '1'),
    'notif_marketing' => getPref($pdo, $userId, 'notif_marketing', '0'),
    'theme_mode' => getPref($pdo, $userId, 'theme_mode', ''),
    'privacy_profile_visibility' => getPref($pdo, $userId, 'privacy_profile_visibility', 'public'),
    'privacy_show_phone' => getPref($pdo, $userId, 'privacy_show_phone', '1'),
    'privacy_show_email' => getPref($pdo, $userId, 'privacy_show_email', '1'),
    'privacy_contact_scope' => getPref($pdo, $userId, 'privacy_contact_scope', 'anyone'),
];

$blockedUsers = [];
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS user_blocks (
            id INT AUTO_INCREMENT PRIMARY KEY,
            blocker_user_id INT NOT NULL,
            blocked_user_id INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uk_block_pair (blocker_user_id, blocked_user_id),
            INDEX (blocker_user_id),
            INDEX (blocked_user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $blockedStmt = $pdo->prepare("
        SELECT ub.blocked_user_id, u.full_name, u.email, ub.created_at
        FROM user_blocks ub
        JOIN users u ON u.id = ub.blocked_user_id
        WHERE ub.blocker_user_id = ?
        ORDER BY ub.created_at DESC
    ");
    $blockedStmt->execute([$userId]);
    $blockedUsers = $blockedStmt->fetchAll();
} catch (Throwable $e) {
    $blockedUsers = [];
}

require_once 'includes/header.php';
?>
<style>
.ps-page { max-width: 1120px; margin: 0 auto; padding: 1.5rem 1.25rem 2.5rem; background: #f4f7fb; min-height: 60vh; }
.ps-page .section-title { margin: 0 0 0.35rem; font-size: 1.65rem; color: #0f172a; }
.ps-lead { margin: 0 0 1.25rem; color: #64748b; font-size: 0.95rem; line-height: 1.45; }
.ps-alert { border-radius: 12px; padding: 0.85rem 1rem; margin-bottom: 1rem; border: 1px solid transparent; }
.ps-alert--ok { background: #ecfdf5; border-color: #a7f3d0; color: #047857; }
.ps-alert--err { background: #fef2f2; border-color: #fecaca; color: #b91c1c; }
.ps-top-grid { display: grid; grid-template-columns: 1fr 320px; gap: 1.25rem; align-items: start; margin-bottom: 1.25rem; }
@media (max-width: 960px) { .ps-top-grid { grid-template-columns: 1fr; } }
.ps-card { background: #fff; border: 1px solid #e5eaf1; border-radius: 14px; box-shadow: 0 4px 20px rgba(15, 23, 42, 0.06); padding: 1.35rem 1.4rem; }
.ps-card-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 0.75rem; margin-bottom: 1.1rem; padding-bottom: 0.85rem; border-bottom: 1px solid #eef2f7; }
.ps-card-head h2 { margin: 0; font-size: 1.08rem; color: #0f172a; display: flex; align-items: center; gap: 0.45rem; }
.ps-card-head .ps-icon { font-size: 1.15rem; opacity: 0.85; }
.ps-help { font-size: 0.82rem; color: #64748b; margin: 0 0 0.75rem; line-height: 1.4; }
.ps-info-box { background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 10px; padding: 0.75rem 0.85rem; font-size: 0.84rem; color: #1e40af; margin-bottom: 1rem; line-height: 1.45; }
.ps-form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem 1.1rem; }
@media (max-width: 640px) { .ps-form-grid { grid-template-columns: 1fr; } }
.ps-form-grid .ps-field-full { grid-column: 1 / -1; }
.ps-field label, .ps-stack-form label { display: block; font-size: 0.8rem; font-weight: 600; color: #334155; margin-bottom: 0.35rem; }
.ps-field input[type="text"], .ps-field input[type="email"], .ps-field input[type="tel"], .ps-field input[type="file"],
.ps-stack-form input[type="text"], .ps-stack-form input[type="email"], .ps-stack-form input[type="password"], .ps-stack-form select {
    width: 100%; padding: 0.62rem 0.75rem; border: 1px solid #d1d9e6; border-radius: 10px; font-size: 0.92rem; background: #fff; transition: border-color 0.15s ease, box-shadow 0.15s ease; }
.ps-field input:focus, .ps-stack-form input:focus, .ps-stack-form select:focus { outline: none; border-color: #3A86FF; box-shadow: 0 0 0 3px rgba(58, 134, 255, 0.12); }
.ps-cover-block img { width: 100%; max-height: 170px; object-fit: cover; border-radius: 12px; border: 1px solid #e2e8f0; margin-bottom: 0.5rem; }
.ps-cover-hint { font-size: 0.78rem; color: #64748b; margin-top: 0.35rem; }
.ps-avatar-block { margin-top: 0.5rem; padding-top: 1rem; border-top: 1px dashed #e2e8f0; }
.ps-avatar-block img { width: 90px; height: 90px; object-fit: cover; border-radius: 50%; border: 2px solid #e2e8f0; margin-bottom: 0.5rem; display: block; }
.ps-form-actions { margin-top: 1.25rem; display: flex; flex-wrap: wrap; gap: 0.6rem; align-items: center; }
.ps-account-side .ps-card { position: sticky; top: 1rem; }
.ps-select { width: 100%; padding: 0.62rem 0.75rem; border-radius: 10px; border: 1px solid #d1d9e6; font-size: 0.9rem; background: #fff; }
.ps-pwd-row { display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.75rem; }
@media (max-width: 900px) { .ps-pwd-row { grid-template-columns: 1fr; } }
.ps-pwd-wrap { position: relative; }
.ps-pwd-wrap input { padding-right: 2.5rem; }
.ps-pwd-toggle { position: absolute; right: 8px; bottom: 10px; border: none; background: transparent; cursor: pointer; padding: 0.25rem; color: #64748b; font-size: 0.75rem; line-height: 1; border-radius: 6px; font-weight: 600; }
.ps-pwd-toggle:hover { color: #0f172a; background: #f1f5f9; }
.ps-2fa-row { margin: 1rem 0; display: flex; align-items: flex-start; gap: 0.65rem; }
.ps-2fa-row input { width: 1.1rem; height: 1.1rem; margin-top: 0.2rem; accent-color: #3A86FF; cursor: pointer; }
.ps-2fa-label { font-size: 0.88rem; color: #334155; font-weight: 500; cursor: pointer; }
.ps-stack { display: flex; flex-direction: column; gap: 1.25rem; }
.ps-notif-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0.65rem 1.25rem; }
@media (max-width: 640px) { .ps-notif-grid { grid-template-columns: 1fr; } }
.ps-switch { display: flex; align-items: center; gap: 0.75rem; cursor: pointer; padding: 0.55rem 0.65rem; border-radius: 10px; border: 1px solid #eef2f7; background: #fafbfc; }
.ps-switch input { position: absolute; opacity: 0; width: 1px; height: 1px; margin: -1px; overflow: hidden; clip: rect(0,0,0,0); }
.ps-switch-ui { width: 44px; height: 24px; border-radius: 999px; background: #cbd5e1; flex-shrink: 0; position: relative; transition: background 0.2s ease; }
.ps-switch-ui::after { content: ''; position: absolute; width: 18px; height: 18px; border-radius: 50%; background: #fff; top: 3px; left: 3px; box-shadow: 0 1px 3px rgba(0,0,0,0.15); transition: transform 0.2s ease; }
.ps-switch input:checked + .ps-switch-ui { background: #3A86FF; }
.ps-switch input:checked + .ps-switch-ui::after { transform: translateX(20px); }
.ps-switch-text { font-size: 0.88rem; color: #334155; font-weight: 500; }
.ps-block-inline { display: flex; flex-wrap: wrap; gap: 0.65rem; align-items: flex-end; margin-bottom: 1rem; }
.ps-block-inline .ps-field { flex: 1; min-width: 200px; margin: 0; }
.ps-block-list { border: 1px solid #e8edf4; border-radius: 12px; overflow: hidden; }
.ps-block-list-header { font-size: 0.78rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.04em; color: #64748b; padding: 0.6rem 0.85rem; background: #f8fafc; border-bottom: 1px solid #e8edf4; }
.ps-block-list ul { list-style: none; margin: 0; padding: 0; max-height: 220px; overflow-y: auto; }
.ps-block-list li { display: flex; align-items: center; justify-content: space-between; gap: 0.75rem; padding: 0.65rem 0.85rem; border-bottom: 1px solid #f1f5f9; font-size: 0.88rem; }
.ps-block-list li:last-child { border-bottom: none; }
.ps-block-meta { color: #64748b; font-size: 0.8rem; }
.ps-stack-form .form-group { margin-bottom: 0; }
@media (max-width: 960px) {
    .ps-stack > .admin-grid { grid-template-columns: 1fr !important; }
}
</style>
<section class="ps-page">
    <h1 class="section-title">Profile Settings</h1>
    <p class="ps-lead">Manage your account information, security, notifications and privacy preferences.</p>

    <?php if ($success): ?><div class="ps-alert ps-alert--ok"><strong><?= htmlspecialchars($success) ?></strong></div><?php endif; ?>
    <?php if ($error): ?><div class="ps-alert ps-alert--err"><strong><?= htmlspecialchars($error) ?></strong></div><?php endif; ?>

    <div class="ps-top-grid">
        <div class="ps-card" id="edit-profile">
            <div class="ps-card-head">
                <h2><span class="ps-icon" aria-hidden="true">👤</span> Profile Information</h2>
            </div>
            <p class="ps-help">Update your public profile details. City and barangay apply to your provider listing when applicable.</p>
            <form method="POST" enctype="multipart/form-data" class="ps-stack-form">
                <input type="hidden" name="action" value="save_profile">
                <div class="ps-avatar-block">
                    <label>Profile Picture / Avatar</label>
                    <?php if (!empty($user['profile_image_path'])): ?>
                        <img src="<?= htmlspecialchars((string)$user['profile_image_path']) ?>" alt="Profile picture">
                    <?php endif; ?>
                    
                </div>
                <div class="ps-form-grid" style="margin-top:1rem;">
                    <div class="ps-field">
                        <label for="ps-full-name">Full Name</label>
                        <input id="ps-full-name" type="text" name="full_name" value="<?= htmlspecialchars((string)($user['full_name'] ?? '')) ?>" placeholder="Your full name" required>
                    </div>
                    <div class="ps-field">
                        <label for="ps-username">Username</label>
                        <input id="ps-username" type="text" name="username" value="<?= htmlspecialchars((string)($user['username'] ?? '')) ?>" placeholder="e.g. jane_doe" required>
                    </div>
                    <div class="ps-field">
                        <label for="ps-email">Email Address <small>(Unchangeable)</small></label>
                        <input id="ps-email" type="email" name="email" value="<?= htmlspecialchars((string)($user['email'] ?? '')) ?>" placeholder="you@example.com" readonly style="background: #f1f5f9; cursor: not-allowed;">
                    </div>
                    <div class="ps-field">
                        <label for="ps-phone">Phone <small>(Unchangeable)</small></label>
                        <input id="ps-phone" type="tel" name="phone" value="<?= htmlspecialchars((string)($user['phone'] ?? '')) ?>" placeholder="09xx xxx xxxx" readonly style="background: #f1f5f9; cursor: not-allowed;">
                    </div>
                    <div class="ps-field ps-field-full">
                        <label for="ps-address">Address / Location</label>
                        <input id="ps-address" type="text" name="address_line" value="<?= htmlspecialchars((string)($user['address_line'] ?? '')) ?>" placeholder="House no., street, subdivision">
                    </div>
                    <div class="ps-field">
                        <label for="ps-city">City</label>
                        <input id="ps-city" type="text" name="city" value="<?= htmlspecialchars((string)($provider['city'] ?? $user['city'] ?? '')) ?>" placeholder="City or municipality">
                    </div>
                    <div class="ps-field ps-field-full">
                        <label for="ps-barangay">Barangay</label>
                        <input id="ps-barangay" type="text" name="barangay" value="<?= htmlspecialchars((string)($provider['barangay'] ?? $user['barangay'] ?? '')) ?>" placeholder="Barangay">
                    </div>
                </div>
                
                <div class="ps-form-actions">
                    <button class="btn btn-primary" type="submit">Save Changes</button>
                </div>
            </form>
        </div>

        <aside class="ps-account-side" id="account-settings">
            <div class="ps-card">
                <div class="ps-card-head">
                    <h2><span class="ps-icon" aria-hidden="true">🔒</span> Account Settings</h2>
                </div>
                <div class="ps-info-box">Account info is now managed in the Profile Information form for a single-save flow.</div>
                <label for="ps-appearance" class="ps-help" style="margin-bottom:0.4rem;font-weight:600;color:#334155;">Appearance mode</label>
                <select id="ps-appearance" class="ps-select" aria-describedby="ps-appearance-help">
                    <option value="light">Light</option>
                    <option value="dark">Dark</option>
                    <option value="system">System default</option>
                </select>
                <p id="ps-appearance-help" class="ps-help" style="margin-top:0.65rem;">Use the profile dropdown to switch appearance quickly, or set your preference here.</p>
            </div>
        </aside>
    </div>

    <div class="ps-stack">
        <div class="ps-card" id="security-settings">
            <div class="ps-card-head">
                <h2><span class="ps-icon" aria-hidden="true">🛡️</span> Security Settings</h2>
            </div>
            <form method="POST" class="ps-stack-form">
                <input type="hidden" name="action" value="save_security">
                <div class="ps-pwd-row">
                    <div class="ps-field ps-pwd-wrap">
                        <label for="ps-cur-pw">Current Password</label>
                        <input id="ps-cur-pw" type="password" name="current_password" autocomplete="current-password">
                        <button type="button" class="ps-pwd-toggle" data-pwd-target="ps-cur-pw" aria-label="Show password">Show</button>
                    </div>
                    <div class="ps-field ps-pwd-wrap">
                        <label for="ps-new-pw">New Password</label>
                        <input id="ps-new-pw" type="password" name="new_password" minlength="8" autocomplete="new-password">
                        <button type="button" class="ps-pwd-toggle" data-pwd-target="ps-new-pw" aria-label="Show password">Show</button>
                    </div>
                    <div class="ps-field ps-pwd-wrap">
                        <label for="ps-confirm-pw">Confirm New Password</label>
                        <input id="ps-confirm-pw" type="password" name="confirm_password" minlength="8" autocomplete="new-password">
                        <button type="button" class="ps-pwd-toggle" data-pwd-target="ps-confirm-pw" aria-label="Show password">Show</button>
                    </div>
                </div>
                <div class="ps-2fa-row">
                    <input type="checkbox" name="security_2fa_enabled" value="1" id="ps-2fa" <?= $prefs['security_2fa_enabled'] === '1' ? 'checked' : '' ?>>
                    <label for="ps-2fa" class="ps-2fa-label">Enable 2FA authenticator <span title="Placeholder — full 2FA setup may come later" style="cursor:help;color:#94a3b8;">ⓘ</span></label>
                </div>
                <div class="ps-form-actions">
                    <button class="btn btn-primary" type="submit">Save Security Settings</button>
                </div>
            </form>
        </div>

        <div class="ps-card" id="notification-settings">
            <div class="ps-card-head">
                <h2><span class="ps-icon" aria-hidden="true">🔔</span> Notifications Settings</h2>
            </div>
            <form method="POST" class="ps-stack-form">
                <input type="hidden" name="action" value="save_notifications">
                <div class="ps-notif-grid">
                    <label class="ps-switch">
                        <input type="checkbox" name="notif_booking" value="1" <?= $prefs['notif_booking'] === '1' ? 'checked' : '' ?>>
                        <span class="ps-switch-ui" aria-hidden="true"></span>
                        <span class="ps-switch-text">Booking updates</span>
                    </label>
                    <label class="ps-switch">
                        <input type="checkbox" name="notif_verification" value="1" <?= $prefs['notif_verification'] === '1' ? 'checked' : '' ?>>
                        <span class="ps-switch-ui" aria-hidden="true"></span>
                        <span class="ps-switch-text">Verification alerts</span>
                    </label>
                    <label class="ps-switch">
                        <input type="checkbox" name="notif_report" value="1" <?= $prefs['notif_report'] === '1' ? 'checked' : '' ?>>
                        <span class="ps-switch-ui" aria-hidden="true"></span>
                        <span class="ps-switch-text">Report alerts</span>
                    </label>
                    <label class="ps-switch">
                        <input type="checkbox" name="notif_marketing" value="1" <?= $prefs['notif_marketing'] === '1' ? 'checked' : '' ?>>
                        <span class="ps-switch-ui" aria-hidden="true"></span>
                        <span class="ps-switch-text">Product / marketing emails</span>
                    </label>
                </div>
                <div class="ps-form-actions" style="margin-top:1rem;">
                    <button class="btn btn-primary" type="submit">Save Notification Settings</button>
                </div>
            </form>
        </div>

        <div class="admin-grid" style="margin:0; display:grid; grid-template-columns:1fr 1fr; gap:1.25rem;">
       

        
        </div>
    </div>
</section>
<script>
(function () {
    document.querySelectorAll('.ps-pwd-toggle').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var id = this.getAttribute('data-pwd-target');
            var input = id && document.getElementById(id);
            if (!input) return;
            input.type = input.type === 'password' ? 'text' : 'password';
            this.textContent = input.type === 'password' ? 'Show' : 'Hide';
            this.setAttribute('aria-label', input.type === 'password' ? 'Show password' : 'Hide password');
        });
    });

    function applySiteThemeFromValue(mode) {
        var dark = false;
        if (mode === 'dark') dark = true;
        else if (mode === 'system') {
            dark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
        }
        document.body.classList.remove('site-theme-light', 'site-theme-dark');
        document.body.classList.add(dark ? 'site-theme-dark' : 'site-theme-light');
    }

    var appearance = document.getElementById('ps-appearance');
    if (appearance) {
        var fromStorage = localStorage.getItem('site_theme_mode') || '';
        var fromServer = <?= json_encode((string)($prefs['theme_mode'] ?? ''), JSON_HEX_TAG) ?>;
        var initial = 'light';
        if (fromServer === 'light' || fromServer === 'dark' || fromServer === 'system') {
            initial = fromServer;
        } else if (fromStorage === 'light' || fromStorage === 'dark') {
            initial = fromStorage;
        } else if (fromStorage) {
            initial = fromStorage;
        }
        if (initial !== 'light' && initial !== 'dark' && initial !== 'system') initial = 'light';
        appearance.value = initial;
        if (appearance.value === 'system') {
            try { localStorage.removeItem('site_theme_mode'); } catch (e) {}
        }
        applySiteThemeFromValue(appearance.value);

        appearance.addEventListener('change', function () {
            var v = this.value;
            if (v === 'system') {
                localStorage.removeItem('site_theme_mode');
            } else {
                localStorage.setItem('site_theme_mode', v);
            }
            applySiteThemeFromValue(v);
            var body = 'pref_key=' + encodeURIComponent('theme_mode') + '&pref_value=' + encodeURIComponent(v);
            fetch('api/save_user_preferences.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body,
                credentials: 'same-origin'
            }).catch(function () {});
        });
    }
})();
</script>
<?php require_once 'includes/footer.php'; ?>
