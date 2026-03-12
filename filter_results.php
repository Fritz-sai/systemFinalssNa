<?php
$pageTitle = 'Find Services';
require_once 'config/config.php';

$location = trim($_GET['location'] ?? '');
$category = (int)($_GET['category'] ?? 0);
$rating = $_GET['rating'] ?? '';
$userCity = $_SESSION['user_city'] ?? '';

$pdo = getDBConnection();
$categories = $pdo->query("SELECT * FROM service_categories ORDER BY name")->fetchAll();

$sql = "SELECT p.id, p.user_id, p.city, p.barangay, p.face_verified, p.profile_image_path, u.full_name,
        (SELECT COUNT(*) FROM services s WHERE s.provider_id = p.id) as service_count
        FROM providers p
        JOIN users u ON p.user_id = u.id
        WHERE p.verification_status = 'approved'";
$params = [];

if ($location) {
    $sql .= " AND (p.city LIKE ? OR p.barangay LIKE ?)";
    $params[] = "%$location%";
    $params[] = "%$location%";
}
if ($category) {
    $sql .= " AND EXISTS (SELECT 1 FROM services s WHERE s.provider_id = p.id AND s.category_id = ?)";
    $params[] = $category;
}

$sql .= " ORDER BY p.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$providers = $stmt->fetchAll();

// Add sponsored flag
foreach ($providers as &$p) {
    $adStmt = $pdo->prepare("SELECT 1 FROM ads WHERE provider_id = ? AND status = 'active'");
    $adStmt->execute([$p['id']]);
    $p['sponsored'] = $adStmt->fetch() ? true : false;
}

require_once 'includes/header.php';
?>
<section style="padding: 2rem;">
    <h1 class="section-title">Find Services</h1>
    
    <form method="GET" class="search-box" style="margin-bottom: 2rem;">
        <input type="text" name="location" placeholder="City or Barangay..." value="<?= htmlspecialchars($location) ?>">
        <select name="category">
            <option value="">All Categories</option>
            <?php foreach ($categories as $c): ?>
                <option value="<?= $c['id'] ?>" <?= $category == $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="rating">
            <option value="">Any Rating</option>
            <option value="4" <?= $rating == '4' ? 'selected' : '' ?>>4+ Stars</option>
            <option value="4.5" <?= $rating == '4.5' ? 'selected' : '' ?>>4.5+ Stars</option>
        </select>
        <button type="submit" class="btn btn-primary">Search</button>
    </form>

    <h2 class="section-title">Results (<?= count($providers) ?> providers)</h2>
    <div class="provider-grid">
        <?php foreach ($providers as $p): ?>
        <a href="provider_profile.php?id=<?= $p['id'] ?>" class="card provider-card <?= $p['sponsored'] ? 'card-sponsored' : '' ?>" style="text-decoration: none; color: inherit;">
            <?php if ($p['sponsored']): ?><span class="badge-sponsored">Sponsored</span><?php endif; ?>
            <div class="card-image" style="overflow:hidden;">
                <?php if (!empty($p['profile_image_path'])): ?>
                    <img src="<?= htmlspecialchars($p['profile_image_path']) ?>" alt="Provider photo" style="width:100%; height:100%; object-fit:cover; display:block;">
                <?php else: ?>
                    👤
                <?php endif; ?>
            </div>
            <div class="card-body">
                <h3><?= htmlspecialchars($p['full_name']) ?><?php if (!empty($p['face_verified'])): ?> <span class="badge-verified">✓</span><?php endif; ?></h3>
                <div class="rating">★ 4.5</div>
                <div class="location"><?= htmlspecialchars($p['city']) ?>, <?= htmlspecialchars($p['barangay']) ?></div>
                <a href="chat.php?provider=<?= $p['id'] ?>" class="btn btn-outline" style="margin-top: 0.75rem;">Message</a>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
    <?php if (empty($providers)): ?>
    <p style="color: var(--text-muted);">No providers found. Try adjusting your filters.</p>
    <?php endif; ?>
</section>
<?php require_once 'includes/footer.php'; ?>
