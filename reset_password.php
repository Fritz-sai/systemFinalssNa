<?php
$pageTitle = 'Reset Password';
require_once 'config/config.php';

if (isset($_SESSION['user_id'])) {
    header('Location: ' . ($_SESSION['role'] === 'provider' ? 'provider_profile.php?id=' . $_SESSION['provider_id'] : 'providers.php'));
    exit;
}

$error = '';
$success = '';
$token = trim($_GET['token'] ?? $_POST['token'] ?? '');
$user = null;

if ($token !== '') {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare('SELECT id, full_name, password_reset_expires FROM users WHERE password_reset_token = ? LIMIT 1');
        $stmt->execute([$token]);
        $user = $stmt->fetch();
        if ($user) {
            $expiresAt = strtotime($user['password_reset_expires']);
            if ($expiresAt === false || $expiresAt < time()) {
                $user = false;
            }
        }
    } catch (Throwable $e) {
        error_log('Reset password token check error: ' . $e->getMessage());
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';

    if (empty($token) || !$user) {
        $error = 'The password reset link is invalid or has expired.';
    } elseif (empty($password) || empty($password_confirm)) {
        $error = 'Please enter and confirm your new password.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($password !== $password_confirm) {
        $error = 'Passwords do not match.';
    } else {
        try {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $update = $pdo->prepare('UPDATE users SET password_hash = ?, password_reset_token = NULL, password_reset_expires = NULL, updated_at = NOW() WHERE id = ?');
            $update->execute([$hash, $user['id']]);
            $success = 'Your password has been reset successfully. You can now log in with your new password.';
            $user = null;
        } catch (Throwable $e) {
            error_log('Reset password update error: ' . $e->getMessage());
            $error = 'Unable to reset your password right now. Please try again later.';
        }
    }
}

require_once 'includes/header.php';
?>
<div class="auth-container">
    <div class="auth-card fade-in">
        <h1>Reset Password</h1>
        <?php if ($error): ?><p class="error-message"><?= htmlspecialchars($error) ?></p><?php endif; ?>
        <?php if ($success): ?><p class="success-message"><?= htmlspecialchars($success) ?></p><?php endif; ?>
        <?php if (!$success): ?>
            <?php if ($token === '' || !$user): ?>
                <p class="error-message">This password reset link is invalid or has expired.</p>
                <p style="text-align:center; margin-top:1rem;"><a href="forgot_password.php">Request a new reset link</a></p>
            <?php else: ?>
                <form method="POST" class="auth-form">
                    <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                    <div class="form-group">
                        <label>New password</label>
                        <input type="password" name="password" required minlength="6">
                    </div>
                    <div class="form-group">
                        <label>Confirm new password</label>
                        <input type="password" name="password_confirm" required minlength="6">
                    </div>
                    <button type="submit" class="btn btn-primary" style="width:100%; margin-top:1rem;">Save New Password</button>
                </form>
            <?php endif; ?>
        <?php endif; ?>
        <p style="text-align:center; margin-top:1rem; color:var(--text-muted);">
            <a href="login.php">Back to Login</a>
        </p>
    </div>
</div>
<?php require_once 'includes/footer.php'; ?>
