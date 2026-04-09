<?php
$pageTitle = 'Forgot Password';
require_once 'config/config.php';

if (isset($_SESSION['user_id'])) {
    header('Location: ' . ($_SESSION['role'] === 'provider' ? 'provider_profile.php?id=' . $_SESSION['provider_id'] : 'providers.php'));
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    if (empty($email)) {
        $error = 'Please enter your email address.';
    } else {
        try {
            $pdo = getDBConnection();
            $stmt = $pdo->prepare('SELECT id, full_name FROM users WHERE email = ? LIMIT 1');
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            if ($user) {
                $token = bin2hex(random_bytes(24));
                $expires = date('Y-m-d H:i:s', time() + 3600);
                $update = $pdo->prepare('UPDATE users SET password_reset_token = ?, password_reset_expires = ? WHERE id = ?');
                $update->execute([$token, $expires, $user['id']]);

                $resetUrl = SITE_URL . '/reset_password.php?token=' . urlencode($token);
                $subject = 'Reset Your ' . SITE_NAME . ' Password';
                $body = "<p>Hi " . htmlspecialchars($user['full_name']) . ",</p>" .
                    "<p>We received a request to reset your password. Click the link below to set a new one:</p>" .
                    "<p><a href=\"$resetUrl\">Reset your password</a></p>" .
                    "<p>If you did not request a password reset, you can safely ignore this email.</p>";

                send_email($email, $subject, $body, true);
            }

            $success = 'If an account exists with that email address, a password reset link has been sent.';
        } catch (Throwable $e) {
            error_log('Forgot password error: ' . $e->getMessage());
            $error = 'Unable to process your request right now. Please try again later.';
        }
    }
}

require_once 'includes/header.php';
?>
<div class="auth-container">
    <div class="auth-card fade-in">
        <h1>Forgot Password</h1>
        <?php if ($error): ?><p class="error-message"><?= htmlspecialchars($error) ?></p><?php endif; ?>
        <?php if ($success): ?><p class="success-message"><?= htmlspecialchars($success) ?></p><?php endif; ?>
        <form method="POST" class="auth-form">
            <div class="form-group">
                <label>Email address</label>
                <input type="email" name="email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%; margin-top:1rem;">Send Reset Link</button>
        </form>
        <p style="text-align:center; margin-top:1rem; color:var(--text-muted);">
            Remembered your password? <a href="login.php">Login</a>
        </p>
    </div>
</div>
<?php require_once 'includes/footer.php'; ?>
