<?php
$pageTitle = 'Find Services';
require_once 'config/config.php';

$city = trim($_GET['city'] ?? '');
$barangay = trim($_GET['barangay'] ?? '');
$category = (int)($_GET['category'] ?? 0);
$rating = $_GET['rating'] ?? '';
$userCity = $_SESSION['user_city'] ?? '';

 $pdo = getDBConnection();
 $categories = $pdo->query("SELECT * FROM service_categories ORDER BY name")->fetchAll();

// Build city -> barangay mapping for the place filters
$rows = $pdo->query("SELECT city, barangay FROM providers WHERE city IS NOT NULL AND city != '' AND barangay IS NOT NULL AND barangay != '' ORDER BY city, barangay")->fetchAll(PDO::FETCH_ASSOC);
$cityBarangays = [];
$allBarangays = [];
foreach ($rows as $r) {
    $c = $r['city'];
    $b = $r['barangay'];
    if (!isset($cityBarangays[$c])) $cityBarangays[$c] = [];
    if (!in_array($b, $cityBarangays[$c], true)) $cityBarangays[$c][] = $b;
    if (!in_array($b, $allBarangays, true)) $allBarangays[] = $b;
}

 $sql = "SELECT p.id, p.user_id, p.city, p.barangay, p.verification_status, p.profile_image_path, u.full_name,
    (SELECT COUNT(*) FROM services s WHERE s.provider_id = p.id) as service_count,
    (SELECT AVG(b.rating) FROM bookings b WHERE b.provider_id = p.id AND b.rating IS NOT NULL) as avg_rating
    FROM providers p
        JOIN users u ON p.user_id = u.id
        WHERE p.verification_status = 'approved'";
$params = [];

if ($city) {
    $sql .= " AND p.city = ?";
    $params[] = $city;
}
if ($barangay) {
    $sql .= " AND p.barangay = ?";
    $params[] = $barangay;
}
if ($category) {
    $sql .= " AND EXISTS (SELECT 1 FROM services s WHERE s.provider_id = p.id AND s.category_id = ?)";
    $params[] = $category;
}

