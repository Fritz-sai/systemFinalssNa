<?php
require_once __DIR__ . '/../config/config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'provider' || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$providerId = (int)($_SESSION['provider_id'] ?? 0);
$categoryId = (int)($_POST['category_id'] ?? 0);
$priceMin = (float)($_POST['price_min'] ?? -1);
$priceMax = (float)($_POST['price_max'] ?? 0);

if ($providerId <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid session']);
    exit;
}

if ($categoryId <= 0 || $priceMin < 0) {
    echo json_encode(['success' => false, 'error' => 'Please select a category and enter a valid minimum price.']);
    exit;
}

if ($priceMax > 0 && $priceMax < $priceMin) {
    echo json_encode(['success' => false, 'error' => 'Maximum price must be greater than or equal to minimum price.']);
    exit;
}

$pdo = getDBConnection();

$catStmt = $pdo->prepare('SELECT id FROM service_categories WHERE id = ? LIMIT 1');
$catStmt->execute([$categoryId]);
if (!$catStmt->fetch()) {
    echo json_encode(['success' => false, 'error' => 'Invalid category.']);
    exit;
}

$effectiveMax = $priceMax > 0 ? $priceMax : $priceMin;

try {
    $pdo->prepare('INSERT INTO services (provider_id, category_id, price_min, price_max) VALUES (?, ?, ?, ?)')
        ->execute([$providerId, $categoryId, $priceMin, $effectiveMax]);
    echo json_encode(['success' => true, 'service_id' => (int)$pdo->lastInsertId()]);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'error' => 'Could not add service. Please try again.']);
}
