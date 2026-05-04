<?php
$pageTitle = 'My Bookings';
require_once 'config/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    header('Location: login.php');
    exit;
}

$pdo = getDBConnection();
$userId = $_SESSION['user_id'];

// Get counts for filters
$counts = [
    'all' => 0,
    'upcoming' => 0,
    'completed' => 0,
    'cancelled' => 0
];

$countStmt = $pdo->prepare("
    SELECT status, COUNT(*) as count 
    FROM bookings 
    WHERE customer_id = ? 
    GROUP BY status
");
$countStmt->execute([$userId]);
foreach ($countStmt->fetchAll() as $row) {
    $counts['all'] += $row['count'];
    if ($row['status'] === 'pending' || $row['status'] === 'confirmed') {
        $counts['upcoming'] += $row['count'];
    } elseif ($row['status'] === 'completed') {
        $counts['completed'] += $row['count'];
    } elseif ($row['status'] === 'cancelled' || $row['status'] === 'rejected') {
        $counts['cancelled'] += $row['count'];
    }
}

// Fetch bookings
$filter = $_GET['filter'] ?? 'all';
$statusSql = "";
if ($filter === 'upcoming') {
    $statusSql = "AND b.status IN ('pending', 'confirmed')";
} elseif ($filter === 'completed') {
    $statusSql = "AND b.status = 'completed'";
} elseif ($filter === 'cancelled') {
    $statusSql = "AND b.status IN ('cancelled', 'rejected')";
}

$bookingsStmt = $pdo->prepare("
    SELECT b.*, s.title as service_title, s.price_min, s.price_max,
           u.full_name as provider_name, p.profile_image_path as provider_image, p.city as provider_city, p.barangay as provider_barangay
    FROM bookings b
    JOIN providers p ON b.provider_id = p.id
    JOIN users u ON p.user_id = u.id
    JOIN services s ON b.service_id = s.id
    WHERE b.customer_id = ? $statusSql
    ORDER BY b.created_at DESC
");
$bookingsStmt->execute([$userId]);
$bookings = $bookingsStmt->fetchAll();

require_once 'includes/header.php';
?>

<style>
    .bookings-container {
        max-width: 1000px;
        margin: 2rem auto;
        padding: 0 1rem;
    }

    .bookings-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 2rem;
    }

    .bookings-header h1 {
        font-size: 1.8rem;
        font-weight: 700;
        color: #0f172a;
        margin-bottom: 0.25rem;
    }

    .bookings-header p {
        color: #64748b;
        font-size: 0.95rem;
    }

    .filter-tabs {
        display: flex;
        gap: 1.5rem;
        border-bottom: 1px solid #e2e8f0;
        margin-bottom: 2rem;
        padding-bottom: 0.5rem;
    }

    .filter-tab {
        padding: 0.5rem 0.25rem;
        color: #64748b;
        font-weight: 500;
        font-size: 0.95rem;
        cursor: pointer;
        position: relative;
        text-decoration: none;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .filter-tab:hover {
        color: #3A86FF;
    }

    .filter-tab.active {
        color: #3A86FF;
    }

    .filter-tab.active::after {
        content: '';
        position: absolute;
        bottom: -0.5rem;
        left: 0;
        right: 0;
        height: 2px;
        background: #3A86FF;
    }

    .tab-count {
        background: #f1f5f9;
        color: #64748b;
        padding: 0.1rem 0.5rem;
        border-radius: 999px;
        font-size: 0.8rem;
    }

    .filter-tab.active .tab-count {
        background: #eff6ff;
        color: #3A86FF;
    }

    .booking-card {
        background: #fff;
        border-radius: 16px;
        border: 1px solid #eef2f6;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.03);
        padding: 1.5rem;
        margin-bottom: 1.5rem;
        position: relative;
    }

    .booking-card-top {
        display: flex;
        gap: 1.5rem;
        margin-bottom: 1.5rem;
    }

    .booking-img {
        width: 100px;
        height: 100px;
        border-radius: 12px;
        object-fit: cover;
        background: #f1f5f9;
    }

    .booking-info {
        flex: 1;
    }

    .booking-info h3 {
        font-size: 1.2rem;
        font-weight: 700;
        color: #0f172a;
        margin-bottom: 0.25rem;
    }

    .provider-name {
        color: #64748b;
        font-size: 0.9rem;
        margin-bottom: 0.75rem;
    }

    .booking-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 1rem;
        font-size: 0.85rem;
        color: #64748b;
    }

    .meta-item {
        display: flex;
        align-items: center;
        gap: 0.4rem;
    }

    .booking-status {
        position: absolute;
        top: 1.5rem;
        right: 1.5rem;
        display: flex;
        flex-direction: column;
        align-items: flex-end;
        gap: 0.5rem;
    }

    .status-badge {
        padding: 0.35rem 0.8rem;
        border-radius: 8px;
        font-size: 0.8rem;
        font-weight: 600;
        text-transform: capitalize;
    }

    .status-accepted { background: #dcfce7; color: #15803d; }
    .status-pending { background: #fef3c7; color: #a16207; }
    .status-upcoming { background: #dbeafe; color: #1d4ed8; }
    .status-completed { background: #dcfce7; color: #15803d; }
    .status-cancelled { background: #fee2e2; color: #b91c1c; }

    .status-message {
        font-size: 0.85rem;
        color: #64748b;
        max-width: 200px;
        text-align: right;
    }

    .booking-actions {
        display: flex;
        justify-content: flex-end;
        gap: 1rem;
        margin-top: 1rem;
    }

    .progress-track {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-top: 2rem;
        padding: 0 1rem;
        position: relative;
    }

    .progress-track::before {
        content: '';
        position: absolute;
        top: 10px;
        left: 2rem;
        right: 2rem;
        height: 2px;
        background: #e2e8f0;
        z-index: 1;
    }

    .progress-step {
        display: flex;
        flex-direction: column;
        align-items: center;
        text-align: center;
        position: relative;
        z-index: 2;
        width: 80px;
    }

    .step-dot {
        width: 20px;
        height: 20px;
        border-radius: 50%;
        background: #fff;
        border: 2px solid #e2e8f0;
        margin-bottom: 0.5rem;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.3s ease;
    }

    .step-dot::after {
        content: '';
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: #e2e8f0;
    }

    .progress-step.active .step-dot {
        border-color: #3A86FF;
    }

    .progress-step.active .step-dot::after {
        background: #3A86FF;
    }

    .progress-step.completed .step-dot {
        background: #3A86FF;
        border-color: #3A86FF;
    }

    .progress-step.completed .step-dot::after {
        content: '✓';
        color: #fff;
        background: transparent;
        font-size: 10px;
        font-weight: 700;
    }

    .step-label {
        font-size: 0.75rem;
        font-weight: 600;
        color: #94a3b8;
    }

    .progress-step.active .step-label,
    .progress-step.completed .step-label {
        color: #0f172a;
    }

    .step-time {
        font-size: 0.65rem;
        color: #94a3b8;
        margin-top: 0.2rem;
    }

    .progress-line-fill {
        position: absolute;
        top: 10px;
        left: 2rem;
        height: 2px;
        background: #3A86FF;
        z-index: 1;
        transition: width 0.5s ease;
    }

    .menu-dots {
        cursor: pointer;
        color: #94a3b8;
        padding: 0.5rem;
        margin: -0.5rem;
    }

    .menu-dots:hover {
        color: #0f172a;
    }

</style>

<div class="bookings-container">
    <div class="bookings-header">
        <div>
            <h1>My Bookings</h1>
            <p>View and track all your booking requests.</p>
        </div>
        <a href="filter_results.php" class="btn btn-primary">
            <svg width="18" height="18" style="margin-right: 8px;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"></path></svg>
            Book a Service
        </a>
    </div>

    <div class="filter-tabs">
        <a href="my_bookings.php?filter=all" class="filter-tab <?= $filter === 'all' ? 'active' : '' ?>">
            All <span class="tab-count"><?= $counts['all'] ?></span>
        </a>
        <a href="my_bookings.php?filter=upcoming" class="filter-tab <?= $filter === 'upcoming' ? 'active' : '' ?>">
            Upcoming <span class="tab-count"><?= $counts['upcoming'] ?></span>
        </a>
        <a href="my_bookings.php?filter=completed" class="filter-tab <?= $filter === 'completed' ? 'active' : '' ?>">
            Completed <span class="tab-count"><?= $counts['completed'] ?></span>
        </a>
        <a href="my_bookings.php?filter=cancelled" class="filter-tab <?= $filter === 'cancelled' ? 'active' : '' ?>">
            Cancelled <span class="tab-count"><?= $counts['cancelled'] ?></span>
        </a>
    </div>

    <?php if (empty($bookings)): ?>
        <div style="text-align: center; padding: 4rem 2rem; background: #fff; border-radius: 16px; border: 1px dashed #e2e8f0;">
            <p style="color: #64748b;">No bookings found for this filter.</p>
        </div>
    <?php else: ?>
        <?php foreach ($bookings as $b): 
            $status = $b['status'];
            $statusClass = 'status-pending';
            $statusLabel = $status;
            $statusMsg = "Waiting for provider to accept.";
            
            if ($status === 'confirmed') {
                $statusClass = 'status-accepted';
                $statusLabel = 'Accepted';
                $statusMsg = "Your booking has been accepted.";
            } elseif ($status === 'completed') {
                $statusClass = 'status-completed';
                $statusLabel = 'Completed';
                $statusMsg = "Thank you! We hope to see you again.";
            } elseif ($status === 'cancelled' || $status === 'rejected') {
                $statusClass = 'status-cancelled';
                if ($status === 'rejected') {
                    $statusLabel = 'Rejected';
                    $statusMsg = "The provider rejected this booking.";
                } else {
                    $statusLabel = 'Cancelled';
                    $statusMsg = "This booking was cancelled.";
                }
            }

            // Progress Bar Logic (4 steps)
            $step1 = 'completed'; // Request Sent
            $step2 = 'upcoming';  // Accepted
            $step3 = 'upcoming';  // In Progress
            $step4 = 'upcoming';  // Completed

            if ($status === 'confirmed') {
                $step2 = 'completed';
                $step3 = 'active';
            } elseif ($status === 'completed') {
                $step2 = 'completed';
                $step3 = 'completed';
                $step4 = 'completed';
            }

            $fillWidth = '0%';
            if ($step4 === 'completed') $fillWidth = '100%';
            elseif ($step3 === 'completed' || $step3 === 'active') $fillWidth = '66%';
            elseif ($step2 === 'completed' || $step2 === 'active') $fillWidth = '33%';

            $date = date('M j, Y', strtotime($b['scheduled_date']));
            $time = date('g:i A', strtotime($b['scheduled_date']));
            $loc = htmlspecialchars($b['provider_barangay'] . ', ' . $b['provider_city']);
            $provImg = !empty($b['provider_image']) ? htmlspecialchars($b['provider_image']) : 'https://ui-avatars.com/api/?name='.urlencode($b['provider_name']).'&background=E2E8F0&color=475569';
        ?>
            <div class="booking-card">
                <div class="booking-card-top">
                    <img src="<?= $provImg ?>" alt="Provider" class="booking-img">
                    <div class="booking-info">
                        <h3><?= htmlspecialchars($b['service_title']) ?></h3>
                        <p class="provider-name"><?= htmlspecialchars($b['provider_name']) ?></p>
                        <div class="booking-meta">
                            <div class="meta-item">
                                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                                <?= $date ?> • <?= $time ?>
                            </div>
                            <div class="meta-item">
                                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                                <?= $loc ?>
                            </div>
                        </div>
                    </div>
                    <div class="booking-status">
                        <div style="display: flex; align-items: center; gap: 1rem;">
                            <span class="status-badge <?= $statusClass ?>"><?= $statusLabel ?></span>
                            <div class="menu-dots">⋮</div>
                        </div>
                        <p class="status-message"><?= $statusMsg ?></p>
                        <?php if ($status === 'rejected'): ?>
                            <button type="button" class="btn btn-outline js-view-rejected-details" 
                                style="padding: 0.4rem 1rem; font-size: 0.85rem; border-width: 1px;"
                                data-booking-id="<?= $b['id'] ?>"
                                data-service-id="<?= $b['service_id'] ?>"
                                data-provider-id="<?= $b['provider_id'] ?>"
                                data-service-title="<?= htmlspecialchars($b['service_title']) ?>"
                                data-provider-name="<?= htmlspecialchars($b['provider_name']) ?>"
                                data-original-date="<?= date('M j, Y g:i A', strtotime($b['scheduled_date'])) ?>"
                                data-reason="<?= htmlspecialchars($b['rejection_reason'] ?? '') ?>"
                                data-reschedule="<?= htmlspecialchars($b['suggested_reschedule_date'] ?? '') ?>"
                            >View Details</button>
                        <?php else: ?>
                            <a href="chat.php?provider=<?= $b['provider_id'] ?>" class="btn btn-outline" style="padding: 0.4rem 1rem; font-size: 0.85rem; border-width: 1px;">Message Provider</a>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="progress-track">
                    <div class="progress-line-fill" style="width: <?= $fillWidth ?>"></div>
                    
                    <div class="progress-step <?= $step1 ?>">
                        <div class="step-dot"></div>
                        <div class="step-label">Request Sent</div>
                        <div class="step-time"><?= date('M j, Y g:i A', strtotime($b['created_at'])) ?></div>
                    </div>
                    
                    <div class="progress-step <?= $step2 ?>">
                        <div class="step-dot"></div>
                        <div class="step-label">Accepted</div>
                        <div class="step-time"><?= $status !== 'pending' ? date('M j, Y g:i A', strtotime($b['updated_at'])) : '' ?></div>
                    </div>
                    
                    <div class="progress-step <?= $step3 ?>">
                        <div class="step-dot"></div>
                        <div class="step-label">In Progress</div>
                    </div>
                    
                    <div class="progress-step <?= $step4 ?>">
                        <div class="step-dot"></div>
                        <div class="step-label">Completed</div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Rejected Details Modal -->
<div id="rejected-details-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15,23,42,0.6); z-index: 1000; align-items: center; justify-content: center; backdrop-filter: blur(4px);">
    <div style="background: white; border-radius: 16px; padding: 2rem; width: 100%; max-width: 480px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
            <h2 style="margin: 0; font-size: 1.25rem; color: #0f172a; font-weight: 700;">Booking Details</h2>
            <button type="button" onclick="document.getElementById('rejected-details-modal').style.display='none'" style="background: none; border: none; font-size: 1.5rem; color: #94a3b8; cursor: pointer;">&times;</button>
        </div>
        
        <div style="margin-bottom: 1.25rem;">
            <span class="status-badge status-cancelled">Rejected</span>
        </div>

        <div style="background: #fff1f2; border: 1px solid #fecdd3; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem;">
            <p style="margin: 0 0 0.5rem; font-size: 0.85rem; color: #9f1239; font-weight: 600;">Reason from Provider</p>
            <p id="modal-rejection-reason" style="margin: 0; font-size: 0.95rem; color: #be123c;"></p>
        </div>

        <div id="modal-reschedule-section" style="display: none; margin-bottom: 1.5rem; padding-bottom: 1.5rem; border-bottom: 1px solid #e2e8f0;">
            <p style="margin: 0 0 0.5rem; font-size: 0.85rem; color: #475569; font-weight: 600;">Suggested New Schedule</p>
            <p id="modal-reschedule-datetime" style="margin: 0 0 1rem; font-size: 0.95rem; color: #0f172a; font-weight: 500;"></p>
            <button type="button" id="modal-accept-schedule" class="btn btn-primary" style="width: 100%;">Accept New Schedule</button>
        </div>

        <div style="margin-bottom: 1.5rem;">
            <p style="margin: 0 0 0.5rem; font-size: 0.85rem; color: #475569; font-weight: 600;">Original Booking Info</p>
            <div style="display: grid; gap: 0.5rem; font-size: 0.9rem; color: #334155;">
                <div style="display: flex; justify-content: space-between;">
                    <span style="color: #64748b;">Service Name</span>
                    <span id="modal-service-name" style="font-weight: 500;"></span>
                </div>
                <div style="display: flex; justify-content: space-between;">
                    <span style="color: #64748b;">Date & Time</span>
                    <span id="modal-original-date" style="font-weight: 500;"></span>
                </div>
                <div style="display: flex; justify-content: space-between;">
                    <span style="color: #64748b;">Provider Name</span>
                    <span id="modal-provider-name" style="font-weight: 500;"></span>
                </div>
            </div>
        </div>

        <div style="display: flex; gap: 0.75rem;">
            <a id="modal-book-again" href="#" class="btn btn-primary" style="flex: 1; text-align: center;">Book Again</a>
            <a id="modal-contact-provider" href="#" class="btn btn-outline" style="flex: 1; text-align: center;">Contact Provider</a>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('.js-view-rejected-details').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var bookingId = this.getAttribute('data-booking-id');
        var serviceId = this.getAttribute('data-service-id');
        var providerId = this.getAttribute('data-provider-id');
        var serviceTitle = this.getAttribute('data-service-title');
        var providerName = this.getAttribute('data-provider-name');
        var originalDate = this.getAttribute('data-original-date');
        var reason = this.getAttribute('data-reason');
        var reschedule = this.getAttribute('data-reschedule');

        document.getElementById('modal-rejection-reason').innerText = reason || 'No reason provided.';
        document.getElementById('modal-service-name').innerText = serviceTitle;
        document.getElementById('modal-original-date').innerText = originalDate;
        document.getElementById('modal-provider-name').innerText = providerName;
        
        document.getElementById('modal-book-again').href = 'book_service.php?service_id=' + serviceId;
        document.getElementById('modal-contact-provider').href = 'chat.php?provider=' + providerId;
        
        var acceptBtn = document.getElementById('modal-accept-schedule');
        var rescheduleSection = document.getElementById('modal-reschedule-section');
        
        if (reschedule && reschedule.trim() !== '') {
            var dateObj = new Date(reschedule.replace(' ', 'T'));
            var formatted = dateObj.toLocaleString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit' });
            document.getElementById('modal-reschedule-datetime').innerText = formatted;
            rescheduleSection.style.display = 'block';
            acceptBtn.setAttribute('data-booking-id', bookingId);
        } else {
            rescheduleSection.style.display = 'none';
        }

        var modal = document.getElementById('rejected-details-modal');
        modal.style.display = 'flex';
    });
});

document.getElementById('modal-accept-schedule').addEventListener('click', function() {
    if (!confirm('Are you sure you want to accept this new schedule?')) return;
    
    var bookingId = this.getAttribute('data-booking-id');
    var fd = new FormData();
    fd.append('booking_id', bookingId);
    
    var self = this;
    self.disabled = true;
    self.textContent = 'Accepting...';
    self.style.opacity = '0.7';
    
    fetch('api/accept_reschedule.php', {
        method: 'POST',
        body: fd,
        credentials: 'same-origin'
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) {
            alert('Schedule accepted! Your booking is now confirmed.');
            window.location.reload();
        } else {
            alert(data.error || 'Failed to accept schedule.');
            self.disabled = false;
            self.textContent = 'Accept New Schedule';
            self.style.opacity = '1';
        }
    })
    .catch(function() {
        alert('Failed to accept schedule.');
        self.disabled = false;
        self.textContent = 'Accept New Schedule';
        self.style.opacity = '1';
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>
