<?php
$pageTitle = 'Buy Credits';
require_once 'config/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'provider') {
    header('Location: login.php');
    exit;
}

$pdo = getDBConnection();
$providerId = $_SESSION['provider_id'];

// Get current credits
$creditsStmt = $pdo->prepare("SELECT credits FROM providers WHERE id = ?");
$creditsStmt->execute([$providerId]);
$currentCredits = (int)($creditsStmt->fetchColumn() ?: 0);

$success = '';
$error = '';

// Handle purchase form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $packageIdx = (int)($_POST['package'] ?? -1);
    $packages = CREDIT_PACKAGES;

    if ($packageIdx < 0 || $packageIdx >= count($packages)) {
        $error = 'Invalid package.';
    } else {
        $pkg = $packages[$packageIdx];
        $credits = (int)$pkg['credits'];
        $amount = (float)$pkg['price'];
        $reference = trim($_POST['reference_no'] ?? '');

        if ($reference === '') {
            $error = 'Enter your GCash reference number.';
        } else {
            $proofPath = null;
            if (!empty($_FILES['proof']['name'])) {
                $proofPath = 'uploads/payments/credits_' . $providerId . '_' . time() . '_' . basename($_FILES['proof']['name']);
                move_uploaded_file($_FILES['proof']['tmp_name'], $proofPath);
            }

            $pdo->prepare("UPDATE providers SET credits = credits + ? WHERE id = ?")->execute([$credits, $providerId]);
            $pdo->prepare("INSERT INTO credit_purchases (provider_id, credits, amount, reference_no, status) VALUES (?, ?, ?, ?, 'completed')")
                ->execute([$providerId, $credits, $amount, $reference]);

            $currentCredits += $credits;
            $success = 'Credits added! Your new balance is ' . $currentCredits . ' credits.';
        }
    }
}

require_once 'includes/header.php';
?>
<section style="padding: 2rem; max-width: 640px; margin: 0 auto;">
    <h1 class="section-title">Buy Credits</h1>

    <div class="card" style="padding: 1rem 1.5rem; margin-bottom: 1.5rem; background: linear-gradient(135deg, var(--accent) 0%, #5a9cff 100%); color: white;">
        <strong>Your balance:</strong> <?= $currentCredits ?> credits
        <p style="margin: 0.25rem 0 0; font-size: 0.9rem; opacity: 0.9;">Use credits to unlock customer contact info (<?= CREDITS_PER_UNLOCK ?> credits per unlock)</p>
    </div>

    <?php if ($success): ?>
        <div class="card" style="padding: 1rem; border-left: 4px solid #2ECC71; margin-bottom: 1rem;">
            <strong style="color:#2ECC71;"><?= htmlspecialchars($success) ?></strong>
        </div>
    <?php endif; ?>
    <?php if ($error): ?>
        <p style="color:#e74c3c; margin-bottom: 1rem;"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <div class="card" style="padding: 1.5rem;">
        <h3>Step 1: Choose a package</h3>
        <form method="POST" enctype="multipart/form-data" id="buy-form">
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 1rem; margin: 1rem 0;">
                <?php foreach (CREDIT_PACKAGES as $i => $pkg): ?>
                <label class="card" style="padding: 1rem; cursor: pointer; border: 2px solid var(--border-color); transition: all 0.2s;" onmouseover="this.style.borderColor='var(--accent)'" onmouseout="this.style.borderColor='var(--border-color)'">
                    <input type="radio" name="package" value="<?= $i ?>" required>
                    <strong><?= (int)$pkg['credits'] ?> credits</strong>
                    <p style="margin: 0.25rem 0 0; color: var(--text-muted);">₱<?= number_format($pkg['price']) ?></p>
                </label>
                <?php endforeach; ?>
            </div>

            <h3 style="margin-top: 1.5rem;">Step 2: Pay via GCash</h3>
            <p style="color: var(--text-muted);">Send payment to <strong><?= htmlspecialchars(GCASH_NUMBER) ?></strong> (<?= htmlspecialchars(GCASH_ACCOUNT_NAME) ?>)</p>
            <a class="btn btn-primary" href="<?= htmlspecialchars(GCASH_PAY_URL) ?>" target="_blank" rel="noopener" style="margin-bottom: 1rem;">Open GCash</a>

            <h3 style="margin-top: 1.5rem;">Step 3: Submit proof</h3>
            <div class="form-group">
                <label>GCash Reference No.</label>
                <input type="text" name="reference_no" placeholder="e.g. 123456789012" required>
            </div>
            <div class="form-group">
                <label>Screenshot (optional)</label>
                <input type="file" name="proof" accept="image/*">
            </div>

            <button type="submit" class="btn btn-primary">Add Credits</button>
            <a href="dashboard_provider.php" class="btn btn-ghost" style="margin-left: 0.5rem;">Back</a>
        </form>
    </div>
</section>
<?php require_once 'includes/footer.php'; ?>
