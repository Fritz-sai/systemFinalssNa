<?php
$pageTitle = 'Find Services';
require_once 'config/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php?require_login=1');
    exit;
}

$pdo = getDBConnection();

// Get categories for dropdown
$categories = $pdo->query("SELECT * FROM service_categories ORDER BY name")->fetchAll();
$cities = $pdo->query("SELECT DISTINCT city FROM providers WHERE city IS NOT NULL AND city <> '' ORDER BY city")->fetchAll(PDO::FETCH_COLUMN);
$barangays = $pdo->query("SELECT DISTINCT barangay FROM providers WHERE barangay IS NOT NULL AND barangay <> '' ORDER BY barangay")->fetchAll(PDO::FETCH_COLUMN);

// Get featured/sponsored providers (paid ads)
$featuredStmt = $pdo->query("
    SELECT p.id, p.user_id, p.city, p.barangay, p.verification_status, p.profile_image_path, u.full_name,
           (SELECT COUNT(*) FROM services s WHERE s.provider_id = p.id) as service_count,
           (SELECT AVG(b.rating) FROM bookings b WHERE b.provider_id = p.id AND b.rating IS NOT NULL) as avg_rating
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
    SELECT p.id, p.user_id, p.city, p.barangay, p.verification_status, p.profile_image_path, u.full_name,
           (SELECT COUNT(*) FROM services s WHERE s.provider_id = p.id) as service_count,
           (SELECT AVG(b.rating) FROM bookings b WHERE b.provider_id = p.id AND b.rating IS NOT NULL) as avg_rating,
           (SELECT title FROM services s WHERE s.provider_id = p.id LIMIT 1) as main_service
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

<section class="hero hero-trusted-services">
    <div class="hero-content fade-in">
        <h1>Find Trusted Services Near You</h1>
        <p>Connect with certified local service providers. Instant booking for verified plumbers, electricians, tutors, and more.</p>
        
        <form id="search-form" action="filter_results.php" method="GET" class="search-box home-search-box">
            <div class="search-input-group">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"></path></svg>
                <select id="search-category" name="category" aria-label="Select a service">
                    <option value="">Select a Service</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>" <?= ($_GET['category'] ?? '') == $cat['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="search-input-group">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                <select id="search-city" aria-label="Select city">
                    <option value="">Select City</option>
                    <?php foreach ($cities as $city): ?>
                        <option value="<?= htmlspecialchars($city) ?>"><?= htmlspecialchars($city) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="search-input-group">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
                <select id="search-barangay" aria-label="Select barangay">
                    <option value="">Select Barangay</option>
                    <?php foreach ($barangays as $barangay): ?>
                        <option value="<?= htmlspecialchars($barangay) ?>"><?= htmlspecialchars($barangay) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <input type="hidden" id="search-location" name="location" value="<?= htmlspecialchars($_GET['location'] ?? '') ?>">
            <button type="submit" class="btn btn-primary btn-search-submit">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                Search
            </button>
        </form>

        <div class="popular-categories">
            <span class="popular-label">Popular Categories</span>
            <a href="filter_results.php?category=plumber" class="category-pill">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"></path></svg> Plumber
            </a>
            <a href="filter_results.php?category=electrician" class="category-pill">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon></svg> Electrician
            </a>
            <a href="filter_results.php?category=tutor" class="category-pill">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path></svg> Tutor
            </a>
        </div>
    </div>
</section>

<?php if (!empty($featuredProviders)): ?>
<section class="home-providers-section">
    <h2 class="section-title">Featured Service Providers</h2>
    <p class="section-subtitle">Sponsored listings from verified providers</p>
    <div class="provider-grid">
        <?php foreach ($featuredProviders as $p): ?>
            <?php
            $provider = [
                'id' => $p['id'],
                'name' => $p['full_name'],
                'avatar' => !empty($p['profile_image_path']) ? $p['profile_image_path'] : '',
                'title' => $p['main_service'] ?? 'Service Professional',
                'bio' => '',
                'service' => isset($p['service_count']) ? (int)$p['service_count'] : 0,
                'location' => trim(($p['city'] ?? '') . ', ' . ($p['barangay'] ?? '')),
                'rate' => isset($p['avg_rating']) ? round((float)$p['avg_rating'],1) : 4.5,
                'sponsored' => true,
                'face_verified' => $p['verification_status'] === 'approved',
                'description' => 'Expert in residential and commercial tasks.',
            ];
            include __DIR__ . '/includes/provider_card.php';
            ?>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<section class="home-providers-section">
    <h2 class="section-title">New Providers in Your Area</h2>
    <p class="section-subtitle">Recently verified service providers</p>
    <?php if (!empty($newProviders)): ?>
    <div class="provider-grid">
        <?php foreach ($newProviders as $p): ?>
            <?php
            $provider = [
                'id' => $p['id'],
                'name' => $p['full_name'],
                'avatar' => !empty($p['profile_image_path']) ? $p['profile_image_path'] : '',
                'title' => $p['main_service'] ?? 'Service Professional',
                'bio' => '',
                'service' => isset($p['service_count']) ? (int)$p['service_count'] : 0,
                'location' => trim(($p['city'] ?? '') . ', ' . ($p['barangay'] ?? '')),
                'rate' => isset($p['avg_rating']) ? round((float)$p['avg_rating'],1) : 4.8,
                'sponsored' => false,
                'face_verified' => $p['verification_status'] === 'approved',
                'description' => 'Expert in residential and commercial tasks.',
            ];
            include __DIR__ . '/includes/provider_card.php';
            ?>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <p style="color: var(--text-muted);">No providers available yet. Be the first to <a href="register.php">register as a provider</a>!</p>
    <?php endif; ?>
</section>

<?php require_once 'includes/footer.php'; ?>
<script>
(function () {
    const cityEl = document.getElementById('search-city');
    const barangayEl = document.getElementById('search-barangay');
    const locationEl = document.getElementById('search-location');
    const formEl = document.getElementById('search-form');
    if (!cityEl || !barangayEl || !locationEl || !formEl) return;

    formEl.addEventListener('submit', function () {
        const city = (cityEl.value || '').trim();
        const barangay = (barangayEl.value || '').trim();
        const location = [city, barangay].filter(Boolean).join(', ');
        locationEl.value = location;
    });
})();
</script>
