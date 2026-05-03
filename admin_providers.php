<?php
$pageTitle = 'Admin Providers';
require_once 'config/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

$pdo = getDBConnection();
$tab = $_GET['tab'] ?? 'pending';

function providerVerificationBadge(array $provider): array
{
    $status = strtolower((string)($provider['verification_status'] ?? 'pending'));
    if ($status === 'approved' || !empty($provider['face_verified'])) {
        return ['class' => 'verified', 'label' => 'Approved'];
    }
    if ($status === 'rejected' || !empty($provider['face_verification_rejected'])) {
        return ['class' => 'banned-user', 'label' => 'Rejected'];
    }
    return ['class' => 'suspended-user', 'label' => 'Pending'];
}

// Handle admin actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $providerId = (int)($_POST['provider_id'] ?? 0);

    if ($providerId > 0 && in_array($action, ['approve', 'reject'], true)) {
        $notes = trim((string)($_POST['notes'] ?? ''));
        if ($action === 'approve') {
            $pdo->prepare("UPDATE providers SET face_verified = 1, face_verification_rejected = 0, verification_status = 'approved', admin_notes = ? WHERE id = ?")
                ->execute([$notes, $providerId]);
        } else {
            $pdo->prepare("UPDATE providers SET face_verified = 0, face_verification_rejected = 1, verification_status = 'rejected', admin_notes = ? WHERE id = ?")
                ->execute([$notes, $providerId]);
        }
    }
    if ($action === 'delete_provider' && $providerId > 0) {
        if ($providerId) {
            try {
                $u = $pdo->prepare("SELECT user_id FROM providers WHERE id = ? LIMIT 1");
                $u->execute([$providerId]);
                $userId = (int)($u->fetchColumn() ?: 0);
                if ($userId) {
                    $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$userId]);
                } else {
                    $pdo->prepare("DELETE FROM providers WHERE id = ?")->execute([$providerId]);
                }
            } catch (Throwable $e) {
                $pdo->prepare("DELETE FROM providers WHERE id = ?")->execute([$providerId]);
            }
        }
    }

    if ($action === 'add_category') {
        $categoryName = trim((string)($_POST['category_name'] ?? ''));
        if ($categoryName !== '') {
            $pdo->prepare("INSERT INTO service_categories (name) VALUES (?)")->execute([$categoryName]);
        }
    } elseif ($action === 'rename_category') {
        $categoryId = (int)($_POST['category_id'] ?? 0);
        $categoryName = trim((string)($_POST['category_name'] ?? ''));
        if ($categoryId > 0 && $categoryName !== '') {
            $pdo->prepare("UPDATE service_categories SET name = ? WHERE id = ?")->execute([$categoryName, $categoryId]);
        }
    } elseif ($action === 'delete_category') {
        $categoryId = (int)($_POST['category_id'] ?? 0);
        if ($categoryId > 0) {
            $inUseStmt = $pdo->prepare("SELECT COUNT(*) FROM services WHERE category_id = ?");
            $inUseStmt->execute([$categoryId]);
            $inUse = (int)$inUseStmt->fetchColumn();
            if ($inUse === 0) {
                $pdo->prepare("DELETE FROM service_categories WHERE id = ?")->execute([$categoryId]);
            }
        }
    }
}

// Pending verifications
$pendingProviders = $pdo->query("
    SELECT p.*, u.full_name, u.email, u.phone
    FROM providers p
    JOIN users u ON p.user_id = u.id
    WHERE p.verification_status = 'pending'
    ORDER BY p.created_at ASC
")->fetchAll();

// All providers with filters
$providerSearch = trim((string)($_GET['q'] ?? ''));
$providerCategory = (int)($_GET['category'] ?? 0);
$providerStatus = trim((string)($_GET['status'] ?? ''));

$providerCategories = $pdo->query("SELECT id, name FROM service_categories ORDER BY name ASC")->fetchAll();

$allSql = "
    SELECT p.*, u.full_name, u.email,
           (
               SELECT s.title
               FROM services s
               WHERE s.provider_id = p.id
               ORDER BY s.created_at ASC
               LIMIT 1
           ) AS primary_service
           ,
           (
               SELECT AVG(b.rating)
               FROM bookings b
               WHERE b.provider_id = p.id AND b.rating IS NOT NULL
           ) AS avg_rating,
           (
               SELECT COUNT(*)
               FROM bookings b2
               WHERE b2.provider_id = p.id AND b2.rating IS NOT NULL
           ) AS review_count
    FROM providers p
    JOIN users u ON p.user_id = u.id
    WHERE 1=1
";
$allParams = [];

if ($providerSearch !== '') {
    $allSql .= " AND (u.full_name LIKE ? OR p.city LIKE ? OR p.barangay LIKE ?)";
    $kw = '%' . $providerSearch . '%';
    $allParams[] = $kw;
    $allParams[] = $kw;
    $allParams[] = $kw;
}

if ($providerStatus === 'verified') {
    $allSql .= " AND p.face_verified = 1";
} elseif ($providerStatus === 'unverified') {
    $allSql .= " AND p.face_verified = 0";
}

if ($providerCategory > 0) {
    $allSql .= " AND EXISTS (
        SELECT 1 FROM services sx
        WHERE sx.provider_id = p.id AND sx.category_id = ?
    )";
    $allParams[] = $providerCategory;
}

