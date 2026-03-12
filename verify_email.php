<?php
require_once 'config/config.php';
$token = $_GET['token'] ?? '';
$isRegistration = isset($_GET['reg']) && $_GET['reg'] === '1';
$message = 'Invalid or expired verification link.';
$success = false;

if ($token) {
    $pdo = getDBConnection();
    
    if ($isRegistration) {
        // Registration verification - check session
        if (isset($_SESSION['reg_email_token']) && $_SESSION['reg_email_token'] === $token) {
            if (isset($_SESSION['reg_code_expires']) && strtotime($_SESSION['reg_code_expires']) > time()) {
                $_SESSION['reg_email_verified'] = true;
                $message = 'Email verified successfully! You can now complete your registration.';
                $success = true;
            } else {
                $message = 'Verification link expired. Please request a new one.';
            }
        } else {
            $message = 'Invalid verification link.';
        }
    } else {
        // Regular user email verification
        $stmt = $pdo->prepare("SELECT user_id FROM verifications WHERE type='email' AND token=? AND expires_at > NOW() AND verified=0");
        $stmt->execute([$token]);
        $row = $stmt->fetch();
        if ($row) {
            $pdo->prepare("UPDATE users SET email_verified=1 WHERE id=?")->execute([$row['user_id']]);
            $pdo->prepare("UPDATE verifications SET verified=1 WHERE token=?")->execute([$token]);
            $message = 'Email verified successfully! You can now login.';
            $success = true;
        }
    }
}

$pageTitle = 'Email Verification';
require_once 'includes/header.php';
?>
<div class="auth-container">
    <div class="auth-card">
        <h1><?= $success ? 'Email Verified!' : 'Verification Failed' ?></h1>
        <p style="color: <?= $success ? '#27ae60' : '#e74c3c' ?>; margin-bottom: 1rem; text-align: center;">
            <?= htmlspecialchars($message) ?>
        </p>
        <?php if ($isRegistration && $success): ?>
            <p style="text-align: center; color: var(--text-muted); font-size: 0.9rem; margin-bottom: 1.5rem;">
                You can now return to the registration form to complete your account creation.
            </p>
            <a href="register.php" class="btn btn-primary" style="width:100%;">Back to Registration</a>
        <?php else: ?>
            <a href="<?= $success ? 'login.php' : 'register.php' ?>" class="btn btn-primary" style="width:100%; margin-top:1rem;">
                <?= $success ? 'Go to Login' : 'Try Again' ?>
            </a>
        <?php endif; ?>
    </div>
</div>
<?php require_once 'includes/footer.php'; ?>
