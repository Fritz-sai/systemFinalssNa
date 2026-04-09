<?php
require_once __DIR__ . '/../config/config.php';

if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('HTTP/1.1 403 Forbidden');
    exit;
}
header('Content-Type: application/json');

$chatId = (int)($_POST['chat_id'] ?? 0);
$message = trim($_POST['message'] ?? '');
$serviceId = (int)($_POST['service_id'] ?? 0);
$instanceId = $_POST['instance_id'] ?? '';
$userId = $_SESSION['user_id'];
$role = $_SESSION['role'];

if (!$chatId || (!$message && !$serviceId)) exit;

$pdo = getDBConnection();

// Verify access
$stmt = $pdo->prepare("SELECT id, customer_id, provider_id FROM chats WHERE id = ?");
$stmt->execute([$chatId]);
$chat = $stmt->fetch();
if (!$chat) exit;

$senderType = 'customer';
if ($role === 'provider') {
    $prov = $pdo->prepare("SELECT id FROM providers WHERE user_id = ?");
    $prov->execute([$userId]);
    $provRow = $prov->fetch();
    if ($provRow && $provRow['id'] == $chat['provider_id']) {
        $senderType = 'provider';
    }
} elseif ($chat['customer_id'] != $userId) {
    exit;
}

// If a service is being shared, format the message
if ($serviceId && $senderType === 'provider') {
    $serviceStmt = $pdo->prepare("
        SELECT id, title, description, price_min, price_max
        FROM services
        WHERE id = ? AND provider_id = ?
    ");
    $serviceStmt->execute([$serviceId, $chat['provider_id']]);
    $service = $serviceStmt->fetch();
    
    if ($service) {
        // Create a special message format for services with unique instance_id
        $messageContent = json_encode([
            'type' => 'service',
            'service_id' => $service['id'],
            'instance_id' => $instanceId ?: 'srv_' . time() . '_' . rand(1000, 9999),
            'title' => $service['title'],
            'description' => $service['description'],
            'price_min' => $service['price_min'],
            'price_max' => $service['price_max']
        ]);
    } else {
        exit;
    }
} else {
    $messageContent = $message;
}

// Use user_id for sender - we need to map: customer uses user_id, provider uses user_id from providers

$pdo->prepare("INSERT INTO messages (chat_id, sender_id, sender_type, message) VALUES (?, ?, ?, ?)")
    ->execute([$chatId, $userId, $senderType, $messageContent]);

$pdo->prepare("UPDATE chats SET updated_at = NOW() WHERE id = ?")->execute([$chatId]);

// Create notification for the recipient
$recipientUserId = null;
if ($senderType === 'customer') {
    $provUser = $pdo->prepare("SELECT user_id FROM providers WHERE id = ?");
    $provUser->execute([$chat['provider_id']]);
    $provUserRow = $provUser->fetch();
    $recipientUserId = $provUserRow['user_id'] ?? null;
} else {
    $recipientUserId = $chat['customer_id'];
}

if ($recipientUserId) {
    $senderNameStmt = $pdo->prepare("SELECT full_name FROM users WHERE id = ?");
    $senderNameStmt->execute([$userId]);
    $senderName = $senderNameStmt->fetchColumn() ?: 'New message';

    $notificationBody = $message ?: ($service['title'] ?? 'Service shared');
    $pdo->prepare("
        INSERT INTO notifications (user_id, type, chat_id, title, body, is_read)
        VALUES (?, 'message', ?, ?, ?, 0)
    ")->execute([
        $recipientUserId,
        $chatId,
        'New message from ' . $senderName,
        mb_substr($notificationBody, 0, 200)
    ]);
}

echo json_encode(['success' => true]);
