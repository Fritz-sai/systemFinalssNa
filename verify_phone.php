<?php
require_once 'config/config.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'provider') {
    header('Location: login.php');
    exit;
}

$pdo = getDBConnection();
$userId = $_SESSION['user_id'];
$error = '';

// Get latest unverified OTP (if any)
$stmt = $pdo->prepare("SELECT token, expires_at FROM verifications WHERE user_id=? AND type='phone' AND verified=0 ORDER BY id DESC LIMIT 1");
$stmt->execute([$userId]);
$otpRow = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = trim($_POST['otp'] ?? '');
    if (empty($code)) {
        $error = 'Enter the OTP code.';
    } else {
        // Look up OTP without relying on database NOW() to avoid timezone issues
        $stmt = $pdo->prepare("SELECT id, expires_at FROM verifications WHERE user_id=? AND type='phone' AND token=? AND verified=0 ORDER BY id DESC LIMIT 1");
        $stmt->execute([$userId, $code]);
        $row = $stmt->fetch();
        if ($row && strtotime($row['expires_at']) > time()) {
            $pdo->prepare("UPDATE users SET phone_verified=1 WHERE id=?")->execute([$userId]);
            $pdo->prepare("UPDATE verifications SET verified=1 WHERE user_id=? AND type='phone'")->execute([$userId]);
            $_SESSION['phone_verified'] = true;
            header('Location: dashboard_provider.php');
            exit;
        }
        $error = 'Invalid or expired OTP.';
    }
}

$otp = '';
// Generate a new OTP only if there is none or the last one is expired
if (!$otpRow || strtotime($otpRow['expires_at']) <= time()) {
    $otp = str_pad(rand(100000, 999999), 6, '0');
    $expires = date('Y-m-d H:i:s', strtotime('+10 minutes'));
    $pdo->prepare("INSERT INTO verifications (user_id, type, token, expires_at) VALUES (?, 'phone', ?, ?)")
        ->execute([$userId, $otp, $expires]);
} else {
    $otp = $otpRow['token'];
}

$pageTitle = 'Verify Phone';
require_once 'includes/header.php';
?>
<div class="auth-container">
    <div class="auth-card">
        <h1>Verify Your Phone</h1>
        <p style="color: var(--text-muted); margin-bottom: 1rem;">We've sent a 6-digit code to your phone. Enter it below.</p>
        <p style="font-size: 0.9rem; color: var(--text-muted); margin-bottom: 1rem;">Demo OTP: <strong><?= htmlspecialchars($otp) ?></strong></p>
        <?php if ($error): ?><p style="color: #e74c3c; margin-bottom: 1rem;"><?= htmlspecialchars($error) ?></p><?php endif; ?>
        <form method="POST">
            <div class="form-group">
                <label>OTP Code</label>
                <input type="text" name="otp" maxlength="6" pattern="[0-9]{6}" placeholder="000000">
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%;">Verify</button>
        </form>
    </div>
</div>
<?php require_once 'includes/footer.php'; ?>
