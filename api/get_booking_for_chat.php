<?php
require_once __DIR__ . '/../config/config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$chatId = (int)($_GET['chat_id'] ?? 0);
$userId = $_SESSION['user_id'];
$pdo = getDBConnection();

if (!$chatId) {
    echo json_encode(['error' => 'Chat ID required']);
    exit;
}

// Verify customer is in this chat
$stmt = $pdo->prepare("SELECT provider_id FROM chats WHERE id = ? AND customer_id = ? AND archived = 0");
$stmt->execute([$chatId, $userId]);
$chat = $stmt->fetch();

if (!$chat) {
    echo json_encode(['error' => 'Chat not found']);
    exit;
}

$providerId = $chat['provider_id'];

// Find the most recent booking for this customer-provider pair with 'completed' or 'pending' status
// that has a service_acceptance record in this chat
$bookingStmt = $pdo->prepare("
    SELECT b.id 
    FROM bookings b
    JOIN service_acceptances sa ON b.service_id = sa.service_id 
        AND b.customer_id = sa.customer_id 
        AND b.provider_id = sa.provider_id
    WHERE sa.chat_id = ? 
        AND b.customer_id = ? 
        AND b.provider_id = ?
        AND sa.status = 'accepted'
        AND b.status IN ('completed', 'pending')
    ORDER BY b.created_at DESC, b.id DESC
    LIMIT 1
");
$bookingStmt->execute([$chatId, $userId, $providerId]);
$booking = $bookingStmt->fetch();

if ($booking) {
    echo json_encode(['booking_id' => (int)$booking['id']]);
} else {
    echo json_encode(['error' => 'No active booking found']);
}
?>
