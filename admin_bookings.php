<?php
$pageTitle = 'Admin Bookings';
require_once 'config/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

$pdo = getDBConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $bookingId = (int)($_POST['booking_id'] ?? 0);
    if ($bookingId > 0) {
        if ($action === 'cancel_booking') {
            $pdo->prepare("UPDATE bookings SET status = 'cancelled' WHERE id = ?")->execute([$bookingId]);
        } elseif ($action === 'resolve_booking') {
            $pdo->prepare("UPDATE bookings SET status = 'completed', completion_confirmed = 'agreed', payment_accepted = 1 WHERE id = ?")
                ->execute([$bookingId]);
        }
    }
}

$bookingSearch = trim((string)($_GET['q'] ?? ''));
$bookingStatus = trim((string)($_GET['status'] ?? ''));
$dateFrom = trim((string)($_GET['date_from'] ?? ''));
$dateTo = trim((string)($_GET['date_to'] ?? ''));
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;
$offset = ($page - 1) * $perPage;

$where = " WHERE 1=1 ";
$params = [];
if ($bookingSearch !== '') {
    $where .= " AND (cu.full_name LIKE ? OR pu.full_name LIKE ? OR s.title LIKE ? OR b.id = ?)";
    $kw = '%' . $bookingSearch . '%';
    $params[] = $kw;
    $params[] = $kw;
    $params[] = $kw;
    $params[] = ctype_digit($bookingSearch) ? (int)$bookingSearch : 0;
}
if ($bookingStatus !== '') {
    $where .= " AND b.status = ?";
    $params[] = $bookingStatus;
}
if ($dateFrom !== '') {
    $where .= " AND DATE(b.created_at) >= ?";
    $params[] = $dateFrom;
}
if ($dateTo !== '') {
    $where .= " AND DATE(b.created_at) <= ?";
    $params[] = $dateTo;
}

$countSql = "
    SELECT COUNT(*)
    FROM bookings b
    JOIN users cu ON cu.id = b.customer_id
    JOIN providers p ON p.id = b.provider_id
    JOIN users pu ON pu.id = p.user_id
    LEFT JOIN services s ON s.id = b.service_id
    {$where}
";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalBookings = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalBookings / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
    $offset = ($page - 1) * $perPage;
}

$sql = "
    SELECT b.*, cu.full_name AS customer_name, cu.email AS customer_email,
           pu.full_name AS provider_name, pu.email AS provider_email,
           s.title AS service_title, s.price_min, s.price_max
    FROM bookings b
    JOIN users cu ON cu.id = b.customer_id
    JOIN providers p ON p.id = b.provider_id
    JOIN users pu ON pu.id = p.user_id
    LEFT JOIN services s ON s.id = b.service_id
    {$where}
    ORDER BY b.created_at DESC
    LIMIT ? OFFSET ?
";
$stmt = $pdo->prepare($sql);
foreach ($params as $idx => $value) {
    $type = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
    $stmt->bindValue($idx + 1, $value, $type);
}
$stmt->bindValue(count($params) + 1, $perPage, PDO::PARAM_INT);
$stmt->bindValue(count($params) + 2, $offset, PDO::PARAM_INT);
$stmt->execute();
$bookings = $stmt->fetchAll();

