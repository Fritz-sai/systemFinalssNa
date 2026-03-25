<?php
/**
 * ServiceLink - One-time install script
 * Run this once to create database and admin user, then delete this file.
 */
session_start();
$pageTitle = 'Installation';
require_once 'config/config.php';

$host = 'localhost';
$user = 'root';
$pass = '';

$installationMessage = '';
$installationError = '';

try {
    $pdo = new PDO("mysql:host=$host", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Ensure database exists
    $pdo->exec("CREATE DATABASE IF NOT EXISTS servicelink");
    $pdo->exec("USE servicelink");

    // Check if core tables already exist
    $tablesStmt = $pdo->query("SHOW TABLES LIKE 'users'");
    $hasUsersTable = $tablesStmt->fetch(PDO::FETCH_NUM) !== false;

    if (!$hasUsersTable) {
        // Fresh install: run full schema from setup.sql
        $sql = file_get_contents(__DIR__ . '/database/setup.sql');
        // Remove CREATE DATABASE / USE statements if present
        $sql = preg_replace('/^CREATE DATABASE.*?;\\s*/ims', '', $sql);
        $sql = preg_replace('/^USE\\s+servicelink;\\s*/ims', '', $sql);

        // Split into individual statements to avoid failing the whole batch
        $statements = array_filter(array_map('trim', preg_split('/;\\s*[\\r\\n]+/', $sql)));
        foreach ($statements as $statement) {
            if ($statement !== '') {
                $pdo->exec($statement);
            }
        }
    }

    // (Re)create admin user safely
    $hash = password_hash('admin123', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare(
        "INSERT INTO users (email, password_hash, full_name, phone, role, email_verified, phone_verified)
         VALUES (?, ?, ?, ?, ?, 1, 1)
         ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash)"
    );
    $stmt->execute(['admin@servicelink.com', $hash, 'Admin User', '0000000000', 'admin']);

    $installationMessage = "Installation complete! Admin: admin@servicelink.com / admin123";
} catch (Exception $e) {
    $installationError = "Install failed: " . $e->getMessage();
}

require_once 'includes/header.php';
?>

<main>
    <div style="padding: 20px; max-width: 600px; margin: 20px auto;">
        <?php if ($installationError): ?>
            <div style="color: red;"><?= htmlspecialchars($installationError) ?></div>
        <?php elseif ($installationMessage): ?>
            <div style="color: green;"><?= htmlspecialchars($installationMessage) ?></div>
            <br>
            <a href="index.php" class="btn btn-primary">Go to ServiceLink</a>
        <?php endif; ?>
    </div>
</main>

<?php require_once 'includes/footer.php'; ?>
