<?php
$pageTitle = 'Saved Providers';
require_once 'config/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$pdo = getDBConnection();
$userId = (int)$_SESSION['user_id'];
$role = (string)($_SESSION['role'] ?? 'customer');

if ($role !== 'customer') {
    require_once 'includes/header.php';
    ?>
    <section style="padding:1.5rem; max-width:780px; margin:0 auto;">
        <div class="card admin-panel-card">
            <h2>Saved Providers/Favorites</h2>
            <p class="small-muted">Favorites are available for customer accounts.</p>
            <a href="index.php" class="btn btn-primary">Browse Providers</a>
        </div>
    </section>
    <?php
    require_once 'includes/footer.php';
    exit;
}

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
    $action = (string)($_POST['action'] ?? '');
    $providerId = (int)($_POST['provider_id'] ?? 0);
    if ($providerId > 0 && $action === 'remove_favorite') {
        $pdo->prepare("DELETE FROM user_favorites WHERE user_id = ? AND provider_id = ?")->execute([$userId, $providerId]);
    }
}

$stmt = $pdo->prepare("
    SELECT uf.provider_id, uf.created_at, u.full_name, u.email, p.city, p.barangay, p.profile_image_path
    FROM user_favorites uf
    JOIN providers p ON p.id = uf.provider_id
    JOIN users u ON u.id = p.user_id
    WHERE uf.user_id = ?
    ORDER BY uf.created_at DESC
");
$stmt->execute([$userId]);
$favorites = $stmt->fetchAll();

require_once 'includes/header.php';
?>
<section style="padding:1.5rem; max-width:980px; margin:0 auto;">
    <h1 class="section-title">Saved Providers/Favorites</h1>
    <div class="admin-grid">
        <div class="card admin-panel-card">
            <div class="admin-card-head"><h2>Favorites</h2></div>
            <?php if (empty($favorites)): ?>
                <p class="small-muted">No saved providers yet.</p>
                <a class="btn btn-primary" href="index.php">Find Providers</a>
            <?php else: ?>
                <div class="admin-table-wrap">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Provider</th>
                                <th>Location</th>
                                <th>Saved</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($favorites as $f): ?>
                                <tr>
                                    <td>
                                        <div class="provider-row-name">
                                            <span class="provider-row-avatar">
                                                <?php if (!empty($f['profile_image_path'])): ?>
                                                    <img src="<?= htmlspecialchars((string)$f['profile_image_path']) ?>" alt="<?= htmlspecialchars((string)$f['full_name']) ?>">
                                                <?php else: ?>
                                                    <?= htmlspecialchars(strtoupper(substr((string)$f['full_name'], 0, 1))) ?>
                                                <?php endif; ?>
                                            </span>
                                            <div>
                                                <strong><?= htmlspecialchars((string)$f['full_name']) ?></strong>
                                                <div class="small-muted"><?= htmlspecialchars((string)$f['email']) ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?= htmlspecialchars((string)$f['city']) ?>, <?= htmlspecialchars((string)$f['barangay']) ?></td>
                                    <td><?= htmlspecialchars(date('M d, Y', strtotime((string)$f['created_at']))) ?></td>
                                    <td>
                                        <div class="admin-inline-actions">
                                            <a class="btn btn-ghost" href="provider_profile.php?id=<?= (int)$f['provider_id'] ?>">View</a>
                                            <form method="POST">
                                                <input type="hidden" name="provider_id" value="<?= (int)$f['provider_id'] ?>">
                                                <button type="submit" name="action" value="remove_favorite" class="btn btn-ghost">Remove</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>
<?php require_once 'includes/footer.php'; ?>