$stats = [
    'total' => (int)$pdo->query("SELECT COUNT(*) FROM bookings")->fetchColumn(),
    'confirmed' => (int)$pdo->query("SELECT COUNT(*) FROM bookings WHERE status = 'confirmed'")->fetchColumn(),
    'completed' => (int)$pdo->query("SELECT COUNT(*) FROM bookings WHERE status = 'completed'")->fetchColumn(),
    'cancelled' => (int)$pdo->query("SELECT COUNT(*) FROM bookings WHERE status = 'cancelled'")->fetchColumn(),
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
        <a href="admin_bookings.php" class="admin-nav-link active">Bookings</a>
        <a href="admin_reports.php" class="admin-nav-link">Reports</a>
        <a href="admin_reviews.php" class="admin-nav-link">Reviews</a>
        <a href="admin_transactions.php" class="admin-nav-link">Transactions</a>
        <a href="admin_settings.php" class="admin-nav-link">Settings</a>
        <a href="logout.php" class="admin-nav-link">Log out</a>
        <div class="admin-side-user">Admin</div>
    </aside>

    <div class="admin-main">
        <div class="admin-topbar">
            <h1>Booking Management</h1>
            <div class="admin-user-chip">Admin</div>
        </div>

        <div class="admin-metric-grid">
            <div class="admin-metric-card"><p class="label">Total Bookings</p><h3><?= number_format($stats['total']) ?></h3></div>
            <div class="admin-metric-card"><p class="label">Confirmed</p><h3><?= number_format($stats['confirmed']) ?></h3></div>
            <div class="admin-metric-card"><p class="label">Completed</p><h3><?= number_format($stats['completed']) ?></h3></div>
            <div class="admin-metric-card"><p class="label">Cancelled</p><h3><?= number_format($stats['cancelled']) ?></h3></div>
        </div>

        <style>
            /* Admin Bookings UI Improvements */
            .admin-panel-card {
                background: #ffffff;
                border-radius: 12px;
                box-shadow: 0 4px 20px rgba(0,0,0,0.03);
                border: 1px solid #f1f5f9;
                padding: 32px;
            }

            .admin-card-head {
                margin-bottom: 24px;
            }
            .admin-card-head h2 {
                font-size: 1.5rem;
                font-weight: 600;
                color: #0f172a;
                margin: 0 0 8px 0;
            }
            .admin-card-head p {
                color: #64748b;
                font-size: 0.95rem;
                margin: 0;
            }

            .admin-filter-bar {
                display: flex;
                gap: 16px;
                margin-bottom: 24px;
                flex-wrap: wrap;
            }

            .search-wrapper, .date-wrapper {
                position: relative;
            }
            .search-wrapper {
                flex-grow: 1;
                max-width: 320px;
            }
            .search-wrapper svg {
                position: absolute;
                left: 14px;
                top: 50%;
                transform: translateY(-50%);
                color: #94a3b8;
                width: 18px;
                height: 18px;
            }
            .date-wrapper svg {
                position: absolute;
                right: 14px;
                top: 50%;
                transform: translateY(-50%);
                color: #94a3b8;
                width: 18px;
                height: 18px;
                pointer-events: none;
            }

            .filter-input {
                width: 100%;
                padding: 10px 16px;
                border: 1px solid #e2e8f0;
                border-radius: 8px;
                font-size: 0.95rem;
                color: #334155;
                outline: none;
                transition: border-color 0.2s, box-shadow 0.2s;
                background-color: #fff;
            }
            .search-input { padding-left: 42px; }
            .date-input { padding-right: 42px; min-width: 160px; }
            
            /* Hide default calendar icon on some browsers if we overlay our own, or keep it */
            .date-input::-webkit-calendar-picker-indicator {
                opacity: 0;
                cursor: pointer;
                width: 100%;
                height: 100%;
                position: absolute;
                top: 0; left: 0; right: 0; bottom: 0;
            }

            .filter-input:focus {
                border-color: #3b82f6;
                box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
            }

            .filter-select {
                padding: 10px 36px 10px 16px;
                border: 1px solid #e2e8f0;
                border-radius: 8px;
                font-size: 0.95rem;
                color: #334155;
                background-color: #fff;
                appearance: none;
                background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%2364748b'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E");
                background-repeat: no-repeat;
                background-position: right 12px center;
                background-size: 16px;
                outline: none;
                cursor: pointer;
                min-width: 160px;
            }
            .filter-select:focus {
                border-color: #3b82f6;
                box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
            }

            .btn-filter {
                display: flex;
                align-items: center;
                gap: 8px;
                padding: 10px 20px;
                border: 1px solid #e2e8f0;
                border-radius: 8px;
                font-size: 0.95rem;
                font-weight: 500;
                color: #3b82f6;
                background: #eff6ff; 
                cursor: pointer;
                transition: all 0.2s;
            }
            .btn-filter:hover {
                background: #dbeafe;
                border-color: #bfdbfe;
            }

            .admin-table {
                width: 100%;
                border-collapse: separate;
                border-spacing: 0;
                margin-top: 8px;
            }
            .admin-table th {
                font-weight: 600;
                color: #64748b;
                font-size: 0.8rem;
                padding: 16px;
                border-bottom: 1px solid #e2e8f0;
                background: #fafbfc;
                text-align: left;
                text-transform: uppercase;
                letter-spacing: 0.05em;
            }
            .admin-table th:first-child { border-top-left-radius: 8px; border-left: 1px solid #e2e8f0; border-top: 1px solid #e2e8f0; }
            .admin-table th:last-child { border-top-right-radius: 8px; border-right: 1px solid #e2e8f0; border-top: 1px solid #e2e8f0; }
            .admin-table th:not(:first-child):not(:last-child) { border-top: 1px solid #e2e8f0; }

            .admin-table td {
                padding: 18px 16px;
                font-size: 0.95rem;
                color: #1e293b;
                border-bottom: 1px solid #f1f5f9;
                vertical-align: middle;
            }
            .admin-table tbody tr {
                transition: background-color 0.2s;
            }
            .admin-table tbody tr:hover {
                background-color: #f8fafc;
            }
            .admin-table tbody tr:last-child td { border-bottom: none; }
            
            .admin-table tbody tr td:first-child { border-left: 1px solid #e2e8f0; }
            .admin-table tbody tr td:last-child { border-right: 1px solid #e2e8f0; }
            .admin-table tbody tr:last-child td:first-child { border-bottom-left-radius: 8px; border-bottom: 1px solid #e2e8f0; }
            .admin-table tbody tr:last-child td:last-child { border-bottom-right-radius: 8px; border-bottom: 1px solid #e2e8f0; }
            .admin-table tbody tr:last-child td:not(:first-child):not(:last-child) { border-bottom: 1px solid #e2e8f0; }

            /* Profile Avatar */
            .user-profile-cell {
                display: flex;
                align-items: center;
                gap: 12px;
            }
            .user-avatar {
                width: 36px;
                height: 36px;
                border-radius: 50%;
                background: #eef2ff;
                color: #4f46e5;
                display: flex;
                align-items: center;
                justify-content: center;
                font-weight: 600;
                font-size: 0.95rem;
            }

            /* Badges */
            .modern-badge {
                display: inline-flex;
                align-items: center;
                gap: 6px;
                padding: 6px 12px;
                border-radius: 999px;
                font-size: 0.8rem;
                font-weight: 500;
                white-space: nowrap;
            }
            .modern-badge::before {
                content: '';
                display: block;
                width: 6px;
                height: 6px;
                border-radius: 50%;
            }
            .badge-confirmed, .badge-completed { background: #ecfdf5; color: #059669; }
            .badge-confirmed::before, .badge-completed::before { background: #10b981; }
            
            .badge-pending { background: #f1f5f9; color: #475569; }
            .badge-pending::before { background: #64748b; }
            
            .badge-cancelled, .badge-declined { background: #fef2f2; color: #dc2626; }
            .badge-cancelled::before, .badge-declined::before { background: #ef4444; }

            /* Kebab Menu Actions */
            .action-menu-wrapper {
                position: relative;
                display: inline-block;
            }
            .btn-action-kebab {
                display: flex;
                align-items: center;
                justify-content: center;
                width: 36px;
                height: 36px;
                border: 1px solid #e2e8f0;
                border-radius: 8px;
                background: #fff;
                color: #475569;
                cursor: pointer;
                transition: all 0.2s;
            }
            .btn-action-kebab:hover {
                background: #f8fafc;
                border-color: #cbd5e1;
            }
            .action-dropdown {
                position: absolute;
                top: calc(100% + 4px);
                right: 0;
                background: #fff;
                border: 1px solid #e2e8f0;
                border-radius: 12px;
                box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1), 0 8px 10px -6px rgba(0,0,0,0.1);
                min-width: 160px;
                z-index: 50;
                padding: 8px;
                display: none;
            }
            .action-dropdown.show {
                display: block;
            }
            .dropdown-item {
                display: flex;
                align-items: center;
                gap: 10px;
                width: 100%;
                text-align: left;
                padding: 10px 12px;
                font-size: 0.9rem;
                font-weight: 500;
                color: #334155;
                background: none;
                border: none;
                border-radius: 6px;
                cursor: pointer;
                transition: background 0.2s;
            }
            .dropdown-item:hover {
                background: #f1f5f9;
            }
            .dropdown-item svg {
                width: 16px;
                height: 16px;
                color: #64748b;
            }

            /* Pagination */
            .pagination-container {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-top: 24px;
                padding-top: 16px;
            }
            .pagination-info {
                font-size: 0.9rem;
                color: #64748b;
            }
            .pagination-controls {
                display: flex;
                align-items: center;
                gap: 8px;
            }
            .page-btn {
                display: flex;
                align-items: center;
                justify-content: center;
                min-width: 32px;
                height: 32px;
                border: none;
                border-radius: 6px;
                background: transparent;
                color: #64748b;
                font-size: 0.9rem;
                font-weight: 500;
                text-decoration: none;
                transition: all 0.2s;
            }
            .page-btn:hover:not(.active) {
                background: #f1f5f9;
            }
            .page-btn.active {
                background: #eef2ff;
                color: #4f46e5;
            }
            .pagination-flex {
                display: flex;
                align-items: center;
                gap: 16px;
            }
            .rows-per-page-wrap {
                display: flex;
                align-items: center;
                gap: 8px;
                font-size: 0.9rem;
                color: #64748b;
            }
            .rows-per-page {
                padding: 6px 28px 6px 12px;
                border: 1px solid #e2e8f0;
                border-radius: 6px;
                font-size: 0.85rem;
                color: #475569;
                background-color: #fff;
                appearance: none;
                background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%2364748b'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E");
                background-repeat: no-repeat;
                background-position: right 8px center;
                background-size: 14px;
                outline: none;
            }

            @media (max-width: 768px) {
                .admin-filter-bar { flex-direction: column; }
                .search-wrapper, .date-wrapper { max-width: 100%; width: 100%; }
                .pagination-container { flex-direction: column; gap: 16px; align-items: flex-start; }
                .admin-table-wrap { overflow-x: auto; }
            }
        </style>

        <div class="admin-grid">
            <div class="admin-panel-card">
                <div class="admin-card-head">
                    <h2>Bookings Table</h2>
                    <p>Manage and track all bookings in one place.</p>
                </div>

                <form method="GET" class="admin-filter-bar">
                    <div class="search-wrapper">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                        <input type="text" name="q" class="filter-input search-input" placeholder="Booking ID, customer, provider..." value="<?= htmlspecialchars($bookingSearch) ?>">
                    </div>
                    
                    <select name="status" class="filter-select">
                        <option value="">All Status</option>
                        <option value="confirmed" <?= $bookingStatus === 'confirmed' ? 'selected' : '' ?>>Confirmed</option>
                        <option value="completed" <?= $bookingStatus === 'completed' ? 'selected' : '' ?>>Completed</option>
                        <option value="cancelled" <?= $bookingStatus === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                        <option value="declined" <?= $bookingStatus === 'declined' ? 'selected' : '' ?>>Declined</option>
                    </select>

                    <div class="date-wrapper">
                        <input type="date" name="date_from" class="filter-input date-input" value="<?= htmlspecialchars($dateFrom) ?>">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                    </div>
                    
                    <div class="date-wrapper">
                        <input type="date" name="date_to" class="filter-input date-input" value="<?= htmlspecialchars($dateTo) ?>">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                    </div>

                    <button type="submit" class="btn-filter">
                        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path></svg>
                        Filter
                    </button>
                </form>

                <div class="admin-table-wrap">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>ID <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="vertical-align: middle; margin-left:4px;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l4-4 4 4m0 6l-4 4-4-4"></path></svg></th>
                                <th>Customer <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="vertical-align: middle; margin-left:4px;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l4-4 4 4m0 6l-4 4-4-4"></path></svg></th>
                                <th>Provider <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="vertical-align: middle; margin-left:4px;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l4-4 4 4m0 6l-4 4-4-4"></path></svg></th>
                                <th>Service <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="vertical-align: middle; margin-left:4px;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l4-4 4 4m0 6l-4 4-4-4"></path></svg></th>
                                <th>Status <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="vertical-align: middle; margin-left:4px;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l4-4 4 4m0 6l-4 4-4-4"></path></svg></th>
                                <th>Scheduled <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="vertical-align: middle; margin-left:4px;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l4-4 4 4m0 6l-4 4-4-4"></path></svg></th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($bookings)): ?>
                                <tr><td colspan="7" style="color: var(--text-muted); text-align: center;">No bookings found.</td></tr>
                            <?php else: ?>
                                <?php foreach ($bookings as $b): ?>
                                    <?php
                                    $status = strtolower((string)($b['status'] ?? ''));
                                    $statusClass = 'badge-pending';
                                    if ($status === 'completed' || $status === 'confirmed') $statusClass = 'badge-confirmed';
                                    if ($status === 'cancelled' || $status === 'declined') $statusClass = 'badge-cancelled';
                                    ?>
                                    <tr>
                                        <td>#<?= (int)$b['id'] ?></td>
                                        <td>
                                            <div class="user-profile-cell">
                                                <div class="user-avatar">
                                                    <?= htmlspecialchars(strtoupper(substr($b['customer_name'], 0, 1))) ?>
                                                </div>
                                                <span><?= htmlspecialchars($b['customer_name']) ?></span>
                                            </div>
                                        </td>
                                        <td><?= htmlspecialchars($b['provider_name']) ?></td>
                                        <td><?= htmlspecialchars($b['service_title'] ?: 'Unknown service') ?></td>
                                        <td><span class="modern-badge <?= $statusClass ?>"><?= htmlspecialchars(ucfirst($status ?: 'pending')) ?></span></td>
                                        <td><?= !empty($b['scheduled_date']) ? htmlspecialchars(date('M d, Y', strtotime($b['scheduled_date']))) : 'Not set' ?></td>
                                        <td>
                                            <div class="action-menu-wrapper">
                                                <button type="button" class="btn-action-kebab" onclick="toggleDropdown(this)">
                                                    <svg width="20" height="20" fill="currentColor" viewBox="0 0 24 24"><path d="M12 8c1.1 0 2-.9 2-2s-.9-2-2-2-2 .9-2 2 .9 2 2 2zm0 2c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zm0 6c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2z"/></svg>
                                                </button>
                                                <div class="action-dropdown">
                                                    <button type="button" class="dropdown-item btn-booking-details" data-booking='<?= htmlspecialchars(json_encode($b), ENT_QUOTES, "UTF-8") ?>'>
                                                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                                                        Details
                                                    </button>
                                                    
                                                    <?php if (!in_array($status, ['cancelled', 'completed'], true)): ?>
                                                        <form method="POST" onsubmit="return confirm('Cancel this booking?');" style="margin:0;">
                                                            <input type="hidden" name="booking_id" value="<?= (int)$b['id'] ?>">
                                                            <button type="submit" name="action" value="cancel_booking" class="dropdown-item">
                                                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                                                Cancel
                                                            </button>
                                                        </form>
                                                        <form method="POST" onsubmit="return confirm('Resolve and mark this booking completed?');" style="margin:0;">
                                                            <input type="hidden" name="booking_id" value="<?= (int)$b['id'] ?>">
                                                            <button type="submit" name="action" value="resolve_booking" class="dropdown-item">
                                                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                                                Resolve
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="pagination-container">
                    <div class="pagination-info">
                        <?php if (count($bookings) > 0): ?>
                            Showing <?= min($offset + 1, $totalBookings) ?> to <?= min($offset + count($bookings), $totalBookings) ?> of <?= number_format($totalBookings) ?> bookings
                        <?php else: ?>
                            No bookings to display
                        <?php endif; ?>
                    </div>
                    
                    <div class="pagination-flex">
                        <div class="pagination-controls">
                            <a class="page-btn" href="?<?= htmlspecialchars($queryBase) ?>page=<?= max(1, $page - 1) ?>" <?= $page <= 1 ? 'style="pointer-events: none; opacity: 0.5;"' : '' ?>>&lsaquo;</a>
                            
                            <a class="page-btn active" href="#"><?= $page ?></a>
                            
                            <a class="page-btn" href="?<?= htmlspecialchars($queryBase) ?>page=<?= min($totalPages, $page + 1) ?>" <?= $page >= $totalPages ? 'style="pointer-events: none; opacity: 0.5;"' : '' ?>>&rsaquo;</a>
                        </div>
                        
                        <div class="rows-per-page-wrap">
                            Rows per page:
                            <select class="rows-per-page">
                                <option value="10">10</option>
                            </select>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <script>
        function toggleDropdown(btn) {
            document.querySelectorAll('.action-dropdown.show').forEach(el => {
                if (el !== btn.nextElementSibling) el.classList.remove('show');
            });
            btn.nextElementSibling.classList.toggle('show');
        }
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.action-menu-wrapper')) {
                document.querySelectorAll('.action-dropdown.show').forEach(el => el.classList.remove('show'));
            }
        });
        </script>
    </div>
</section>

<script>
(function () {
    const modal = document.getElementById('bookingDetailsModal');
    const body = document.getElementById('bookingDetailsBody');
    const close = document.getElementById('closeBookingDetailsModal');
    const buttons = document.querySelectorAll('.btn-booking-details');
    if (!modal || !body || !close || buttons.length === 0) return;
    const row = (k, v) => '<div class="admin-modal-row"><strong>' + k + '</strong><span>' + v + '</span></div>';
    buttons.forEach(function (btn) {
        btn.addEventListener('click', function () {
            let b;
            try { b = JSON.parse(btn.getAttribute('data-booking') || '{}'); } catch (e) { return; }
            body.innerHTML =
                row('Booking ID', '#' + String(b.id || '')) +
                row('Customer', String(b.customer_name || '')) +
                row('Customer Email', String(b.customer_email || '')) +
                row('Provider', String(b.provider_name || '')) +
                row('Provider Email', String(b.provider_email || '')) +
                row('Service', String(b.service_title || '')) +
                row('Status', String(b.status || '')) +
                row('Completion Confirmed', String(b.completion_confirmed || 'pending')) +
                row('Scheduled Date', String(b.scheduled_date || 'N/A')) +
                row('Price Range', 'PHP ' + String(b.price_min || 0) + ' - ' + String(b.price_max || 0)) +
                row('Notes', String(b.notes || 'N/A')) +
                row('Created At', String(b.created_at || ''));
            modal.hidden = false;
            document.body.style.overflow = 'hidden';
        });
    });
    function closeModal() { modal.hidden = true; document.body.style.overflow = ''; }
    close.addEventListener('click', closeModal);
    modal.addEventListener('click', function (e) { if (e.target === modal) closeModal(); });
})();
</script>
<?php require_once 'includes/footer.php'; ?>
