<?php
require_once 'config/config.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'provider') {
    header('Location: login.php');
    exit;
}

$pdo = getDBConnection();
$userId = $_SESSION['user_id'];
$providerId = $_SESSION['provider_id'];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $phone = trim($_POST['phone'] ?? '');
    
    if (empty($phone)) {
        $error = 'Phone number is required.';
    } elseif (!preg_match('/^09\d{9}$/', $phone)) {
        $error = 'Invalid phone format. Use 09xxxxxxxxx.';
    } else {
        // Check if phone is already used by another user
        $stmt = $pdo->prepare("SELECT id FROM users WHERE phone = ? AND id != ?");
        $stmt->execute([$phone, $userId]);
        if ($stmt->fetch()) {
            $error = 'This phone number is already registered by another user.';
        } else {
            // Update phone and set as verified
            $pdo->prepare("UPDATE users SET phone = ?, phone_verified = 1 WHERE id = ?")
                ->execute([$phone, $userId]);
            $_SESSION['phone_verified'] = true;
            header('Location: provider_profile.php?id=' . $providerId);
            exit;
        }
    }
}

$pageTitle = 'Verify Phone';
require_once 'includes/header.php';
?>
<div class="auth-container">
    <div class="auth-card fade-in">
        <h1>Add Your Phone Number</h1>
        <p style="color: #6c757d; margin: 0.5rem 0 1rem;">Customers will use this number to contact you.</p>

        <?php if ($error): ?><p class="error-message"><?= htmlspecialchars($error) ?></p><?php endif; ?>

        <form method="POST" class="auth-form">
            <div class="form-group">
                <label>Phone Number</label>
                <input type="tel" name="phone" required placeholder="09xxxxxxxxx" pattern="09\d{9}" autocomplete="tel">
                <small style="color: #6c757d;">Format: 09xxxxxxxxx (11 digits)</small>
            </div>
            <button type="submit" class="btn btn-primary">Save Phone Number</button>

            <p class="toggle-link"><a href="provider_profile.php?id=<?= $providerId ?>">Skip for now</a></p>
        </form>
    </div>
</div>
<?php require_once 'includes/footer.php'; ?>

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
