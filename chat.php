<?php
$pageTitle = 'Messages';
require_once 'config/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$chatId = (int)($_GET['chat'] ?? 0);
$providerId = (int)($_GET['provider'] ?? ($_GET['to'] ?? 0));
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
        WHERE c.customer_id = ? AND c.archived = 0
        ORDER BY c.updated_at DESC
    ");
    $stmt->execute([$userId]);
} else {
    $stmt = $pdo->prepare("
        SELECT c.id, c.customer_id, c.provider_id, c.service_id, c.updated_at,
               u.full_name, (SELECT message FROM messages WHERE chat_id = c.id ORDER BY id DESC LIMIT 1) as last_msg
        FROM chats c
        JOIN users u ON c.customer_id = u.id
        WHERE c.provider_id = ? AND c.archived = 0
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
        $chk = $pdo->prepare("SELECT id FROM chats WHERE customer_id = ? AND provider_id = ? AND archived = 0");
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
            $stmt = $pdo->prepare("SELECT c.id, c.provider_id, u.full_name FROM chats c JOIN providers pr ON c.provider_id = pr.id JOIN users u ON pr.user_id = u.id WHERE c.id = ? AND c.customer_id = ? AND c.archived = 0");
            $stmt->execute([$chatId, $userId]);
        } else {
            $stmt = $pdo->prepare("SELECT c.id, c.provider_id, u.full_name FROM chats c JOIN users u ON c.customer_id = u.id WHERE c.id = ? AND c.provider_id = ? AND c.archived = 0");
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

// Get current user info
$userStmt = $pdo->prepare("SELECT full_name FROM users WHERE id = ?");
$userStmt->execute([$userId]);
$currentUser = $userStmt->fetch();

require_once 'includes/header.php';
?>
<section style="padding: 2rem;">
    <div class="chat-container">
        <div class="chat-list">
            <!-- User Profile Section -->
            <div class="chat-sidebar-top">
                <div class="user-profile-card">
                    <div class="user-avatar-large">
                        <?php 
                        $initials = strtoupper(substr($currentUser['full_name'] ?? 'U', 0, 1));
                        echo $initials;
                        ?>
                    </div>
                    <div class="user-info">
                        <strong><?= htmlspecialchars($currentUser['full_name'] ?? 'User') ?></strong>
                        <span class="status-badge">available</span>
                    </div>
                </div>
                
                <!-- Search Box -->
                <div class="chat-search">
                    <input type="text" id="chat-search-input" placeholder="Search" autocomplete="off">
                </div>
            </div>

            <!-- Chats List -->
            <div class="chats-list-scroll">
                <div class="chats-list-header">Last chats</div>
                <?php foreach ($chats as $c): ?>
                <a href="chat.php?chat=<?= $c['id'] ?>" class="chat-item <?= $activeChat && $activeChat['id'] == $c['id'] ? 'active' : '' ?>" style="text-decoration: none; color: inherit;" data-chat-name="<?= htmlspecialchars(strtolower($c['full_name'])) ?>">
                    <div class="chat-item-avatar">
                        <?= strtoupper(substr($c['full_name'], 0, 1)) ?>
                    </div>
                    <div class="chat-item-content">
                        <div class="chat-item-name"><?= htmlspecialchars($c['full_name']) ?></div>
                        <?php if (!empty($c['last_msg'])): ?>
                            <div class="chat-item-message"><?= htmlspecialchars(substr($c['last_msg'], 0, 40)) ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="chat-item-time"><?= date('H:i', strtotime($c['updated_at'])) ?></div>
                </a>
                <?php endforeach; ?>
                <?php if (empty($chats)): ?>
                <p style="padding: 1rem; color: var(--text-muted); text-align: center;">No conversations yet. Find a provider and start chatting!</p>
                <?php endif; ?>
            </div>
        </div>
        <div class="chat-messages">
            <?php if ($activeChat): ?>
            <div class="chat-header">
                <strong><?= htmlspecialchars($otherName) ?></strong>
                <button type="button" id="delete-chat-btn" class="btn btn-danger" style="margin-left:auto; font-size:0.9rem; padding:0.5rem 0.75rem;">Delete this conversation</button>
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
                <?php if ($role === 'provider'): ?>
                <button type="button" class="btn-icon-plus" id="share-service-btn" title="Share a service">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="12" y1="5" x2="12" y2="19"></line>
                        <line x1="5" y1="12" x2="19" y2="12"></line>
                    </svg>
                </button>
                <?php endif; ?>
                <input type="text" id="chat-input" placeholder="Type a message..." autocomplete="off">
                <button type="button" class="btn btn-primary" id="send-btn">Send</button>
                <?php if ($role === 'customer'): ?>
                <button type="button" class="btn btn-outline" id="review-btn" title="Write a review" style="display:none;">✓ Write Review</button>
                <?php endif; ?>
            </div>
            <?php else: ?>
            <div style="flex: 1; display: flex; align-items: center; justify-content: center; color: var(--text-muted);">
                Select a conversation or <a href="filter_results.php">find a provider</a> to start chatting.
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Review Modal (for customers) -->
    <?php if ($role === 'customer'): ?>
    <div id="review-modal" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.5); z-index:1000; align-items:center; justify-content:center;">
        <div style="background:white; border-radius:var(--radius); max-width:600px; width:90%; padding:2rem; max-height:90vh; overflow-y:auto;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem;">
                <h3 style="margin:0;">Write a Review</h3>
                <button type="button" id="close-review-modal" style="background:none; border:none; font-size:1.5rem; cursor:pointer; color:var(--text-muted);">×</button>
            </div>
            
            <form id="inline-review-form" enctype="multipart/form-data">
                <div style="margin-bottom: 1rem;">
                    <label style="display: block; font-weight: 500; margin-bottom: 0.5rem;">Rating:</label>
                    <select name="rating" required style="padding: 0.5rem; width: 100%; border: 1px solid var(--border-color); border-radius: 4px;">
                        <option value="">Choose rating...</option>
                        <option value="5">★★★★★ 5 - Excellent</option>
                        <option value="4">★★★★☆ 4 - Good</option>
                        <option value="3">★★★☆☆ 3 - Okay</option>
                        <option value="2">★★☆☆☆ 2 - Poor</option>
                        <option value="1">★☆☆☆☆ 1 - Bad</option>
                    </select>
                </div>
                
                <div style="margin-bottom: 1rem;">
                    <label style="display: block; font-weight: 500; margin-bottom: 0.5rem;">Your Review (optional):</label>
                    <textarea name="review" placeholder="Share your experience with this provider..." style="padding: 0.5rem; width: 100%; height: 100px; border: 1px solid var(--border-color); border-radius: 4px; font-family: inherit;"></textarea>
                </div>
                
                <div style="margin-bottom: 1rem;">
                    <label style="display: block; font-weight: 500; margin-bottom: 0.5rem;">Photo (optional):</label>
                    <input type="file" name="review_photo" accept="image/*" style="padding: 0.5rem; width: 100%; border: 1px solid var(--border-color); border-radius: 4px;">
                    <div id="inline-photo-preview" style="margin-top: 0.75rem; display: none;">
                        <img id="inline-img-preview" style="max-width: 200px; border-radius: 4px;">
                    </div>
                </div>
                
                <div style="margin-bottom: 1.5rem;">
                    <label style="display: flex; align-items: center; gap: 0.5rem;">
                        <input type="checkbox" name="payment_accepted" required>
                        <span>I confirm the work is done and payment is acceptable</span>
                    </label>
                </div>
                
                <div style="display: flex; gap: 1rem;">
                    <button type="submit" class="btn btn-primary" style="flex: 1;">Submit Review</button>
                    <button type="button" id="cancel-review-modal" class="btn btn-outline" style="flex: 1;">Cancel</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Unlock Customer Modal (for providers) -->
    <?php if ($role === 'provider'): ?>
    <div id="unlock-modal" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.5); z-index:1000; align-items:center; justify-content:center;">
        <div style="background:white; border-radius:var(--radius); max-width:400px; width:90%; padding:2rem; text-align:center;">
            <h3 style="margin-top:0;">Unlock Customer Messages?</h3>
            <p style="color:var(--text-muted); margin:1rem 0;">Viewing and replying to this customer requires <strong>5 credits</strong>.</p>
            <div style="background:var(--bg-light); padding:1rem; border-radius:6px; margin-bottom:1.5rem;">
                <div style="font-size:0.9rem; color:var(--text-muted); margin-bottom:0.5rem;">Your Credits:</div>
                <div style="font-size:1.5rem; font-weight:bold;" id="unlock-modal-credits">0</div>
            </div>
            <div style="display:flex; gap:1rem;">
                <button type="button" id="unlock-modal-cancel" class="btn btn-outline" style="flex:1;">Cancel</button>
                <button type="button" id="unlock-modal-confirm" class="btn btn-primary" style="flex:1;">Unlock (5 Credits)</button>
            </div>
            <a href="buy_credits.php" style="display:block; margin-top:1rem; font-size:0.9rem;">Buy more credits</a>
        </div>
    </div>
    
    <!-- Service Share Modal -->
    <div id="service-modal" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.5); z-index:1000; align-items:center; justify-content:center;">
        <div style="background:white; border-radius:var(--radius); max-width:500px; width:90%; padding:2rem; max-height:80vh; overflow-y:auto;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem;">
                <h3 style="margin:0;">Share a Service</h3>
                <button type="button" id="close-service-modal" style="background:none; border:none; font-size:1.5rem; cursor:pointer; color:var(--text-muted);">×</button>
            </div>
            <div id="services-list" style="display:flex; flex-direction:column; gap:0.75rem;">
                <!-- Services will be loaded here -->
            </div>
            <div id="services-loading" style="text-align:center; padding:1rem; color:var(--text-muted);">Loading your services...</div>
        </div>
    </div>
    <?php endif; ?>
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
                    '<strong>Messages Locked</strong><div style=\"color: var(--text-muted); margin-top: 0.25rem;\">Click the unlock button above to pay 5 credits and view messages.</div>' +
                    '</div>';
                // Show unlock modal for providers
                if (!isCustomer) {
                    setTimeout(showUnlockModal, 300);
                }
                return;
            }
            el.innerHTML = (data.messages || []).map(m => {
                const sent = (m.sender_type === 'customer' && isCustomer) || (m.sender_type === 'provider' && !isCustomer);
                
                // Check if this is a service status message
                let messageHtml = '';
                try {
                    const msgData = JSON.parse(m.message);
                    
                    // Handle service status messages (accept/decline)
                    if (msgData && msgData.type === 'service_status') {
                        const statusEmoji = msgData.action === 'accepted' ? '✅' : '❌';
                        const statusText = msgData.action === 'accepted' ? 'accepted' : 'declined';
                        const statusColor = msgData.action === 'accepted' ? '#2ECC71' : '#e74c3c';
                        
                        messageHtml = '<div style=\"padding:1rem; background:' + statusColor + '; color:white; border-radius:8px; text-align:center; margin:0.5rem 0; font-weight:500;\">' +
                            statusEmoji + ' Service ' + statusText.charAt(0).toUpperCase() + statusText.slice(1) + ': <strong>' + msgData.service_name + '</strong>' +
                            '</div>';
                        
                        // Show review button for customers after accepting
                        if (isCustomer && msgData.action === 'accepted') {
                            setTimeout(function() {
                                var reviewBtn = document.getElementById('review-btn');
                                if (reviewBtn) reviewBtn.style.display = 'block';
                            }, 100);
                        }
                        return messageHtml;
                    }
                    
                    // Handle service offer messages
                    if (msgData.type === 'service') {
                        const priceRange = parseFloat(msgData.price_min) === parseFloat(msgData.price_max) ? 
                            '₱' + parseFloat(msgData.price_min).toFixed(2) :
                            '₱' + parseFloat(msgData.price_min).toFixed(2) + ' - ₱' + parseFloat(msgData.price_max).toFixed(2);
                        const title = msgData.title || 'Service';
                        const description = msgData.description || 'No description';
                        
                        let buttonsHtml = '';
                        // Only show accept/decline buttons for customers receiving the service if not already responded
                        if (!sent && isCustomer) {
                            // Check if customer has already responded to THIS specific service instance
                            const hasResponded = (data.messages || []).some(msg => {
                                try {
                                    const sData = JSON.parse(msg.message);
                                    return sData && sData.type === 'service_status' && sData.instance_id == msgData.instance_id;
                                } catch(e) {
                                    return false;
                                }
                            });
                            
                            if (!hasResponded) {
                                buttonsHtml = '<div style=\"display:flex; gap:0.5rem; margin-top:0.75rem;\">' +
                                    '<button type=\"button\" class=\"service-accept-btn\" data-service-id=\"' + msgData.service_id + '\" data-instance-id=\"' + msgData.instance_id + '\" data-chat-id=\"' + chatId + '\" style=\"flex:1; padding:0.5rem; background:var(--accent); color:white; border:none; border-radius:6px; cursor:pointer; font-weight:500; font-size:0.85rem; transition:all 0.3s;\" onmouseover=\"this.style.background=\\'var(--accent-hover)\\'\" onmouseout=\"this.style.background=\\'var(--accent)\\'\">Accept</button>' +
                                    '<button type=\"button\" class=\"service-decline-btn\" data-service-id=\"' + msgData.service_id + '\" data-instance-id=\"' + msgData.instance_id + '\" data-chat-id=\"' + chatId + '\" style=\"flex:1; padding:0.5rem; background:#e9ecef; color:#666; border:none; border-radius:6px; cursor:pointer; font-weight:500; font-size:0.85rem; transition:all 0.3s;\" onmouseover=\"this.style.background=\\'#ddd\\'\" onmouseout=\"this.style.background=\\'#e9ecef\\'\">Decline</button>' +
                                    '</div>';
                            }
                        }
                        
                        messageHtml = '<div class=\"message-bubble ' + (sent ? 'sent' : 'received') + '\" style=\"max-width:400px;\">' +
                            '<div style=\"background:rgba(255,255,255,0.1); padding:0.75rem; border-radius:8px;\">' +
                            '<div style=\"font-weight:600; margin-bottom:0.5rem;\">' + title + '</div>' +
                            '<div style=\"font-size:0.85rem; margin-bottom:0.5rem; opacity:0.95;\">' + description.substring(0, 100) + (description.length > 100 ? '...' : '') + '</div>' +
                            '<div style=\"font-weight:600; font-size:1rem;\">' + priceRange + '</div>' +
                            buttonsHtml +
                            '</div>' +
                            '<div class=\"message-meta\">' + m.created_at + '</div>' +
                            '</div>';
                        return messageHtml;
                    }
                } catch(e) {
                    // Not JSON or parse error, will be treated as regular message
                }
                
                // Regular text message
                return '<div class=\"message-bubble ' + (sent ? 'sent' : 'received') + '\"><div>' + m.message + '</div><div class=\"message-meta\">' + m.created_at + '</div></div>';
            }).join('');
            
            // Add event listeners to accept/decline buttons
            document.querySelectorAll('.service-accept-btn').forEach(btn => {
                btn.addEventListener('click', function(e) {
                    const serviceId = this.getAttribute('data-service-id');
                    const instanceId = this.getAttribute('data-instance-id');
                    acceptService(serviceId, instanceId);
                });
            });
            document.querySelectorAll('.service-decline-btn').forEach(btn => {
                btn.addEventListener('click', function(e) {
                    const serviceId = this.getAttribute('data-service-id');
                    const instanceId = this.getAttribute('data-instance-id');
                    declineService(serviceId, instanceId);
                });
            });
            
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
    }).then(r => r.json()).then(data => {
        if (data.error === 'locked') {
            alert('You need to unlock this customer\'s contact first. Please click the unlock button above to proceed.');
            loadMessages();
        } else if (data.success !== false) {
            loadMessages();
        }
    }).catch(() => loadMessages());
}

