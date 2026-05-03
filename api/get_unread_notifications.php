<?php
require_once __DIR__ . '/../config/config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['count' => 0]);
    exit;
}

$pdo = getDBConnection();
$userId = $_SESSION['user_id'];

$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM notifications
    WHERE user_id = ?
      AND is_read = 0
      AND type IN ('message', 'service_accepted', 'service_declined')
");
$stmt->execute([$userId]);
$count = (int)$stmt->fetchColumn();

echo json_encode(['count' => $count]);

