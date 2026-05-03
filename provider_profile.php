<?php
$pageTitle = 'Provider Profile';
require_once 'config/config.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    header('Location: index.php');
    exit;
}

$pdo = getDBConnection();
$stmt = $pdo->prepare("
    SELECT p.*, u.full_name, u.phone, u.email
    FROM providers p
    JOIN users u ON p.user_id = u.id
    WHERE p.id = ?
");
$stmt->execute([$id]);
$provider = $stmt->fetch();

if (!$provider) {
    header('Location: index.php');
    exit;
}

// Respect provider profile visibility preference.
try {
    $visStmt = $pdo->prepare("
        SELECT pref_value
        FROM user_preferences
        WHERE user_id = ? AND pref_key = 'privacy_profile_visibility'
        LIMIT 1
    ");
    $visStmt->execute([(int)$provider['user_id']]);
    $profileVisibility = (string)($visStmt->fetchColumn() ?: 'public');
    $isOwner = isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] === (int)$provider['user_id'];
    $isAdmin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
    if ($profileVisibility === 'hidden' && !$isOwner && !$isAdmin) {
        header('Location: index.php');
        exit;
    }
} catch (Throwable $e) {
    // ignore if preferences table doesn't exist yet
}

$services = $pdo->prepare("SELECT s.*, sc.name as category_name FROM services s JOIN service_categories sc ON s.category_id = sc.id WHERE s.provider_id = ?");
$services->execute([$id]);
$services = $services->fetchAll();

// fetch reviews (bookings with reviews)
$reviewsStmt = $pdo->prepare("SELECT b.rating, b.review, b.review_photo_path, b.payment_accepted, b.created_at, u.full_name as customer_name FROM bookings b JOIN users u ON b.customer_id = u.id WHERE b.provider_id = ? AND b.review IS NOT NULL ORDER BY b.created_at DESC LIMIT 10");
$reviewsStmt->execute([$id]);
$reviews = $reviewsStmt->fetchAll();

$isSponsored = $pdo->prepare("SELECT 1 FROM ads WHERE provider_id = ? AND status = 'active'");
$isSponsored->execute([$id]);
$isSponsored = $isSponsored->fetch();

$avgRating = $pdo->prepare("SELECT AVG(rating) FROM bookings WHERE provider_id = ? AND rating IS NOT NULL");
$avgRating->execute([$id]);
$avgRating = $avgRating->fetchColumn();
$avgRating = $avgRating ? number_format((float)$avgRating, 1) : '—';

$totalServices = is_array($services) ? count($services) : 0;
$completedBookings = 0;
$upcomingBookings = 0;
try {
    $completedStmt = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE provider_id = ? AND status = 'completed'");
    $completedStmt->execute([$id]);
    $completedBookings = (int)$completedStmt->fetchColumn();

    $upcomingStmt = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE provider_id = ? AND status IN ('pending', 'confirmed') AND scheduled_date IS NOT NULL AND scheduled_date >= CURDATE()");
    $upcomingStmt->execute([$id]);
    $upcomingBookings = (int)$upcomingStmt->fetchColumn();
} catch (Throwable $e) {
    // keep defaults if booking stats query fails
}

$addServiceModalCategories = null;
if (isset($_SESSION['user_id']) && ($_SESSION['role'] ?? '') === 'provider' && (int)($_SESSION['provider_id'] ?? 0) === $id) {
    $addServiceModalCategories = $pdo->query('SELECT * FROM service_categories ORDER BY name')->fetchAll();
}

$isFavorite = false;
if (isset($_SESSION['user_id']) && ($_SESSION['role'] ?? '') === 'customer') {
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS user_favorites (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                provider_id INT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uk_user_provider (user_id, provider_id),
                INDEX (user_id),
                INDEX (provider_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    } catch (Throwable $e) {
        // ignore
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $favAction = (string)($_POST['favorite_action'] ?? '');
        if ($favAction === 'add') {
            $pdo->prepare("
                INSERT INTO user_favorites (user_id, provider_id)
                VALUES (?, ?)
                ON DUPLICATE KEY UPDATE created_at = CURRENT_TIMESTAMP
            ")->execute([(int)$_SESSION['user_id'], $id]);
        } elseif ($favAction === 'remove') {
            $pdo->prepare("DELETE FROM user_favorites WHERE user_id = ? AND provider_id = ?")
                ->execute([(int)$_SESSION['user_id'], $id]);
        }
    }

    $favStmt = $pdo->prepare("SELECT 1 FROM user_favorites WHERE user_id = ? AND provider_id = ? LIMIT 1");
    $favStmt->execute([(int)$_SESSION['user_id'], $id]);
    $isFavorite = (bool)$favStmt->fetchColumn();
}

require_once 'includes/header.php';
?>
<style>
.pp-page { background: #f6f8fc; padding: 1.5rem; }
.pp-container { max-width: 1100px; margin: 0 auto; display: grid; gap: 1rem; }
.pp-card {
    background: #fff;
    border: 1px solid #e7edf5;
    border-radius: 16px;
    box-shadow: 0 10px 26px rgba(15, 23, 42, 0.06);
}
.pp-header-wrap { overflow: hidden; }
.pp-banner {
    min-height: 110px;
    background: linear-gradient(115deg, #0d6efd 0%, #2d8cff 56%, #5aa8ff 100%);
    position: relative;
}
.pp-banner::before {
    content: "";
    position: absolute;
    inset: 0;
    background: radial-gradient(circle at 20% 25%, rgba(255,255,255,0.18) 0%, transparent 40%),
                radial-gradient(circle at 80% 10%, rgba(255,255,255,0.14) 0%, transparent 38%);
}
.pp-header-body { padding: 1.2rem 1.4rem 1.4rem; }
.pp-profile-row { display: flex; gap: 1rem; align-items: flex-start; margin-top: -56px; position: relative; z-index: 2; }
.pp-avatar {
    width: 108px;
    height: 108px;
    border-radius: 50%;
    background: #fff;
    border: 4px solid #fff;
    overflow: hidden;
    box-shadow: 0 10px 24px rgba(0, 0, 0, 0.16);
    flex-shrink: 0;
}
.pp-main-info h1 { margin: 0; color: #0f172a; font-size: 2rem; line-height: 1.15; }
.pp-meta-line { margin-top: 0.45rem; color: #475569; display: flex; align-items: center; gap: 0.65rem; flex-wrap: wrap; }
.pp-muted { color: #64748b; font-size: 0.92rem; }
.pp-actions { margin-left: auto; display: flex; flex-wrap: wrap; gap: 0.5rem; align-items: flex-start; }
.pp-rating-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    border-radius: 999px;
    padding: 0.32rem 0.7rem;
    background: #eff6ff;
    color: #1d4ed8;
    font-weight: 700;
    font-size: 0.82rem;
}
.pp-stats { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 0.85rem; margin-top: 1.1rem; }
.pp-stat {
    border: 1px solid #e8eef7;
    border-radius: 13px;
    padding: 0.9rem 1rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    background: #fff;
}
.pp-stat-icon {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
}
.pp-stat h4 { margin: 0; color: #0f172a; font-size: 1.25rem; }
.pp-stat p { margin: 0.1rem 0 0; font-size: 0.82rem; color: #64748b; }
.pp-section-card { padding: 1.2rem; }
.pp-section-head { display: flex; align-items: center; justify-content: space-between; gap: 0.75rem; margin-bottom: 0.9rem; }
.pp-section-head h2 { margin: 0; font-size: 1.55rem; color: #0f172a; }
.pp-section-head p { margin: 0.25rem 0 0; color: #64748b; }
.pp-service-grid { display: grid; gap: 0.85rem; }
.pp-service-item {
    border: 1px solid #e6edf5;
    border-radius: 14px;
    padding: 1rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.9rem;
    background: #fff;
}
.pp-service-item h3 { margin: 0; color: #0f172a; font-size: 1.06rem; }
.pp-service-item .meta { margin-top: 0.3rem; color: #64748b; font-size: 0.9rem; }
.pp-price { margin-top: 0.35rem; color: #0d6efd; font-weight: 700; font-size: 1.05rem; }
.pp-reviews { display: grid; gap: 0.75rem; }
.pp-review {
    border: 1px solid #e7edf5;
    border-radius: 12px;
    padding: 0.9rem;
    background: #fff;
}
.pp-review-top { display: flex; justify-content: space-between; gap: 0.8rem; align-items: center; margin-bottom: 0.45rem; }
.pp-tag-paid { color: #15803d; font-size: 0.82rem; font-weight: 600; }
@media (max-width: 900px) {
    .pp-stats { grid-template-columns: 1fr; }
    .pp-service-item { flex-direction: column; align-items: flex-start; }
    .pp-actions { margin-left: 0; }
}
@media (max-width: 700px) {
    .pp-page { padding: 0.9rem; }
    .pp-header-body { padding: 1rem; }
    .pp-profile-row { flex-direction: column; margin-top: -48px; }
    .pp-main-info h1 { font-size: 1.55rem; }
}
</style>
<section class="pp-page">
    <div class="pp-container">
    <?php
    // determine avatar path (reuse logic similar to provider_card)
    $rawAvatar = $provider['profile_image_path'] ?? '';
    if (empty($rawAvatar)) {
        $avatarPath = 'assets/img/default-avatar.svg';
    } elseif (preg_match('#^https?://#i', $rawAvatar)) {
        $avatarPath = $rawAvatar;
    } else {
        $candidate = __DIR__ . '/'. ltrim($rawAvatar, '/\\');
        if (file_exists($candidate)) {
            $avatarPath = $rawAvatar;
        } else {
            $avatarPath = 'assets/img/default-avatar.svg';
        }
    }

    $coverRaw = $provider['cover_image_path'] ?? '';
    $hasCover = false;
    $coverStyle = '';
    if (!empty($coverRaw)) {
        if (preg_match('#^https?://#i', $coverRaw)) {
            $coverStyle = "background-image: url('" . htmlspecialchars($coverRaw) . "'); background-size: cover; background-position: center;";
            $hasCover = true;
        } else {
            $candidateC = __DIR__ . '/'. ltrim($coverRaw, '/\\');
            if (file_exists($candidateC)) {
                $coverStyle = "background-image: url('" . htmlspecialchars($coverRaw) . "'); background-size: cover; background-position: center;";
                $hasCover = true;
            }
        }
    }
    ?>

    <div class="pp-card pp-header-wrap">
        <div class="pp-banner" style="<?= $coverStyle ?>">
            <?php if (!empty($isSponsored)): ?><span class="badge-sponsored" style="position:absolute; top:12px; right:12px;">Sponsored</span><?php endif; ?>
        </div>
        <div class="pp-header-body">
            <div class="pp-profile-row">
                <div class="pp-avatar"><img src="<?= htmlspecialchars($avatarPath) ?>" alt="<?= htmlspecialchars($provider['full_name']) ?>" style="width:100%; height:100%; object-fit:cover;"></div>
                <div class="pp-main-info">
                    <h1><?= htmlspecialchars($provider['full_name']) ?> <?php if (!empty($provider['face_verified'])): ?><span class="badge-verified">✓ Verified Provider</span><?php endif; ?></h1>
                    <div class="pp-meta-line">
                        <span class="pp-rating-pill">★ <?= $avgRating ?></span>
                        <span class="pp-muted">📍 <?= htmlspecialchars($provider['city'] ?? '') ?><?= !empty($provider['barangay']) ? ', ' . htmlspecialchars($provider['barangay']) : '' ?></span>
                        <span class="pp-muted">🗓 Member since <?= htmlspecialchars(date('M Y', strtotime((string)($provider['created_at'] ?? 'now')))) ?></span>
                    </div>
                </div>
                <div class="pp-actions">
                    <?php if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'customer'): ?>
                        <a href="chat.php?provider=<?= $id ?>" class="btn btn-primary">Message</a>
                        <a href="book_service.php?provider=<?= $id ?>" class="btn btn-outline">Book Service</a>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="favorite_action" value="<?= $isFavorite ? 'remove' : 'add' ?>">
                            <button type="submit" class="btn btn-ghost"><?= $isFavorite ? 'Remove Favorite' : 'Save Favorite' ?></button>
                        </form>
                    <?php elseif (!isset($_SESSION['user_id'])): ?>
                        <a href="login.php" class="btn btn-primary">Login to Contact</a>
                    <?php endif; ?>
                    
                </div>
            </div>

            <div class="pp-stats">
                <div class="pp-stat">
                    <span class="pp-stat-icon" style="background:#dbeafe;color:#1d4ed8;">🧰</span>
                    <div><h4><?= (int)$totalServices ?></h4><p>Services Offered</p></div>
                </div>
                <div class="pp-stat">
                    <span class="pp-stat-icon" style="background:#dcfce7;color:#15803d;">✅</span>
                    <div><h4><?= (int)$completedBookings ?></h4><p>Completed Bookings</p></div>
                </div>
                <div class="pp-stat">
                    <span class="pp-stat-icon" style="background:#fef3c7;color:#a16207;">⏰</span>
                    <div><h4><?= (int)$upcomingBookings ?></h4><p>Upcoming Bookings</p></div>
                </div>
            </div>
        </div>
    </div>


    <div class="pp-card pp-section-card">
        <div class="pp-section-head">
            <div>
                <h2 id="services">Services Offered</h2>
                <p>Services provided for customers.</p>
            </div>
            <?php if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'provider' && $_SESSION['provider_id'] == $id): ?>
                <button type="button" class="btn btn-primary js-open-add-service-modal">+ Add Service</button>
            <?php endif; ?>
        </div>
        <div class="pp-service-grid">
            <?php foreach ($services as $s): ?>
            <div class="pp-service-item">
                <div>
                    <h3><?= htmlspecialchars($s['category_name']) ?></h3>
                    <div class="meta">Service Category</div>
                    <div class="pp-price">₱<?= number_format($s['price_min']) ?> - ₱<?= number_format($s['price_max']) ?></div>
                </div>
                <div>
                    <?php if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'customer'): ?>
                        <a href="book_service.php?provider=<?= $id ?>&service=<?= $s['id'] ?>" class="btn btn-primary">Book this service</a>
                    <?php elseif (isset($_SESSION['user_id']) && $_SESSION['role'] === 'provider' && $_SESSION['provider_id'] == $id): ?>
                        <a href="provider_edit_service.php?id=<?= $s['id'] ?>" class="btn btn-outline">Edit Service</a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
            <?php if (empty($services)): ?>
            <p class="pp-muted">No services listed.</p>
            <?php endif; ?>
        </div>
    </div>

    <div class="pp-card pp-section-card">
        <div class="pp-section-head">
            <div>
                <h2 id="reviews">Reviews</h2>
                <p>Recent customer feedback and ratings.</p>
            </div>
        </div>
        <div class="pp-reviews">
        <?php if (!empty($reviews)): ?>
            <?php foreach ($reviews as $r): ?>
                <div class="pp-review">
                    <div class="pp-review-top">
                        <strong><?= htmlspecialchars($r['customer_name']) ?></strong>
                        <div class="pp-rating-pill">★ <?= number_format((float)$r['rating'],1) ?></div>
                    </div>
                    <?php if (!empty($r['review_photo_path'])): ?>
                        <img src="<?= htmlspecialchars($r['review_photo_path']) ?>" alt="Review photo" style="max-width: 150px; border-radius: 8px; margin-bottom: 0.55rem; display: block;">
                    <?php endif; ?>
                    <p class="pp-muted" style="margin:0.4rem 0 0.5rem;"><?= htmlspecialchars($r['review']) ?></p>
                    <div style="display:flex; gap:1rem; font-size:0.85rem;">
                        <div class="pp-muted"><?= htmlspecialchars(date('M j, Y', strtotime($r['created_at']))) ?></div>
                        <?php if ($r['payment_accepted']): ?>
                            <div class="pp-tag-paid">✓ Payment accepted</div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p class="pp-muted">No reviews yet.</p>
        <?php endif; ?>
        </div>
    </div>
    </div>
</section>
<?php
if (!empty($addServiceModalCategories)) {
    require_once __DIR__ . '/includes/add_service_modal.php';
}
?>
<?php require_once 'includes/footer.php'; ?>
