<?php
require_once __DIR__ . '/../config/config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['success' => false]);
    exit;
}

$prefKey = trim((string)($_POST['pref_key'] ?? ''));
$prefValue = trim((string)($_POST['pref_value'] ?? ''));
if ($prefKey === '') {
    echo json_encode(['success' => false, 'message' => 'Invalid preference key']);
    exit;
}

$pdo = getDBConnection();
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS user_preferences (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            pref_key VARCHAR(100) NOT NULL,
            pref_value TEXT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uk_user_pref (user_id, pref_key),
            INDEX (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $stmt = $pdo->prepare("
        INSERT INTO user_preferences (user_id, pref_key, pref_value)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE pref_value = VALUES(pref_value)
    ");
    $stmt->execute([(int)$_SESSION['user_id'], $prefKey, $prefValue]);
    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => 'Failed to save preference']);
}
?>
