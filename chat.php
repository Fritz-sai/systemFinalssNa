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
                <div style="margin-bottom: 1rem; padding: 0.75rem; background: var(--bg-light); border: 1px solid var(--border-color); border-radius: 6px;">
                    <div style="margin-bottom: 0.5rem;">
                        <label style="display: block; font-weight: 500; margin-bottom: 0.25rem;">Service:</label>
                        <div id="review-service-name" style="color: var(--text-dark);">Loading...</div>
                    </div>
                    <div>
                        <label style="display: block; font-weight: 500; margin-bottom: 0.25rem;">Price:</label>
                        <div id="review-service-price" style="color: var(--text-dark);">Loading...</div>
                    </div>
                </div>

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
        <div style="background:white; border-radius:var(--radius); max-width:550px; width:90%; padding:2rem; max-height:90vh; overflow-y:auto;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem;">
                <h3 style="margin:0;">Share a Service</h3>
                <button type="button" id="close-service-modal" style="background:none; border:none; font-size:1.5rem; cursor:pointer; color:var(--text-muted);">×</button>
            </div>
            
            <div id="services-loading" style="display:none; text-align:center; padding:2rem;">
                <div class="spinner" style="border: 4px solid rgba(0,0,0,0.1); border-left-color: var(--primary-color); border-radius: 50%; width: 30px; height: 30px; animation: spin 1s linear infinite; margin: 0 auto 1rem;"></div>
                <p style="color: var(--text-muted);">Loading your services...</p>
            </div>
            
            <div id="services-list" style="display:flex; flex-direction:column; gap:1rem;">
                <!-- Services will be loaded here -->
            </div>
            <form id="service-share-form" style="display:none; animation: fadeIn 0.3s ease-out;">
                <div style="background: var(--bg-light, #f8f9fa); padding: 1.5rem; border-radius: 12px; margin-bottom: 1.5rem; border: 1px solid var(--border-color, #e9ecef);">
                    <h4 style="margin-top: 0; margin-bottom: 1rem; color: var(--text-dark, #212529); font-size: 1.1rem; display: flex; align-items: center; gap: 0.5rem;">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color: var(--primary-color);"><path d="M9 11l3 3L22 4"></path><path d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        Selected Service
                    </h4>
                    <input type="text" id="service-title" name="service_title" min="0" step="0.01" required placeholder="service title" style="width:100%; padding:0.75rem 0.75rem 0.75rem 2.5rem; border:1px solid #ced4da; border-radius:8px; font-size:1.1rem; font-weight: 600; color: var(--primary-color); outline: none; transition: border-color 0.2s;">
                   
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.5rem;">
                    <div class="form-group">
                        <label style="display:flex; align-items:center; gap:0.5rem; margin-bottom: 0.5rem; color: var(--text-dark); font-weight: 600; font-size: 0.95rem;">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                            Date
                        </label>
                        <input type="date" id="service-date" name="scheduled_date" required style="width:100%; padding:0.75rem; border:1px solid #ced4da; border-radius:8px; font-size:1rem; outline: none; transition: border-color 0.2s;">
                    </div>
                    
                    <div class="form-group">
                        <label style="display:flex; align-items:center; gap:0.5rem; margin-bottom: 0.5rem; color: var(--text-dark); font-weight: 600; font-size: 0.95rem;">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                            Time
                        </label>
                        <input type="time" id="service-time" name="scheduled_time" required style="width:100%; padding:0.75rem; border:1px solid #ced4da; border-radius:8px; font-size:1rem; outline: none; transition: border-color 0.2s;">
                    </div>
                </div>
                
                <div class="form-group" style="margin-bottom: 2rem;">
                    <label style="display:flex; align-items:center; gap:0.5rem; margin-bottom: 0.5rem; color: var(--text-dark); font-weight: 600; font-size: 0.95rem;">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"></line><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg>
                        Final Price (₱)
                    </label>
                    <div style="position: relative;">
                        <span style="position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: #6c757d; font-weight: 600;">₱</span>
                        <input type="number" id="service-price" name="price" min="0" step="0.01" required placeholder="0.00" style="width:100%; padding:0.75rem 0.75rem 0.75rem 2.5rem; border:1px solid #ced4da; border-radius:8px; font-size:1.1rem; font-weight: 600; color: var(--primary-color); outline: none; transition: border-color 0.2s;">
                    </div>
                    <small style="display: block; margin-top: 0.5rem; color: #6c757d;">You can adjust the price based on customer requirements before sending the offer.</small>
                </div>
                
                <div style="display:flex; gap:1rem; margin-top:1.5rem; border-top: 1px solid #e9ecef; padding-top: 1.5rem;">
                    <button type="button" id="cancel-service-form" class="btn btn-outline" style="flex:1; padding: 0.8rem; font-weight: 600; border-radius: 8px; font-size: 1rem;">Back</button>
                    <button type="submit" class="btn btn-primary" style="flex:2; padding: 0.8rem; font-weight: 600; border-radius: 8px; font-size: 1rem; display: flex; align-items: center; justify-content: center; gap: 0.5rem;">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg>
                        Send Offer
                    </button>
                </div>
                <style>
                    #service-date:focus, #service-time:focus, #service-price:focus, #service-select:focus {
                        border-color: var(--primary-color) !important;
                        box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.25);
                    }
                </style>
            </form>
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
                        const priceRange = msgData.price ? 
                            '₱' + parseFloat(msgData.price).toFixed(2) :
                            (parseFloat(msgData.price_min) === parseFloat(msgData.price_max) ? 
                            '₱' + parseFloat(msgData.price_min).toFixed(2) :
                            '₱' + parseFloat(msgData.price_min).toFixed(2) + ' - ₱' + parseFloat(msgData.price_max).toFixed(2));
                        const title = msgData.title || 'Service';
                        const scheduledDate = msgData.scheduled_date || msgData.date;
                        const scheduledTime = msgData.scheduled_time || msgData.time;
                        
                        // Format date nicely
                        let formattedDate = '';
                        if (scheduledDate) {
                            const dateObj = new Date(scheduledDate);
                            const options = { year: 'numeric', month: 'long', day: 'numeric' };
                            formattedDate = dateObj.toLocaleDateString('en-US', options);
                        }
                        
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
                                buttonsHtml = '<div style=\"display:flex; gap:0.75rem; margin-top:1.5rem; padding-top:1rem; border-top:1px solid rgba(0,0,0,0.1);\">' +
                                    '<button type=\"button\" class=\"service-accept-btn\" data-service-id=\"' + msgData.service_id + '\" data-instance-id=\"' + msgData.instance_id + '\" data-chat-id=\"' + chatId + '\" style=\"flex:1; padding:0.65rem; background:#4CAF50; color:white; border:none; border-radius:6px; cursor:pointer; font-weight:600; font-size:0.9rem; transition:all 0.3s;\" onmouseover=\"this.style.background=\\'#45a049\\'\" onmouseout=\"this.style.background=\\'#4CAF50\\'\">✓ Accept</button>' +
                                    '<button type=\"button\" class=\"service-decline-btn\" data-service-id=\"' + msgData.service_id + '\" data-instance-id=\"' + msgData.instance_id + '\" data-chat-id=\"' + chatId + '\" style=\"flex:1; padding:0.65rem; background:#f44336; color:white; border:none; border-radius:6px; cursor:pointer; font-weight:600; font-size:0.9rem; transition:all 0.3s;\" onmouseover=\"this.style.background=\\'#da190b\\'\" onmouseout=\"this.style.background=\\'#f44336\\'\">✕ Decline</button>' +
                                    '</div>';
                            }
                        }
                        
                        // Build the service card with icons
                        messageHtml = '<div class=\"message-bubble ' + (sent ? 'sent' : 'received') + '\" style=\"max-width:450px;\">' +
                            '<div style=\"background:white; padding:1.5rem; border-radius:12px; border:1px solid #e0e0e0;\">' +
                            '<div style=\"display:flex; align-items:flex-start; gap:1rem; margin-bottom:1.25rem;\">' +
                            '<svg width=\"24\" height=\"24\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"#1976d2\" stroke-width=\"2\" style=\"flex-shrink:0; margin-top:2px;\"><path d=\"M9 11l3 3L22 4\"></path><path d=\"M21 12a9 9 0 11-18 0 9 9 0 0118 0z\"></path></svg>' +
                            '<div style=\"flex:1;\"><div style=\"font-size:0.75rem; color:#999; text-transform:uppercase; font-weight:600; letter-spacing:0.5px; margin-bottom:0.25rem;\">Service</div>' +
                            '<div style=\"font-size:1rem; font-weight:600; color:#333;\">' + title + '</div></div></div>' +
                            (scheduledDate ? '<div style=\"display:flex; align-items:flex-start; gap:1rem; margin-bottom:1.25rem;\">' +
                            '<svg width=\"24\" height=\"24\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"#1976d2\" stroke-width=\"2\" style=\"flex-shrink:0; margin-top:2px;\"><rect x=\"3\" y=\"4\" width=\"18\" height=\"18\" rx=\"2\" ry=\"2\"></rect><line x1=\"16\" y1=\"2\" x2=\"16\" y2=\"6\"></line><line x1=\"8\" y1=\"2\" x2=\"8\" y2=\"6\"></line><line x1=\"3\" y1=\"10\" x2=\"21\" y2=\"10\"></line></svg>' +
                            '<div style=\"flex:1;\"><div style=\"font-size:0.75rem; color:#999; text-transform:uppercase; font-weight:600; letter-spacing:0.5px; margin-bottom:0.25rem;\">Date</div>' +
                            '<div style=\"font-size:1rem; font-weight:600; color:#333;\">' + formattedDate + '</div></div></div>' : '') +
                            (scheduledTime ? '<div style=\"display:flex; align-items:flex-start; gap:1rem; margin-bottom:1.25rem;\">' +
                            '<svg width=\"24\" height=\"24\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"#1976d2\" stroke-width=\"2\" style=\"flex-shrink:0; margin-top:2px;\"><circle cx=\"12\" cy=\"12\" r=\"10\"></circle><polyline points=\"12 6 12 12 16 14\"></polyline></svg>' +
                            '<div style=\"flex:1;\"><div style=\"font-size:0.75rem; color:#999; text-transform:uppercase; font-weight:600; letter-spacing:0.5px; margin-bottom:0.25rem;\">Time</div>' +
                            '<div style=\"font-size:1rem; font-weight:600; color:#333;\">' + scheduledTime + '</div></div></div>' : '') +
                            '<div style=\"display:flex; align-items:flex-start; gap:1rem;\">' +
                            '<svg width=\"24\" height=\"24\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"#1976d2\" stroke-width=\"2\" style=\"flex-shrink:0; margin-top:2px;\"><line x1=\"12\" y1=\"1\" x2=\"12\" y2=\"23\"></line><path d=\"M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6\"></path></svg>' +
                            '<div style=\"flex:1;\"><div style=\"font-size:0.75rem; color:#999; text-transform:uppercase; font-weight:600; letter-spacing:0.5px; margin-bottom:0.25rem;\">Price</div>' +
                            '<div style=\"font-size:1.2rem; font-weight:700; color:#1976d2;\">' + priceRange + '</div></div></div>' +
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
const serviceForm = document.getElementById('service-share-form');
const servicesList = document.getElementById('services-list');
const servicesLoading = document.getElementById('services-loading');

