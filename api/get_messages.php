<?php
require_once __DIR__ . '/../config/config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$chatId = (int)($_GET['chat_id'] ?? 0);
$userId = $_SESSION['user_id'];
$role = $_SESSION['role'];

$pdo = getDBConnection();

// Verify user is in this chat
$stmt = $pdo->prepare("SELECT id FROM chats WHERE id = ? AND archived = 0 AND (customer_id = ? OR provider_id = (SELECT id FROM providers WHERE user_id = ?))");
$stmt->execute([$chatId, $userId, $userId]);
if (!$stmt->fetch()) {
    echo json_encode(['error' => 'Access denied']);
    exit;
}

// Providers must unlock the conversation (spend credits) before viewing customer messages
if ($role === 'provider') {
    $provStmt = $pdo->prepare("SELECT id FROM providers WHERE user_id = ? LIMIT 1");
    $provStmt->execute([$userId]);
    $providerId = (int)($provStmt->fetchColumn() ?: 0);

    $chatStmt = $pdo->prepare("SELECT customer_id FROM chats WHERE id = ? AND provider_id = ? LIMIT 1");
    $chatStmt->execute([$chatId, $providerId]);
    $chatRow = $chatStmt->fetch();

    if ($chatRow) {
        $customerId = (int)$chatRow['customer_id'];
        $unlock = $pdo->prepare("SELECT 1 FROM contact_unlocks WHERE provider_id = ? AND customer_id = ? LIMIT 1");
        $unlock->execute([$providerId, $customerId]);
        if (!$unlock->fetch()) {
            echo json_encode(['locked' => true, 'messages' => []]);
            exit;
        }
    }
}

$stmt = $pdo->prepare("SELECT m.*, u.full_name FROM messages m JOIN users u ON m.sender_id = u.id WHERE m.chat_id = ? ORDER BY m.created_at ASC");
$stmt->execute([$chatId]);
$messages = $stmt->fetchAll();

// Mark chat message notifications as read for this user
$pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND type = 'message' AND chat_id = ? AND is_read = 0")
    ->execute([$userId, $chatId]);

$result = [];
foreach ($messages as $m) {
    // Check if message is a service message (JSON with type='service' or type='service_status')
    $messageContent = $m['message'];
    $decoded = json_decode($messageContent, true);
    
    if ($decoded && isset($decoded['type']) && ($decoded['type'] === 'service' || $decoded['type'] === 'service_status')) {
        // It's a service message, keep JSON as-is (don't escape)
        $displayMessage = $messageContent;
    } else {
        // Regular text message, escape HTML
        $displayMessage = htmlspecialchars($messageContent);
    }
    
    $result[] = [
        'id' => $m['id'],
        'message' => $displayMessage,
        'sender_type' => $m['sender_type'],
        'sender_name' => $m['full_name'],
        'created_at' => date('M j, g:i A', strtotime($m['created_at']))
    ];
}

echo json_encode(['messages' => $result]);
