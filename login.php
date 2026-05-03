<?php
$pageTitle = 'Login';
require_once 'config/config.php';

if (isset($_SESSION['user_id'])) {
    header('Location: ' . ($_SESSION['role'] === 'provider' ? 'provider_profile.php' : ($_SESSION['role'] === 'admin' ? 'admin_panel.php' : 'providers.php')));
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $maxAttempts = 8;
    $lockoutMinutes = 15;

    try {
        $pdo = getDBConnection();
        $secStmt = $pdo->prepare("
            SELECT setting_key, setting_value
            FROM app_settings
            WHERE setting_key IN ('max_login_attempts', 'lockout_minutes')
        ");
        $secStmt->execute();
        foreach ($secStmt->fetchAll() as $row) {
            if (($row['setting_key'] ?? '') === 'max_login_attempts') {
                $maxAttempts = max(3, min(20, (int)($row['setting_value'] ?? 8)));
            } elseif (($row['setting_key'] ?? '') === 'lockout_minutes') {
                $lockoutMinutes = max(5, min(180, (int)($row['setting_value'] ?? 15)));
            }
        }
    } catch (Throwable $e) {
        // use defaults if settings unavailable
    }

    $attemptKey = strtolower($email);
    $_SESSION['login_attempts'] = $_SESSION['login_attempts'] ?? [];
    $attemptState = $_SESSION['login_attempts'][$attemptKey] ?? ['count' => 0, 'lock_until' => 0];
    if (!empty($attemptState['lock_until']) && time() < (int)$attemptState['lock_until']) {
        $remain = max(1, (int)ceil(((int)$attemptState['lock_until'] - time()) / 60));
        $error = 'Too many failed attempts. Try again in ' . $remain . ' minute(s).';
    } else {
        if (!empty($attemptState['lock_until']) && time() >= (int)$attemptState['lock_until']) {
            $attemptState = ['count' => 0, 'lock_until' => 0];
        }

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
                unset($_SESSION['login_attempts'][$attemptKey]);
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
                $redirect = $user['role'] === 'admin' ? 'admin_panel.php' : ($user['role'] === 'provider' ? 'provider_profile.php?id=' . $_SESSION['provider_id'] : 'providers.php');
                header("Location: $redirect");
                exit;
            } else {
                $attemptState['count'] = (int)($attemptState['count'] ?? 0) + 1;
                if ($attemptState['count'] >= $maxAttempts) {
                    $attemptState['lock_until'] = time() + ($lockoutMinutes * 60);
                    $attemptState['count'] = 0;
                    $error = 'Too many failed attempts. Login temporarily locked for ' . $lockoutMinutes . ' minutes.';
                } else {
                    $error = 'Invalid email or password.';
                }
                $_SESSION['login_attempts'][$attemptKey] = $attemptState;
            }
        }
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
            <a href="forgot_password.php">Forgot Password?</a>
        </p>
        <p style="text-align:center; margin-top:0.5rem; color:var(--text-muted);">
            Don't have an account? <a href="register.php">Register</a>
        </p>
    </div>
</div>
<?php require_once 'includes/footer.php'; ?>