$allSql .= " ORDER BY p.created_at DESC";
$allStmt = $pdo->prepare($allSql);
$allStmt->execute($allParams);
$allProviders = $allStmt->fetchAll();

$verificationStats = [
    'pending' => (int)$pdo->query("SELECT COUNT(*) FROM providers WHERE verification_status = 'pending'")->fetchColumn(),
    'approved' => (int)$pdo->query("SELECT COUNT(*) FROM providers WHERE verification_status = 'approved'")->fetchColumn(),
    'rejected' => (int)$pdo->query("SELECT COUNT(*) FROM providers WHERE verification_status = 'rejected'")->fetchColumn(),
];

$categoryUsage = $pdo->query("
    SELECT sc.id, sc.name, COUNT(s.id) AS service_count
    FROM service_categories sc
    LEFT JOIN services s ON s.category_id = sc.id
    GROUP BY sc.id, sc.name
    ORDER BY sc.name ASC
")->fetchAll();

require_once 'includes/header.php';
?>
<section class="admin-shell">
    <aside class="admin-side">
        <div class="admin-side-brand">ServiceLink</div>
        <a href="admin_dashboard.php" class="admin-nav-link">Dashboard</a>
        <a href="admin_providers.php" class="admin-nav-link active">Providers</a>
        <a href="admin_users.php" class="admin-nav-link">Users</a>
        <a href="admin_bookings.php" class="admin-nav-link">Bookings</a>
        <a href="admin_reports.php" class="admin-nav-link">Reports</a>
        <a href="admin_reviews.php" class="admin-nav-link">Reviews</a>
        <a href="admin_transactions.php" class="admin-nav-link">Transactions</a>
        <a href="admin_settings.php" class="admin-nav-link">Settings</a>
        <a href="logout.php" class="admin-nav-link">Log out</a>
        <div class="admin-side-user">Admin</div>
    </aside>

    <div class="admin-main">
        <div class="admin-topbar">
            <h1>Providers</h1>
            <div class="admin-user-chip">Admin</div>
        </div>

        <div class="admin-grid">
            <div class="card admin-panel-card">
                <div class="admin-card-head">
                    <h2><?= $tab === 'pending' ? 'Pending Face Verifications' : ($tab === 'categories' ? 'Service Category Management' : 'All Providers') ?></h2>
                    <div class="admin-actions">
                        <a href="?tab=pending" class="btn <?= $tab === 'pending' ? 'btn-primary' : 'btn-ghost' ?>">Pending</a>
                        <a href="?tab=all" class="btn <?= $tab === 'all' ? 'btn-primary' : 'btn-ghost' ?>">All Providers</a>
                        <a href="?tab=categories" class="btn <?= $tab === 'categories' ? 'btn-primary' : 'btn-ghost' ?>">Categories</a>
                    </div>
                </div>

                <div class="admin-metric-grid" style="margin-bottom: 0.85rem;">
                    <div class="admin-metric-card">
                        <p class="label">Pending Verification</p>
                        <h3><?= number_format($verificationStats['pending']) ?></h3>
                    </div>
                    <div class="admin-metric-card">
                        <p class="label">Approved Providers</p>
                        <h3><?= number_format($verificationStats['approved']) ?></h3>
                    </div>
                    <div class="admin-metric-card">
                        <p class="label">Rejected Providers</p>
                        <h3><?= number_format($verificationStats['rejected']) ?></h3>
                    </div>
                </div>

                <?php if ($tab === 'pending'): ?>
                    <?php if (empty($pendingProviders)): ?>
                        <p style="color: var(--text-muted);">No pending verifications.</p>
                    <?php else: ?>
                        <div class="admin-verify-grid">
                            <?php foreach ($pendingProviders as $p): ?>
                            <div class="admin-verify-card">
                                <h3><?= htmlspecialchars($p['full_name']) ?></h3>
                                <p><?= htmlspecialchars($p['email']) ?> | <?= htmlspecialchars($p['phone']) ?></p>
                                <p><?= htmlspecialchars($p['city']) ?>, <?= htmlspecialchars($p['barangay']) ?></p>
                                <div class="admin-doc-links">
                                    <?php if ($p['reference_photo_path']): ?><a href="<?= htmlspecialchars($p['reference_photo_path']) ?>" target="_blank">Reference</a><?php endif; ?>
                                    <?php if ($p['selfie_path']): ?><a href="<?= htmlspecialchars($p['selfie_path']) ?>" target="_blank">Selfie</a><?php endif; ?>
                                    <?php if ($p['id_image_path']): ?><a href="<?= htmlspecialchars($p['id_image_path']) ?>" target="_blank">ID</a><?php endif; ?>
                                    <?php if ($p['business_permit_path']): ?><a href="<?= htmlspecialchars($p['business_permit_path']) ?>" target="_blank">Permit</a><?php endif; ?>
                                </div>
                                <form method="POST" style="margin-top: 0.9rem;">
                                    <input type="hidden" name="provider_id" value="<?= $p['id'] ?>">
                                    <div class="form-group">
                                        <label>Notes</label>
                                        <textarea name="notes" rows="2"><?= htmlspecialchars($p['admin_notes'] ?? '') ?></textarea>
                                    </div>
                                    <button type="submit" name="action" value="approve" class="btn btn-primary">Approve</button>
                                    <button type="submit" name="action" value="reject" class="btn btn-ghost">Reject</button>
                                </form>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                <?php elseif ($tab === 'all'): ?>
                    <form method="GET" class="admin-provider-filters">
                        <input type="hidden" name="tab" value="all">
                        <input type="text" name="q" placeholder="Search provider or location" value="<?= htmlspecialchars($providerSearch) ?>">
                        <select name="category">
                            <option value="0">All Categories</option>
                            <?php foreach ($providerCategories as $cat): ?>
                                <option value="<?= (int)$cat['id'] ?>" <?= $providerCategory === (int)$cat['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cat['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <select name="status">
                            <option value="">All Status</option>
                            <option value="verified" <?= $providerStatus === 'verified' ? 'selected' : '' ?>>Verified</option>
                            <option value="unverified" <?= $providerStatus === 'unverified' ? 'selected' : '' ?>>Unverified</option>
                        </select>
                        <button type="submit" class="btn btn-ghost">Apply</button>
                    </form>
                    <div class="admin-table-wrap">
                        <table class="admin-table admin-provider-table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Service</th>
                                    <th>Location</th>
                                    <th>Credit Score</th>
                                    <th>Ratings</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($allProviders as $p): ?>
                                <tr>
                                    <td>
                                        <div class="provider-row-name">
                                            <span class="provider-row-avatar">
                                                <?php if (!empty($p['profile_image_path'])): ?>
                                                    <img src="<?= htmlspecialchars($p['profile_image_path']) ?>" alt="<?= htmlspecialchars($p['full_name']) ?>">
                                                <?php else: ?>
                                                    <?= strtoupper(substr((string)$p['full_name'], 0, 1)) ?>
                                                <?php endif; ?>
                                            </span>
                                            <div>
                                                <strong><?= htmlspecialchars($p['full_name']) ?></strong>
                                                <div class="small-muted"><?= htmlspecialchars($p['email']) ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?= htmlspecialchars($p['primary_service'] ?: 'No service yet') ?></td>
                                    <td><?= htmlspecialchars($p['city']) ?>, <?= htmlspecialchars($p['barangay']) ?></td>
                                    <td><span class="credit-pill"><?= number_format((int)($p['credits'] ?? 0)) ?></span></td>
                                    <td>
                                        <?php $avg = (float)($p['avg_rating'] ?? 0); ?>
                                        <span class="status-pill active-user">
                                            <?= $avg > 0 ? number_format($avg, 1) . '/5' : 'No rating' ?>
                                        </span>
                                        <div class="small-muted"><?= number_format((int)($p['review_count'] ?? 0)) ?> review(s)</div>
                                    </td>
                                    <td>
                                        <?php $v = providerVerificationBadge($p); ?>
                                        <span class="status-pill <?= htmlspecialchars($v['class']) ?>">
                                            <?= htmlspecialchars($v['label']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="admin-inline-actions">
                                            <button
                                                type="button"
                                                class="btn btn-ghost btn-provider-docs"
                                                data-docs='<?= htmlspecialchars(json_encode([
                                                    'name' => (string)$p['full_name'],
                                                    'reference' => (string)($p['reference_photo_path'] ?? ''),
                                                    'selfie' => (string)($p['selfie_path'] ?? ''),
                                                    'id' => (string)($p['id_image_path'] ?? ''),
                                                    'permit' => (string)($p['business_permit_path'] ?? '')
                                                ]), ENT_QUOTES, "UTF-8") ?>'
                                            >Docs</button>
                                            <?php if (($p['verification_status'] ?? '') !== 'approved'): ?>
                                                <form method="POST">
                                                    <input type="hidden" name="provider_id" value="<?= (int)$p['id'] ?>">
                                                    <button type="submit" name="action" value="approve" class="btn btn-ghost">Approve</button>
                                                </form>
                                            <?php endif; ?>
                                            <?php if (($p['verification_status'] ?? '') !== 'rejected'): ?>
                                                <form method="POST">
                                                    <input type="hidden" name="provider_id" value="<?= (int)$p['id'] ?>">
                                                    <button type="submit" name="action" value="reject" class="btn btn-ghost">Reject</button>
                                                </form>
                                            <?php endif; ?>
                                            <form method="POST" onsubmit="return confirm('Delete this provider? This cannot be undone.');">
                                                <input type="hidden" name="provider_id" value="<?= (int)$p['id'] ?>">
                                                <button type="submit" name="action" value="delete_provider" class="btn btn-ghost">Delete</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <p class="small-muted" style="margin-top: 0.6rem;">Showing <?= count($allProviders) ?> provider entries</p>
                <?php else: ?>
                    <form method="POST" class="admin-provider-filters">
                        <input type="text" name="category_name" placeholder="New category name" required>
                        <button type="submit" name="action" value="add_category" class="btn btn-primary">Add Category</button>
                    </form>
                    <div class="admin-table-wrap">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Category</th>
                                    <th>Services Using</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($categoryUsage as $cat): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($cat['name']) ?></td>
                                        <td><?= number_format((int)$cat['service_count']) ?></td>
                                        <td>
                                            <div class="admin-inline-actions">
                                                <form method="POST" class="admin-inline-actions">
                                                    <input type="hidden" name="category_id" value="<?= (int)$cat['id'] ?>">
                                                    <input type="text" name="category_name" value="<?= htmlspecialchars($cat['name']) ?>" required>
                                                    <button type="submit" name="action" value="rename_category" class="btn btn-ghost">Rename</button>
                                                </form>
                                                <form method="POST" onsubmit="return confirm('Delete this category? Only empty categories can be deleted.');">
                                                    <input type="hidden" name="category_id" value="<?= (int)$cat['id'] ?>">
                                                    <button type="submit" name="action" value="delete_category" class="btn btn-ghost" <?= (int)$cat['service_count'] > 0 ? 'disabled title="Category has services"' : '' ?>>Delete</button>
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
    </div>
