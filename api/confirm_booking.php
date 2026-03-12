<?php
require_once __DIR__ . '/../config/config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer' || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$bookingId = (int)($_POST['booking_id'] ?? 0);
$agreed = isset($_POST['agreed']) && $_POST['agreed'] === '1';
$rating = isset($_POST['rating']) ? (int)$_POST['rating'] : 0;
$review = trim($_POST['review'] ?? '');

$pdo = getDBConnection();
$userId = $_SESSION['user_id'];

if (!$bookingId) {
    echo json_encode(['success' => false, 'error' => 'Booking ID required']);
    exit;
}

$stmt = $pdo->prepare("SELECT id, provider_id, completion_confirmed FROM bookings WHERE id = ? AND customer_id = ?");
$stmt->execute([$bookingId, $userId]);
$booking = $stmt->fetch();

if (!$booking) {
    echo json_encode(['success' => false, 'error' => 'Booking not found']);
    exit;
}

$conf = $booking['completion_confirmed'] ?? 'pending';
if ($conf !== 'pending' && $conf !== 'agreed') {
    echo json_encode(['success' => false, 'error' => 'Already confirmed']);
    exit;
}
if ($conf === 'agreed') {
    // Already agreed, just adding/updating rating
    if ($rating >= 1 && $rating <= 5) {
        $pdo->prepare("UPDATE bookings SET rating = ?, review = ? WHERE id = ?")
            ->execute([$rating, $review ?: null, $bookingId]);
        $avgStmt = $pdo->prepare("SELECT AVG(rating) FROM bookings WHERE provider_id = ? AND rating IS NOT NULL");
        $avgStmt->execute([$booking['provider_id']]);
    }
    echo json_encode(['success' => true]);
    exit;
}

$confirmed = $agreed ? 'agreed' : 'disputed';
$pdo->prepare("UPDATE bookings SET completion_confirmed = ? WHERE id = ?")->execute([$confirmed, $bookingId]);

if ($agreed && $rating >= 1 && $rating <= 5) {
    $pdo->prepare("UPDATE bookings SET rating = ?, review = ? WHERE id = ?")
        ->execute([$rating, $review ?: null, $bookingId]);

    // Update provider's average rating (denormalized for display)
    $avgStmt = $pdo->prepare("SELECT AVG(rating) FROM bookings WHERE provider_id = ? AND rating IS NOT NULL");
    $avgStmt->execute([$booking['provider_id']]);
    // You could store avg in providers if desired
}

echo json_encode(['success' => true]);
