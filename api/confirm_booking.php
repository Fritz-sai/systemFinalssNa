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
$paymentAccepted = isset($_POST['payment_accepted']) && $_POST['payment_accepted'] === 'on';

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

// Handle photo upload
$photoPath = null;
if (isset($_FILES['review_photo']) && $_FILES['review_photo']['error'] === UPLOAD_ERR_OK) {
    $file = $_FILES['review_photo'];
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    
    if (in_array($file['type'], $allowedTypes)) {
        $uploadDir = __DIR__ . '/../uploads/reviews/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
        
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'booking_' . $bookingId . '_' . time() . '.' . $ext;
        $filepath = $uploadDir . $filename;
        
        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            $photoPath = 'uploads/reviews/' . $filename;
        }
    }
}

if ($conf === 'agreed') {
    // Already agreed, just adding/updating rating and photo
    if ($rating >= 1 && $rating <= 5) {
        $pdo->prepare("UPDATE bookings SET rating = ?, review = ?, review_photo_path = COALESCE(?, review_photo_path), payment_accepted = ? WHERE id = ?")
            ->execute([$rating, $review ?: null, $photoPath, $paymentAccepted ? 1 : 0, $bookingId]);
    }
    echo json_encode(['success' => true]);
    exit;
}

$confirmed = $agreed ? 'agreed' : 'disputed';
$pdo->prepare("UPDATE bookings SET completion_confirmed = ?, payment_accepted = ? WHERE id = ?")
    ->execute([$confirmed, $paymentAccepted ? 1 : 0, $bookingId]);

if ($agreed && $rating >= 1 && $rating <= 5) {
    $pdo->prepare("UPDATE bookings SET rating = ?, review = ?, review_photo_path = ? WHERE id = ?")
        ->execute([$rating, $review ?: null, $photoPath, $bookingId]);
}

echo json_encode(['success' => true]);
