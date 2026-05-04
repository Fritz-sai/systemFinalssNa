<?php
require_once __DIR__ . '/config/config.php';
$pdo = getDBConnection();
try {
    $pdo->exec("ALTER TABLE `bookings` MODIFY COLUMN `status` ENUM('pending', 'confirmed', 'completed', 'cancelled', 'rejected') DEFAULT 'pending'");
    $pdo->exec("ALTER TABLE `bookings` ADD COLUMN `rejection_reason` TEXT NULL AFTER `notes`");
    $pdo->exec("ALTER TABLE `bookings` ADD COLUMN `suggested_reschedule_date` DATETIME NULL AFTER `rejection_reason`");
    echo "Migration successful!";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