function deleteChat() {
    if (!confirm('Delete this conversation? The chat will be archived and a new conversation will start if you message again.')) return;
    fetch('api/delete_chat.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'chat_id=' + chatId
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            window.location.href = 'chat.php';
        } else {
            alert(data.error || 'Unable to delete conversation');
        }
    })
    .catch(() => alert('Unable to delete conversation'));
}

function acceptService(serviceId, instanceId) {
    if (!confirm('Accept this service?')) return;
    fetch('api/accept_service.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'chat_id=' + chatId + '&service_id=' + serviceId + '&instance_id=' + instanceId
    }).then(r => r.json()).then(function(data) {
        if (data.success) {
            alert('Service accepted!');
            loadMessages();
        } else {
            if (data.error && data.error.includes('already responded')) {
                alert('You already booked this service');
            } else {
                alert(data.error || 'Failed to accept service');
            }
            loadMessages();
        }
    }).catch(function(err) {
        console.error('Error:', err);
        alert('Error accepting service');
    });
}

function declineService(serviceId, instanceId) {
    if (!confirm('Decline this service?')) return;
    fetch('api/decline_service.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'chat_id=' + chatId + '&service_id=' + serviceId + '&instance_id=' + instanceId
    }).then(r => r.json()).then(function(data) {
        if (data.success) {
            alert('Service declined');
            loadMessages();
        } else {
            if (data.error && data.error.includes('already responded')) {
                alert('You already responded to this service');
            } else {
                alert(data.error || 'Failed to decline service');
            }
            loadMessages();
        }
    }).catch(function(err) {
        console.error('Error:', err);
        alert('Error declining service');
    });
}