if ($rating !== '') {
    // filter providers by average booking rating
    $sql .= " AND COALESCE((SELECT AVG(b.rating) FROM bookings b WHERE b.provider_id = p.id AND b.rating IS NOT NULL), 0) >= ?";
    $params[] = (float)$rating;
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

// Debug panel: list all approved providers and reasons they're excluded by current filters
if (!empty($_GET['debug_providers'])) {
    $allStmt = $pdo->query("SELECT p.id, u.full_name, p.city, p.barangay, p.profile_image_path, p.verification_status,
        (SELECT COUNT(*) FROM services s WHERE s.provider_id = p.id) as service_count,
        (SELECT AVG(b.rating) FROM bookings b WHERE b.provider_id = p.id AND b.rating IS NOT NULL) as avg_rating
        FROM providers p JOIN users u ON p.user_id = u.id WHERE p.verification_status = 'approved'");
    $allProviders = $allStmt->fetchAll(PDO::FETCH_ASSOC);

    echo '<div style="padding:1rem;background:#fff;border-radius:8px;margin:1rem 0;border:1px solid #eee;">';
    echo '<h3 style="margin:0 0 8px;">Debug: providers and exclusion reasons</h3>';
    foreach ($allProviders as $ap) {
        $reasons = [];
        if ($city && $ap['city'] !== $city) $reasons[] = "city({$ap['city']}) != filter({$city})";
        if ($barangay && $ap['barangay'] !== $barangay) $reasons[] = "barangay({$ap['barangay']}) != filter({$barangay})";
        if ($category) {
            $catCheck = $pdo->prepare("SELECT 1 FROM services s WHERE s.provider_id = ? AND s.category_id = ? LIMIT 1");
            $catCheck->execute([$ap['id'], $category]);
            if (!$catCheck->fetch()) $reasons[] = 'no matching service category';
        }
        if ($rating !== '') {
            $avg = $ap['avg_rating'] ? (float)$ap['avg_rating'] : 0.0;
            if ($avg < (float)$rating) $reasons[] = "avg_rating({$avg}) < filter({$rating})";
        }

        // If no reasons, provider would match filters
        $status = empty($reasons) ? '<strong style="color:green">MATCH</strong>' : '<span style="color:#d9534f">'.htmlspecialchars(implode('; ', $reasons)).'</span>';
        echo '<div style="padding:6px 0;border-top:1px solid #f1f1f1;">';
        echo '<div><strong>'.htmlspecialchars($ap['full_name']).'</strong> — <span class="small-muted">'.htmlspecialchars(trim($ap['city'].', '.$ap['barangay'])).'</span> — rating: '.($ap['avg_rating']?number_format($ap['avg_rating'],1):'—')."</div>";
        echo '<div style="font-size:0.9rem;margin-top:4px;">Status: '.$status.'</div>';
        echo '</div>';
    }
    echo '</div>';
}

require_once 'includes/header.php';
?>
<section style="padding: 2rem;">
    <h1 class="section-title">Find Services</h1>
    
    <form method="GET" class="filters-card">
        <div class="filter-row">
            <div class="filter-col" style="flex:1;">
                <label for="city">City</label>
                <select id="city" name="city">
                    <option value="">All Cities</option>
                    <?php foreach (array_keys($cityBarangays) as $c): ?>
                        <option value="<?= htmlspecialchars($c) ?>" <?= $city == $c ? 'selected' : '' ?>><?= htmlspecialchars($c) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-col" style="max-width:320px;">
                <label for="barangay">Barangay</label>
                <select id="barangay" name="barangay">
                    <option value="">All Barangays</option>
                    <?php foreach ($allBarangays as $b): ?>
                        <option value="<?= htmlspecialchars($b) ?>" <?= $barangay == $b ? 'selected' : '' ?>><?= htmlspecialchars($b) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-col" style="max-width:320px;">
                <label for="category">Service</label>
                <select id="category" name="category">
                    <option value="">All Services</option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= $category == $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-col" style="max-width:160px;">
                <label for="rating">Minimum Rate</label>
                <select id="rating" name="rating">
                    <option value="">Any</option>
                    <option value="3" <?= $rating == '3' ? 'selected' : '' ?>>3.0+</option>
                    <option value="4" <?= $rating == '4' ? 'selected' : '' ?>>4.0+</option>
                    <option value="4.5" <?= $rating == '4.5' ? 'selected' : '' ?>>4.5+</option>
                </select>
            </div>
        </div>
        <div class="filter-actions">
            <button type="submit" class="btn btn-primary">Filter</button>
            <a href="filter_results.php" class="btn btn-ghost">Reset</a>
        </div>
    </form>

    <h2 class="section-title">Results (<?= count($providers) ?> providers)</h2>
    <div class="provider-grid">
        <?php foreach ($providers as $p): ?>
            <?php
            $provider = [
                'id' => $p['id'],
                'name' => $p['full_name'],
                'avatar' => !empty($p['profile_image_path']) ? $p['profile_image_path'] : '',
                'title' => '',
                'bio' => '',
                'service' => isset($p['service_count']) ? (int)$p['service_count'] : 0,
                'location' => trim(($p['city'] ?? '') . ', ' . ($p['barangay'] ?? '')),
                'rate' => isset($p['avg_rating']) ? round((float)$p['avg_rating'], 1) : 0,
                'sponsored' => !empty($p['sponsored']),
                'face_verified' => $p['verification_status'] === 'approved',
            ];
            include __DIR__ . '/includes/provider_card.php';
            ?>
        <?php endforeach; ?>
    </div>
    <?php if (empty($providers)): ?>
    <p style="color: var(--text-muted);">No providers found. Try adjusting your filters.</p>
    <?php endif; ?>
</section>
<script>
    // city -> barangays mapping generated server-side
    (function(){
        const CITY_BARANGAYS = <?= json_encode($cityBarangays, JSON_UNESCAPED_UNICODE) ?>;
        const allBarangays = <?= json_encode($allBarangays, JSON_UNESCAPED_UNICODE) ?>;
        const citySel = document.getElementById('city');
        const barangaySel = document.getElementById('barangay');

        function populateBarangays(city) {
            // clear
            while (barangaySel.options.length) barangaySel.remove(0);
            const opt = document.createElement('option');
            opt.value = '';
            opt.text = 'All Barangays';
            barangaySel.add(opt);
            const list = city ? (CITY_BARANGAYS[city] || []) : allBarangays;
            list.forEach(b => {
                const o = document.createElement('option');
                o.value = b;
                o.text = b;
                if (o.value === <?= json_encode($barangay) ?>) o.selected = true;
                barangaySel.add(o);
            });
        }

        if (citySel) {
            citySel.addEventListener('change', function(){ populateBarangays(this.value); });
            // initialize
            populateBarangays(citySel.value);
        }
    })();
</script>
<?php require_once 'includes/footer.php'; ?>
