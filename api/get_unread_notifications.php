<?php
require_once __DIR__ . '/../config/config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['count' => 0]);
    exit;
}

$pdo = getDBConnection();
$userId = $_SESSION['user_id'];

$count = (int)$pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0")
    ->execute([$userId]) ?: 0;

// PDOStatement::execute returns bool; fetch count properly
$stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
$stmt->execute([$userId]);
$count = (int)$stmt->fetchColumn();

echo json_encode(['count' => $count]);