document.getElementById('send-btn')?.addEventListener('click', sendMessage);
document.getElementById('chat-input')?.addEventListener('keypress', e => { if (e.key === 'Enter') sendMessage(); });
document.getElementById('delete-chat-btn')?.addEventListener('click', deleteChat);

loadMessages();
setInterval(loadMessages, 3000);
";

    if ($role === 'provider') {
        $extraJs .= "
// Service sharing for providers
const serviceModal = document.getElementById('service-modal');
const shareServiceBtn = document.getElementById('share-service-btn');
const closeServiceBtn = document.getElementById('close-service-modal');

if (shareServiceBtn && serviceModal) {
    shareServiceBtn.addEventListener('click', function() {
        serviceModal.style.display = 'flex';
        loadProviderServices();
    });
}

if (closeServiceBtn) {
    closeServiceBtn.addEventListener('click', function() {
        serviceModal.style.display = 'none';
    });
}

serviceModal?.addEventListener('click', function(e) {
    if (e.target === serviceModal) {
        serviceModal.style.display = 'none';
    }
});

function loadProviderServices() {
    const listEl = document.getElementById('services-list');
    const loadingEl = document.getElementById('services-loading');
    listEl.innerHTML = '';
    loadingEl.style.display = 'block';
    
    fetch('api/get_provider_services.php')
        .then(r => r.json())
        .then(function(data) {
            loadingEl.style.display = 'none';
            if (!data.services || data.services.length === 0) {
                listEl.innerHTML = '<div style=\"text-align:center; padding:1rem; color:var(--text-muted);\">No services yet. Create one first.</div>';
                return;
            }
            listEl.innerHTML = data.services.map(s => {
                const categoryBadge = s.category_name ? '<div style=\"display:inline-block; background:var(--accent); color:white; padding:0.25rem 0.6rem; border-radius:12px; font-size:0.75rem; font-weight:600; margin-bottom:0.5rem;\">' + s.category_name + '</div>' : '';
                return '<div class=\"service-item\" style=\"padding:1rem; border:1px solid var(--border-color); border-radius:var(--radius); cursor:pointer; transition:var(--transition);\" data-service-id=\"' + s.id + '\">' +
                    categoryBadge +
                    '<div style=\"font-weight:500; color:var(--text-dark); margin-bottom:0.25rem;\">' + s.title + '</div>' +
                    '<div style=\"font-size:0.85rem; color:var(--text-muted); margin-bottom:0.5rem;\">' + (s.description ? s.description.substring(0, 60) + (s.description.length > 60 ? '...' : '') : '') + '</div>' +
                    '<div style=\"font-weight:600; color:var(--accent); font-size:0.95rem;\">₱' + parseFloat(s.price_min).toFixed(2) + (s.price_max != s.price_min ? ' - ₱' + parseFloat(s.price_max).toFixed(2) : '') + '</div>' +
                    '</div>';
            }).join('');
            
            document.querySelectorAll('.service-item').forEach(item => {
                item.addEventListener('click', function() {
                    const serviceId = this.getAttribute('data-service-id');
                    sendServiceMessage(serviceId);
                });
                item.addEventListener('mouseover', function() {
                    this.style.background = 'var(--bg-light)';
                    this.style.borderColor = 'var(--accent)';
                });
                item.addEventListener('mouseout', function() {
                    this.style.background = 'transparent';
                    this.style.borderColor = 'var(--border-color)';
                });
            });
        })
        .catch(function() {
            loadingEl.style.display = 'none';
            listEl.innerHTML = '<div style=\"text-align:center; padding:1rem; color:var(--text-muted);\">Failed to load services.</div>';
        });
}

function sendServiceMessage(serviceId) {
    const instanceId = 'srv_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
    fetch('api/send_message.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'chat_id=' + chatId + '&message=&service_id=' + serviceId + '&instance_id=' + instanceId
    }).then(r => r.json()).then(function() {
        serviceModal.style.display = 'none';
        loadMessages();
    });
}

