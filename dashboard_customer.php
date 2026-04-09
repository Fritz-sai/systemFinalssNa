
<?php
$pageTitle = 'Customer Dashboard';
require_once 'config/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    header('Location: login.php');
    exit;
}

$pdo = getDBConnection();
$userId = $_SESSION['user_id'];

// Get user's location if set
$userStmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$userStmt->execute([$userId]);
$user = $userStmt->fetch();

$location = $_SESSION['user_city'] ?? '';
$categories = $pdo->query("SELECT * FROM service_categories ORDER BY name")->fetchAll();

// My Bookings (with completion confirmation and rating)
$bookingsStmt = $pdo->prepare("
    SELECT b.id, b.scheduled_date, b.status, b.completion_confirmed, b.rating, b.review,
           b.provider_id, s.title as service_title, u.full_name as provider_name
    FROM bookings b
    JOIN providers p ON b.provider_id = p.id
    JOIN users u ON p.user_id = u.id
    JOIN services s ON b.service_id = s.id
    WHERE b.customer_id = ?
    ORDER BY b.scheduled_date DESC, b.created_at DESC
");
$bookingsStmt->execute([$userId]);
$bookings = $bookingsStmt->fetchAll();

// Providers near user (simplified - use any if no location)
$providersStmt = $pdo->query("
    SELECT p.id, p.city, p.barangay, p.profile_image_path, u.full_name
    FROM providers p
    JOIN users u ON p.user_id = u.id
    WHERE p.verification_status = 'approved'
    ORDER BY p.created_at DESC
    LIMIT 8
");
$providers = $providersStmt->fetchAll();

require_once 'includes/header.php';
?>
<section style="padding: 2rem;">
    <h1 class="section-title">Welcome, <?= htmlspecialchars($_SESSION['full_name']) ?>!</h1>
    
    <div class="form-group" style="max-width: 400px; margin-bottom: 2rem;">
        <label>Your Location (for "New in Your Area")</label>
        <form method="POST" action="api/set_location.php" style="display:flex; gap:0.5rem;">
            <input type="text" name="city" placeholder="City" value="<?= htmlspecialchars($location) ?>" style="flex:1;">
            <button type="submit" class="btn btn-primary">Save</button>
        </form>
    </div>

    <h2 class="section-title">My Bookings</h2>
    <?php if (empty($bookings)): ?>
        <p style="color: var(--text-muted); margin-bottom: 2rem;">No bookings yet. <a href="filter_results.php">Find a provider</a> and book a service.</p>
    <?php else: ?>
        <div style="display: flex; flex-direction: column; gap: 1rem; margin-bottom: 2rem;">
            <?php foreach ($bookings as $b): ?>
            <div class="card" style="padding: 1.25rem;">
                <div style="display: flex; justify-content: space-between; flex-wrap: wrap; gap: 1rem;">
                    <div>
                        <strong><?= htmlspecialchars($b['service_title']) ?></strong> — <?= htmlspecialchars($b['provider_name']) ?>
                        <p style="color: var(--text-muted); font-size: 0.9rem; margin-top: 0.25rem;">
                            Scheduled: <?= date('M j, Y', strtotime($b['scheduled_date'])) ?>
                        </p>
                    </div>
                    <div>
                        <?php
                        $conf = $b['completion_confirmed'] ?? 'pending';
                        $isPast = strtotime($b['scheduled_date']) <= strtotime('today');
                        if ($conf === 'agreed'): ?>
                            <span style="color:#2ECC71;">✓ Completed</span>
                            <?php if (!empty($b['rating'])): ?>
                                <span class="rating">★ <?= (int)$b['rating'] ?></span>
                            <?php endif; ?>
                        <?php elseif ($conf === 'disputed'): ?>
                            <span style="color:#e74c3c;">Reported incomplete</span>
                        <?php elseif ($isPast): ?>
                            <div id="confirm-area-<?= $b['id'] ?>">
                                <p style="font-size: 0.9rem; margin-bottom: 0.5rem;">Was the work completed?</p>
                                <button type="button" class="btn btn-primary confirm-yes" data-id="<?= $b['id'] ?>" style="padding: 0.4rem 0.8rem; font-size: 0.9rem;">Yes</button>
                                <button type="button" class="btn btn-ghost confirm-no" data-id="<?= $b['id'] ?>" style="padding: 0.4rem 0.8rem; font-size: 0.9rem;">No</button>
                            </div>
                            <div id="rate-area-<?= $b['id'] ?>" style="display:none;">
                                <p style="font-size: 0.9rem; margin-bottom: 0.5rem;">Rate this provider and service:</p>
                                <form class="rate-form" data-id="<?= $b['id'] ?>" enctype="multipart/form-data">
                                    <div style="margin-bottom: 0.75rem;">
                                        <label style="display: block; font-size: 0.85rem; margin-bottom: 0.25rem;">Rating:</label>
                                        <select name="rating" required style="padding: 0.4rem; width: 100%;">
                                            <option value="">Choose rating...</option>
                                            <option value="5">★★★★★ 5 - Excellent</option>
                                            <option value="4">★★★★☆ 4 - Good</option>
                                            <option value="3">★★★☆☆ 3 - Okay</option>
                                            <option value="2">★★☆☆☆ 2 - Poor</option>
                                            <option value="1">★☆☆☆☆ 1 - Bad</option>
                                        </select>
                                    </div>
                                    <div style="margin-bottom: 0.75rem;">
                                        <label style="display: block; font-size: 0.85rem; margin-bottom: 0.25rem;">Review (optional):</label>
                                        <textarea name="review" placeholder="Share your experience..." style="padding: 0.4rem; width: 100%; height: 60px; font-family: inherit;"></textarea>
                                    </div>
                                    <div style="margin-bottom: 0.75rem;">
                                        <label style="display: block; font-size: 0.85rem; margin-bottom: 0.25rem;">Photo (optional):</label>
                                        <input type="file" name="review_photo" accept="image/*" style="padding: 0.4rem; width: 100%;">
                                        <div id="photo-preview-<?= $b['id'] ?>" style="margin-top: 0.5rem; display: none;">
                                            <img id="img-preview-<?= $b['id'] ?>" style="max-width: 150px; border-radius: 4px;">
                                        </div>
                                    </div>
                                    <div style="margin-bottom: 0.75rem;">
                                        <label style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.9rem;">
                                            <input type="checkbox" name="payment_accepted" required>
                                            I confirm the work is done and payment is acceptable
                                        </label>
                                    </div>
                                    <button type="submit" class="btn btn-primary" style="padding: 0.4rem 0.8rem; width: 100%;">Submit Review</button>
                                </form>
                            </div>
                        <?php else: ?>
                            <span style="color: var(--text-muted);">Upcoming</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <h2 class="section-title">Find Services</h2>
    <form action="filter_results.php" method="GET" class="search-box" style="margin-bottom: 2rem;">
        <input type="text" name="location" placeholder="City or Barangay...">
        <select name="category">
            <option value="">All Categories</option>
            <?php foreach ($categories as $c): ?>
                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="rating">
            <option value="">Any Rating</option>
            <option value="4">4+ Stars</option>
            <option value="4.5">4.5+ Stars</option>
        </select>
        <button type="submit" class="btn btn-primary">Search</button>
    </form>

    <h2 class="section-title">Service Providers</h2>
    <div class="provider-grid">
        <?php foreach ($providers as $p): ?>
        <a href="provider_profile.php?id=<?= $p['id'] ?>" class="card provider-card" style="text-decoration: none; color: inherit;">
            <div class="card-image" style="overflow:hidden;">
                <?php if (!empty($p['profile_image_path'])): ?>
                    <img src="<?= htmlspecialchars($p['profile_image_path']) ?>" alt="Provider photo" style="width:100%; height:100%; object-fit:cover; display:block;">
                <?php else: ?>
                    👤
                <?php endif; ?>
            </div>
            <div class="card-body">
                <h3><?= htmlspecialchars($p['full_name']) ?></h3>
                <div class="rating">★ 4.5</div>
                <div class="location"><?= htmlspecialchars($p['city']) ?>, <?= htmlspecialchars($p['barangay']) ?></div>
                <a href="chat.php?provider=<?= $p['id'] ?>" class="btn btn-outline" style="margin-top: 0.75rem;">Message</a>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
</section>

<script>
document.querySelectorAll('.confirm-yes').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var id = this.getAttribute('data-id');
        var fd = new FormData();
        fd.append('booking_id', id);
        fd.append('agreed', '1');
        fd.append('rating', '0');
        fetch('api/confirm_booking.php', { method: 'POST', body: fd })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) {
                    document.getElementById('confirm-area-' + id).style.display = 'none';
                    document.getElementById('rate-area-' + id).style.display = 'block';
                } else alert(data.error || 'Failed');
            });
    });
});
document.querySelectorAll('.confirm-no').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var id = this.getAttribute('data-id');
        if (!confirm('Report that the work was not completed?')) return;
        var fd = new FormData();
        fd.append('booking_id', id);
        fd.append('agreed', '0');
        fetch('api/confirm_booking.php', { method: 'POST', body: fd })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) location.reload();
                else alert(data.error || 'Failed');
            });
    });
});
document.querySelectorAll('.rate-form').forEach(function(form) {
    var bookingId = form.getAttribute('data-id');
    
    // Photo preview handler
    var photoInput = form.querySelector('input[name="review_photo"]');
    if (photoInput) {
        photoInput.addEventListener('change', function(e) {
            var file = e.target.files[0];
            if (file) {
                var reader = new FileReader();
                reader.onload = function(event) {
                    var preview = document.getElementById('photo-preview-' + bookingId);
                    var img = document.getElementById('img-preview-' + bookingId);
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
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        var id = this.getAttribute('data-id');
        var fd = new FormData(this);
        fd.append('booking_id', id);
        fd.append('agreed', '1');
        if (!fd.get('rating')) { alert('Please choose a rating.'); return; }
        if (!fd.get('payment_accepted')) { alert('Please confirm the work is done.'); return; }
        fetch('api/confirm_booking.php', { method: 'POST', body: fd })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) location.reload();
                else alert(data.error || 'Failed');
            });
    });
});
</script>
<?php require_once 'includes/footer.php'; ?>
