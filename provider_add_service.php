<?php
$pageTitle = 'Add Service';
require_once 'config/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'provider') {
    header('Location: login.php');
    exit;
}

$providerId = $_SESSION['provider_id'];
$pdo = getDBConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $category = (int)($_POST['category_id'] ?? 0);
    $priceMin = (float)($_POST['price_min'] ?? 0);
    $priceMax = (float)($_POST['price_max'] ?? 0);

    if ($category && $priceMin >= 0) {
        $pdo->prepare("INSERT INTO services (provider_id, category_id, price_min, price_max) VALUES (?, ?, ?, ?)")
            ->execute([$providerId, $category, $priceMin, $priceMax ?: $priceMin]);
        header('Location: face_verification.php');
        exit;
    }
}

$categories = $pdo->query("SELECT * FROM service_categories ORDER BY name")->fetchAll();
require_once 'includes/header.php';
?>
<section style="padding: 2rem; max-width: 600px;">
    <h1 class="section-title">Add Service</h1>
    <form method="POST" class="card" style="padding: 2rem;">
        <div class="form-group">
            <label>Category</label>
            <select name="category_id" required>
                <?php foreach ($categories as $c): ?>
                    <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Price Range (Min - Max ₱)</label>
            <div style="display: flex; gap: 1rem;">
                <input type="number" name="price_min" min="0" step="0.01" required placeholder="Min">
                <input type="number" name="price_max" min="0" step="0.01" placeholder="Max">
            </div>
        </div>
        <button type="submit" class="btn btn-primary">Add Service</button>
        <a href="provider_profile.php?id=<?= $providerId ?>" class="btn btn-ghost" style="margin-left: 0.5rem;">Cancel</a>
    </form>
</section>
<?php require_once 'includes/footer.php'; ?>