if (shareServiceBtn && serviceModal) {
    shareServiceBtn.addEventListener('click', function() {
        serviceModal.style.display = 'flex';
        loadProviderServices();
    });
}

if (closeServiceBtn) {
    closeServiceBtn.addEventListener('click', function() {
        serviceModal.style.display = 'none';
        serviceForm.style.display = 'none';
        servicesList.style.display = 'flex';
    });
}

serviceModal?.addEventListener('click', function(e) {
    if (e.target === serviceModal) {
        serviceModal.style.display = 'none';
        serviceForm.style.display = 'none';
        servicesList.style.display = 'flex';
    }
});

document.getElementById('cancel-service-form')?.addEventListener('click', function() {
    serviceForm.style.display = 'none';
    servicesList.style.display = 'flex';
    serviceForm.reset();
});

function loadProviderServices() {
    servicesList.innerHTML = '';
    servicesLoading.style.display = 'block';
    
    fetch('api/get_provider_services.php')
        .then(r => r.json())
        .then(function(data) {
            servicesLoading.style.display = 'none';
            if (!data.services || data.services.length === 0) {
                servicesList.innerHTML = '<div style=\"text-align:center; padding:1rem; color:var(--text-muted);\">No services yet. Create one first.</div>';
                return;
            }
            servicesList.innerHTML = data.services.map(s => {
                const categoryBadge = s.category_name ? '<div style=\"display:inline-block; background:var(--accent); color:white; padding:0.25rem 0.6rem; border-radius:12px; font-size:0.75rem; font-weight:600; margin-bottom:0.5rem;\">' + s.category_name + '</div>' : '';
                return '<div class=\"service-item\" style=\"padding:1rem; border:1px solid var(--border-color); border-radius:var(--radius); cursor:pointer; transition:var(--transition);\" data-service-id=\"' + s.id + '\" data-service-title=\"' + (s.title || '') + '\" data-price-min=\"' + (s.price_min || 0) + '\" data-price-max=\"' + (s.price_max || 0) + '\">' +
                    categoryBadge +
                    '<div style=\"font-weight:500; color:var(--text-dark); margin-bottom:0.25rem;\">' + s.title + '</div>' +
                    '<div style=\"font-size:0.85rem; color:var(--text-muted); margin-bottom:0.5rem;\">' + (s.description ? s.description.substring(0, 60) + (s.description.length > 60 ? '...' : '') : '') + '</div>' +
                    '<div style=\"font-weight:600; color:var(--accent); font-size:0.95rem;\">₱' + parseFloat(s.price_min).toFixed(2) + (s.price_max != s.price_min ? ' - ₱' + parseFloat(s.price_max).toFixed(2) : '') + '</div>' +
                    '</div>';
            }).join('');
            
            document.querySelectorAll('.service-item').forEach(item => {
                item.addEventListener('click', function() {
                    const serviceId = this.getAttribute('data-service-id');
                    const serviceTitle = this.getAttribute('data-service-title');
                    const priceMin = this.getAttribute('data-price-min');
                    showServiceForm(serviceId, serviceTitle, priceMin);
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
            servicesLoading.style.display = 'none';
            servicesList.innerHTML = '<div style=\"text-align:center; padding:1rem; color:var(--text-muted);\">Failed to load services.</div>';
        });
}

function showServiceForm(serviceId, serviceTitle, priceMin) {
    const select = document.getElementById('service-select');
    if (select && select.options.length > 0) {
        select.options[0].value = serviceId;
        select.options[0].text = serviceTitle;
        select.value = serviceId;
    }
    
    const priceInput = document.getElementById('service-price');
    if (priceInput && priceMin) {
        priceInput.value = parseFloat(priceMin).toFixed(2);
    }
    
    // Set minimum date to today
    const today = new Date().toISOString().split('T')[0];
    document.getElementById('service-date').min = today;
    
    servicesList.style.display = 'none';
    servicesLoading.style.display = 'none';
    serviceForm.style.display = 'block';
    document.getElementById('service-date').focus();
}

document.getElementById('service-share-form')?.addEventListener('submit', function(e) {
    e.preventDefault();
    const serviceId = document.getElementById('service-select').value;
    const scheduledDate = document.getElementById('service-date').value;
    const scheduledTime = document.getElementById('service-time').value;
    const price = document.getElementById('service-price').value;
    
    if (!serviceId || !scheduledDate || !scheduledTime || !price) {
        alert('Please fill in all fields');
        return;
    }
    
    sendServiceMessage(serviceId, scheduledDate, scheduledTime, price);
});

function sendServiceMessage(serviceId, scheduledDate, scheduledTime, price) {
    const instanceId = 'srv_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
    fetch('api/send_message.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'chat_id=' + chatId + '&message=&service_id=' + serviceId + '&instance_id=' + instanceId + '&scheduled_date=' + encodeURIComponent(scheduledDate) + '&scheduled_time=' + encodeURIComponent(scheduledTime) + '&price=' + encodeURIComponent(price)
    }).then(r => r.json()).then(function() {
        serviceModal.style.display = 'none';
        serviceForm.style.display = 'none';
        serviceForm.reset();
        servicesList.style.display = 'flex';
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

loadContactStatus();
";
    }

    if ($role === 'customer') {
        $extraJs .= "
// Review Modal Handlers (for customers)
let activeBookingId = null;

function formatPhpPrice(minPrice, maxPrice) {
    const min = Number(minPrice);
    const max = Number(maxPrice);
    if (minPrice == null || maxPrice == null || Number.isNaN(min) || Number.isNaN(max)) return 'Not available';
    if (min === max) return 'PHP ' + min.toFixed(2);
    return 'PHP ' + min.toFixed(2) + ' - PHP ' + max.toFixed(2);
}

function fillReviewBookingInfo(data) {
    const serviceEl = document.getElementById('review-service-name');
    const priceEl = document.getElementById('review-service-price');
    if (serviceEl) serviceEl.textContent = data.service_title || 'Not available';
    if (priceEl) priceEl.textContent = formatPhpPrice(data.price_min, data.price_max);
}

function loadReviewBookingInfo() {
    return fetch('api/get_booking_for_chat.php?chat_id=' + chatId)
        .then(r => r.json())
        .then(data => {
            if (!data.booking_id) {
                throw new Error(data.error || 'No booking found');
            }
            activeBookingId = data.booking_id;
            fillReviewBookingInfo(data);
            return data;
        });
}

// Open review modal
document.getElementById('review-btn')?.addEventListener('click', function() {
    const modal = document.getElementById('review-modal');
    if (modal) {
        modal.style.display = 'flex';
        // Reset form
        document.getElementById('inline-review-form').reset();
        document.getElementById('inline-photo-preview').style.display = 'none';
        const serviceEl = document.getElementById('review-service-name');
        const priceEl = document.getElementById('review-service-price');
        if (serviceEl) serviceEl.textContent = 'Loading...';
        if (priceEl) priceEl.textContent = 'Loading...';
        loadReviewBookingInfo().catch(function(err) {
            if (serviceEl) serviceEl.textContent = 'Not available';
            if (priceEl) priceEl.textContent = 'Not available';
            alert(err.message || 'Could not load booking details.');
        });
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

    const submitReview = function() {
            if (!activeBookingId) {
                alert('Could not find booking. Please try again.');
                return;
            }
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
                        loadMessages();
                    } else {
                        alert(res.error || 'Failed to submit review');
                    }
                    if (btn) { btn.disabled = false; btn.textContent = 'Submit Review'; }
                })
                .catch(function() {
                    alert('Error submitting review');
                    if (btn) { btn.disabled = false; btn.textContent = 'Submit Review'; }
                });
    };

    if (activeBookingId) {
        submitReview();
    } else {
        loadReviewBookingInfo()
            .then(submitReview)
            .catch(function() {
                alert('Error fetching booking information');
            });
    }
});
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
