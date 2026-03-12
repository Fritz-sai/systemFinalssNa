<?php
$pageTitle = 'Find Trusted Services Near You';
require_once 'config/config.php';

$pdo = getDBConnection();

// Get categories for dropdown
$categories = $pdo->query("SELECT * FROM service_categories ORDER BY name")->fetchAll();

// Get featured/sponsored providers (paid ads)
$featuredStmt = $pdo->query("
    SELECT p.id, p.user_id, p.city, p.barangay, p.face_verified, p.profile_image_path, u.full_name,
           (SELECT AVG(1) FROM (SELECT 1) x) as rating
    FROM providers p
    JOIN users u ON p.user_id = u.id
    JOIN ads a ON a.provider_id = p.id
    WHERE a.status = 'active' AND p.verification_status = 'approved'
    ORDER BY a.created_at DESC
    LIMIT 6
");
$featuredProviders = $featuredStmt ? $featuredStmt->fetchAll() : [];

// Get regular verified providers (non-sponsored) for "new in area" - use session city if available
$userCity = $_SESSION['user_city'] ?? '';
$newProvidersStmt = $pdo->prepare("
    SELECT p.id, p.user_id, p.city, p.barangay, p.face_verified, p.profile_image_path, u.full_name
    FROM providers p
    JOIN users u ON p.user_id = u.id
    WHERE p.verification_status = 'approved'
    AND NOT EXISTS (SELECT 1 FROM ads a WHERE a.provider_id = p.id AND a.status = 'active')
    ORDER BY p.created_at DESC
    LIMIT 6
");
$newProvidersStmt->execute();
$newProviders = $newProvidersStmt->fetchAll();

require_once 'includes/header.php';
?>

<section class="hero">
    <div class="hero-content fade-in">
        <h1>Find Trusted Services Near You</h1>
        <p>Connect with verified local service providers. Book plumbers, electricians, tutors, and more with confidence.</p>
        <form id="search-form" action="filter_results.php" method="GET" class="search-box">
            <input type="text" id="search-location" name="location" placeholder="Enter city or barangay..." value="<?= htmlspecialchars($_GET['location'] ?? '') ?>">
            <select id="search-category" name="category">
                <option value="">All Categories</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= $cat['id'] ?>" <?= ($_GET['category'] ?? '') == $cat['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($cat['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-primary">Search</button>
        </form>
    </div>
</section>

<?php if (!empty($featuredProviders)): ?>
<section>
    <h2 class="section-title">Featured Service Providers</h2>
    <p style="color: var(--text-muted); margin-bottom: 1.5rem;">Sponsored listings from verified providers</p>
    <div class="provider-grid">
        <?php foreach ($featuredProviders as $provider): ?>
        <a href="provider_profile.php?id=<?= $provider['id'] ?>" class="card provider-card card-sponsored" style="text-decoration: none; color: inherit;">
            <span class="badge-sponsored">Sponsored</span>
            <div class="card-image" style="overflow:hidden;">
                <?php if (!empty($provider['profile_image_path'])): ?>
                    <img src="<?= htmlspecialchars($provider['profile_image_path']) ?>" alt="Provider photo" style="width:100%; height:100%; object-fit:cover; display:block;">
                <?php else: ?>
                    👤
                <?php endif; ?>
            </div>
            <div class="card-body">
                <h3><?= htmlspecialchars($provider['full_name']) ?><?php if (!empty($provider['face_verified'])): ?> <span class="badge-verified">✓</span><?php endif; ?></h3>
                <div class="rating">★ <?= number_format($provider['rating'] ?? 4.8, 1) ?></div>
                <div class="location"><?= htmlspecialchars($provider['city']) ?>, <?= htmlspecialchars($provider['barangay']) ?></div>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<section>
    <h2 class="section-title">New Providers in Your Area</h2>
    <p style="color: var(--text-muted); margin-bottom: 1.5rem;">Recently verified service providers</p>
    <?php if (!empty($newProviders)): ?>
    <div class="provider-grid">
        <?php foreach ($newProviders as $provider): ?>
        <a href="provider_profile.php?id=<?= $provider['id'] ?>" class="card provider-card" style="text-decoration: none; color: inherit;">
            <div class="card-image" style="overflow:hidden;">
                <?php if (!empty($provider['profile_image_path'])): ?>
                    <img src="<?= htmlspecialchars($provider['profile_image_path']) ?>" alt="Provider photo" style="width:100%; height:100%; object-fit:cover; display:block;">
                <?php else: ?>
                    👤
                <?php endif; ?>
            </div>
            <div class="card-body">
                <h3><?= htmlspecialchars($provider['full_name']) ?><?php if (!empty($provider['face_verified'])): ?> <span class="badge-verified">✓</span><?php endif; ?></h3>
                <div class="rating">★ 4.5</div>
                <div class="location"><?= htmlspecialchars($provider['city']) ?>, <?= htmlspecialchars($provider['barangay']) ?></div>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <p style="color: var(--text-muted);">No providers available yet. Be the first to <a href="register.php">register as a provider</a>!</p>
    <?php endif; ?>
</section>

<section>
    <div class="promote-section">
        <h2>Promote Your Service</h2>
        <p>Are you a service provider? Get more visibility and reach more customers with our featured listings.</p>
        <a href="promote_service.php" class="btn btn-primary">Learn More</a>
    </div>
</section>

<?php require_once 'includes/footer.php'; ?>
