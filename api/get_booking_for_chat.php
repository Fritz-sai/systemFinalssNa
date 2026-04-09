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

function getServiceLabel($title, $serviceId = 0) {
    $name = trim((string)($title ?? ''));
    if ($name !== '') {
        return $name;
    }
    return $serviceId > 0 ? ('Service #' . (int)$serviceId) : 'Booked service';
}

if (!$chatId) {
    echo json_encode(['error' => 'Chat ID required']);
    exit;
}

// Verify customer is in this chat
$stmt = $pdo->prepare("SELECT provider_id, service_id FROM chats WHERE id = ? AND customer_id = ? AND archived = 0");
$stmt->execute([$chatId, $userId]);
$chat = $stmt->fetch();

if (!$chat) {
    echo json_encode(['error' => 'Chat not found']);
    exit;
}

$providerId = $chat['provider_id'];

// First try: booking explicitly accepted from this chat.
$bookingStmt = $pdo->prepare("
    SELECT b.id, b.service_id,
           COALESCE(NULLIF(TRIM(s.title), ''), NULLIF(TRIM(sc.name), ''), CONCAT('Service #', b.service_id)) AS service_title,
           s.price_min, s.price_max
    FROM bookings b
    LEFT JOIN services s ON b.service_id = s.id
    LEFT JOIN service_categories sc ON s.category_id = sc.id
    JOIN service_acceptances sa ON b.service_id = sa.service_id 
        AND b.customer_id = sa.customer_id 
        AND b.provider_id = sa.provider_id
    WHERE sa.chat_id = ? 
        AND b.customer_id = ? 
        AND b.provider_id = ?
        AND sa.status = 'accepted'
    ORDER BY (b.status = 'cancelled') ASC, b.created_at DESC, b.id DESC
    LIMIT 1
");
$bookingStmt->execute([$chatId, $userId, $providerId]);
$booking = $bookingStmt->fetch();

// Fallback: most recent booking between this customer and provider.
// This covers bookings created directly from Book Service (without chat accept records).
if (!$booking) {
    $chatServiceId = (int)($chat['service_id'] ?? 0);
    if ($chatServiceId > 0) {
        $bookingStmt = $pdo->prepare("
            SELECT b.id, b.service_id,
                   COALESCE(NULLIF(TRIM(s.title), ''), NULLIF(TRIM(sc.name), ''), CONCAT('Service #', b.service_id)) AS service_title,
                   s.price_min, s.price_max
            FROM bookings b
            LEFT JOIN services s ON b.service_id = s.id
            LEFT JOIN service_categories sc ON s.category_id = sc.id
            WHERE b.customer_id = ?
                AND b.provider_id = ?
                AND b.service_id = ?
            ORDER BY (b.status = 'cancelled') ASC, b.created_at DESC, b.id DESC
            LIMIT 1
        ");
        $bookingStmt->execute([$userId, $providerId, $chatServiceId]);
        $booking = $bookingStmt->fetch();
    }
}

if (!$booking) {
    $bookingStmt = $pdo->prepare("
        SELECT b.id, b.service_id,
               COALESCE(NULLIF(TRIM(s.title), ''), NULLIF(TRIM(sc.name), ''), CONCAT('Service #', b.service_id)) AS service_title,
               s.price_min, s.price_max
        FROM bookings b
        LEFT JOIN services s ON b.service_id = s.id
        LEFT JOIN service_categories sc ON s.category_id = sc.id
        WHERE b.customer_id = ?
            AND b.provider_id = ?
        ORDER BY (b.status = 'cancelled') ASC, b.created_at DESC, b.id DESC
        LIMIT 1
    ");
    $bookingStmt->execute([$userId, $providerId]);
    $booking = $bookingStmt->fetch();
}

// Final fallback for older chat accepts with no booking row yet:
// create a booking from latest accepted service in this chat.
if (!$booking) {
    $acceptedServiceStmt = $pdo->prepare("
        SELECT sa.service_id,
               COALESCE(NULLIF(TRIM(s.title), ''), NULLIF(TRIM(sc.name), ''), CONCAT('Service #', sa.service_id)) AS service_title,
               s.price_min, s.price_max
        FROM service_acceptances sa
        LEFT JOIN services s ON sa.service_id = s.id
        LEFT JOIN service_categories sc ON s.category_id = sc.id
        WHERE sa.chat_id = ?
            AND sa.customer_id = ?
            AND sa.provider_id = ?
            AND sa.status = 'accepted'
        ORDER BY sa.accepted_at DESC, sa.id DESC
        LIMIT 1
    ");
    $acceptedServiceStmt->execute([$chatId, $userId, $providerId]);
    $acceptedService = $acceptedServiceStmt->fetch();

    if ($acceptedService && !empty($acceptedService['service_id'])) {
        $createStmt = $pdo->prepare("
            INSERT INTO bookings (customer_id, provider_id, service_id, status, scheduled_date, notes)
            VALUES (?, ?, ?, 'confirmed', NULL, 'Auto-created from accepted chat service')
        ");
        $createStmt->execute([$userId, $providerId, (int)$acceptedService['service_id']]);
        $newBookingId = (int)$pdo->lastInsertId();

        $booking = [
            'id' => $newBookingId,
            'service_id' => (int)$acceptedService['service_id'],
            'service_title' => getServiceLabel($acceptedService['service_title'] ?? '', (int)$acceptedService['service_id']),
            'price_min' => $acceptedService['price_min'] ?? null,
            'price_max' => $acceptedService['price_max'] ?? null
        ];
    }
}

if ($booking) {
    echo json_encode([
        'booking_id' => (int)$booking['id'],
        'service_title' => getServiceLabel($booking['service_title'] ?? '', (int)($booking['service_id'] ?? 0)),
        'price_min' => isset($booking['price_min']) ? (float)$booking['price_min'] : null,
        'price_max' => isset($booking['price_max']) ? (float)$booking['price_max'] : null
    ]);
} else {
    echo json_encode(['error' => 'No active booking found']);
}
?>