// Contact unlock for providers
const unlockCost = " . (int)CREDITS_PER_UNLOCK . ";
let isUnlocked = false;
let pendingLoadMessages = false;

function loadContactStatus() {
    const el = document.getElementById('contact-unlock-area');
    const chatInput = document.getElementById('chat-input');
    const sendBtn = document.getElementById('send-btn');
    if (!el) return;
    fetch('api/get_contact_status.php?chat_id=' + chatId)
        .then(r => r.json())
        .then(function(data) {
            isUnlocked = data.unlocked ? true : false;
            if (isUnlocked && data.contact) {
                el.innerHTML = '<strong>✓ Contact unlocked:</strong> ' + (data.contact.phone || '-') + ' | ' + (data.contact.email || '-');
                if (chatInput) chatInput.disabled = false;
                if (sendBtn) sendBtn.disabled = false;
            } else {
                el.innerHTML = '<strong>Locked:</strong> This customer\'s messages require ' + unlockCost + ' credits to unlock.';
                if (chatInput) chatInput.disabled = true;
                if (sendBtn) sendBtn.disabled = true;
                // Update modal credits display
                document.getElementById('unlock-modal-credits').textContent = (data.credits || 0);
            }
        })
        .catch(function() { el.innerHTML = 'Could not load.'; });
}

