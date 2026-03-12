<?php
$pageTitle = 'Messages';
require_once 'config/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$chatId = (int)($_GET['chat'] ?? 0);
$providerId = (int)($_GET['provider'] ?? 0);
$userId = $_SESSION['user_id'];
$role = $_SESSION['role'];
$pdo = getDBConnection();

$chats = [];
$activeChat = null;

// Get user's chats
if ($role === 'customer') {
    $stmt = $pdo->prepare("
        SELECT c.id, c.provider_id, c.service_id, c.updated_at,
               pr.id as prov_id, u.full_name, (SELECT message FROM messages WHERE chat_id = c.id ORDER BY id DESC LIMIT 1) as last_msg
        FROM chats c
        JOIN providers pr ON c.provider_id = pr.id
        JOIN users u ON pr.user_id = u.id
        WHERE c.customer_id = ?
        ORDER BY c.updated_at DESC
    ");
    $stmt->execute([$userId]);
} else {
    $stmt = $pdo->prepare("
        SELECT c.id, c.customer_id, c.provider_id, c.service_id, c.updated_at,
               u.full_name, (SELECT message FROM messages WHERE chat_id = c.id ORDER BY id DESC LIMIT 1) as last_msg
        FROM chats c
        JOIN users u ON c.customer_id = u.id
        WHERE c.provider_id = ?
        ORDER BY c.updated_at DESC
    ");
    $stmt->execute([$_SESSION['provider_id'] ?? 0]);
}
$chats = $stmt->fetchAll();

// If user opened /chat without selecting a convo, keep last active convo
if (!$chatId && !$providerId) {
    $sessionChatId = (int)($_SESSION['active_chat_id'] ?? 0);
    if ($sessionChatId) {
        $chatId = $sessionChatId;
    } elseif (!empty($chats[0]['id'])) {
        $chatId = (int)$chats[0]['id'];
    }
}

// Open or create chat
if ($providerId && $role === 'customer') {
    $provStmt = $pdo->prepare("SELECT pr.id, u.full_name FROM providers pr JOIN users u ON pr.user_id = u.id WHERE pr.id = ?");
    $provStmt->execute([$providerId]);
    $provRow = $provStmt->fetch();
    if ($provRow) {
        $chk = $pdo->prepare("SELECT id FROM chats WHERE customer_id = ? AND provider_id = ?");
        $chk->execute([$userId, $providerId]);
        $existing = $chk->fetch();
        if ($existing) {
            $chatId = $existing['id'];
        } else {
            $pdo->prepare("INSERT INTO chats (customer_id, provider_id) VALUES (?, ?)")->execute([$userId, $providerId]);
            $chatId = $pdo->lastInsertId();
        }
    }
}

if ($chatId) {
    foreach ($chats as $c) {
        if ((int)$c['id'] === $chatId) {
            $activeChat = $c;
            break;
        }
    }
    if (!$activeChat) {
        if ($role === 'customer') {
            $stmt = $pdo->prepare("SELECT c.id, c.provider_id, u.full_name FROM chats c JOIN providers pr ON c.provider_id = pr.id JOIN users u ON pr.user_id = u.id WHERE c.id = ? AND c.customer_id = ?");
            $stmt->execute([$chatId, $userId]);
        } else {
            $stmt = $pdo->prepare("SELECT c.id, c.provider_id, u.full_name FROM chats c JOIN users u ON c.customer_id = u.id WHERE c.id = ? AND c.provider_id = ?");
            $stmt->execute([$chatId, $_SESSION['provider_id']]);
        }
        $activeChat = $stmt->fetch();
    }
}

$activeChatId = $activeChat['id'] ?? 0;
if ($activeChatId) {
    $_SESSION['active_chat_id'] = (int)$activeChatId;
}

$otherName = $activeChat['full_name'] ?? 'Chat';

require_once 'includes/header.php';
?>
<section style="padding: 2rem;">
    <h1 class="section-title">Messages</h1>
    <div class="chat-container">
        <div class="chat-list">
            <?php foreach ($chats as $c): ?>
            <a href="chat.php?chat=<?= $c['id'] ?>" class="chat-item <?= $activeChat && $activeChat['id'] == $c['id'] ? 'active' : '' ?>" style="text-decoration: none; color: inherit;">
                <strong><?= htmlspecialchars($c['full_name']) ?></strong>
                <?php if (!empty($c['last_msg'])): ?>
                    <p style="font-size: 0.85rem; color: var(--text-muted); margin-top: 0.25rem;"><?= htmlspecialchars(substr($c['last_msg'], 0, 40)) ?>...</p>
                <?php endif; ?>
            </a>
            <?php endforeach; ?>
            <?php if (empty($chats)): ?>
            <p style="padding: 1rem; color: var(--text-muted);">No conversations yet. Find a provider and start chatting!</p>
            <?php endif; ?>
        </div>
        <div class="chat-messages">
            <?php if ($activeChat): ?>
            <div class="chat-header">
                <strong><?= htmlspecialchars($otherName) ?></strong>
            </div>
            <?php if ($role === 'provider'): ?>
            <div id="contact-unlock-area" style="padding: 0.75rem 1.5rem; border-bottom: 1px solid var(--border-color); font-size: 0.9rem; background: var(--bg-light);">
                <span class="contact-loading">Loading...</span>
            </div>
            <?php endif; ?>
            <div class="chat-body" id="chat-messages">
                <!-- Messages loaded via JS -->
            </div>
            <div class="chat-input">
                <input type="text" id="chat-input" placeholder="Type a message..." autocomplete="off">
                <button type="button" class="btn btn-primary" id="send-btn">Send</button>
            </div>
            <?php else: ?>
            <div style="flex: 1; display: flex; align-items: center; justify-content: center; color: var(--text-muted);">
                Select a conversation or <a href="filter_results.php">find a provider</a> to start chatting.
            </div>
            <?php endif; ?>
        </div>
    </div>
</section>
<?php
$extraJs = '';
if ($activeChat) {
    $extraJs = "<script>
const chatId = " . (int)$activeChat['id'] . ";
const userId = " . (int)$userId . ";
const isCustomer = " . ($role === 'customer' ? 'true' : 'false') . ";

function loadMessages() {
    fetch('api/get_messages.php?chat_id=' + chatId)
        .then(r => r.json())
        .then(data => {
            const el = document.getElementById('chat-messages');
            if (data.locked) {
                el.innerHTML = '<div class=\"card\" style=\"padding: 1rem; max-width: 520px;\">' +
                    '<strong>Locked</strong><div style=\"color: var(--text-muted); margin-top: 0.25rem;\">Unlock this conversation to view the customer\\'s message.</div>' +
                    '</div>';
                return;
            }
            el.innerHTML = (data.messages || []).map(m => {
                const sent = (m.sender_type === 'customer' && isCustomer) || (m.sender_type === 'provider' && !isCustomer);
                return '<div class=\"message-bubble ' + (sent ? 'sent' : 'received') + '\"><div>' + m.message + '</div><div class=\"message-meta\">' + m.created_at + '</div></div>';
            }).join('');
            el.scrollTop = el.scrollHeight;
        });
}

function sendMessage() {
    const input = document.getElementById('chat-input');
    const msg = input.value.trim();
    if (!msg) return;
    input.value = '';
    fetch('api/send_message.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'chat_id=' + chatId + '&message=' + encodeURIComponent(msg)
    }).then(() => loadMessages());
}

document.getElementById('send-btn')?.addEventListener('click', sendMessage);
document.getElementById('chat-input')?.addEventListener('keypress', e => { if (e.key === 'Enter') sendMessage(); });

loadMessages();
setInterval(loadMessages, 3000);
";

    if ($role === 'provider') {
        $extraJs .= "
// Contact unlock for providers
const unlockCost = " . (int)CREDITS_PER_UNLOCK . ";
function loadContactStatus() {
    const el = document.getElementById('contact-unlock-area');
    if (!el) return;
    fetch('api/get_contact_status.php?chat_id=' + chatId)
        .then(r => r.json())
        .then(function(data) {
            if (data.unlocked && data.contact) {
                el.innerHTML = '<strong>Contact:</strong> ' + (data.contact.phone || '-') + ' | ' + (data.contact.email || '-');
                return;
            }
            el.innerHTML = '<strong>Credits:</strong> ' + (data.credits || 0) + ' &nbsp;|&nbsp; ' +
                '<button type=\"button\" id=\"unlock-contact-btn\" class=\"btn btn-primary\" style=\"padding: 0.35rem 0.75rem; font-size: 0.85rem;\">Unlock contact (' + unlockCost + ' credits)</button>' +
                ' <a href=\"buy_credits.php\" style=\"margin-left: 0.5rem;\">Buy credits</a>';
            document.getElementById('unlock-contact-btn')?.addEventListener('click', unlockContact);
        })
        .catch(function() { el.innerHTML = 'Could not load.'; });
}
function unlockContact() {
    const btn = document.getElementById('unlock-contact-btn');
    if (btn) { btn.disabled = true; btn.textContent = 'Unlocking...'; }
    const fd = new FormData(); fd.append('chat_id', chatId);
    fetch('api/unlock_contact.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(function(data) {
            if (data.success) { loadContactStatus(); loadMessages(); }
            else {
                alert(data.error || 'Failed');
                if (btn) { btn.disabled = false; btn.textContent = 'Unlock contact (' + unlockCost + ' credits)'; }
            }
        })
        .catch(function() {
            if (btn) { btn.disabled = false; btn.textContent = 'Unlock contact (' + unlockCost + ' credits)'; }
        });
}
loadContactStatus();
";
    }

    $extraJs .= "\n</script>";
}
require_once 'includes/footer.php';
?>
