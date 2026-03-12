<?php
$pageTitle = 'Admin Panel';
require_once 'config/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

$pdo = getDBConnection();
$tab = $_GET['tab'] ?? 'providers';

// Handle approve/reject face verification
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $providerId = (int)($_POST['provider_id'] ?? 0);
    if ($providerId && in_array($action, ['approve', 'reject'])) {
        $notes = trim($_POST['notes'] ?? '');
        if ($action === 'approve') {
            $pdo->prepare("UPDATE providers SET face_verified = 1, face_verification_rejected = 0, admin_notes = ? WHERE id = ?")
                ->execute([$notes, $providerId]);
        } else {
            $pdo->prepare("UPDATE providers SET face_verification_rejected = 1, admin_notes = ? WHERE id = ?")
                ->execute([$notes, $providerId]);
        }
    }
}

// Pending = has submitted selfie+ID but not yet face_verified
$pendingProviders = $pdo->query("
    SELECT p.*, u.full_name, u.email, u.phone
    FROM providers p
    JOIN users u ON p.user_id = u.id
    WHERE p.selfie_path IS NOT NULL AND p.selfie_path != ''
    AND p.id_image_path IS NOT NULL AND p.id_image_path != ''
    AND p.face_verified = 0 AND (p.face_verification_rejected = 0 OR p.face_verification_rejected IS NULL)
    ORDER BY p.created_at ASC
")->fetchAll();

$allProviders = $pdo->query("
    SELECT p.*, u.full_name, u.email
    FROM providers p
    JOIN users u ON p.user_id = u.id
    ORDER BY p.created_at DESC
")->fetchAll();

$stats = [
    'users' => $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(),
    'providers' => $pdo->query("SELECT COUNT(*) FROM providers")->fetchColumn(),
    'pending' => $pdo->query("SELECT COUNT(*) FROM providers WHERE selfie_path IS NOT NULL AND id_image_path IS NOT NULL AND face_verified = 0")->fetchColumn(),
    'ads' => $pdo->query("SELECT COUNT(*) FROM ads WHERE status = 'active'")->fetchColumn(),
];

require_once 'includes/header.php';
?>
<section style="padding: 2rem;">
    <h1 class="section-title">Admin Panel</h1>
    
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1rem; margin-bottom: 2rem;">
        <div class="card" style="padding: 1rem;">
            <strong><?= $stats['users'] ?></strong>
            <p style="color: var(--text-muted); font-size: 0.9rem;">Total Users</p>
        </div>
        <div class="card" style="padding: 1rem;">
            <strong><?= $stats['providers'] ?></strong>
            <p style="color: var(--text-muted); font-size: 0.9rem;">Providers</p>
        </div>
        <div class="card" style="padding: 1rem;">
            <strong><?= $stats['pending'] ?></strong>
            <p style="color: var(--text-muted); font-size: 0.9rem;">Pending Approval</p>
        </div>
        <div class="card" style="padding: 1rem;">
            <strong><?= $stats['ads'] ?></strong>
            <p style="color: var(--text-muted); font-size: 0.9rem;">Active Ads</p>
        </div>
    </div>

    <div style="display: flex; gap: 1rem; margin-bottom: 1.5rem;">
        <a href="?tab=providers" class="btn <?= $tab === 'providers' ? 'btn-primary' : 'btn-ghost' ?>">Pending Verifications</a>
        <a href="?tab=all" class="btn <?= $tab === 'all' ? 'btn-primary' : 'btn-ghost' ?>">All Providers</a>
    </div>

    <?php if ($tab === 'providers'): ?>
    <h2 class="section-title">Pending Face Verifications</h2>
    <?php if (empty($pendingProviders)): ?>
        <p style="color: var(--text-muted);">No pending verifications.</p>
    <?php else: ?>
    <div class="provider-grid">
        <?php foreach ($pendingProviders as $p): ?>
        <div class="card" style="padding: 1.5rem;">
            <h3><?= htmlspecialchars($p['full_name']) ?></h3>
            <p><?= htmlspecialchars($p['email']) ?> | <?= htmlspecialchars($p['phone']) ?></p>
            <p><?= htmlspecialchars($p['city']) ?>, <?= htmlspecialchars($p['barangay']) ?></p>
            <?php if ($p['selfie_path']): ?><p><a href="<?= htmlspecialchars($p['selfie_path']) ?>" target="_blank">View Selfie</a></p><?php endif; ?>
            <?php if ($p['id_image_path']): ?><p><a href="<?= htmlspecialchars($p['id_image_path']) ?>" target="_blank">View ID</a></p><?php endif; ?>
            <form method="POST" style="margin-top: 1rem;">
                <input type="hidden" name="provider_id" value="<?= $p['id'] ?>">
                <div class="form-group">
                    <label>Notes (optional)</label>
                    <textarea name="notes" rows="2"><?= htmlspecialchars($p['admin_notes'] ?? '') ?></textarea>
                </div>
                <button type="submit" name="action" value="approve" class="btn btn-primary">Approve</button>
                <button type="submit" name="action" value="reject" class="btn btn-ghost" style="margin-left: 0.5rem;">Reject</button>
            </form>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <?php else: ?>
    <h2 class="section-title">All Providers</h2>
    <div style="overflow-x: auto;">
        <table style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="border-bottom: 2px solid var(--border-color);">
                    <th style="padding: 0.75rem; text-align: left;">Name</th>
                    <th style="padding: 0.75rem; text-align: left;">Email</th>
                    <th style="padding: 0.75rem; text-align: left;">Location</th>
                    <th style="padding: 0.75rem; text-align: left;">Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($allProviders as $p): ?>
                <tr style="border-bottom: 1px solid var(--border-color);">
                    <td style="padding: 0.75rem;"><?= htmlspecialchars($p['full_name']) ?></td>
                    <td style="padding: 0.75rem;"><?= htmlspecialchars($p['email']) ?></td>
                    <td style="padding: 0.75rem;"><?= htmlspecialchars($p['city']) ?>, <?= htmlspecialchars($p['barangay']) ?></td>
                    <td style="padding: 0.75rem;"><?= !empty($p['face_verified']) ? 'Verified ✓' : 'Unverified' ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</section>
<?php require_once 'includes/footer.php'; ?>
