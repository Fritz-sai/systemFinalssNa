<?php
require_once 'config/config.php';
$token = $_GET['token'] ?? '';
$message = 'Invalid or expired verification link.';

if ($token) {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT user_id FROM verifications WHERE type='email' AND token=? AND expires_at > NOW() AND verified=0");
    $stmt->execute([$token]);
    $row = $stmt->fetch();
    if ($row) {
        $pdo->prepare("UPDATE users SET email_verified=1 WHERE id=?")->execute([$row['user_id']]);
        $pdo->prepare("UPDATE verifications SET verified=1 WHERE token=?")->execute([$token]);
        $message = 'Email verified successfully! You can now login.';
    }
}

$pageTitle = 'Email Verification';
require_once 'includes/header.php';
?>
<div class="auth-container">
    <div class="auth-card">
        <h1><?= htmlspecialchars($message) ?></h1>
        <a href="login.php" class="btn btn-primary" style="width:100%; margin-top:1rem;">Go to Login</a>
    </div>
</div>
<?php require_once 'includes/footer.php'; ?>