</section>

<script>
(function () {
    const modal = document.getElementById('providerDocsModal');
    const modalBody = document.getElementById('providerDocsBody');
    const modalTitle = document.getElementById('providerDocsTitle');
    const closeBtn = document.getElementById('closeProviderDocsModal');
    const buttons = document.querySelectorAll('.btn-provider-docs');
    if (!modal || !modalBody || !modalTitle || !closeBtn || buttons.length === 0) return;

    function card(label, src) {
        if (!src) return '<div class="admin-modal-row"><strong>' + label + '</strong><span>No file uploaded</span></div>';
        return '<div class="admin-modal-doc"><strong>' + label + '</strong><a href="' + src + '" target="_blank" rel="noopener">Open File</a><img src="' + src + '" alt="' + label + '"></div>';
    }

    buttons.forEach(function (btn) {
        btn.addEventListener('click', function () {
            const payload = btn.getAttribute('data-docs');
            if (!payload) return;
            let docs;
            try { docs = JSON.parse(payload); } catch (e) { return; }
            modalTitle.textContent = (docs.name || 'Provider') + ' Documents';
            modalBody.innerHTML =
                '<div class="admin-modal-doc-grid">' +
                    card('Reference Photo', docs.reference || '') +
                    card('Live Selfie', docs.selfie || '') +
                    card('Valid ID', docs.id || '') +
                    card('Business Permit', docs.permit || '') +
                '</div>';
            modal.hidden = false;
            document.body.style.overflow = 'hidden';
        });
    });

    function closeModal() {
        modal.hidden = true;
        document.body.style.overflow = '';
    }
    closeBtn.addEventListener('click', closeModal);
    modal.addEventListener('click', function (e) {
        if (e.target === modal) closeModal();
    });
})();
</script>
<?php require_once 'includes/footer.php'; ?>
