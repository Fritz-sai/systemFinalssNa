<?php
/**
 * API: Get providers with filters
 */
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');

$city = $_GET['city'] ?? '';
$barangay = $_GET['barangay'] ?? '';
$category = $_GET['category'] ?? '';
$rating = $_GET['rating'] ?? '';
$sponsored = isset($_GET['sponsored']) && $_GET['sponsored'] === '1';
$newInArea = isset($_GET['new_in_area']) && $_GET['new_in_area'] === '1';
$userCity = $_GET['user_city'] ?? '';

$pdo = getDBConnection();

$sql = "SELECT p.id, p.user_id, p.city, p.barangay, p.profile_image_path, u.full_name, u.phone,
        (SELECT AVG(r.rating) FROM (SELECT 1 as rating) r) as avg_rating,
        (SELECT COUNT(*) FROM services s WHERE s.provider_id = p.id) as service_count,
        (SELECT MIN(s.price_min) FROM services s WHERE s.provider_id = p.id) as min_price
        FROM providers p
        JOIN users u ON p.user_id = u.id
        WHERE p.verification_status = 'approved'";

$params = [];

if ($city) {
    $sql .= " AND p.city LIKE ?";
    $params[] = "%$city%";
}
if ($barangay) {
    $sql .= " AND p.barangay LIKE ?";
    $params[] = "%$barangay%";
}
if ($category) {
    $sql .= " AND EXISTS (SELECT 1 FROM services s WHERE s.provider_id = p.id AND s.category_id = ?)";
    $params[] = $category;
}

if ($sponsored) {
    $sql .= " AND EXISTS (SELECT 1 FROM ads a WHERE a.provider_id = p.id AND a.status = 'active')";
}

if ($newInArea && $userCity) {
    $sql .= " AND p.city = ?";
    $params[] = $userCity;
}

$sql .= " ORDER BY p.created_at DESC LIMIT 20";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$providers = $stmt->fetchAll();

// Add sponsored flag and rating
foreach ($providers as &$p) {
    $adStmt = $pdo->prepare("SELECT 1 FROM ads WHERE provider_id = ? AND status = 'active'");
    $adStmt->execute([$p['id']]);
    $p['sponsored'] = $adStmt->fetch() ? true : false;
    $p['avg_rating'] = $p['avg_rating'] ?? 4.5;
}

echo json_encode(['success' => true, 'providers' => $providers]);
