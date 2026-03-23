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

$services = $pdo->prepare("SELECT s.*, sc.name as category_name FROM services s JOIN service_categories sc ON s.category_id = sc.id WHERE s.provider_id = ?");
$services->execute([$id]);
$services = $services->fetchAll();

// fetch reviews (bookings with reviews)
$reviewsStmt = $pdo->prepare("SELECT b.rating, b.review, b.created_at, u.full_name as customer_name FROM bookings b JOIN users u ON b.customer_id = u.id WHERE b.provider_id = ? AND b.review IS NOT NULL ORDER BY b.created_at DESC LIMIT 10");
$reviewsStmt->execute([$id]);
$reviews = $reviewsStmt->fetchAll();

$isSponsored = $pdo->prepare("SELECT 1 FROM ads WHERE provider_id = ? AND status = 'active'");
$isSponsored->execute([$id]);
$isSponsored = $isSponsored->fetch();

$avgRating = $pdo->prepare("SELECT AVG(rating) FROM bookings WHERE provider_id = ? AND rating IS NOT NULL");
$avgRating->execute([$id]);
$avgRating = $avgRating->fetchColumn();
$avgRating = $avgRating ? number_format((float)$avgRating, 1) : '—';

require_once 'includes/header.php';
?>
<section style="padding: 2rem;">
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

    <div class="profile-header">
        <div class="profile-banner" style="<?= $coverStyle ?>">
            <?php if (!empty($isSponsored)): ?><span class="badge-sponsored" style="position:absolute; top:12px; right:12px;">Sponsored</span><?php endif; ?>
            <div class="profile-avatar"><img src="<?= htmlspecialchars($avatarPath) ?>" alt="<?= htmlspecialchars($provider['full_name']) ?>" style="width:100%; height:100%; object-fit:cover;"></div>
        </div>
        <div class="profile-header-body">
            <div class="profile-main">
                <h1 style="margin: 0;"><?= htmlspecialchars($provider['full_name']) ?> <?php if (!empty($provider['face_verified'])): ?><span class="badge-verified">✓</span><?php endif; ?></h1>
                <!-- contact info removed to prevent contacting outside the system -->
                <div style="margin-top: 6px;"><span class="rating">★ <?= $avgRating ?></span></div>
                <div class="small-muted" style="margin-top:6px;"><?= htmlspecialchars($provider['city'] ?? '') ?><?= !empty($provider['barangay']) ? ', ' . htmlspecialchars($provider['barangay']) : '' ?></div>
            </div>
            <div class="profile-actions">
                <?php if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'customer'): ?>
                    <a href="chat.php?provider=<?= $id ?>" class="btn btn-primary">Message</a>
                    <a href="book_service.php?provider=<?= $id ?>" class="btn btn-outline">Book Service</a>
                <?php elseif (!isset($_SESSION['user_id'])): ?>
                    <a href="login.php" class="btn btn-primary">Login to Contact</a>
                <?php endif; ?>
                <?php if (!(isset($_SESSION['user_id']) && $_SESSION['role'] === 'customer')): ?>
                    <!-- For non-customers we still show a message button that points to login -->
                    <a href="login.php" class="btn btn-ghost">Message</a>
                <?php endif; ?>
            </div>
        </div>
        <div class="profile-tabs">
            <a href="#services" class="active">Services</a>
            <a href="#reviews">Reviews</a>
        </div>
    </div>


    <h2 id="services" class="section-title" style="margin-top: 1.5rem;">Services Offered</h2>
    <div class="provider-grid">
        <?php foreach ($services as $s): ?>
        <div class="card provider-card">
            <div class="card-body">
                <h3><?= htmlspecialchars($s['category_name']) ?></h3>
                <p style="color: var(--text-muted); font-size: 0.9rem;">Service Category</p>
                <p><strong>₱<?= number_format($s['price_min']) ?> - ₱<?= number_format($s['price_max']) ?></strong></p>
                <?php if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'customer'): ?>
                    <a href="book_service.php?provider=<?= $id ?>&service=<?= $s['id'] ?>" class="btn btn-primary" style="margin-top: 0.5rem;">Book this service</a>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
        <?php if (empty($services)): ?>
        <p style="color: var(--text-muted);">No services listed.</p>
        <?php endif; ?>
    </div>
    <h2 id="reviews" class="section-title" style="margin-top: 1.5rem;">Reviews</h2>
    <div class="card" style="padding: 1rem;">
        <?php if (!empty($reviews)): ?>
            <?php foreach ($reviews as $r): ?>
                <div style="border-bottom: 1px solid var(--border-color); padding: 0.75rem 0;">
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <strong><?= htmlspecialchars($r['customer_name']) ?></strong>
                        <div class="small-muted">★ <?= number_format((float)$r['rating'],1) ?></div>
                    </div>
                    <p style="margin:0.5rem 0; color: var(--text-muted);"><?= htmlspecialchars($r['review']) ?></p>
                    <div class="small-muted" style="font-size:0.85rem;"><?= htmlspecialchars(date('M j, Y', strtotime($r['created_at']))) ?></div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p class="small-muted">No reviews yet.</p>
        <?php endif; ?>
    </div>
</section>
<?php require_once 'includes/footer.php'; ?>
