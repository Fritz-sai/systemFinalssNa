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
$stmt = $pdo->prepare("SELECT id, customer_id, provider_id FROM chats WHERE id = ? AND archived = 0");
$stmt->execute([$chatId]);
$chat = $stmt->fetch();
if (!$chat) exit;

$senderType = 'customer';
$providerId = 0;
if ($role === 'provider') {
    $prov = $pdo->prepare("SELECT id FROM providers WHERE user_id = ?");
    $prov->execute([$userId]);
    $provRow = $prov->fetch();
    if ($provRow && $provRow['id'] == $chat['provider_id']) {
        $senderType = 'provider';
        $providerId = (int)$provRow['id'];
    }
} elseif ($chat['customer_id'] != $userId) {
    exit;
}

// Providers must unlock customer contact to send messages
if ($senderType === 'provider') {
    $unlock = $pdo->prepare("SELECT 1 FROM contact_unlocks WHERE provider_id = ? AND customer_id = ?");
    $unlock->execute([$providerId, $chat['customer_id']]);
    if (!$unlock->fetch()) {
        echo json_encode(['error' => 'locked', 'message' => 'Please unlock this customer\'s contact first to reply.']);
        exit;
    }
}

// Resolve recipient user ID first for privacy checks.
$recipientUserId = null;
if ($senderType === 'customer') {
    $provUser = $pdo->prepare("SELECT user_id, face_verified FROM providers WHERE id = ?");
    $provUser->execute([$chat['provider_id']]);
    $provUserRow = $provUser->fetch();
    $recipientUserId = $provUserRow['user_id'] ?? null;
} else {
    $recipientUserId = $chat['customer_id'];
}

// Block list enforcement (either direction blocks messaging).
if ($recipientUserId) {
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS user_blocks (
                id INT AUTO_INCREMENT PRIMARY KEY,
                blocker_user_id INT NOT NULL,
                blocked_user_id INT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uk_block_pair (blocker_user_id, blocked_user_id),
                INDEX (blocker_user_id),
                INDEX (blocked_user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        $blockStmt = $pdo->prepare("
            SELECT 1
            FROM user_blocks
            WHERE (blocker_user_id = ? AND blocked_user_id = ?)
               OR (blocker_user_id = ? AND blocked_user_id = ?)
            LIMIT 1
        ");
        $blockStmt->execute([$userId, (int)$recipientUserId, (int)$recipientUserId, $userId]);
        if ($blockStmt->fetch()) {
            echo json_encode(['error' => 'blocked', 'message' => 'Message cannot be sent due to privacy restrictions.']);
            exit;
        }
    } catch (Throwable $e) {
        // ignore and proceed
    }

    // Contact-scope privacy enforcement.
    try {
        $prefStmt = $pdo->prepare("
            SELECT pref_key, pref_value
            FROM user_preferences
            WHERE user_id = ? AND pref_key IN ('privacy_contact_scope')
        ");
        $prefStmt->execute([(int)$recipientUserId]);
        $contactScope = 'anyone';
        foreach ($prefStmt->fetchAll() as $pref) {
            if (($pref['pref_key'] ?? '') === 'privacy_contact_scope') {
                $contactScope = (string)($pref['pref_value'] ?? 'anyone');
            }
        }
        if ($contactScope === 'no_one') {
            echo json_encode(['error' => 'privacy', 'message' => 'This user is not accepting messages right now.']);
            exit;
        }
        if ($contactScope === 'verified_only') {
            $verStmt = $pdo->prepare("SELECT email_verified FROM users WHERE id = ? LIMIT 1");
            $verStmt->execute([$userId]);
            $senderVerified = (int)($verStmt->fetchColumn() ?: 0) === 1;
            if (!$senderVerified) {
                echo json_encode(['error' => 'privacy', 'message' => 'Only verified users can contact this account.']);
                exit;
            }
        }
    } catch (Throwable $e) {
        // ignore and proceed
    }
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
            'title' => $_POST['service_title'] ?? $service['title'],
            'description' => $service['description'],
            'price_min' => $service['price_min'],
            'price_max' => $service['price_max'],
            'price' => isset($_POST['price']) ? (float)$_POST['price'] : null,
            'scheduled_date' => $_POST['scheduled_date'] ?? null,
            'scheduled_time' => $_POST['scheduled_time'] ?? null
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
if ($recipientUserId) {
    $senderNameStmt = $pdo->prepare("SELECT full_name FROM users WHERE id = ?");
    $senderNameStmt->execute([$userId]);
    $senderName = $senderNameStmt->fetchColumn() ?: 'New message';

    $notificationBody = $message ?: ($_POST['service_title'] ?? ($service['title'] ?? 'Service shared'));
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
