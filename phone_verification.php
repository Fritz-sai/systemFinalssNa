<?php
$pageTitle = 'Verify Phone';
require_once 'config/config.php';

if (isset($_SESSION['user_id'])) {
    header('Location: ' . ($_SESSION['role'] === 'provider' ? 'dashboard_provider.php' : 'dashboard_customer.php'));
    exit;
}

if (empty($_SESSION['reg_email_verified'])) {
    header('Location: email_verification.php');
    exit;
}

$error = '';
$success = '';
$pdo = getDBConnection();
$reg_phone = $_SESSION['reg_phone'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'send_phone_code') {
        $phone = trim($_POST['phone'] ?? '');

        if (empty($phone)) {
            $error = 'Phone number is required.';
        } elseif (!preg_match('/^09\d{9}$/', $phone)) {
            $error = 'Invalid phone format. Use 09xxxxxxxxx.';
        } else {
            $stmt = $pdo->prepare('SELECT id FROM users WHERE phone = ?');
            $stmt->execute([$phone]);
            if ($stmt->fetch()) {
                $error = 'Phone already registered.';
            } else {
                $_SESSION['reg_phone'] = $phone;
                $_SESSION['reg_phone_verified'] = false;
                $_SESSION['reg_phone_code'] = str_pad(rand(100000, 999999), 6, '0');
                $_SESSION['reg_phone_code_expires'] = date('Y-m-d H:i:s', strtotime('+15 minutes'));

                $success = 'Verification code sent. (Demo code: ' . htmlspecialchars($_SESSION['reg_phone_code']) . ')';
                $reg_phone = $phone;
            }
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'verify_phone_code') {
        $code = trim($_POST['phone_code'] ?? '');

        if (empty($code)) {
            $error = 'Please enter the verification code.';
        } elseif (!isset($_SESSION['reg_phone_code']) || !isset($_SESSION['reg_phone_code_expires'])) {
            $error = 'Please send a code first.';
        } elseif (strtotime($_SESSION['reg_phone_code_expires']) <= time()) {
            $error = 'Code expired. Please resend.';
        } elseif ($code !== ($_SESSION['reg_phone_code'] ?? '')) {
            $error = 'Incorrect code.';
        } else {
            $_SESSION['reg_phone_verified'] = true;
            header('Location: register.php');
            exit;
        }
    }
}

require_once 'includes/header.php';
?>
<div class="auth-container">
    <div class="auth-card fade-in">
        <h1>Verify Your Phone</h1>
        <p style="color: #6c757d; margin: 0.5rem 0 1rem;">We sent an OTP to:</p>
        <strong><?= htmlspecialchars($reg_phone ?: '09xxxxxxxxx') ?></strong>

        <?php if ($error): ?><p class="error-message"><?= htmlspecialchars($error) ?></p><?php endif; ?>
        <?php if ($success): ?><p class="success-message"><?= htmlspecialchars($success) ?></p><?php endif; ?>

        <form method="POST" class="auth-form">
            <div class="form-group">
                <label>Phone Number</label>
                <input type="tel" name="phone" required placeholder="09xxxxxxxxx" value="<?= htmlspecialchars($reg_phone) ?>">
            </div>
            <button type="submit" name="action" value="send_phone_code" class="btn btn-primary">Send Code</button>

            <?php if (!empty($_SESSION['reg_phone_code'])): ?>
                <div class="otp-inputs">
                    <input type="text" name="phone_code" maxlength="6" pattern="\d{6}" placeholder="000000" inputmode="numeric" autocomplete="one-time-code" required>
                </div>
                <button type="submit" name="action" value="verify_phone_code" class="btn btn-primary">Verify Phone</button>
            <?php endif; ?>

            <p class="toggle-link">Already have an account? <a href="login.php">Login</a></p>
        </form>
    </div>
</div>
<?php require_once 'includes/footer.php'; ?>
