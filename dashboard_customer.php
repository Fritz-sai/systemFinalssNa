
<?php
$pageTitle = 'Customer Dashboard';
require_once 'config/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    header('Location: login.php');
    exit;
}

$pdo = getDBConnection();
$userId = $_SESSION['user_id'];

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $city = trim($_POST['city'] ?? '');
    $barangay = trim($_POST['barangay'] ?? '');

    if ($city === '' || $barangay === '') {
        $error = 'City and barangay are required.';
    } else {
        $profileImagePath = null;
        $imgStmt = $pdo->prepare("SELECT profile_image_path FROM users WHERE id = ?");
        $imgStmt->execute([$userId]);
        $profileImagePath = $imgStmt->fetchColumn() ?: null;

        if (!empty($_FILES['profile_image']['name'])) {
            $allowedExt = ['jpg', 'jpeg', 'png', 'webp'];
            $ext = strtolower(pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION));
            $size = (int)($_FILES['profile_image']['size'] ?? 0);

            if (!in_array($ext, $allowedExt, true)) {
                $error = 'Profile picture must be JPG, JPEG, PNG, or WEBP.';
            } elseif ($size > 5 * 1024 * 1024) {
                $error = 'Profile picture must be 5MB or smaller.';
            } elseif (!is_uploaded_file($_FILES['profile_image']['tmp_name'])) {
                $error = 'Invalid profile picture upload.';
            } else {
                $uploadDir = __DIR__ . '/uploads/customers';
                if (!is_dir($uploadDir)) {
                    @mkdir($uploadDir, 0777, true);
                }
                $filename = 'customer_' . $userId . '_' . time() . '.' . $ext;
                $targetAbs = $uploadDir . '/' . $filename;
                $targetRel = 'uploads/customers/' . $filename;
                if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $targetAbs)) {
                    $profileImagePath = $targetRel;
                } else {
                    $error = 'Failed to upload profile picture.';
                }
            }
        }

        if ($error === '') {
            $pdo->prepare("UPDATE users SET city = ?, barangay = ?, profile_image_path = ? WHERE id = ?")
                ->execute([$city, $barangay, $profileImagePath, $userId]);
            $_SESSION['user_city'] = $city;
            $success = 'Profile updated successfully.';
        }
    }
}

// Get user's location if set
$userStmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$userStmt->execute([$userId]);
$user = $userStmt->fetch();

$location = $user['city'] ?? ($_SESSION['user_city'] ?? '');
$categories = $pdo->query("SELECT * FROM service_categories ORDER BY name")->fetchAll();

