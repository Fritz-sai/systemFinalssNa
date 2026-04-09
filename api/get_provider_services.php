<?php
require_once __DIR__ . '/../config/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'provider') {
    header('HTTP/1.1 403 Forbidden');
    exit;
}

header('Content-Type: application/json');

$userId = $_SESSION['user_id'];
$pdo = getDBConnection();

// Get provider ID from user_id
$provStmt = $pdo->prepare("SELECT id FROM providers WHERE user_id = ?");
$provStmt->execute([$userId]);
$provRow = $provStmt->fetch();

if (!$provRow) {
    echo json_encode(['services' => []]);
    exit;
}

$providerId = $provRow['id'];

// Get all services for this provider with category names
$stmt = $pdo->prepare("
    SELECT s.id, s.title, s.description, s.price_min, s.price_max, 
           sc.name as category_name
    FROM services s
    LEFT JOIN service_categories sc ON s.category_id = sc.id
    WHERE s.provider_id = ?
    ORDER BY s.created_at DESC
");
$stmt->execute([$providerId]);
$services = $stmt->fetchAll();

echo json_encode(['services' => $services]);
