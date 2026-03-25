<?php
$pageTitle = 'Provider Dashboard';
require_once 'config/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'provider') {
    header('Location: login.php');
    exit;
}

require_provider_documents();

$pdo = getDBConnection();
$providerId = $_SESSION['provider_id'];
$userId = $_SESSION['user_id'];

$provStmt = $pdo->prepare("SELECT p.*, u.full_name, u.email, u.phone, COALESCE(p.credits, 0) as credits FROM providers p JOIN users u ON p.user_id = u.id WHERE p.id = ?");
$provStmt->execute([$providerId]);
$provider = $provStmt->fetch();

if (!$provider) {
    header('Location: logout.php');
    exit;
}

$services = $pdo->prepare("SELECT s.*, sc.name as category_name FROM services s JOIN service_categories sc ON s.category_id = sc.id WHERE s.provider_id = ?");
$services->execute([$providerId]);
$services = $services->fetchAll();

$categories = $pdo->query("SELECT * FROM service_categories ORDER BY name")->fetchAll();

$adStatus = $pdo->prepare("SELECT * FROM ads WHERE provider_id = ? ORDER BY created_at DESC LIMIT 1");
$adStatus->execute([$providerId]);
$adStatus = $adStatus->fetch();

require_once 'includes/header.php';
?>
<section style="padding: 2rem;">
    <h1 class="section-title">Provider Dashboard</h1>
    
    <div class="card" style="padding: 1.5rem; margin-bottom: 2rem;">
        <h3>Account Status</h3>
        <?php if ($provider['verification_status'] === 'approved'): ?>
            <p><span class="badge-verified">✓ Verified</span> You have the Verified badge.</p>
        <?php elseif ($provider['verification_status'] === 'pending'): ?>
            <p>Face verification: <strong>Under Review</strong></p>
            <p style="color: var(--text-muted); font-size: 0.9rem;">We're reviewing your documents. You'll get the Verified badge once approved.</p>
        <?php elseif ($provider['verification_status'] === 'rejected'): ?>
            <p>Face verification: <strong>Rejected</strong></p>
            <p style="color: var(--text-muted); font-size: 0.9rem;">Your verification was rejected. Please check your email or contact support.</p>
        <?php else: ?>
            <p>Face verification: <strong>Not verified</strong></p>
            <p style="color: var(--text-muted); font-size: 0.9rem;">Get the Verified badge to build trust with customers.</p>
            <a href="face_verification.php" class="btn btn-primary" style="margin-top: 0.5rem;">Verify Now</a>
        <?php endif; ?>
        <div style="margin-top: 0.75rem; display:flex; flex-wrap:wrap; gap:0.5rem; align-items: center;">
            <span style="margin-right: 0.5rem;"><strong><?= (int)($provider['credits'] ?? 0) ?></strong> credits</span>
            <a href="buy_credits.php" class="btn btn-ghost">Buy Credits</a>
            <a href="provider_settings.php" class="btn btn-ghost">Profile Settings</a>
            <a href="promote_service.php" class="btn btn-outline">Promote Your Service</a>
        </div>
    </div>

    <?php if (true): ?>
    <h2 class="section-title">My Services</h2>
    <a href="provider_add_service.php" class="btn btn-primary" style="margin-bottom: 1rem;">+ Add Service</a>
    
    <div class="provider-grid">
        <?php foreach ($services as $s): ?>
        <div class="card provider-card">
            <div class="card-body">
                <h3><?= htmlspecialchars($s['title']) ?></h3>
                <p style="color: var(--text-muted); font-size: 0.9rem;"><?= htmlspecialchars($s['category_name']) ?></p>
                <p>₱<?= number_format($s['price_min']) ?> - ₱<?= number_format($s['price_max']) ?></p>
                <a href="provider_edit_service.php?id=<?= $s['id'] ?>" class="btn btn-ghost" style="margin-top: 0.5rem;">Edit</a>
            </div>
        </div>
        <?php endforeach; ?>
        <?php if (empty($services)): ?>
        <p style="color: var(--text-muted);">No services yet. <a href="provider_add_service.php">Add your first service</a></p>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</section>
<?php require_once 'includes/footer.php'; ?>
