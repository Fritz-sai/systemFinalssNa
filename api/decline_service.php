<?php
require_once __DIR__ . '/../config/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer' || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

$chatId = (int)($_POST['chat_id'] ?? 0);
$serviceId = (int)($_POST['service_id'] ?? 0);
$instanceId = $_POST['instance_id'] ?? '';
$userId = $_SESSION['user_id'];

if (!$chatId || !$serviceId) {
    echo json_encode(['error' => 'Missing parameters']);
    exit;
}

$pdo = getDBConnection();
try {

// Verify chat belongs to this customer
$chatStmt = $pdo->prepare("SELECT id, provider_id FROM chats WHERE id = ? AND customer_id = ?");
$chatStmt->execute([$chatId, $userId]);
$chat = $chatStmt->fetch();
if (!$chat) {
    echo json_encode(['error' => 'Chat not found']);
    exit;
}

// Verify service belongs to the chat's provider
$serviceStmt = $pdo->prepare("SELECT id FROM services WHERE id = ? AND provider_id = ?");
$serviceStmt->execute([$serviceId, $chat['provider_id']]);
if (!$serviceStmt->fetch()) {
    echo json_encode(['error' => 'Service not found']);
    exit;
}

// Check if this specific instance has already been responded to
$allMessages = $pdo->prepare("SELECT message FROM messages WHERE chat_id = ?");
$allMessages->execute([$chatId]);
$messages = $allMessages->fetchAll();

$alreadyResponded = false;
foreach ($messages as $msg) {
    $decoded = json_decode($msg['message'], true);
    if ($decoded && isset($decoded['type']) && $decoded['type'] === 'service_status' && isset($decoded['instance_id']) && $decoded['instance_id'] === $instanceId) {
        $alreadyResponded = true;
        break;
    }
}

if ($alreadyResponded) {
    echo json_encode(['error' => 'You already responded to this service']);
    exit;
}

// Create/update service response (unique key is chat_id + service_id)
$insertStmt = $pdo->prepare("
    INSERT INTO service_acceptances (chat_id, service_id, customer_id, provider_id, status, accepted_at)
    VALUES (?, ?, ?, ?, 'declined', NOW())
    ON DUPLICATE KEY UPDATE
        customer_id = VALUES(customer_id),
        provider_id = VALUES(provider_id),
        status = 'declined',
        accepted_at = NOW()
");
$insertStmt->execute([$chatId, $serviceId, $userId, $chat['provider_id']]);

// Get service name for the status message
$serviceNameStmt = $pdo->prepare("SELECT title FROM services WHERE id = ?");
$serviceNameStmt->execute([$serviceId]);
$serviceName = $serviceNameStmt->fetchColumn() ?: 'Service';

// Insert status message in chat
$statusMessage = json_encode([
    'type' => 'service_status',
    'service_id' => $serviceId,
    'instance_id' => $instanceId,
    'action' => 'declined',
    'service_name' => $serviceName
]);
$pdo->prepare("INSERT INTO messages (chat_id, sender_id, sender_type, message) VALUES (?, ?, ?, ?)")
    ->execute([$chatId, $userId, 'customer', $statusMessage]);

// Create a notification for the provider
$providerStmt = $pdo->prepare("SELECT user_id FROM providers WHERE id = ?");
$providerStmt->execute([$chat['provider_id']]);
$providerRow = $providerStmt->fetch();

if ($providerRow) {
    $customerNameStmt = $pdo->prepare("SELECT full_name FROM users WHERE id = ?");
    $customerNameStmt->execute([$userId]);
    $customerName = $customerNameStmt->fetchColumn() ?: 'Customer';
    
    $serviceNameStmt = $pdo->prepare("SELECT title FROM services WHERE id = ?");
    $serviceNameStmt->execute([$serviceId]);
    $serviceName = $serviceNameStmt->fetchColumn() ?: 'Service';
    
    $pdo->prepare("
        INSERT INTO notifications (user_id, type, chat_id, title, body, is_read)
        VALUES (?, 'service_declined', ?, ?, ?, 0)
    ")->execute([
        $providerRow['user_id'],
        $chatId,
        'Service Declined',
        $customerName . ' declined your ' . $serviceName . ' service'
    ]);
}

echo json_encode(['success' => true]);
} catch (Throwable $e) {
    echo json_encode(['error' => 'Failed to decline service. Please try again.']);
}