// My Bookings (with completion confirmation and rating)
$bookingsStmt = $pdo->prepare("
    SELECT b.id, b.scheduled_date, b.status, b.completion_confirmed, b.rating, b.review,
           b.provider_id, s.title as service_title,
           u.full_name as provider_name, p.profile_image_path as provider_image, p.city as provider_city
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
");
$providers = $providersStmt->fetchAll();

require_once 'includes/header.php';
?>
<style>
.profile-wrapper { background: #f8faff; min-height: 100vh; padding: 2rem; font-family: 'Inter', sans-serif; }
.top-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 2rem; }
@media (max-width: 768px) { .top-grid { grid-template-columns: 1fr; } }
.dash-card { background: white; border-radius: 16px; padding: 1.5rem; box-shadow: 0 4px 20px rgba(0,0,0,0.03); }
.dash-card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; }
.dash-card-title { font-size: 1.25rem; font-weight: 700; color: #1e293b; margin: 0; }
.badge-complete { background: #dcfce7; color: #166534; padding: 0.25rem 0.75rem; border-radius: 50px; font-size: 0.8rem; font-weight: 600; }

.profile-info { display: flex; gap: 1.5rem; align-items: center; flex-wrap: wrap; }
.profile-avatar { width: 90px; height: 90px; border-radius: 50%; object-fit: cover; border: 3px solid #f1f5f9; }
.profile-details h2 { margin: 0 0 0.25rem 0; font-size: 1.25rem; color: #0f172a; font-weight: 700; text-transform: capitalize; }
.profile-location { display: flex; align-items: center; gap: 0.5rem; color: #64748b; font-size: 0.9rem; margin-bottom: 1rem; }
.btn-edit { background: white; border: 1px solid #3A86FF; color: #3A86FF; padding: 0.5rem 1.25rem; border-radius: 8px; font-weight: 500; cursor: pointer; transition: all 0.2s; font-size: 0.9rem; }
.btn-edit:hover { background: #eff6ff; }

.action-btn { display: flex; align-items: center; gap: 1rem; padding: 1rem; border: 1px solid #e2e8f0; border-radius: 12px; margin-bottom: 1rem; cursor: pointer; text-decoration: none; color: inherit; transition: all 0.2s; }
.action-btn:last-child { margin-bottom: 0; }
.action-btn:hover { border-color: #3A86FF; background: #f8fafc; }
.action-icon { width: 40px; height: 40px; border-radius: 10px; background: #3A86FF; color: white; display: flex; align-items: center; justify-content: center; flex-shrink: 0;}
.action-text h4 { margin: 0 0 0.25rem 0; color: #0f172a; font-size: 1rem; font-weight: 600; }
.action-text p { margin: 0; color: #64748b; font-size: 0.85rem; }
.action-arrow { margin-left: auto; color: #cbd5e1; }

.booking-card { display: flex; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; margin-bottom: 1rem; background: white; }
@media (max-width: 768px) { .booking-card { flex-direction: column; } .booking-left { width: 100% !important; border-right: none !important; border-bottom: 1px solid #e2e8f0; } }
.booking-left { padding: 1.5rem; background: #fcfcfd; border-right: 1px solid #e2e8f0; width: 40%; flex-shrink: 0; display: flex; gap: 1.25rem; }
.booking-provider-img { width: 64px; height: 64px; border-radius: 8px; object-fit: cover; }
.booking-provider-info h4 { margin: 0 0 0.25rem 0; font-size: 1.1rem; color: #0f172a; font-weight: 600; }
.booking-provider-info p { margin: 0; color: #64748b; font-size: 0.85rem; display: flex; align-items: center; gap: 0.4rem; margin-bottom: 0.4rem; }
.status-badge { display: inline-block; padding: 0.25rem 0.6rem; border-radius: 4px; font-size: 0.75rem; font-weight: 600; margin-bottom: 0.5rem; text-transform: capitalize; }
.status-confirmed { background: #dcfce7; color: #166534; }
.status-pending { background: #fef3c7; color: #92400e; }
.status-rejected { background: #fee2e2; color: #b91c1c; }
.booking-right { padding: 1.5rem; flex-grow: 1; display: flex; flex-direction: column; justify-content: space-between; }
.booking-desc { color: #334155; font-size: 0.95rem; margin-bottom: 1rem; line-height: 1.5; }
.booking-price { font-size: 1.25rem; font-weight: 700; color: #3A86FF; margin-bottom: 1rem; }
.btn-view-details { padding: 0.5rem 1rem; border: 1px solid #3A86FF; color: #3A86FF; background: transparent; border-radius: 6px; font-size: 0.9rem; font-weight: 500; cursor: pointer; transition: all 0.2s; align-self: flex-start; text-decoration: none; }
.btn-view-details:hover { background: #eff6ff; }

.service-filters { display: flex; gap: 0.75rem; margin-bottom: 1.5rem; overflow-x: auto; padding-bottom: 0.5rem; scrollbar-width: none; }
.service-filters::-webkit-scrollbar { display: none; }
.filter-pill { padding: 0.5rem 1.25rem; border-radius: 50px; border: 1px solid #e2e8f0; background: white; color: #475569; font-size: 0.9rem; cursor: pointer; white-space: nowrap; transition: all 0.2s; font-weight: 500; }
.filter-pill.active { background: #3A86FF; color: white; border-color: #3A86FF; }

.provider-list { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 1.5rem; }
.provider-card { background: white; border-radius: 12px; padding: 1.25rem; border: 1px solid #e2e8f0; display: flex; align-items: center; gap: 1rem; transition: all 0.2s; text-decoration: none; color: inherit; cursor: pointer; }
.provider-card:hover { box-shadow: 0 4px 15px rgba(0,0,0,0.05); border-color: #cbd5e1; transform: translateY(-2px); }
.provider-card-img { width: 50px; height: 50px; border-radius: 8px; object-fit: cover; }
.provider-card-info h4 { margin: 0 0 0.25rem 0; font-size: 1rem; color: #0f172a; font-weight: 600;}
.provider-card-info p { margin: 0; color: #64748b; font-size: 0.8rem; }
.provider-card-rating { display: flex; align-items: center; gap: 0.25rem; font-size: 0.8rem; font-weight: 600; color: #334155; margin-top: 0.25rem; }
.provider-card-arrow { margin-left: auto; color: #94a3b8; }

.modal-overlay { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center; z-index: 1000; opacity: 0; pointer-events: none; transition: opacity 0.3s; backdrop-filter: blur(4px); }
.modal-overlay.active { opacity: 1; pointer-events: auto; }
.modal-content { background: white; border-radius: 16px; padding: 2rem; width: 90%; max-width: 500px; transform: translateY(20px); transition: transform 0.3s; box-shadow: 0 20px 40px rgba(0,0,0,0.1); max-height: 90vh; overflow-y: auto; }
.modal-overlay.active .modal-content { transform: translateY(0); }
.modal-close { float: right; cursor: pointer; font-size: 1.5rem; line-height: 1; color: #94a3b8; background: transparent; border: none; }
</style>

<div class="profile-wrapper">
    <?php if ($success): ?>
        <div class="card" style="padding: 1rem; border-left: 4px solid #2ECC71; margin-bottom: 1rem;">
            <strong style="color:#2ECC71;"><?= htmlspecialchars($success) ?></strong>
        </div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="card" style="padding: 1rem; border-left: 4px solid #e74c3c; margin-bottom: 1rem;">
            <strong style="color:#e74c3c;"><?= htmlspecialchars($error) ?></strong>
        </div>
    <?php endif; ?>

    <!-- Top Grid -->
    <div class="top-grid">
        <!-- My Profile Card -->
        <div class="dash-card">
            <div class="dash-card-header">
                <h3 class="dash-card-title">My Profile</h3>
                <?php if(!empty($user['profile_image_path']) && !empty($user['city'])): ?>
                <span class="badge-complete">Profile Complete</span>
                <?php endif; ?>
            </div>
            <div class="profile-info">
                <?php $avatar = !empty($user['profile_image_path']) ? htmlspecialchars($user['profile_image_path']) : 'https://ui-avatars.com/api/?name='.urlencode($_SESSION['full_name']).'&background=E2E8F0&color=475569'; ?>
                <img src="<?= $avatar ?>" alt="Profile" class="profile-avatar">
                <div class="profile-details">
                    <h2><?= htmlspecialchars($_SESSION['full_name']) ?></h2>
                    <div class="profile-location">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                        <?= htmlspecialchars($location ?: 'Location not set') ?>
                    </div>
                    <button type="button" class="btn-edit" onclick="document.getElementById('editModal').classList.add('active')">Edit Profile</button>
                </div>
            </div>
        </div>

        <!-- Quick Actions Card -->
        <div class="dash-card">
            <h3 class="dash-card-title" style="margin-bottom: 1.5rem;">Quick Actions</h3>
            <a href="filter_results.php" class="action-btn">
                <div class="action-icon">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                </div>
                <div class="action-text">
                    <h4>Book a Service</h4>
                    <p>Browse providers and book a service</p>
                </div>
                <svg class="action-arrow" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"></polyline></svg>
            </a>
            <a href="chat.php" class="action-btn">
                <div class="action-icon">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
                </div>
                <div class="action-text">
                    <h4>View Messages</h4>
                    <p>Check your conversations</p>
                </div>
                <svg class="action-arrow" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"></polyline></svg>
            </a>
        </div>
    </div>

    <!-- My Bookings -->
    <div class="dash-card" style="margin-bottom: 2rem;">
        <div class="dash-card-header">
            <h3 class="dash-card-title">My Bookings</h3>
            <a href="#" style="color: #3A86FF; text-decoration: none; font-size: 0.9rem; font-weight: 500;">View All</a>
        </div>

        <?php if (empty($bookings)): ?>
            <p style="color: #64748b; text-align: center; padding: 2rem 0;">No bookings yet. Start by booking a service.</p>
        <?php else: ?>
            <?php foreach ($bookings as $b): 
                $statusColor = $b['status'] === 'confirmed' ? 'status-confirmed' : ($b['status'] === 'pending' ? 'status-pending' : 'status-rejected');
                $price = 'Price to be discussed';
                $provImg = !empty($b['provider_image']) ? htmlspecialchars($b['provider_image']) : 'https://ui-avatars.com/api/?name='.urlencode($b['provider_name']);
                $date = date('M j, Y', strtotime($b['scheduled_date']));
                $time = date('g:i A', strtotime($b['scheduled_date']));
            ?>
            <div class="booking-card">
                <div class="booking-left">
                    <img src="<?= $provImg ?>" alt="Provider" class="booking-provider-img">
                    <div class="booking-provider-info">
                        <span class="status-badge <?= $statusColor ?>"><?= htmlspecialchars($b['status']) ?></span>
                        <h4><?= htmlspecialchars($b['provider_name']) ?></h4>
                        <p style="color: #3A86FF; font-weight: 500; margin-bottom: 0.5rem;"><?= htmlspecialchars($b['service_title']) ?></p>
                        <p><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg> <?= $date ?></p>
                        <p><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg> <?= $time ?></p>
                        <p><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg> <?= htmlspecialchars($b['provider_city']) ?></p>
                    </div>
                </div>
                <div class="booking-right">
                    <div class="booking-desc">
                        <!-- We mock description here or use service title if description isn't available -->
                        Task involves <?= htmlspecialchars(strtolower($b['service_title'])) ?> services as agreed.
                        
                        <?php 
                        $conf = $b['completion_confirmed'] ?? 'pending';
                        $isPast = strtotime($b['scheduled_date']) <= strtotime('today');
                        if ($isPast && $conf !== 'agreed' && $conf !== 'disputed'): ?>
                            <div id="confirm-area-<?= $b['id'] ?>" style="margin-top: 1rem; background: #fff8f1; padding: 1rem; border-radius: 8px; border: 1px solid #fed7aa;">
                                <p style="font-size: 0.9rem; margin-bottom: 0.5rem; font-weight: 600; color: #9a3412;">Was the work completed?</p>
                                <button type="button" class="btn btn-primary confirm-yes" data-id="<?= $b['id'] ?>" style="padding: 0.4rem 0.8rem; font-size: 0.85rem;">Yes</button>
                                <button type="button" class="btn btn-ghost confirm-no" data-id="<?= $b['id'] ?>" style="padding: 0.4rem 0.8rem; font-size: 0.85rem;">No</button>
                            </div>
                            
                            <div id="rate-area-<?= $b['id'] ?>" style="display:none; margin-top: 1rem; background: #f8fafc; padding: 1rem; border-radius: 8px;">
                                <p style="font-size: 0.9rem; margin-bottom: 0.5rem; font-weight: 600;">Rate this provider and service:</p>
                                <form class="rate-form" data-id="<?= $b['id'] ?>" enctype="multipart/form-data">
                                    <div style="margin-bottom: 0.75rem;">
                                        <select name="rating" required style="padding: 0.4rem; width: 100%; border: 1px solid #cbd5e1; border-radius: 4px;">
                                            <option value="">Choose rating...</option>
                                            <option value="5">★★★★★ 5 - Excellent</option>
                                            <option value="4">★★★★☆ 4 - Good</option>
                                            <option value="3">★★★☆☆ 3 - Okay</option>
                                            <option value="2">★★☆☆☆ 2 - Poor</option>
                                            <option value="1">★☆☆☆☆ 1 - Bad</option>
                                        </select>
                                    </div>
                                    <div style="margin-bottom: 0.75rem;">
                                        <textarea name="review" placeholder="Share your experience..." style="padding: 0.4rem; width: 100%; height: 60px; font-family: inherit; border: 1px solid #cbd5e1; border-radius: 4px;"></textarea>
                                    </div>
                                    <div style="margin-bottom: 0.75rem;">
                                        <label style="display: block; font-size: 0.8rem; margin-bottom: 0.25rem;">Photo (optional):</label>
                                        <input type="file" name="review_photo" accept="image/*" style="font-size: 0.8rem;">
                                        <div id="photo-preview-<?= $b['id'] ?>" style="margin-top: 0.5rem; display: none;">
                                            <img id="img-preview-<?= $b['id'] ?>" style="max-width: 100px; border-radius: 4px;">
                                        </div>
                                    </div>
                                    <div style="margin-bottom: 0.75rem;">
                                        <label style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.8rem;">
                                            <input type="checkbox" name="payment_accepted" required>
                                            Work is done & payment acceptable
                                        </label>
                                    </div>
                                    <button type="submit" class="btn btn-primary" style="padding: 0.4rem 0.8rem; width: 100%; font-size: 0.85rem;">Submit Review</button>
                                </form>
                            </div>
                        <?php elseif ($conf === 'agreed'): ?>
                            <div style="margin-top: 1rem;">
                                <span style="color:#166534; font-weight: 500; font-size: 0.9rem;">✓ Work Completed</span>
                                <?php if (!empty($b['rating'])): ?>
                                    <span style="color: #f59e0b; font-weight: bold; margin-left: 0.5rem;">★ <?= (int)$b['rating'] ?></span>
                                <?php endif; ?>
                            </div>
                        <?php elseif ($conf === 'disputed'): ?>
                            <div style="margin-top: 1rem;"><span style="color:#b91c1c; font-weight: 500; font-size: 0.9rem;">Reported incomplete</span></div>
                        <?php endif; ?>
                    </div>
                    <div>
                        <div class="booking-price"><?= $price ?></div>
                        <a href="chat.php?provider=<?= $b['provider_id'] ?>" class="btn-view-details">View Details & Message</a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Recommended Providers -->
    <div style="margin-bottom: 1rem;">
        <h3 class="dash-card-title" style="margin-bottom: 0.5rem;">Book a Service</h3>
        <p style="color: #64748b; font-size: 0.95rem; margin-bottom: 1.5rem;">Find trusted providers in your area</p>
        
        <div class="service-filters">
            <div class="filter-pill active">All Services</div>
            <div class="filter-pill">Cleaning</div>
            <div class="filter-pill">Plumbing</div>
            <div class="filter-pill">Electrical</div>
            <div class="filter-pill">Carpentry</div>
            <div class="filter-pill">Others</div>
        </div>

        <div class="provider-list">
            <?php foreach($providers as $p): 
                $img = !empty($p['profile_image_path']) ? htmlspecialchars($p['profile_image_path']) : 'https://ui-avatars.com/api/?name='.urlencode($p['full_name']);
                // Mock rating and price range since they aren't directly in provider query currently
                $mockRating = number_format(rand(45, 50) / 10, 1);
                $mockReviews = rand(10, 50);
            ?>
            <a href="provider_profile.php?id=<?= $p['id'] ?>" class="provider-card">
                <img src="<?= $img ?>" alt="Provider" class="provider-card-img">
                <div class="provider-card-info">
                    <h4><?= htmlspecialchars($p['full_name']) ?></h4>
                    <p>Verified Provider • <?= htmlspecialchars($p['city']) ?></p>
                    <div class="provider-card-rating">
                        <?= $mockRating ?> <span style="color: #f59e0b;">★</span> <span style="color: #94a3b8; font-weight: normal;">(<?= $mockReviews ?>)</span>
                    </div>
                </div>
                <svg class="provider-card-arrow" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"></polyline></svg>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Edit Profile Modal -->
<div class="modal-overlay" id="editModal">
    <div class="modal-content">
        <button class="modal-close" onclick="document.getElementById('editModal').classList.remove('active')">&times;</button>
        <h2 style="margin-bottom: 1.5rem; font-size: 1.25rem; font-weight: 700;">Edit Profile</h2>
        <form method="POST" enctype="multipart/form-data">
            <div class="form-group" style="margin-bottom: 1rem;">
                <label style="display: block; font-weight: 500; margin-bottom: 0.5rem; font-size: 0.9rem;">Profile Picture</label>
                <?php if (!empty($user['profile_image_path'])): ?>
                    <div style="margin-bottom: 0.75rem;">
                        <img src="<?= htmlspecialchars($user['profile_image_path']) ?>" alt="Profile picture" style="width: 60px; height: 60px; border-radius: 50%; object-fit: cover; border: 2px solid #e2e8f0;">
                    </div>
                <?php endif; ?>
                <input type="file" name="profile_image" accept="image/*" style="font-size: 0.9rem;">
                <small style="color: #64748b; display: block; margin-top: 0.25rem;">JPG, PNG, or WEBP. Max size: 5MB.</small>
            </div>
            <div class="form-group" style="margin-bottom: 1rem;">
                <label style="display: block; font-weight: 500; margin-bottom: 0.5rem; font-size: 0.9rem;">City</label>
                <input type="text" name="city" placeholder="City / Municipality" value="<?= htmlspecialchars($user['city'] ?? $location) ?>" required style="width: 100%; padding: 0.75rem; border: 1px solid #cbd5e1; border-radius: 8px;">
            </div>
            <div class="form-group" style="margin-bottom: 1.5rem;">
                <label style="display: block; font-weight: 500; margin-bottom: 0.5rem; font-size: 0.9rem;">Barangay</label>
                <input type="text" name="barangay" placeholder="Barangay" value="<?= htmlspecialchars($user['barangay'] ?? '') ?>" required style="width: 100%; padding: 0.75rem; border: 1px solid #cbd5e1; border-radius: 8px;">
            </div>
            <button type="submit" class="btn btn-primary" style="width: 100%; padding: 0.75rem; border-radius: 8px; font-weight: 600;">Save Changes</button>
        </form>
    </div>
</div>

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
