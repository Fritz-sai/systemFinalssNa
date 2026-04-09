<?php
$pageTitle = 'Admin Panel';
require_once 'config/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

$pdo = getDBConnection();
$rawTab = $_GET['tab'] ?? 'providers';
$focusSection = '';
if ($rawTab === 'users') {
    $tab = 'all';
    $focusSection = 'customers';
} elseif ($rawTab === 'transactions') {
    $tab = 'all';
    $focusSection = 'transactions';
} else {
    $tab = $rawTab;
}
$pageHeading = $rawTab === 'users' ? 'Users' : ($rawTab === 'transactions' ? 'Transactions' : 'Dashboard');

// Handle approve/reject face verification
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $providerId = (int)($_POST['provider_id'] ?? 0);
    if ($providerId && in_array($action, ['approve', 'reject'])) {
        $notes = trim($_POST['notes'] ?? '');
        if ($action === 'approve') {
            $pdo->prepare("UPDATE providers SET face_verified = 1, face_verification_rejected = 0, verification_status = 'approved', admin_notes = ? WHERE id = ?")
                ->execute([$notes, $providerId]);
        } else {
            $pdo->prepare("UPDATE providers SET face_verification_rejected = 1, verification_status = 'rejected', admin_notes = ? WHERE id = ?")
                ->execute([$notes, $providerId]);
        }
    }
}

// Handle delete actions (provider or user)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'delete_provider') {
        $providerId = (int)($_POST['provider_id'] ?? 0);
        if ($providerId) {
            // Find the linked user_id and delete the user (will cascade to providers via FK)
            try {
                $u = $pdo->prepare("SELECT user_id FROM providers WHERE id = ? LIMIT 1");
                $u->execute([$providerId]);
                $userId = (int)($u->fetchColumn() ?: 0);
                if ($userId) {
                    $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$userId]);
                } else {
                    // Fallback: delete provider row if no linked user found
                    $pdo->prepare("DELETE FROM providers WHERE id = ?")->execute([$providerId]);
                }
            } catch (Throwable $e) {
                // On error, attempt deleting provider directly
                $pdo->prepare("DELETE FROM providers WHERE id = ?")->execute([$providerId]);
            }
        }
    }
    if ($action === 'delete_user') {
        $userId = (int)($_POST['user_id'] ?? 0);
        if ($userId) {
            $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$userId]);
        }
    }
}

