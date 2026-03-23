<?php
$pageTitle = 'Enter Phone Number';
require_once 'config/config.php';

if (isset($_SESSION['user_id'])) {
    header('Location: ' . ($_SESSION['role'] === 'provider' ? 'provider_profile.php?id=' . $_SESSION['provider_id'] : 'index.php'));
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
    $phone = trim($_POST['phone'] ?? '');

    if (empty($phone)) {
        $error = 'Phone number is required.';
    } elseif (!preg_match('/^09\d{9}$/', $phone)) {
        $error = 'Invalid phone format. Use 09xxxxxxxxx.';
    } else {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE phone = ?');
        $stmt->execute([$phone]);
        if ($stmt->fetch()) {
            $error = 'Phone number already registered.';
        } else {
            $_SESSION['reg_phone'] = $phone;
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
        <h1>Enter Your Phone Number</h1>
        <p style="color: #6c757d; margin: 0.5rem 0 1rem;">We'll use this to contact you about bookings.</p>

        <?php if ($error): ?><p class="error-message"><?= htmlspecialchars($error) ?></p><?php endif; ?>

        <form method="POST" class="auth-form">
            <div class="form-group">
                <label>Phone Number</label>
                <input type="tel" name="phone" required placeholder="09xxxxxxxxx" pattern="09\d{9}" value="<?= htmlspecialchars($reg_phone) ?>">
                <small style="color: #6c757d;">Format: 09xxxxxxxxx (11 digits)</small>
            </div>
            <button type="submit" class="btn btn-primary">Continue</button>

            <p class="toggle-link">Already have an account? <a href="login.php">Login</a></p>
        </form>
    </div>
</div>
<?php require_once 'includes/footer.php'; ?>
