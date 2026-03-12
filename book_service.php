<?php
$pageTitle = 'Book Service';
require_once 'config/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    header('Location: login.php');
    exit;
}

$providerId = (int)($_GET['provider'] ?? 0);
$serviceId = (int)($_GET['service'] ?? 0);
$pdo = getDBConnection();

$provStmt = $pdo->prepare("SELECT p.*, u.full_name FROM providers p JOIN users u ON p.user_id = u.id WHERE p.id = ? AND p.verification_status = 'approved'");
$provStmt->execute([$providerId]);
$provider = $provStmt->fetch();

if (!$provider) {
    header('Location: index.php');
    exit;
}

$servicesStmt = $pdo->prepare("SELECT * FROM services WHERE provider_id = ?");
$servicesStmt->execute([$providerId]);
$services = $servicesStmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $serviceId = (int)($_POST['service_id'] ?? 0);
    $date = $_POST['scheduled_date'] ?? '';
    $notes = trim($_POST['notes'] ?? '');
    if ($serviceId && $date) {
        $pdo->prepare("INSERT INTO bookings (customer_id, provider_id, service_id, scheduled_date, notes) VALUES (?, ?, ?, ?, ?)")
            ->execute([$_SESSION['user_id'], $providerId, $serviceId, $date, $notes]);
        header('Location: dashboard_customer.php');
        exit;
    }
}

require_once 'includes/header.php';
?>
<section style="padding: 2rem; max-width: 600px;">
    <h1 class="section-title">Book with <?= htmlspecialchars($provider['full_name']) ?></h1>
    <?php if (empty($services)): ?>
        <div class="card" style="padding: 2rem;">
            <p style="color: var(--text-muted);">This provider has not added any services yet.</p>
            <a href="provider_profile.php?id=<?= $providerId ?>" class="btn btn-ghost">Back to Profile</a>
        </div>
    <?php else: ?>
    <form method="POST" class="card" style="padding: 2rem;">
        <div class="form-group">
            <label>Select Service</label>
            <select name="service_id" required>
                <option value="">Choose a service...</option>
                <?php foreach ($services as $s): ?>
                    <option value="<?= $s['id'] ?>" <?= $serviceId && (int)$s['id'] === $serviceId ? 'selected' : '' ?>>
                        <?= htmlspecialchars($s['title']) ?> - ₱<?= number_format($s['price_min']) ?>-<?= number_format($s['price_max']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Preferred Date</label>
            <input type="date" name="scheduled_date" required min="<?= date('Y-m-d') ?>">
        </div>
        <div class="form-group">
            <label>Notes</label>
            <textarea name="notes" placeholder="Any special requests..."></textarea>
        </div>
        <button type="submit" class="btn btn-primary">Book Now</button>
        <a href="provider_profile.php?id=<?= $providerId ?>" class="btn btn-ghost" style="margin-left: 0.5rem;">Back</a>
    </form>
    <?php endif; ?>
</section>
<?php require_once 'includes/footer.php'; ?>