function showUnlockModal() {
    const modal = document.getElementById('unlock-modal');
    if (!modal) return;
    // Update credits display in modal
    const el = document.getElementById('contact-unlock-area');
    if (el && el.textContent.includes('Locked')) {
        modal.style.display = 'flex';
    }
}

function unlockContact() {
    const btn = document.getElementById('unlock-modal-confirm');
    if (btn) { btn.disabled = true; btn.textContent = 'Processing...'; }
    const fd = new FormData(); fd.append('chat_id', chatId);
    fetch('api/unlock_contact.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(function(data) {
            if (data.success) {
                const modal = document.getElementById('unlock-modal');
                if (modal) modal.style.display = 'none';
                loadContactStatus();
                loadMessages();
            } else {
                alert(data.error || 'Failed to unlock');
                if (btn) { btn.disabled = false; btn.textContent = 'Unlock (5 Credits)'; }
            }
        })
        .catch(function() {
            alert('Error unlocking customer');
            if (btn) { btn.disabled = false; btn.textContent = 'Unlock (5 Credits)'; }
        });
}

document.getElementById('unlock-modal-confirm')?.addEventListener('click', unlockContact);
document.getElementById('unlock-modal-cancel')?.addEventListener('click', function() {
    const modal = document.getElementById('unlock-modal');
    if (modal) modal.style.display = 'none';
});

