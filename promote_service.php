<?php
$pageTitle = 'Promote Your Service';
require_once 'config/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'provider') {
    header('Location: login.php');
    exit;
}

$providerId = $_SESSION['provider_id'];
$pdo = getDBConnection();

// Check if approved
$prov = $pdo->prepare("SELECT verification_status FROM providers WHERE id = ?");
$prov->execute([$providerId]);
$provRow = $prov->fetch();
if (!$provRow || $provRow['verification_status'] !== 'approved') {
    header('Location: dashboard_provider.php');
    exit;
}

$services = $pdo->prepare("SELECT id, title FROM services WHERE provider_id = ?");
$services->execute([$providerId]);
$services = $services->fetchAll();

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $serviceId = (int)($_POST['service_id'] ?? 0);
    $amount = (float)($_POST['amount'] ?? 0);
    $duration = (int)($_POST['duration'] ?? 7);

    if ($amount >= 100 && $duration >= 7) {
        $start = date('Y-m-d');
        $end = date('Y-m-d', strtotime("+$duration days"));
        // Create ad as pending payment first, then redirect to GCash payment page
        $pdo->prepare("INSERT INTO ads (provider_id, service_id, status, start_date, end_date, amount) VALUES (?, ?, 'pending', ?, ?, ?)")
            ->execute([$providerId, $serviceId ?: null, $start, $end, $amount]);
        $adId = (int)$pdo->lastInsertId();
        header('Location: gcash_pay.php?ad=' . $adId);
        exit;
    } else {
        $error = 'Minimum ad cost is ₱100 for 7 days.';
    }
}

$currentAd = $pdo->prepare("SELECT * FROM ads WHERE provider_id = ? AND status IN ('active','pending') ORDER BY created_at DESC LIMIT 1");
$currentAd->execute([$providerId]);
$currentAd = $currentAd->fetch();

require_once 'includes/header.php';
?>
<section style="padding: 2rem; max-width: 700px;">
    <h1 class="section-title">Promote Your Service</h1>
    
    <?php if ($success): ?><p style="color: #2ECC71; margin-bottom: 1rem;"><?= htmlspecialchars($success) ?></p><?php endif; ?>
    <?php if ($error): ?><p style="color: #e74c3c; margin-bottom: 1rem;"><?= htmlspecialchars($error) ?></p><?php endif; ?>

    <?php if ($currentAd): ?>
    <div class="card" style="padding: 1.5rem; margin-bottom: 2rem; border-left: 4px solid var(--accent);">
        <h3>Active Promotion</h3>
        <p>
            Status:
            <strong>
                <?= ($currentAd['status'] === 'active') ? 'Active' : 'Pending Payment' ?>
            </strong>
            <?php if (!empty($currentAd['end_date'])): ?>
                until <?= date('M j, Y', strtotime($currentAd['end_date'])) ?>
            <?php endif; ?>
        </p>
        <p>Amount: ₱<?= number_format($currentAd['amount']) ?></p>
        <?php if ($currentAd['status'] !== 'active'): ?>
            <a class="btn btn-primary" href="gcash_pay.php?ad=<?= (int)$currentAd['id'] ?>" style="margin-top: 0.75rem;">Pay via GCash</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="card" style="padding: 2rem;">
        <h3>Create New Ad</h3>
        <p style="color: var(--text-muted); margin-bottom: 1.5rem;">Get featured at the top of search results. Minimum ₱100 for 7 days.</p>
        <form method="POST">
            <div class="form-group">
                <label>Select Service (optional)</label>
                <select name="service_id">
                    <option value="">All my services</option>
                    <?php foreach ($services as $s): ?>
                        <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['title']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Ad Duration (days)</label>
                <select name="duration">
                    <option value="7">7 days - ₱100</option>
                    <option value="14">14 days - ₱180</option>
                    <option value="30">30 days - ₱350</option>
                </select>
            </div>
            <div class="form-group">
                <label>Amount (₱)</label>
                <input type="number" name="amount" min="100" value="100" required>
            </div>
            <button type="submit" class="btn btn-primary">Proceed to GCash Payment</button>
            <a href="dashboard_provider.php" class="btn btn-ghost" style="margin-left: 0.5rem;">Back</a>
        </form>
    </div>
</section>
<?php require_once 'includes/footer.php'; ?>
