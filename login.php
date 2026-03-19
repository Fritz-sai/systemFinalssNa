<?php
$pageTitle = 'Login';
require_once 'config/config.php';

if (isset($_SESSION['user_id'])) {
    header('Location: ' . ($_SESSION['role'] === 'provider' ? 'dashboard_provider.php' : ($_SESSION['role'] === 'admin' ? 'admin_panel.php' : 'dashboard_customer.php')));
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Email and password are required.';
    } else {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT id, password_hash, full_name, role, city, barangay FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['full_name'] = $user['full_name'];
            // set location session for customers
            if ($user['role'] === 'customer') {
                $_SESSION['user_city'] = $user['city'] ?? '';
                $_SESSION['user_barangay'] = $user['barangay'] ?? '';
            }
            if ($user['role'] === 'provider') {
                $prov = $pdo->prepare("SELECT id FROM providers WHERE user_id = ?");
                $prov->execute([$user['id']]);
                $provRow = $prov->fetch();
                $_SESSION['provider_id'] = $provRow ? $provRow['id'] : null;
                // also set provider's city/barangay from providers table
                try {
                    $p = $pdo->prepare("SELECT city, barangay FROM providers WHERE user_id = ? LIMIT 1");
                    $p->execute([$user['id']]);
                    $provLoc = $p->fetch();
                    if ($provLoc) {
                        $_SESSION['user_city'] = $provLoc['city'] ?? '';
                        $_SESSION['user_barangay'] = $provLoc['barangay'] ?? '';
                    }
                } catch (Throwable $e) { }
            }
            $redirect = $user['role'] === 'admin' ? 'admin_panel.php' : ($user['role'] === 'provider' ? 'dashboard_provider.php' : 'dashboard_customer.php');
            header("Location: $redirect");
            exit;
        }
        $error = 'Invalid email or password.';
    }
}

require_once 'includes/header.php';
?>
<div class="auth-container">
    <div class="auth-card fade-in">
        <h1>Login</h1>
        <?php if ($error): ?><p style="color: #e74c3c; margin-bottom: 1rem;"><?= htmlspecialchars($error) ?></p><?php endif; ?>
        <form method="POST">
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" required>
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%; margin-top:1rem;">Login</button>
        </form>
        <p style="text-align:center; margin-top:1rem; color:var(--text-muted);">
            Don't have an account? <a href="register.php">Register</a>
        </p>
    </div>
</div>
<?php require_once 'includes/footer.php'; ?>
