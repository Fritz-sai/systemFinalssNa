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

// Get user data
$userStmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$userStmt->execute([$userId]);
$user = $userStmt->fetch();

$location = $user['city'] ?? ($_SESSION['user_city'] ?? '');
$memberSince = date('M Y', strtotime($user['created_at']));

$categories = $pdo->query("SELECT * FROM service_categories ORDER BY name")->fetchAll();

// Providers near user
$providersStmt = $pdo->query("
    SELECT p.id, p.city, p.barangay, p.profile_image_path, u.full_name
    FROM providers p
    JOIN users u ON p.user_id = u.id
    WHERE p.verification_status = 'approved'
    ORDER BY p.created_at DESC
    LIMIT 9
");
$providers = $providersStmt->fetchAll();

require_once 'includes/header.php';
?>

<style>
.profile-wrapper { background: #f8faff; min-height: 100vh; padding: 2rem; font-family: 'Inter', sans-serif; }
.top-grid { display: grid; grid-template-columns: 1.2fr 1fr; gap: 1.5rem; margin-bottom: 2rem; }
@media (max-width: 992px) { .top-grid { grid-template-columns: 1fr; } }

.dash-card { background: white; border-radius: 16px; padding: 1.5rem; box-shadow: 0 4px 20px rgba(0,0,0,0.03); border: 1px solid #eef2f6; }
.dash-card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; }
.dash-card-title { font-size: 1.1rem; font-weight: 700; color: #0f172a; margin: 0; }

.badge-complete { background: #dcfce7; color: #15803d; padding: 0.3rem 0.75rem; border-radius: 50px; font-size: 0.75rem; font-weight: 600; display: flex; align-items: center; gap: 0.4rem; }

/* Profile Card */
.profile-info { display: flex; gap: 1.5rem; align-items: center; margin-bottom: 2rem; }
.profile-avatar { width: 80px; height: 80px; border-radius: 50%; object-fit: cover; border: 2px solid #f1f5f9; }
.profile-details h2 { margin: 0 0 0.25rem 0; font-size: 1.4rem; color: #0f172a; font-weight: 700; }
.profile-location { display: flex; align-items: center; gap: 0.4rem; color: #64748b; font-size: 0.85rem; margin-bottom: 0.75rem; }
.btn-edit-profile { background: #fff; border: 1px solid #3A86FF; color: #3A86FF; padding: 0.4rem 1rem; border-radius: 8px; font-weight: 600; font-size: 0.85rem; cursor: pointer; display: flex; align-items: center; gap: 0.5rem; }

.profile-stats-row { display: grid; grid-template-columns: 1fr 1fr 1fr; border-top: 1px solid #f1f5f9; margin-top: 1rem; padding-top: 1rem; }
.stat-item { display: flex; align-items: center; gap: 0.75rem; padding: 0 0.5rem; }
.stat-item:not(:last-child) { border-right: 1px solid #f1f5f9; }
.stat-icon { width: 36px; height: 36px; border-radius: 10px; background: #f8fafc; color: #3A86FF; display: flex; align-items: center; justify-content: center; font-size: 1rem; }
.stat-info { display: flex; flex-direction: column; }
.stat-label { font-size: 0.75rem; color: #64748b; }
.stat-value { font-size: 0.85rem; font-weight: 700; color: #0f172a; }

/* Quick Actions */
.action-btn { display: flex; align-items: center; gap: 1rem; padding: 1rem; border: 1px solid #eef2f6; border-radius: 12px; margin-bottom: 1rem; cursor: pointer; text-decoration: none; color: inherit; transition: all 0.2s; }
.action-btn:hover { border-color: #3A86FF; background: #f8fafc; transform: translateX(4px); }
.action-icon { width: 44px; height: 44px; border-radius: 10px; background: #3A86FF; color: white; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; flex-shrink: 0; }
.action-text h4 { margin: 0 0 0.2rem 0; color: #0f172a; font-size: 0.95rem; font-weight: 700; }
.action-text p { margin: 0; color: #64748b; font-size: 0.8rem; }
.action-arrow { margin-left: auto; color: #cbd5e1; font-size: 1.2rem; }

/* Banner */
.banner-cta { background: #f0f7ff; border-radius: 16px; padding: 1.5rem 2rem; margin-bottom: 2.5rem; display: flex; align-items: center; justify-content: space-between; border: 1px solid #dbeafe; position: relative; overflow: hidden; }
.banner-cta::after { content: ''; position: absolute; top: 0; right: 0; width: 300px; height: 100%; background: linear-gradient(90deg, transparent, rgba(58, 134, 255, 0.05)); clip-path: polygon(20% 0%, 100% 0, 100% 100%, 0% 100%); }
.banner-content { display: flex; align-items: center; gap: 1.5rem; position: relative; z-index: 1; }
.banner-icon { width: 50px; height: 50px; border-radius: 12px; background: #3A86FF; color: white; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; }
.banner-text h3 { margin: 0 0 0.25rem 0; font-size: 1.1rem; font-weight: 700; color: #0f172a; }
.banner-text p { margin: 0; color: #64748b; font-size: 0.9rem; }
.btn-banner { background: #3A86FF; color: white; padding: 0.6rem 1.5rem; border-radius: 10px; font-weight: 600; text-decoration: none; font-size: 0.9rem; position: relative; z-index: 1; }

/* Provider Section */
.section-header { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 1.5rem; }
.section-title-wrap h3 { font-size: 1.25rem; font-weight: 700; color: #0f172a; margin: 0 0 0.25rem 0; }
.section-title-wrap p { font-size: 0.9rem; color: #64748b; margin: 0; }

.filter-search-row { display: flex; justify-content: space-between; align-items: center; gap: 1rem; margin-bottom: 2rem; flex-wrap: wrap; }
.service-filters { display: flex; gap: 0.5rem; overflow-x: auto; padding-bottom: 0.5rem; scrollbar-width: none; }
.service-filters::-webkit-scrollbar { display: none; }
.filter-pill { padding: 0.5rem 1.25rem; border-radius: 50px; border: 1px solid #eef2f6; background: white; color: #64748b; font-size: 0.85rem; cursor: pointer; white-space: nowrap; font-weight: 600; transition: all 0.2s; }
.filter-pill.active { background: #3A86FF; color: white; border-color: #3A86FF; }
.filter-pill:hover:not(.active) { background: #f8fafc; border-color: #cbd5e1; }

.search-input-wrap { position: relative; min-width: 300px; }
.search-input-wrap input { width: 100%; padding: 0.6rem 1rem 0.6rem 2.5rem; border: 1px solid #eef2f6; border-radius: 10px; outline: none; font-size: 0.9rem; }
.search-icon { position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: #94a3b8; }

.provider-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 1.25rem; }
.provider-card { background: white; border-radius: 14px; padding: 1rem; border: 1px solid #eef2f6; display: flex; align-items: center; gap: 0.85rem; text-decoration: none; color: inherit; transition: all 0.2s; position: relative; }
.provider-card:hover { transform: translateY(-4px); box-shadow: 0 10px 25px rgba(0,0,0,0.05); border-color: #3A86FF; }
.provider-card-avatar { width: 52px; height: 52px; border-radius: 12px; object-fit: cover; flex-shrink: 0; }
.provider-card-initials { width: 52px; height: 52px; border-radius: 12px; background: #eff6ff; color: #3A86FF; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 1.1rem; flex-shrink: 0; }
.provider-card-info { flex: 1; min-width: 0; }
.provider-card-info h4 { margin: 0 0 0.15rem 0; font-size: 0.95rem; font-weight: 700; color: #0f172a; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.provider-card-status { font-size: 0.75rem; color: #64748b; margin-bottom: 0.25rem; }
.provider-card-loc { font-size: 0.75rem; color: #64748b; display: flex; align-items: center; gap: 0.2rem; margin-bottom: 0.4rem; }
.provider-card-rating { font-size: 0.8rem; font-weight: 700; color: #0f172a; display: flex; align-items: center; gap: 0.25rem; }
.provider-card-rating span { color: #94a3b8; font-weight: 400; }
.provider-card-arrow { color: #cbd5e1; font-size: 1rem; margin-left: auto; }

.more-providers-card { background: #fff; border: 1px dashed #3A86FF; border-radius: 14px; padding: 1.5rem; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; gap: 0.75rem; text-decoration: none; color: inherit; transition: all 0.2s; }
.more-providers-card:hover { background: #f0f7ff; }
.more-icon-btn { width: 40px; height: 40px; border-radius: 50%; background: #eff6ff; color: #3A86FF; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; }
.more-providers-card h4 { margin: 0; font-size: 0.9rem; font-weight: 700; color: #3A86FF; }
.more-providers-card p { margin: 0; font-size: 0.75rem; color: #64748b; line-height: 1.4; }

/* Benefits Footer */
.benefits-footer { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; margin-top: 4rem; padding-top: 2rem; border-top: 1px solid #eef2f6; }
@media (max-width: 768px) { .benefits-footer { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 480px) { .benefits-footer { grid-template-columns: 1fr; } }
.benefit-item { display: flex; align-items: flex-start; gap: 1rem; }
.benefit-icon { width: 44px; height: 44px; border-radius: 12px; background: #fff; border: 1px solid #eef2f6; color: #3A86FF; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; flex-shrink: 0; }
.benefit-text h4 { margin: 0 0 0.25rem 0; font-size: 0.85rem; font-weight: 700; color: #0f172a; }
.benefit-text p { margin: 0; font-size: 0.75rem; color: #64748b; line-height: 1.4; }

/* Modal Styles */
.modal-overlay { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center; z-index: 1000; opacity: 0; pointer-events: none; transition: opacity 0.3s; backdrop-filter: blur(4px); }
.modal-overlay.active { opacity: 1; pointer-events: auto; }
.modal-content { background: white; border-radius: 16px; padding: 2rem; width: 90%; max-width: 500px; transform: translateY(20px); transition: transform 0.3s; box-shadow: 0 20px 40px rgba(0,0,0,0.1); max-height: 90vh; overflow-y: auto; }
.modal-overlay.active .modal-content { transform: translateY(0); }
.modal-close { float: right; cursor: pointer; font-size: 1.5rem; line-height: 1; color: #94a3b8; background: transparent; border: none; }
</style>

<div class="profile-wrapper">
    <?php if ($success): ?>
        <div class="card" style="padding: 1rem; border-left: 4px solid #2ECC71; margin-bottom: 1rem; background: #fff;">
            <strong style="color:#2ECC71;"><?= htmlspecialchars($success) ?></strong>
        </div>
    <?php endif; ?>

    <div class="top-grid">
        <!-- My Profile Card -->
        <div class="dash-card">
            <div class="dash-card-header">
                <h3 class="dash-card-title">My Profile</h3>
                <?php if(!empty($user['profile_image_path']) && !empty($user['city'])): ?>
                <span class="badge-complete">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"></polyline></svg>
                    Profile Complete
                </span>
                <?php endif; ?>
            </div>
            <div class="profile-info">
                <?php $avatar = !empty($user['profile_image_path']) ? htmlspecialchars($user['profile_image_path']) : 'https://ui-avatars.com/api/?name='.urlencode($user['full_name']).'&background=EFF6FF&color=3A86FF&bold=true'; ?>
                <img src="<?= $avatar ?>" alt="Profile" class="profile-avatar">
                <div class="profile-details">
                    <h2><?= htmlspecialchars($user['full_name']) ?></h2>
                    <div class="profile-location">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                        <?= htmlspecialchars($location ?: 'Location not set') ?>
                    </div>
                    <button type="button" class="btn-edit-profile" onclick="document.getElementById('editModal').classList.add('active')">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"></path><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path></svg>
                        Edit Profile
                    </button>
                </div>
            </div>
            <div class="profile-stats-row">
                <div class="stat-item">
                    <div class="stat-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg></div>
                    <div class="stat-info">
                        <span class="stat-label">Account Status</span>
                        <span class="stat-value" style="color: #10b981;">Active</span>
                    </div>
                </div>
                <div class="stat-item">
                    <div class="stat-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg></div>
                    <div class="stat-info">
                        <span class="stat-label">Member Since</span>
                        <span class="stat-value"><?= $memberSince ?></span>
                    </div>
                </div>
                <div class="stat-item">
                    <div class="stat-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg></div>
                    <div class="stat-info">
                        <span class="stat-label">Account Type</span>
                        <span class="stat-value">Customer</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions Card -->
        <div class="dash-card">
            <h3 class="dash-card-title" style="margin-bottom: 1.5rem;">Quick Actions</h3>
            <a href="filter_results.php" class="action-btn">
                <div class="action-icon">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                </div>
                <div class="action-text">
                    <h4>Book a Service</h4>
                    <p>Browse providers and book a service</p>
                </div>
                <svg class="action-arrow" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"></polyline></svg>
            </a>
            <a href="chat.php" class="action-btn">
                <div class="action-icon" style="background: #a855f7;">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
                </div>
                <div class="action-text">
                    <h4>View Messages</h4>
                    <p>Check your conversations</p>
                </div>
                <svg class="action-arrow" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"></polyline></svg>
            </a>
        </div>
    </div>

    <!-- Banner -->
    <div class="banner-cta">
        <div class="banner-content">
            <div class="banner-icon">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line></svg>
            </div>
            <div class="banner-text">
                <h3>Find the best service for you</h3>
                <p>Browse top-rated providers and book your next appointment with ease.</p>
            </div>
        </div>
        <a href="filter_results.php" class="btn-banner">
            <svg width="18" height="18" style="margin-right: 8px; vertical-align: middle;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
            Book a Service
        </a>
    </div>

    <!-- Book a Service Section -->
   

    

    <!-- Benefits Footer -->
    <div class="benefits-footer">
        <div class="benefit-item">
            <div class="benefit-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg></div>
            <div class="benefit-text">
                <h4>Verified Providers</h4>
                <p>All providers are verified and background checked.</p>
            </div>
        </div>
        <div class="benefit-item">
            <div class="benefit-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg></div>
            <div class="benefit-text">
                <h4>Top Rated Services</h4>
                <p>Book with confidence from highly rated professionals.</p>
            </div>
        </div>
        <div class="benefit-item">
            <div class="benefit-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg></div>
            <div class="benefit-text">
                <h4>Secure Payments</h4>
                <p>Your payments and personal information are protected.</p>
            </div>
        </div>
        <div class="benefit-item">
            <div class="benefit-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path></svg></div>
            <div class="benefit-text">
                <h4>24/7 Support</h4>
                <p>Need help? Our support team is here for you.</p>
            </div>
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

<?php require_once 'includes/footer.php'; ?>
