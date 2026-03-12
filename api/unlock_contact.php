<?php
require_once __DIR__ . '/../config/config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'provider' || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$chatId = (int)($_POST['chat_id'] ?? 0);
$pdo = getDBConnection();
$providerId = $_SESSION['provider_id'] ?? null;

// Fallback: get provider_id from providers if not in session
if (empty($providerId)) {
    $p = $pdo->prepare("SELECT id FROM providers WHERE user_id = ?");
    $p->execute([$_SESSION['user_id']]);
    $providerId = (int)($p->fetchColumn() ?: 0);
    if ($providerId) {
        $_SESSION['provider_id'] = $providerId;
    }
}

if (!$chatId) {
    echo json_encode(['success' => false, 'error' => 'chat_id required']);
    exit;
}
if (!$providerId) {
    echo json_encode(['success' => false, 'error' => 'Provider account not found.']);
    exit;
}

// Get chat and customer_id
$stmt = $pdo->prepare("SELECT customer_id FROM chats WHERE id = ? AND provider_id = ?");
$stmt->execute([$chatId, $providerId]);
$chat = $stmt->fetch();
if (!$chat) {
    echo json_encode(['success' => false, 'error' => 'Chat not found']);
    exit;
}

$customerId = (int)$chat['customer_id'];

// Already unlocked?
$chk = $pdo->prepare("SELECT 1 FROM contact_unlocks WHERE provider_id = ? AND customer_id = ?");
$chk->execute([$providerId, $customerId]);
if ($chk->fetch()) {
    $userStmt = $pdo->prepare("SELECT full_name, phone, email FROM users WHERE id = ?");
    $userStmt->execute([$customerId]);
    $user = $userStmt->fetch();
    echo json_encode([
        'success' => true,
        'already_unlocked' => true,
        'contact' => [
            'full_name' => $user['full_name'] ?? '',
            'phone' => $user['phone'] ?? '',
            'email' => $user['email'] ?? ''
        ]
    ]);
    exit;
}

$cost = CREDITS_PER_UNLOCK;

// Check credits
$creditsStmt = $pdo->prepare("SELECT credits FROM providers WHERE id = ?");
$creditsStmt->execute([$providerId]);
$credits = (int)($creditsStmt->fetchColumn() ?: 0);

if ($credits < $cost) {
    echo json_encode(['success' => false, 'error' => 'Insufficient credits. You need ' . $cost . ' credits.']);
    exit;
}

// Deduct credits and record unlock (transaction)
try {
    $pdo->beginTransaction();

    $upd = $pdo->prepare("UPDATE providers SET credits = GREATEST(0, COALESCE(credits, 0) - ?) WHERE id = ? AND COALESCE(credits, 0) >= ?");
    $upd->execute([(int)$cost, (int)$providerId, (int)$cost]);

    if ($upd->rowCount() === 0) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'error' => 'Insufficient credits or provider not found.']);
        exit;
    }

    $ins = $pdo->prepare("INSERT INTO contact_unlocks (provider_id, customer_id) VALUES (?, ?)");
    $ins->execute([(int)$providerId, (int)$customerId]);

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    // Expose error temporarily for debugging; remove the 'debug' key in production
    echo json_encode(['success' => false, 'error' => 'Failed to unlock.', 'debug' => $e->getMessage()]);
    exit;
}

// Return contact
$userStmt = $pdo->prepare("SELECT full_name, phone, email FROM users WHERE id = ?");
$userStmt->execute([$customerId]);
$user = $userStmt->fetch();

echo json_encode([
    'success' => true,
    'credits_remaining' => $credits - $cost,
    'contact' => [
        'full_name' => $user['full_name'] ?? '',
        'phone' => $user['phone'] ?? '',
        'email' => $user['email'] ?? ''
    ]
]);
