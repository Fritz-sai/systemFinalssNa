<?php
/**
 * ServiceLink - Database Configuration
 */

define('DB_HOST', 'localhost');
define('DB_NAME', 'servicelink');
define('DB_USER', 'root');
define('DB_PASS', '');

function getDBConnection() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]);

            // Add provider profile image column if missing
            try {
                $col = $pdo->prepare("
                    SELECT COUNT(*)
                    FROM INFORMATION_SCHEMA.COLUMNS
                    WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'providers' AND COLUMN_NAME = 'profile_image_path'
                ");
                $col->execute([DB_NAME]);
                $hasCol = (int)$col->fetchColumn() > 0;
                if (!$hasCol) {
                    $pdo->exec("ALTER TABLE providers ADD COLUMN profile_image_path VARCHAR(255) NULL");
                }
            } catch (Throwable $e) {
                // ignore if no permission
            }

            // Provider credits column
            try {
                $col = $pdo->prepare("
                    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
                    WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'providers' AND COLUMN_NAME = 'credits'
                ");
                $col->execute([DB_NAME]);
                if ((int)$col->fetchColumn() === 0) {
                    $pdo->exec("ALTER TABLE providers ADD COLUMN credits INT NOT NULL DEFAULT 0");
                }
            } catch (Throwable $e) { /* ignore */ }

            // Contact unlocks (provider_id, customer_id = user_id of customer)
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS contact_unlocks (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    provider_id INT NOT NULL,
                    customer_id INT NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY uk_provider_customer (provider_id, customer_id),
                    INDEX (provider_id),
                    INDEX (customer_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");

            // Credit purchases (for history)
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS credit_purchases (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    provider_id INT NOT NULL,
                    credits INT NOT NULL,
                    amount DECIMAL(10,2) NOT NULL,
                    reference_no VARCHAR(64) DEFAULT NULL,
                    status VARCHAR(32) NOT NULL DEFAULT 'completed',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX (provider_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");

            // Add booking completion/rating columns if missing
            try {
                $col = $pdo->prepare("
                    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
                    WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'bookings' AND COLUMN_NAME = 'completion_confirmed'
                ");
                $col->execute([DB_NAME]);
                if ((int)$col->fetchColumn() === 0) {
                    $pdo->exec("ALTER TABLE bookings ADD COLUMN completion_confirmed ENUM('pending','agreed','disputed') DEFAULT 'pending'");
                    $pdo->exec("ALTER TABLE bookings ADD COLUMN rating TINYINT UNSIGNED NULL");
                    $pdo->exec("ALTER TABLE bookings ADD COLUMN review TEXT NULL");
                }
            } catch (Throwable $e) { /* ignore */ }

            // Notifications table (for chat + other alerts)
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS notifications (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NOT NULL,
                    type VARCHAR(32) NOT NULL,
                    chat_id INT DEFAULT NULL,
                    title VARCHAR(120) DEFAULT NULL,
                    body VARCHAR(255) DEFAULT NULL,
                    is_read TINYINT(1) NOT NULL DEFAULT 0,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX (user_id),
                    INDEX (chat_id),
                    INDEX (is_read)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
        } catch (PDOException $e) {
            die("Database connection failed: " . $e->getMessage());
        }
    }
    return $pdo;
}
?>
