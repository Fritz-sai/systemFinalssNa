<?php
$pageTitle = 'Verify Email';
require_once 'config/config.php';

if (isset($_SESSION['user_id'])) {
    header('Location: ' . ($_SESSION['role'] === 'provider' ? 'dashboard_provider.php' : 'dashboard_customer.php'));
    exit;
}

$error = '';
$success = '';
$pdo = getDBConnection();
$reg_email = $_SESSION['reg_email'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'send_email_code') {
        $email = trim($_POST['email'] ?? '');

        if (empty($email)) {
            $error = 'Email is required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Invalid email format.';
        } else {
            $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $error = 'This email is already registered.';
            } else {
                $_SESSION['reg_email'] = $email;
                $_SESSION['reg_email_verified'] = false;
                $_SESSION['reg_email_code'] = str_pad(rand(100000, 999999), 6, '0');
                $_SESSION['reg_email_code_expires'] = date('Y-m-d H:i:s', strtotime('+15 minutes'));

                $subject = 'Your verification code';
                $body = "Your email verification code is: " . $_SESSION['reg_email_code'];
                @mail($email, $subject, $body);

                $success = 'Verification code sent. (Demo code: ' . htmlspecialchars($_SESSION['reg_email_code']) . ')';
                $reg_email = $email;
            }
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'verify_email_code') {
        $code = trim($_POST['email_code'] ?? '');

        if (empty($code)) {
            $error = 'Please enter the verification code.';
        } elseif (!isset($_SESSION['reg_email_code']) || !isset($_SESSION['reg_email_code_expires'])) {
            $error = 'Please send a code first.';
        } elseif (strtotime($_SESSION['reg_email_code_expires']) <= time()) {
            $error = 'Code expired. Please resend.';
        } elseif ($code !== ($_SESSION['reg_email_code'] ?? '')) {
            $error = 'Incorrect code.';
        } else {
            $_SESSION['reg_email_verified'] = true;
            header('Location: phone_verification.php');
            exit;
        }
    }
}

require_once 'includes/header.php';
?>
<div class="auth-container">
    <div class="auth-card fade-in">
        <h1>Verify Your Email</h1>
        <p style="color: #6c757d; margin: 0.5rem 0 1rem;">We sent an OTP to:</p>
        <strong><?= htmlspecialchars($reg_email ?: 'your@email.com') ?></strong>

        <?php if ($error): ?><p class="error-message"><?= htmlspecialchars($error) ?></p><?php endif; ?>
        <?php if ($success): ?><p class="success-message"><?= htmlspecialchars($success) ?></p><?php endif; ?>

        <form method="POST" class="auth-form">
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" required placeholder="you@example.com" value="<?= htmlspecialchars($reg_email) ?>">
            </div>
            <button type="submit" name="action" value="send_email_code" class="btn btn-primary">Send Code</button>

            <?php if (!empty($_SESSION['reg_email_code'])): ?>
                <div class="otp-inputs">
                    <input type="text" name="email_code" maxlength="6" pattern="\d{6}" placeholder="000000" inputmode="numeric" autocomplete="one-time-code" required>
                </div>
                <button type="submit" name="action" value="verify_email_code" class="btn btn-primary">Verify Email</button>
            <?php endif; ?>

            
            <p class="toggle-link">Already have an account? <a href="login.php">Login</a></p>
        </form>
    </div>
</div>
<?php require_once 'includes/footer.php'; ?>
