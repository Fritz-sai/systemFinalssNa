<?php
require_once __DIR__ . '/../config/config.php';

if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('HTTP/1.1 403 Forbidden');
    exit;
}
header('Content-Type: application/json');

$chatId = (int)($_POST['chat_id'] ?? 0);
$userId = $_SESSION['user_id'];
$role = $_SESSION['role'];

if (!$chatId) {
    echo json_encode(['error' => 'Missing chat id']);
    exit;
}

$pdo = getDBConnection();

$stmt = $pdo->prepare("SELECT id, customer_id, provider_id FROM chats WHERE id = ? AND archived = 0");
$stmt->execute([$chatId]);
$chat = $stmt->fetch();
if (!$chat) {
    echo json_encode(['error' => 'Conversation not found or already deleted']);
    exit;
}

$allowed = false;
if ($role === 'customer' && $chat['customer_id'] === $userId) {
    $allowed = true;
} elseif ($role === 'provider') {
    $prov = $pdo->prepare("SELECT id FROM providers WHERE user_id = ?");
    $prov->execute([$userId]);
    $provRow = $prov->fetch();
    if ($provRow && $provRow['id'] == $chat['provider_id']) {
        $allowed = true;
    }
}

if (!$allowed) {
    echo json_encode(['error' => 'Access denied']);
    exit;
}

$update = $pdo->prepare("UPDATE chats SET archived = 1 WHERE id = ?");
$update->execute([$chatId]);

// Clear active chat session if the deleted chat was selected
if (isset($_SESSION['active_chat_id']) && $_SESSION['active_chat_id'] == $chatId) {
    unset($_SESSION['active_chat_id']);
}

echo json_encode(['success' => true]);
