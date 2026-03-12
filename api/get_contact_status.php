<?php
require_once __DIR__ . '/../config/config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'provider') {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$chatId = (int)($_GET['chat_id'] ?? 0);
$pdo = getDBConnection();
$providerId = $_SESSION['provider_id'];

if (!$chatId) {
    echo json_encode(['error' => 'chat_id required']);
    exit;
}

// Get chat and customer_id
$stmt = $pdo->prepare("SELECT customer_id FROM chats WHERE id = ? AND provider_id = ?");
$stmt->execute([$chatId, $providerId]);
$chat = $stmt->fetch();
if (!$chat) {
    echo json_encode(['error' => 'Chat not found']);
    exit;
}

$customerId = (int)$chat['customer_id'];

// Check if already unlocked
$unlock = $pdo->prepare("SELECT 1 FROM contact_unlocks WHERE provider_id = ? AND customer_id = ?");
$unlock->execute([$providerId, $customerId]);
$isUnlocked = $unlock->fetch() ? true : false;

// Get provider credits
$creditsStmt = $pdo->prepare("SELECT credits FROM providers WHERE id = ?");
$creditsStmt->execute([$providerId]);
$credits = (int)($creditsStmt->fetchColumn() ?: 0);

$result = [
    'unlocked' => $isUnlocked,
    'credits' => $credits,
    'cost' => CREDITS_PER_UNLOCK
];

if ($isUnlocked) {
    $userStmt = $pdo->prepare("SELECT full_name, phone, email FROM users WHERE id = ?");
    $userStmt->execute([$customerId]);
    $user = $userStmt->fetch();
    $result['contact'] = [
        'full_name' => $user['full_name'] ?? '',
        'phone' => $user['phone'] ?? '',
        'email' => $user['email'] ?? ''
    ];
}

echo json_encode($result);