// Review Modal Handlers (for customers)
if (isCustomer) {
    let activeBookingId = null;
    
    // Get active booking ID from chat
    function getActiveBookingId() {
        if (activeBookingId) return activeBookingId;
        // Will be set when we load the chat details
        return null;
    }
    
    // Open review modal
    document.getElementById('review-btn')?.addEventListener('click', function() {
        const modal = document.getElementById('review-modal');
        if (modal) {
            modal.style.display = 'flex';
            // Reset form
            document.getElementById('inline-review-form').reset();
            document.getElementById('inline-photo-preview').style.display = 'none';
        }
    });
    
    // Close review modal buttons
    document.getElementById('close-review-modal')?.addEventListener('click', function() {
        const modal = document.getElementById('review-modal');
        if (modal) modal.style.display = 'none';
    });
    
    document.getElementById('cancel-review-modal')?.addEventListener('click', function() {
        const modal = document.getElementById('review-modal');
        if (modal) modal.style.display = 'none';
    });
    
    // Photo preview handler
    const photoInput = document.querySelector('input[name=\"review_photo\"]');
    if (photoInput) {
        photoInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(event) {
                    const preview = document.getElementById('inline-photo-preview');
                    const img = document.getElementById('inline-img-preview');
                    if (preview && img) {
                        img.src = event.target.result;
                        preview.style.display = 'block';
                    }
                };
                reader.readAsDataURL(file);
            }
        });
    }
    
    // Form submission
    document.getElementById('inline-review-form')?.addEventListener('submit', function(e) {
        e.preventDefault();
        
        // Get booking ID from chat - query the database
        fetch('api/get_booking_for_chat.php?chat_id=' + chatId)
            .then(r => r.json())
            .then(data => {
                if (!data.booking_id) {
                    alert('Could not find booking. Please try again.');
                    return;
                }
                
                activeBookingId = data.booking_id;
                const formData = new FormData(e.target);
                formData.append('booking_id', activeBookingId);
                formData.append('agreed', '1');
                
                const btn = e.target.querySelector('button[type=\"submit\"]');
                if (btn) { btn.disabled = true; btn.textContent = 'Submitting...'; }
                
                fetch('api/confirm_booking.php', { method: 'POST', body: formData })
                    .then(r => r.json())
                    .then(function(res) {
                        if (res.success) {
                            alert('Review submitted successfully!');
                            const modal = document.getElementById('review-modal');
                            if (modal) modal.style.display = 'none';
                            loadMessages(); // Refresh chat
                        } else {
                            alert(res.error || 'Failed to submit review');
                        }
                        if (btn) { btn.disabled = false; btn.textContent = 'Submit Review'; }
                    })
                    .catch(function(err) {
                        alert('Error submitting review');
                        if (btn) { btn.disabled = false; btn.textContent = 'Submit Review'; }
                    });
            })
            .catch(function() {
                alert('Error fetching booking information');
            });
    });
}

loadContactStatus();
";
    }

    $extraJs .= "\n";
}

$extraJs .= "
// Chat search functionality
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('chat-search-input');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const query = this.value.toLowerCase();
            const chatItems = document.querySelectorAll('.chat-item');
            chatItems.forEach(item => {
                const name = item.getAttribute('data-chat-name');
                if (name && name.includes(query)) {
                    item.style.display = '';
                } else {
                    item.style.display = 'none';
                }
            });
        });
    }
});
</script>";

require_once 'includes/footer.php';
?>
