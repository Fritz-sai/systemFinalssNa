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
    <div class="card" style="max-width: 800px; overflow: hidden;">
        <?php if ($isSponsored): ?><span class="badge-sponsored">Sponsored</span><?php endif; ?>
        <div style="display: flex; flex-wrap: wrap; gap: 2rem; padding: 2rem;">
            <div class="card-image" style="width: 120px; height: 120px; border-radius: 50%; flex-shrink: 0; overflow:hidden;">
                <?php if (!empty($provider['profile_image_path'])): ?>
                    <img src="<?= htmlspecialchars($provider['profile_image_path']) ?>" alt="Provider photo" style="width:100%; height:100%; object-fit:cover; display:block;">
                <?php else: ?>
                    👤
                <?php endif; ?>
            </div>
            <div style="flex: 1; min-width: 200px;">
                <h1><?= htmlspecialchars($provider['full_name']) ?> <?php if (!empty($provider['face_verified'])): ?><span class="badge-verified" style="font-size: 0.6em; vertical-align: middle;">✓ Verified</span><?php endif; ?></h1>
                <div class="rating" style="margin: 0.5rem 0;">★ <?= $avgRating ?></div>
                <p><?= htmlspecialchars($provider['city']) ?>, <?= htmlspecialchars($provider['barangay']) ?></p>
                <?php if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'customer'): ?>
                    <a href="chat.php?provider=<?= $id ?>" class="btn btn-primary" style="margin-top: 1rem;">Chat</a>
                    <a href="book_service.php?provider=<?= $id ?>" class="btn btn-outline" style="margin-top: 1rem; margin-left: 0.5rem;">Book Service</a>
                <?php elseif (!isset($_SESSION['user_id'])): ?>
                    <a href="login.php" class="btn btn-primary" style="margin-top: 1rem;">Login to Chat or Book</a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <h2 class="section-title" style="margin-top: 2rem;">Services Offered</h2>
    <div class="provider-grid">
        <?php foreach ($services as $s): ?>
        <div class="card provider-card">
            <div class="card-body">
                <h3><?= htmlspecialchars($s['title']) ?></h3>
                <p style="color: var(--text-muted); font-size: 0.9rem;"><?= htmlspecialchars($s['category_name']) ?></p>
                <?php if ($s['description']): ?>
                    <p style="font-size: 0.9rem; margin: 0.5rem 0;"><?= htmlspecialchars(substr($s['description'], 0, 100)) ?>...</p>
                <?php endif; ?>
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
</section>
<?php require_once 'includes/footer.php'; ?>
