<?php
$pageTitle = 'Face Verification';
require_once 'config/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'provider') {
    header('Location: login.php');
    exit;
}

$providerId = $_SESSION['provider_id'];
$pdo = getDBConnection();

$stmt = $pdo->prepare("SELECT * FROM providers WHERE id = ? AND user_id = ?");
$stmt->execute([$providerId, $_SESSION['user_id']]);
$provider = $stmt->fetch();

if (!$provider) {
    header('Location: dashboard_provider.php');
    exit;
}

$error = '';
$success = '';

if ($provider['face_verified']) {
    $success = 'You already have the Verified badge!';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$provider['face_verified']) {
    $selfiePath = $provider['selfie_path'] ?? '';
    $idPath = $provider['id_image_path'] ?? '';

    if (!empty($_FILES['selfie']['name'])) {
        $selfiePath = 'uploads/selfies/' . $provider['user_id'] . '_' . time() . '_' . basename($_FILES['selfie']['name']);
        if (move_uploaded_file($_FILES['selfie']['tmp_name'], $selfiePath)) {
            // ok
        } else {
            $selfiePath = $provider['selfie_path'] ?? '';
        }
    }
    if (!empty($_FILES['id_image']['name'])) {
        $idPath = 'uploads/ids/' . $provider['user_id'] . '_' . time() . '_' . basename($_FILES['id_image']['name']);
        if (move_uploaded_file($_FILES['id_image']['tmp_name'], $idPath)) {
            // ok
        } else {
            $idPath = $provider['id_image_path'] ?? '';
        }
    }

    if (empty($selfiePath) || empty($idPath)) {
        $error = 'Please upload both a selfie and your ID image.';
    } else {
        $pdo->prepare("UPDATE providers SET selfie_path = ?, id_image_path = ?, face_verification_rejected = 0 WHERE id = ?")
            ->execute([$selfiePath, $idPath, $providerId]);
        $success = 'Face verification submitted! Our team will review your documents. You\'ll get the Verified badge once approved.';
        $provider['selfie_path'] = $selfiePath;
        $provider['id_image_path'] = $idPath;
    }
}

$statusMessage = '';
if ($provider['face_verified']) {
    $statusMessage = 'verified';
} elseif (($provider['selfie_path'] || $provider['id_image_path']) && !$provider['face_verified'] && empty($provider['face_verification_rejected'])) {
    $statusMessage = 'pending';
} elseif (!empty($provider['face_verification_rejected'])) {
    $statusMessage = 'rejected';
}

require_once 'includes/header.php';
?>
<section style="padding: 2rem; max-width: 600px; margin: 0 auto;">
    <h1 class="section-title">Face Verification</h1>
    <p style="color: var(--text-muted); margin-bottom: 2rem;">Get the <strong>Verified</strong> badge to build trust with customers. Upload a selfie and valid ID for verification.</p>

    <?php if ($success): ?>
    <div class="card" style="padding: 1.5rem; margin-bottom: 2rem; border-left: 4px solid #2ECC71;">
        <p style="color: #2ECC71; margin: 0;"><?= htmlspecialchars($success) ?></p>
    </div>
    <?php endif; ?>
    <?php if ($error): ?>
    <p style="color: #e74c3c; margin-bottom: 1rem;"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <?php if ($statusMessage === 'verified'): ?>
    <div class="card" style="padding: 2rem; text-align: center;">
        <div style="font-size: 3rem; margin-bottom: 1rem;">✓</div>
        <h2 style="color: #2ECC71;">You're Verified!</h2>
        <p style="color: var(--text-muted);">Your Verified badge is displayed on your profile.</p>
        <a href="dashboard_provider.php" class="btn btn-primary" style="margin-top: 1rem;">Back to Dashboard</a>
    </div>
    <?php elseif ($statusMessage === 'pending'): ?>
    <div class="card" style="padding: 2rem; text-align: center;">
        <div style="font-size: 3rem; margin-bottom: 1rem;">⏳</div>
        <h2>Under Review</h2>
        <p style="color: var(--text-muted);">Your documents are being reviewed. We'll notify you once approved.</p>
        <a href="dashboard_provider.php" class="btn btn-ghost" style="margin-top: 1rem;">Back to Dashboard</a>
    </div>
    <?php elseif ($statusMessage === 'rejected'): ?>
    <div class="card" style="padding: 2rem;">
        <h2>Verification Rejected</h2>
        <p style="color: var(--text-muted);">Your submission was not approved. You can try again with clearer images.</p>
        <?php if (!empty($provider['admin_notes'])): ?>
        <p style="margin-top: 1rem;"><strong>Note:</strong> <?= htmlspecialchars($provider['admin_notes']) ?></p>
        <?php endif; ?>
        <form method="POST" enctype="multipart/form-data" style="margin-top: 1.5rem;">
            <div class="form-group">
                <label>Selfie</label>
                <input type="file" name="selfie" accept="image/*" required>
            </div>
            <div class="form-group">
                <label>ID Image</label>
                <input type="file" name="id_image" accept="image/*" required>
            </div>
            <button type="submit" class="btn btn-primary">Submit Again</button>
        </form>
    </div>
    <?php else: ?>
    <div class="card" style="padding: 2rem;">
        <h3>Submit for Verification</h3>
        <p style="color: var(--text-muted); margin-bottom: 1.5rem;">Upload a clear selfie and a valid government-issued ID (driver's license, passport, etc.)</p>
        <form method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label>Selfie</label>
                <input type="file" name="selfie" accept="image/*" required>
                <small style="color: var(--text-muted);">Your face should be clearly visible</small>
            </div>
            <div class="form-group">
                <label>ID Image</label>
                <input type="file" name="id_image" accept="image/*" required>
                <small style="color: var(--text-muted);">Government-issued ID (front side)</small>
            </div>
            <button type="submit" class="btn btn-primary">Submit for Verification</button>
            <a href="dashboard_provider.php" class="btn btn-ghost" style="margin-left: 0.5rem;">Cancel</a>
        </form>
    </div>
    <?php endif; ?>

    <p style="text-align: center; margin-top: 2rem;">
        <a href="dashboard_provider.php">← Back to Dashboard</a>
    </p>
</section>
<?php require_once 'includes/footer.php'; ?>
