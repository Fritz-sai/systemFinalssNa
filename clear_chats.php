<?php
require_once 'config/config.php';

// Security: only allow from admin or with a specific token
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die('Access denied. Admin only.');
}

$pdo = getDBConnection();

try {
    // Disable foreign key checks
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    
    // Delete records (not truncate, to avoid FK constraint issues)
    $pdo->exec("DELETE FROM messages");
    $pdo->exec("DELETE FROM contact_unlocks");
    $pdo->exec("DELETE FROM notifications");
    $pdo->exec("DELETE FROM chats");
    
    // Reset auto-increment counters
    $pdo->exec("ALTER TABLE messages AUTO_INCREMENT = 1");
    $pdo->exec("ALTER TABLE chats AUTO_INCREMENT = 1");
    $pdo->exec("ALTER TABLE contact_unlocks AUTO_INCREMENT = 1");
    $pdo->exec("ALTER TABLE notifications AUTO_INCREMENT = 1");
    
    // Re-enable foreign key checks
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    
    echo "✓ All conversations, messages, contact unlocks, and notifications cleared successfully.";
} catch (Exception $e) {
    echo "Error clearing data: " . $e->getMessage();
}
?>