// Pending = has submitted all documents but not yet approved
$pendingProviders = $pdo->query("
    SELECT p.*, u.full_name, u.email, u.phone
    FROM providers p
    JOIN users u ON p.user_id = u.id
    WHERE p.verification_status = 'pending'
    ORDER BY p.created_at ASC
")->fetchAll();

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

// Customers list for admin management
$userSearch = trim((string)($_GET['user_q'] ?? ''));
$userStatus = trim((string)($_GET['user_status'] ?? ''));
$customersSql = "
    SELECT id, email, full_name, phone, created_at, email_verified
    FROM users
    WHERE role = 'customer'
";
$customerParams = [];
if ($userSearch !== '') {
    $customersSql .= " AND (full_name LIKE ? OR email LIKE ? OR phone LIKE ?)";
    $kw = '%' . $userSearch . '%';
    $customerParams[] = $kw;
    $customerParams[] = $kw;
    $customerParams[] = $kw;
}
if ($userStatus === 'active') {
    $customersSql .= " AND email_verified = 1";
} elseif ($userStatus === 'inactive') {
    $customersSql .= " AND email_verified = 0";
}
$customersSql .= " ORDER BY created_at DESC";
$customersStmt = $pdo->prepare($customersSql);
$customersStmt->execute($customerParams);
$customers = $customersStmt->fetchAll();

$stats = [
    'users' => $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(),
    'providers' => $pdo->query("SELECT COUNT(*) FROM providers")->fetchColumn(),
    'pending' => $pdo->query("SELECT COUNT(*) FROM providers WHERE verification_status = 'pending'")->fetchColumn(),
    'ads' => $pdo->query("SELECT COUNT(*) FROM ads WHERE status = 'active'")->fetchColumn(),
];

$latestPurchases = $pdo->query("
    SELECT cp.created_at, cp.amount, cp.status, u.full_name
    FROM credit_purchases cp
    JOIN providers p ON p.id = cp.provider_id
    JOIN users u ON u.id = p.user_id
    ORDER BY cp.created_at DESC
    LIMIT 8
")->fetchAll();

$topProviders = $pdo->query("
    SELECT u.full_name, COALESCE(SUM(cp.amount), 0) AS total_earnings, COUNT(cp.id) AS tx_count
    FROM providers p
    JOIN users u ON u.id = p.user_id
    LEFT JOIN credit_purchases cp ON cp.provider_id = p.id
    GROUP BY p.id, u.full_name
    ORDER BY total_earnings DESC, tx_count DESC
    LIMIT 5
")->fetchAll();

require_once 'includes/header.php';
?>
<section class="admin-shell">
    <aside class="admin-side">
        <div class="admin-side-brand">ServiceLink</div>
        <a href="admin_panel.php?tab=providers" class="admin-nav-link <?= $rawTab === 'providers' ? 'active' : '' ?>">Dashboard</a>
        <a href="admin_panel.php?tab=all" class="admin-nav-link <?= $rawTab === 'all' ? 'active' : '' ?>">Providers</a>
        <a href="admin_panel.php?tab=users" class="admin-nav-link <?= $rawTab === 'users' ? 'active' : '' ?>">Users</a>
        <a href="admin_panel.php?tab=transactions" class="admin-nav-link <?= $rawTab === 'transactions' ? 'active' : '' ?>">Transactions</a>
        <a href="logout.php" class="admin-nav-link">Log out</a>
        <div class="admin-side-user">Admin</div>
    </aside>

    <div class="admin-main">
        <div class="admin-topbar">
            <h1><?= htmlspecialchars($pageHeading) ?></h1>
            <div class="admin-user-chip">Admin</div>
        </div>

        <div class="admin-metric-grid">
            <div class="admin-metric-card">
                <p class="label">Total Users</p>
                <h3><?= number_format((int)$stats['users']) ?></h3>
            </div>
            <div class="admin-metric-card">
                <p class="label">Providers</p>
                <h3><?= number_format((int)$stats['providers']) ?></h3>
            </div>
            <div class="admin-metric-card">
                <p class="label">Pending</p>
                <h3><?= number_format((int)$stats['pending']) ?></h3>
            </div>
            <div class="admin-metric-card">
                <p class="label">Active Ads</p>
                <h3><?= number_format((int)$stats['ads']) ?></h3>
            </div>
        </div>

        <div class="admin-grid">
            <div class="card admin-panel-card">
                <div class="admin-card-head">
                    <h2><?= $tab === 'providers' ? 'Pending Face Verifications' : 'All Providers' ?></h2>
                    <div class="admin-actions">
                        <a href="?tab=providers" class="btn <?= $tab === 'providers' ? 'btn-primary' : 'btn-ghost' ?>">Pending</a>
                        <a href="?tab=all" class="btn <?= $tab === 'all' ? 'btn-primary' : 'btn-ghost' ?>">All Providers</a>
                    </div>
                </div>

                <?php if ($tab === 'providers'): ?>
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
                <?php else: ?>
                    <div class="admin-provider-toolbar">
                        <form method="GET" class="admin-provider-search-form">
                            <input type="hidden" name="tab" value="all">
                            <input type="text" name="q" value="<?= htmlspecialchars($providerSearch) ?>" placeholder="Search providers..." class="admin-provider-search">
                            <button type="submit" class="btn btn-primary">Search</button>
                        </form>
                        <a href="register.php" class="btn btn-primary">+ Add Provider</a>
                    </div>
                    <form method="GET" class="admin-provider-filters">
                        <input type="hidden" name="tab" value="all">
                        <input type="hidden" name="q" value="<?= htmlspecialchars($providerSearch) ?>">
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
                                        <span class="status-pill <?= !empty($p['face_verified']) ? 'verified' : 'unverified' ?>">
                                            <?= !empty($p['face_verified']) ? 'Verified' : 'Unverified' ?>
                                        </span>
                                    </td>
                                    <td>
                                        <form method="POST" onsubmit="return confirm('Delete this provider? This cannot be undone.');">
                                            <input type="hidden" name="provider_id" value="<?= $p['id'] ?>">
                                            <button type="submit" name="action" value="delete_provider" class="btn btn-ghost">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <p class="small-muted" style="margin-top: 0.6rem;">Showing <?= count($allProviders) ?> provider entries</p>
                <?php endif; ?>
            </div>

            <div class="card admin-panel-card" id="transactions">
                <div class="admin-card-head">
                    <h2>Latest Transactions</h2>
                </div>
                <div class="admin-table-wrap">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Provider</th>
                                <th>Amount</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($latestPurchases)): ?>
                                <tr><td colspan="4" style="color: var(--text-muted);">No transactions yet.</td></tr>
                            <?php else: ?>
                                <?php foreach ($latestPurchases as $tx): ?>
                                <tr>
                                    <td><?= htmlspecialchars(date('m.d', strtotime($tx['created_at']))) ?></td>
                                    <td><?= htmlspecialchars($tx['full_name'] ?? 'Unknown') ?></td>
                                    <td>$<?= number_format((float)$tx['amount'], 2) ?></td>
                                    <td><?= htmlspecialchars(ucfirst((string)$tx['status'])) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card admin-panel-card">
                <div class="admin-card-head">
                    <h2>Top Providers</h2>
                </div>
                <div class="admin-table-wrap">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Transactions</th>
                                <th>Earnings</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($topProviders)): ?>
                                <tr><td colspan="3" style="color: var(--text-muted);">No provider data yet.</td></tr>
                            <?php else: ?>
                                <?php foreach ($topProviders as $tp): ?>
                                <tr>
                                    <td><?= htmlspecialchars($tp['full_name']) ?></td>
                                    <td><?= number_format((int)$tp['tx_count']) ?></td>
                                    <td>$<?= number_format((float)$tp['total_earnings'], 2) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card admin-panel-card" id="customers">
                <div class="admin-card-head">
                    <h2>Customers</h2>
                </div>
                <div class="admin-provider-toolbar">
                    <form method="GET" class="admin-provider-search-form">
                        <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>">
                        <input type="hidden" name="q" value="<?= htmlspecialchars($providerSearch) ?>">
                        <input type="hidden" name="category" value="<?= (int)$providerCategory ?>">
                        <input type="hidden" name="status" value="<?= htmlspecialchars($providerStatus) ?>">
                        <input type="text" name="user_q" value="<?= htmlspecialchars($userSearch) ?>" placeholder="Search users..." class="admin-provider-search">
                        <button type="submit" class="btn btn-primary">Search</button>
                    </form>
                    <a href="register.php" class="btn btn-primary">+ Add User</a>
                </div>
                <form method="GET" class="admin-provider-filters" style="margin-bottom: 0.95rem;">
                    <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>">
                    <input type="hidden" name="q" value="<?= htmlspecialchars($providerSearch) ?>">
                    <input type="hidden" name="category" value="<?= (int)$providerCategory ?>">
                    <input type="hidden" name="status" value="<?= htmlspecialchars($providerStatus) ?>">
                    <input type="hidden" name="user_q" value="<?= htmlspecialchars($userSearch) ?>">
                    <select name="user_status">
                        <option value="">All Status</option>
                        <option value="active" <?= $userStatus === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= $userStatus === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                    </select>
                    <button type="submit" class="btn btn-ghost">Apply</button>
                </form>
                <div class="admin-table-wrap">
                    <table class="admin-table admin-provider-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Contact</th>
                                <th>Joined</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($customers as $c): ?>
                            <tr>
                                <td>
                                    <div class="provider-row-name">
                                        <span class="provider-row-avatar">
                                            <?= strtoupper(substr((string)($c['full_name'] ?: 'U'), 0, 1)) ?>
                                        </span>
                                        <strong><?= htmlspecialchars($c['full_name'] ?: '(No name)') ?></strong>
                                    </div>
                                </td>
                                <td>
                                    <div><?= htmlspecialchars($c['email']) ?></div>
                                    <div class="small-muted"><?= htmlspecialchars($c['phone'] ?? '') ?></div>
                                </td>
                                <td><?= htmlspecialchars(date('Y-m-d', strtotime((string)$c['created_at']))) ?></td>
                                <td>
                                    <?php $isActiveUser = (int)($c['email_verified'] ?? 0) === 1; ?>
                                    <span class="status-pill <?= $isActiveUser ? 'active-user' : 'inactive-user' ?>">
                                        <?= $isActiveUser ? 'Active' : 'Inactive' ?>
                                    </span>
                                </td>
                                <td>
                                    <form method="POST" onsubmit="return confirm('Delete this customer? This will remove their account.');">
                                        <input type="hidden" name="user_id" value="<?= $c['id'] ?>">
                                        <button type="submit" name="action" value="delete_user" class="btn btn-ghost">Delete</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <p class="small-muted" style="margin-top: 0.6rem;">Showing <?= count($customers) ?> user entries</p>
            </div>
        </div>
    </div>
</section>
<?php if ($focusSection !== ''): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var el = document.getElementById('<?= htmlspecialchars($focusSection) ?>');
    if (el) {
        setTimeout(function () {
            el.scrollIntoView({ behavior: 'auto', block: 'start' });
        }, 40);
    }
});
</script>
<?php endif; ?>
<?php require_once 'includes/footer.php'; ?>
