<?php
$pageTitle = 'GCash Payment';
require_once 'config/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'provider') {
    header('Location: login.php');
    exit;
}

$pdo = getDBConnection();
$providerId = $_SESSION['provider_id'];
$adId = (int)($_GET['ad'] ?? 0);

if (!$adId) {
    header('Location: promote_service.php');
    exit;
}

// Ensure payments table exists (lightweight)
$pdo->exec("
    CREATE TABLE IF NOT EXISTS ad_payments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ad_id INT NOT NULL,
        provider_id INT NOT NULL,
        reference_no VARCHAR(64) NOT NULL,
        proof_path VARCHAR(255) DEFAULT NULL,
        status VARCHAR(32) NOT NULL DEFAULT 'submitted',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (ad_id),
        INDEX (provider_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

$adStmt = $pdo->prepare("SELECT * FROM ads WHERE id = ? AND provider_id = ? ORDER BY id DESC LIMIT 1");
$adStmt->execute([$adId, $providerId]);
$ad = $adStmt->fetch();

if (!$ad) {
    header('Location: promote_service.php');
    exit;
}

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reference = trim($_POST['reference_no'] ?? '');
    if ($reference === '') {
        $error = 'Enter your GCash reference number.';
    } else {
        $proofPath = null;
        if (!empty($_FILES['proof']['name'])) {
            $proofPath = 'uploads/payments/' . $providerId . '_' . $adId . '_' . time() . '_' . basename($_FILES['proof']['name']);
            if (!move_uploaded_file($_FILES['proof']['tmp_name'], $proofPath)) {
                $proofPath = null;
            }
        }

        $pdo->prepare("INSERT INTO ad_payments (ad_id, provider_id, reference_no, proof_path, status) VALUES (?, ?, ?, ?, 'submitted')")
            ->execute([$adId, $providerId, $reference, $proofPath]);

        // Simulated confirmation: mark ad active immediately after submission
        $pdo->prepare("UPDATE ads SET status = 'active' WHERE id = ? AND provider_id = ?")
            ->execute([$adId, $providerId]);

        $success = 'Payment submitted. Your ad is now active!';
        $ad['status'] = 'active';
    }
}

require_once 'includes/header.php';
?>
<section style="padding: 2rem; max-width: 720px; margin: 0 auto;">
    <h1 class="section-title">Pay with GCash</h1>

    <?php if ($success): ?>
        <div class="card" style="padding: 1rem; border-left: 4px solid #2ECC71; margin-bottom: 1rem;">
            <strong style="color:#2ECC71;"><?= htmlspecialchars($success) ?></strong>
        </div>
    <?php endif; ?>
    <?php if ($error): ?>
        <p style="color:#e74c3c; margin-bottom: 1rem;"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <div class="card" style="padding: 1.5rem; margin-bottom: 1rem;">
        <h3>Ad Payment Details</h3>
        <p>Amount: <strong>₱<?= number_format((float)($ad['amount'] ?? 0), 2) ?></strong></p>
        <p>Status: <strong><?= htmlspecialchars($ad['status'] ?? 'pending') ?></strong></p>
        <?php if (!empty($ad['end_date'])): ?>
            <p>End date: <?= htmlspecialchars(date('M j, Y', strtotime($ad['end_date']))) ?></p>
        <?php endif; ?>
    </div>

    <div class="card" style="padding: 1.5rem;">
        <h3>Step 1: Pay via GCash</h3>
        <p style="color: var(--text-muted);">
            Send the amount to <strong><?= htmlspecialchars(GCASH_NUMBER) ?></strong> (<?= htmlspecialchars(GCASH_ACCOUNT_NAME) ?>).
            After paying, copy the <strong>GCash reference number</strong> and submit it below.
        </p>
        <a class="btn btn-primary" href="<?= htmlspecialchars(GCASH_PAY_URL) ?>" target="_blank" rel="noopener noreferrer" style="margin-top: 0.75rem;">
            Open GCash Payment Link
        </a>

        <hr style="margin: 1.25rem 0; border: none; border-top: 1px solid var(--border-color);" />

        <h3>Step 2: Submit proof</h3>
        <form method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label>GCash Reference No.</label>
                <input type="text" name="reference_no" placeholder="e.g. 123456789012" required>
            </div>
            <div class="form-group">
                <label>Upload Screenshot (optional)</label>
                <input type="file" name="proof" accept="image/*">
            </div>
            <button type="submit" class="btn btn-primary">Submit Payment</button>
            <a href="promote_service.php" class="btn btn-ghost" style="margin-left: 0.5rem;">Back</a>
        </form>
    </div>
</section>
<?php require_once 'includes/footer.php'; ?>

