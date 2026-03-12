<?php
$pageTitle = 'Edit Service';
require_once 'config/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'provider') {
    header('Location: login.php');
    exit;
}

$id = (int)($_GET['id'] ?? 0);
$providerId = $_SESSION['provider_id'];
$pdo = getDBConnection();

$stmt = $pdo->prepare("SELECT * FROM services WHERE id = ? AND provider_id = ?");
$stmt->execute([$id, $providerId]);
$service = $stmt->fetch();

if (!$service) {
    header('Location: dashboard_provider.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $category = (int)($_POST['category_id'] ?? 0);
    $priceMin = (float)($_POST['price_min'] ?? 0);
    $priceMax = (float)($_POST['price_max'] ?? 0);
    $pdo->prepare("UPDATE services SET title=?, description=?, category_id=?, price_min=?, price_max=? WHERE id=? AND provider_id=?")
        ->execute([$title, $description, $category, $priceMin, $priceMax ?: $priceMin, $id, $providerId]);
    header('Location: dashboard_provider.php');
    exit;
}

$categories = $pdo->query("SELECT * FROM service_categories ORDER BY name")->fetchAll();
require_once 'includes/header.php';
?>
<section style="padding: 2rem; max-width: 600px;">
    <h1 class="section-title">Edit Service</h1>
    <form method="POST" class="card" style="padding: 2rem;">
        <div class="form-group">
            <label>Title</label>
            <input type="text" name="title" required value="<?= htmlspecialchars($service['title']) ?>">
        </div>
        <div class="form-group">
            <label>Category</label>
            <select name="category_id" required>
                <?php foreach ($categories as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= $service['category_id'] == $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Description</label>
            <textarea name="description"><?= htmlspecialchars($service['description']) ?></textarea>
        </div>
        <div class="form-group">
            <label>Price Range (Min - Max ₱)</label>
            <div style="display: flex; gap: 1rem;">
                <input type="number" name="price_min" min="0" step="0.01" required value="<?= $service['price_min'] ?>">
                <input type="number" name="price_max" min="0" step="0.01" value="<?= $service['price_max'] ?>">
            </div>
        </div>
        <button type="submit" class="btn btn-primary">Save</button>
        <a href="dashboard_provider.php" class="btn btn-ghost" style="margin-left: 0.5rem;">Cancel</a>
    </form>
</section>
<?php require_once 'includes/footer.php'; ?>
