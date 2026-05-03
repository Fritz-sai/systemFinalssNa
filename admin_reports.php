<?php
$pageTitle = 'Admin Reports';
require_once 'config/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

$pdo = getDBConnection();

// Ensure reports and warnings tables exist
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS reports (
            id INT AUTO_INCREMENT PRIMARY KEY,
            booking_id INT NULL,
            provider_id INT NULL,
            reporter_user_id INT NULL,
            report_type VARCHAR(50) NOT NULL DEFAULT 'complaint',
            reason VARCHAR(255) NULL,
            details TEXT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'open',
            resolved_note VARCHAR(255) NULL,
            resolved_by INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            resolved_at DATETIME NULL,
            INDEX (provider_id),
            INDEX (reporter_user_id),
            INDEX (status),
            INDEX (booking_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS provider_warnings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            provider_id INT NOT NULL,
            report_id INT NULL,
            admin_id INT NOT NULL,
            warning_message VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX (provider_id),
            INDEX (report_id),
            INDEX (admin_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
} catch (Throwable $e) {
    // ignore table creation failures and continue
}

// Ensure account_state exists for provider suspension
try {
    $col = $pdo->prepare("
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'users'
          AND COLUMN_NAME = 'account_state'
    ");
    $col->execute();
    if ((int)$col->fetchColumn() === 0) {
        $pdo->exec("ALTER TABLE users ADD COLUMN account_state VARCHAR(20) NOT NULL DEFAULT 'active'");
    }
} catch (Throwable $e) {
    // ignore
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $reportId = (int)($_POST['report_id'] ?? 0);
    $providerId = (int)($_POST['provider_id'] ?? 0);

    if ($action === 'resolve_report' && $reportId > 0) {
        $note = trim((string)($_POST['resolve_note'] ?? 'Resolved by admin'));
        $pdo->prepare("
            UPDATE reports
            SET status = 'resolved', resolved_note = ?, resolved_by = ?, resolved_at = NOW()
            WHERE id = ?
        ")->execute([$note, (int)$_SESSION['user_id'], $reportId]);
    } elseif ($action === 'suspend_provider' && $providerId > 0) {
        $pdo->prepare("
            UPDATE users u
            JOIN providers p ON p.user_id = u.id
            SET u.account_state = 'suspended'
            WHERE p.id = ?
        ")->execute([$providerId]);
    } elseif ($action === 'issue_warning' && $providerId > 0) {
        $message = trim((string)($_POST['warning_message'] ?? 'Admin warning issued due to report.'));
        if ($message !== '') {
            $pdo->prepare("
                INSERT INTO provider_warnings (provider_id, report_id, admin_id, warning_message)
                VALUES (?, ?, ?, ?)
            ")->execute([$providerId, $reportId > 0 ? $reportId : null, (int)$_SESSION['user_id'], $message]);
        }
    }
}

$statusFilter = trim((string)($_GET['status'] ?? ''));
$search = trim((string)($_GET['q'] ?? ''));
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;
$offset = ($page - 1) * $perPage;

$where = " WHERE 1=1 ";
$params = [];
if ($statusFilter !== '') {
    $where .= " AND r.status = ? ";
    $params[] = $statusFilter;
}
if ($search !== '') {
    $where .= " AND (u.full_name LIKE ? OR ru.full_name LIKE ? OR r.reason LIKE ? OR r.report_type LIKE ? OR r.id = ?) ";
    $kw = '%' . $search . '%';
    $params[] = $kw;
    $params[] = $kw;
    $params[] = $kw;
    $params[] = $kw;
    $params[] = ctype_digit($search) ? (int)$search : 0;
}

$countSql = "
    SELECT COUNT(*)
    FROM reports r
    LEFT JOIN providers p ON p.id = r.provider_id
    LEFT JOIN users u ON u.id = p.user_id
    LEFT JOIN users ru ON ru.id = r.reporter_user_id
    {$where}
";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalReports = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalReports / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
    $offset = ($page - 1) * $perPage;
}

$reportsSql = "
    SELECT r.*,
           p.id AS provider_pk,
           u.full_name AS provider_name,
           u.email AS provider_email,
           COALESCE(u.account_state, 'active') AS provider_state,
           ru.full_name AS reporter_name,
           (
               SELECT COUNT(*)
               FROM provider_warnings pw
               WHERE pw.provider_id = r.provider_id
           ) AS warning_count
    FROM reports r
    LEFT JOIN providers p ON p.id = r.provider_id
    LEFT JOIN users u ON u.id = p.user_id
    LEFT JOIN users ru ON ru.id = r.reporter_user_id
    {$where}
    ORDER BY r.created_at DESC
    LIMIT ? OFFSET ?
";
$reportsStmt = $pdo->prepare($reportsSql);
foreach ($params as $idx => $value) {
    $reportsStmt->bindValue($idx + 1, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$reportsStmt->bindValue(count($params) + 1, $perPage, PDO::PARAM_INT);
$reportsStmt->bindValue(count($params) + 2, $offset, PDO::PARAM_INT);
$reportsStmt->execute();
$reports = $reportsStmt->fetchAll();

$stats = [
    'total' => (int)$pdo->query("SELECT COUNT(*) FROM reports")->fetchColumn(),
    'open' => (int)$pdo->query("SELECT COUNT(*) FROM reports WHERE status = 'open'")->fetchColumn(),
    'resolved' => (int)$pdo->query("SELECT COUNT(*) FROM reports WHERE status = 'resolved'")->fetchColumn(),
    'warnings' => (int)$pdo->query("SELECT COUNT(*) FROM provider_warnings")->fetchColumn(),
];

$queryParams = $_GET;
unset($queryParams['page']);
$queryBase = http_build_query($queryParams);
$queryBase = $queryBase !== '' ? ($queryBase . '&') : '';

require_once 'includes/header.php';
?>
<section class="admin-shell">
    <aside class="admin-side">
        <div class="admin-side-brand">ServiceLink</div>
        <a href="admin_dashboard.php" class="admin-nav-link">Dashboard</a>
        <a href="admin_providers.php" class="admin-nav-link">Providers</a>
        <a href="admin_users.php" class="admin-nav-link">Users</a>
        <a href="admin_bookings.php" class="admin-nav-link">Bookings</a>
        <a href="admin_reports.php" class="admin-nav-link active">Reports</a>
        <a href="admin_reviews.php" class="admin-nav-link">Reviews</a>
        <a href="admin_transactions.php" class="admin-nav-link">Transactions</a>
        <a href="admin_settings.php" class="admin-nav-link">Settings</a>
        <a href="logout.php" class="admin-nav-link">Log out</a>
        <div class="admin-side-user">Admin</div>
    </aside>

    <div class="admin-main">
        <div class="admin-topbar">
            <h1>Reports & Complaints</h1>
            <div class="admin-user-chip">Admin</div>
        </div>

        <div class="admin-metric-grid">
            <div class="admin-metric-card"><p class="label">Total Reports</p><h3><?= number_format($stats['total']) ?></h3></div>
            <div class="admin-metric-card"><p class="label">Open Reports</p><h3><?= number_format($stats['open']) ?></h3></div>
            <div class="admin-metric-card"><p class="label">Resolved Reports</p><h3><?= number_format($stats['resolved']) ?></h3></div>
            <div class="admin-metric-card"><p class="label">Warnings Issued</p><h3><?= number_format($stats['warnings']) ?></h3></div>
        </div>

        <div class="admin-grid">
            <div class="card admin-panel-card">
                <div class="admin-card-head">
                    <h2>Reports Table</h2>
                </div>

                <form method="GET" class="admin-provider-filters">
                    <input type="text" name="q" placeholder="Search report, provider, reporter" value="<?= htmlspecialchars($search) ?>">
                    <select name="status">
                        <option value="">All Status</option>
                        <option value="open" <?= $statusFilter === 'open' ? 'selected' : '' ?>>Open</option>
                        <option value="resolved" <?= $statusFilter === 'resolved' ? 'selected' : '' ?>>Resolved</option>
                    </select>
                    <button type="submit" class="btn btn-ghost">Filter</button>
                </form>

                <div class="admin-table-wrap">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Report</th>
                                <th>Provider</th>
                                <th>Reporter</th>
                                <th>Type / Reason</th>
                                <th>Status</th>
                                <th>Warnings</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($reports)): ?>
                                <tr><td colspan="7" style="color: var(--text-muted);">No reports yet.</td></tr>
                            <?php else: ?>
                                <?php foreach ($reports as $r): ?>
                                    <?php $isResolved = ((string)$r['status'] === 'resolved'); ?>
                                    <tr>
                                        <td>#<?= (int)$r['id'] ?><div class="small-muted"><?= htmlspecialchars(date('M d, Y h:i A', strtotime((string)$r['created_at']))) ?></div></td>
                                        <td>
                                            <?= htmlspecialchars((string)($r['provider_name'] ?? 'Unknown Provider')) ?>
                                            <div class="small-muted"><?= htmlspecialchars((string)($r['provider_email'] ?? '')) ?></div>
                                        </td>
                                        <td><?= htmlspecialchars((string)($r['reporter_name'] ?? 'Anonymous')) ?></td>
                                        <td>
                                            <span class="status-pill suspended-user"><?= htmlspecialchars(ucfirst((string)$r['report_type'])) ?></span>
                                            <div class="small-muted"><?= htmlspecialchars((string)($r['reason'] ?? 'No reason provided')) ?></div>
                                        </td>
                                        <td>
                                            <span class="status-pill <?= $isResolved ? 'verified' : 'banned-user' ?>">
                                                <?= $isResolved ? 'Resolved' : 'Open' ?>
                                            </span>
                                        </td>
                                        <td><span class="status-pill active-user"><?= number_format((int)$r['warning_count']) ?></span></td>
                                        <td>
                                            <div class="admin-inline-actions">
                                                <?php if (!$isResolved): ?>
                                                    <form method="POST">
                                                        <input type="hidden" name="report_id" value="<?= (int)$r['id'] ?>">
                                                        <input type="hidden" name="resolve_note" value="Resolved after admin review.">
                                                        <button type="submit" name="action" value="resolve_report" class="btn btn-ghost">Resolve</button>
                                                    </form>
                                                <?php endif; ?>
                                                <?php if ((int)$r['provider_id'] > 0): ?>
                                                    <form method="POST" onsubmit="return confirm('Suspend this provider account?');">
                                                        <input type="hidden" name="provider_id" value="<?= (int)$r['provider_id'] ?>">
                                                        <button type="submit" name="action" value="suspend_provider" class="btn btn-ghost">Suspend Provider</button>
                                                    </form>
                                                    <form method="POST" onsubmit="return confirm('Issue warning to this provider?');">
                                                        <input type="hidden" name="provider_id" value="<?= (int)$r['provider_id'] ?>">
                                                        <input type="hidden" name="report_id" value="<?= (int)$r['id'] ?>">
                                                        <input type="hidden" name="warning_message" value="Warning issued due to complaint report #<?= (int)$r['id'] ?>.">
                                                        <button type="submit" name="action" value="issue_warning" class="btn btn-ghost">Issue Warning</button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="admin-pagination-wrap">
                    <p class="small-muted">Showing <?= count($reports) ?> of <?= number_format($totalReports) ?> reports</p>
                    <div class="admin-pagination">
                        <?php if ($page > 1): ?><a class="btn btn-ghost" href="?<?= htmlspecialchars($queryBase) ?>page=<?= $page - 1 ?>">Prev</a><?php endif; ?>
                        <span class="admin-page-indicator">Page <?= $page ?> of <?= $totalPages ?></span>
                        <?php if ($page < $totalPages): ?><a class="btn btn-ghost" href="?<?= htmlspecialchars($queryBase) ?>page=<?= $page + 1 ?>">Next</a><?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
<?php require_once 'includes/footer.php'; ?>
