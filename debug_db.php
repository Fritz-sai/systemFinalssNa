<?php
require_once __DIR__ . '/config/config.php';
$pdo = getDBConnection();
$stmt = $pdo->query("DESCRIBE bookings");
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($columns, JSON_PRETTY_PRINT);
