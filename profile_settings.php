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
            $email = trim((string)($_POST['email'] ?? ''));
            $phone = trim((string)($_POST['phone'] ?? ''));
            $city = trim((string)($_POST['city'] ?? ''));
            $barangay = trim((string)($_POST['barangay'] ?? ''));
            $addressLine = trim((string)($_POST['address_line'] ?? ''));
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
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('Please enter a valid email address.');
            }

            $dupEmail = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1");
            $dupEmail->execute([$email, $userId]);
            if ($dupEmail->fetch()) throw new RuntimeException('Email is already in use by another account.');

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
<section style="padding: 1.4rem; max-width: 980px; margin: 0 auto;">
    <h1 class="section-title">Profile Settings</h1>
    <?php if ($success): ?><div class="card" style="padding:0.9rem; border-left:4px solid #2ECC71; margin-bottom:0.8rem;"><strong style="color:#2ECC71;"><?= htmlspecialchars($success) ?></strong></div><?php endif; ?>
    <?php if ($error): ?><div class="card" style="padding:0.9rem; border-left:4px solid #e74c3c; margin-bottom:0.8rem;"><strong style="color:#e74c3c;"><?= htmlspecialchars($error) ?></strong></div><?php endif; ?>

    <div class="admin-dashboard-insights">
        <div class="card admin-panel-card" id="edit-profile">
            <div class="admin-card-head"><h2>Profile Information</h2></div>
            <form method="POST" enctype="multipart/form-data" class="admin-settings-form">
                <input type="hidden" name="action" value="save_profile">
                <label>Cover Photo / Banner</label>
                <?php if (!empty($user['cover_image_path'])): ?>
                    <img src="<?= htmlspecialchars((string)$user['cover_image_path']) ?>" alt="Cover photo" style="width:100%; max-height:170px; object-fit:cover; border-radius:10px; border:1px solid #dce7fb;">
                <?php endif; ?>
                <input type="file" name="cover_image" accept="image/*">
                <label>Full Name</label>
                <input type="text" name="full_name" value="<?= htmlspecialchars((string)($user['full_name'] ?? '')) ?>" required>
                <label>Username</label>
                <input type="text" name="username" value="<?= htmlspecialchars((string)($user['username'] ?? '')) ?>" required>
                <label>Email Address</label>
                <input type="email" name="email" value="<?= htmlspecialchars((string)($user['email'] ?? '')) ?>" required>
                <label>Phone</label>
                <input type="text" name="phone" value="<?= htmlspecialchars((string)($user['phone'] ?? '')) ?>">
                <label>Address / Location</label>
                <input type="text" name="address_line" value="<?= htmlspecialchars((string)($user['address_line'] ?? '')) ?>" placeholder="House no, street, subdivision">
                <label>City</label>
                <input type="text" name="city" value="<?= htmlspecialchars((string)($provider['city'] ?? $user['city'] ?? '')) ?>">
                <label>Barangay</label>
                <input type="text" name="barangay" value="<?= htmlspecialchars((string)($provider['barangay'] ?? $user['barangay'] ?? '')) ?>">
                <label>Profile Picture / Avatar</label>
                <?php if (!empty($user['profile_image_path'])): ?>
                    <img src="<?= htmlspecialchars((string)$user['profile_image_path']) ?>" alt="Profile picture" style="width:90px; height:90px; object-fit:cover; border-radius:50%; border:2px solid #dce7fb;">
                <?php endif; ?>
                <input type="file" name="profile_image" accept="image/*">
                <button class="btn btn-primary" type="submit">Save Changes</button>
            </form>
        </div>

        <div class="card admin-panel-card" id="account-settings">
            <div class="admin-card-head"><h2>Account Settings</h2></div>
            <p class="small-muted">Account info is now managed in the Profile Information form for a single-save flow.</p>
            <p class="small-muted" style="margin-top:0.5rem;">Use the profile dropdown to switch appearance mode quickly.</p>
        </div>
    </div>

    <div class="admin-grid" style="margin-top:1rem;">
        <div class="card admin-panel-card" id="security-settings">
            <div class="admin-card-head"><h2>Security Settings</h2></div>
            <form method="POST" class="admin-settings-form">
                <input type="hidden" name="action" value="save_security">
                <label>Current Password</label>
                <input type="password" name="current_password">
                <label>New Password</label>
                <input type="password" name="new_password" minlength="8">
                <label>Confirm New Password</label>
                <input type="password" name="confirm_password" minlength="8">
                <label style="display:flex;gap:0.4rem;align-items:center;"><input type="checkbox" name="security_2fa_enabled" value="1" <?= $prefs['security_2fa_enabled'] === '1' ? 'checked' : '' ?>>Enable 2FA placeholder</label>
                <button class="btn btn-primary" type="submit">Save Security</button>
            </form>
        </div>

        <div class="card admin-panel-card" id="notification-settings">
            <div class="admin-card-head"><h2>Notifications Settings</h2></div>
            <form method="POST" class="admin-settings-form">
                <input type="hidden" name="action" value="save_notifications">
                <label style="display:flex;gap:0.4rem;align-items:center;"><input type="checkbox" name="notif_booking" value="1" <?= $prefs['notif_booking'] === '1' ? 'checked' : '' ?>>Booking updates</label>
                <label style="display:flex;gap:0.4rem;align-items:center;"><input type="checkbox" name="notif_verification" value="1" <?= $prefs['notif_verification'] === '1' ? 'checked' : '' ?>>Verification alerts</label>
                <label style="display:flex;gap:0.4rem;align-items:center;"><input type="checkbox" name="notif_report" value="1" <?= $prefs['notif_report'] === '1' ? 'checked' : '' ?>>Report alerts</label>
                <label style="display:flex;gap:0.4rem;align-items:center;"><input type="checkbox" name="notif_marketing" value="1" <?= $prefs['notif_marketing'] === '1' ? 'checked' : '' ?>>Product/marketing emails</label>
                <button class="btn btn-primary" type="submit">Save Notification Settings</button>
            </form>
        </div>
    </div>

    <div class="admin-grid" style="margin-top:1rem;">
        <div class="card admin-panel-card" id="privacy-settings">
            <div class="admin-card-head"><h2>Privacy Settings</h2></div>
            <form method="POST" class="admin-settings-form">
                <input type="hidden" name="action" value="save_privacy">
                <label>Profile Visibility</label>
                <select name="profile_visibility">
                    <option value="public" <?= $prefs['privacy_profile_visibility'] === 'public' ? 'selected' : '' ?>>Show profile publicly</option>
                    <option value="hidden" <?= $prefs['privacy_profile_visibility'] === 'hidden' ? 'selected' : '' ?>>Hide profile from public view</option>
                </select>
                <label style="display:flex;gap:0.4rem;align-items:center;"><input type="checkbox" name="privacy_show_phone" value="1" <?= $prefs['privacy_show_phone'] === '1' ? 'checked' : '' ?>>Show phone number</label>
                <label style="display:flex;gap:0.4rem;align-items:center;"><input type="checkbox" name="privacy_show_email" value="1" <?= $prefs['privacy_show_email'] === '1' ? 'checked' : '' ?>>Show email address</label>
                <label>Who can contact me</label>
                <select name="contact_scope">
                    <option value="anyone" <?= $prefs['privacy_contact_scope'] === 'anyone' ? 'selected' : '' ?>>Anyone with access</option>
                    <option value="verified_only" <?= $prefs['privacy_contact_scope'] === 'verified_only' ? 'selected' : '' ?>>Verified users only</option>
                    <option value="no_one" <?= $prefs['privacy_contact_scope'] === 'no_one' ? 'selected' : '' ?>>No one</option>
                </select>
                <button class="btn btn-primary" type="submit">Save Privacy Settings</button>
            </form>
        </div>

        <div class="card admin-panel-card">
            <div class="admin-card-head"><h2>Block Users</h2></div>
            <form method="POST" class="admin-settings-form">
                <input type="hidden" name="action" value="block_user">
                <label>Block by email</label>
                <input type="email" name="block_email" placeholder="user@example.com" required>
                <button class="btn btn-primary" type="submit">Block User</button>
            </form>
            <div style="margin-top:0.7rem;">
                <?php if (empty($blockedUsers)): ?>
                    <p class="small-muted">No blocked users.</p>
                <?php else: ?>
                    <div class="admin-table-wrap">
                        <table class="admin-table">
                            <thead>
                                <tr><th>Name</th><th>Email</th><th>Blocked Since</th><th>Action</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($blockedUsers as $bu): ?>
                                    <tr>
                                        <td><?= htmlspecialchars((string)$bu['full_name']) ?></td>
                                        <td><?= htmlspecialchars((string)$bu['email']) ?></td>
                                        <td><?= htmlspecialchars(date('M d, Y', strtotime((string)$bu['created_at']))) ?></td>
                                        <td>
                                            <form method="POST">
                                                <input type="hidden" name="action" value="unblock_user">
                                                <input type="hidden" name="blocked_user_id" value="<?= (int)$bu['blocked_user_id'] ?>">
                                                <button class="btn btn-ghost" type="submit">Unblock</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>
<?php require_once 'includes/footer.php'; ?>
