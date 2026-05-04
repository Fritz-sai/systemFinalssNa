<?php
require_once __DIR__ . '/config/config.php';
try {
    $pdo = getDBConnection();
    $sql = file_get_contents(__DIR__ . '/database/add_gcash_column.sql');
    $pdo->exec($sql);
    echo "Migration successful. 'gcash_number' column added.";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "Column already exists.";
    } else {
        echo "Error: " . $e->getMessage();
    }
}
