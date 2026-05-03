<?php
$pageTitle = 'Admin Transactions';
require_once 'config/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

$pdo = getDBConnection();

// Get all transactions with pagination
$transactionSearch = trim((string)($_GET['search'] ?? ''));
$transactionStatus = trim((string)($_GET['status'] ?? ''));

$sql = "
    SELECT cp.id, cp.created_at, cp.amount, cp.status, u.full_name, u.email, p.id as provider_id
    FROM credit_purchases cp
    JOIN providers p ON p.id = cp.provider_id
    JOIN users u ON u.id = p.user_id
    WHERE 1=1
";
$params = [];

if ($transactionSearch !== '') {
    $sql .= " AND (u.full_name LIKE ? OR u.email LIKE ?)";
    $kw = '%' . $transactionSearch . '%';
    $params[] = $kw;
    $params[] = $kw;
}

if ($transactionStatus !== '') {
    $sql .= " AND cp.status = ?";
    $params[] = $transactionStatus;
}

$sql .= " ORDER BY cp.created_at DESC LIMIT 100";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$transactions = $stmt->fetchAll();

// Get transaction stats
$stats = [
    'total' => $pdo->query("SELECT COUNT(*) FROM credit_purchases")->fetchColumn(),
    'pending' => $pdo->query("SELECT COUNT(*) FROM credit_purchases WHERE status = 'pending'")->fetchColumn(),
    'completed' => $pdo->query("SELECT COUNT(*) FROM credit_purchases WHERE status = 'completed'")->fetchColumn(),
    'failed' => $pdo->query("SELECT COUNT(*) FROM credit_purchases WHERE status = 'failed'")->fetchColumn(),
];

$totalRevenue = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM credit_purchases WHERE status = 'completed'")->fetchColumn();

require_once 'includes/header.php';
?>
<section class="admin-shell">
    <aside class="admin-side">
        <div class="admin-side-brand">ServiceLink</div>
        <a href="admin_dashboard.php" class="admin-nav-link">Dashboard</a>
        <a href="admin_providers.php" class="admin-nav-link">Providers</a>
        <a href="admin_users.php" class="admin-nav-link">Users</a>
        <a href="admin_bookings.php" class="admin-nav-link">Bookings</a>
        <a href="admin_reports.php" class="admin-nav-link">Reports</a>
        <a href="admin_reviews.php" class="admin-nav-link">Reviews</a>
        <a href="admin_transactions.php" class="admin-nav-link active">Transactions</a>
        <a href="admin_settings.php" class="admin-nav-link">Settings</a>
        <a href="logout.php" class="admin-nav-link">Log out</a>
        <div class="admin-side-user">Admin</div>
    </aside>

    <div class="admin-main">
        <style>
            /* Admin Transactions UI Improvements */
            .admin-header-row {
                display: flex;
                justify-content: space-between;
                align-items: flex-start;
                margin-bottom: 24px;
            }
            .admin-header-text h1 {
                font-size: 1.5rem;
                font-weight: 600;
                color: #0f172a;
                margin: 0 0 8px 0;
            }
            .admin-header-text p {
                color: #64748b;
                font-size: 0.95rem;
                margin: 0;
            }
            .btn-export {
                display: flex;
                align-items: center;
                gap: 8px;
                padding: 10px 16px;
                background: #fff;
                border: 1px solid #e2e8f0;
                border-radius: 8px;
                color: #475569;
                font-size: 0.9rem;
                font-weight: 500;
                cursor: pointer;
                transition: all 0.2s;
            }
            .btn-export:hover {
                background: #f8fafc;
                border-color: #cbd5e1;
            }

            .admin-metric-grid {
                display: grid;
                grid-template-columns: repeat(4, 1fr);
                gap: 24px;
                margin-bottom: 24px;
            }
            .insight-card {
                background: #ffffff;
                border-radius: 12px;
                border: 1px solid #f1f5f9;
                padding: 24px;
                box-shadow: 0 4px 20px rgba(0,0,0,0.02);
                display: flex;
                gap: 16px;
                align-items: flex-start;
            }
            .insight-icon {
                width: 48px;
                height: 48px;
                border-radius: 12px;
                display: flex;
                align-items: center;
                justify-content: center;
                flex-shrink: 0;
            }
            .insight-icon.blue { background: #eff6ff; color: #3b82f6; }
            .insight-icon.green { background: #f0fdf4; color: #10b981; }
            .insight-icon.orange { background: #fff7ed; color: #f59e0b; }
            .insight-icon.red { background: #fef2f2; color: #ef4444; }
            
            .insight-data { flex-grow: 1; }
            .insight-label { color: #64748b; font-size: 0.85rem; margin: 0 0 8px 0; }
            .insight-value { color: #0f172a; font-size: 1.5rem; font-weight: 600; margin: 0 0 8px 0; }
            .insight-sub { color: #94a3b8; font-size: 0.8rem; margin: 0; }

            .admin-panel-card {
                background: #ffffff;
                border-radius: 12px;
                box-shadow: 0 4px 20px rgba(0,0,0,0.03);
                border: 1px solid #f1f5f9;
                padding: 32px;
            }

            /* Filter Bar */
            .admin-filter-bar {
                display: flex;
                gap: 16px;
                margin-bottom: 24px;
                flex-wrap: wrap;
            }
            .search-wrapper { position: relative; flex-grow: 1; max-width: 400px; }
            .search-wrapper svg { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: #94a3b8; width: 18px; height: 18px; }
            .filter-input {
                width: 100%; padding: 10px 16px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 0.95rem; color: #334155; outline: none; transition: all 0.2s;
            }
            .search-input { padding-left: 42px; }
            .filter-input:focus { border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1); }
            
            .filter-select {
                padding: 10px 36px 10px 16px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 0.95rem; color: #334155; background-color: #fff; appearance: none;
                background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%2364748b'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E");
                background-repeat: no-repeat; background-position: right 12px center; background-size: 16px; outline: none; cursor: pointer; min-width: 160px;
            }
            
            .btn-filter {
                display: flex; align-items: center; gap: 8px; padding: 10px 20px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 0.95rem; font-weight: 500; color: #4f46e5; background: #eef2ff; cursor: pointer; transition: all 0.2s;
            }
            .btn-filter:hover { background: #e0e7ff; border-color: #c7d2fe; }

            /* Table Styles */
            .admin-table { width: 100%; border-collapse: separate; border-spacing: 0; margin-top: 8px; }
            .admin-table th { font-weight: 600; color: #64748b; font-size: 0.8rem; padding: 16px; border-bottom: 1px solid #e2e8f0; background: #fafbfc; text-align: left; text-transform: uppercase; letter-spacing: 0.05em; }
            .admin-table th:first-child { border-top-left-radius: 8px; border-left: 1px solid #e2e8f0; border-top: 1px solid #e2e8f0; }
            .admin-table th:last-child { border-top-right-radius: 8px; border-right: 1px solid #e2e8f0; border-top: 1px solid #e2e8f0; }
            .admin-table th:not(:first-child):not(:last-child) { border-top: 1px solid #e2e8f0; }
            .admin-table td { padding: 18px 16px; font-size: 0.95rem; color: #1e293b; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
            .admin-table tbody tr:hover { background-color: #f8fafc; }
            .admin-table tbody tr:last-child td { border-bottom: none; }
            .admin-table tbody tr td:first-child { border-left: 1px solid #e2e8f0; }
            .admin-table tbody tr td:last-child { border-right: 1px solid #e2e8f0; }
            .admin-table tbody tr:last-child td:first-child { border-bottom-left-radius: 8px; border-bottom: 1px solid #e2e8f0; }
            .admin-table tbody tr:last-child td:last-child { border-bottom-right-radius: 8px; border-bottom: 1px solid #e2e8f0; }
            .admin-table tbody tr:last-child td:not(:first-child):not(:last-child) { border-bottom: 1px solid #e2e8f0; }

            /* Avatar */
            .user-profile-cell { display: flex; align-items: center; gap: 12px; }
            .user-avatar { width: 32px; height: 32px; border-radius: 50%; background: #eef2ff; color: #4f46e5; display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 0.85rem; }
            .user-avatar.cyan { background: #e0f2fe; color: #0284c7; }

            /* Badges */
            .modern-badge { display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px; border-radius: 999px; font-size: 0.8rem; font-weight: 500; white-space: nowrap; }
            .modern-badge::before { content: ''; display: block; width: 6px; height: 6px; border-radius: 50%; }
            .badge-completed { background: #ecfdf5; color: #059669; }
            .badge-completed::before { background: #10b981; }
            .badge-pending { background: #fffbeb; color: #d97706; }
            .badge-pending::before { background: #f59e0b; }
            .badge-failed { background: #fef2f2; color: #dc2626; }
            .badge-failed::before { background: #ef4444; }

            /* Action Kebab */
            .btn-action-kebab { display: flex; align-items: center; justify-content: center; width: 36px; height: 36px; border: 1px solid #e2e8f0; border-radius: 8px; background: #fff; color: #475569; cursor: pointer; transition: all 0.2s; }
            .btn-action-kebab:hover { background: #f8fafc; border-color: #cbd5e1; }

            /* Pagination */
            .pagination-container { display: flex; justify-content: space-between; align-items: center; margin-top: 24px; padding-top: 16px; }
            .pagination-info { font-size: 0.9rem; color: #64748b; }
            .page-btn { display: flex; align-items: center; justify-content: center; min-width: 32px; height: 32px; border: none; border-radius: 6px; background: transparent; color: #64748b; font-size: 0.9rem; font-weight: 500; text-decoration: none; transition: all 0.2s; }
            .page-btn:hover:not(.active) { background: #f1f5f9; }
            .page-btn.active { background: #eef2ff; color: #4f46e5; }
            .pagination-flex { display: flex; align-items: center; gap: 16px; }
            .rows-per-page-wrap { display: flex; align-items: center; gap: 8px; font-size: 0.9rem; color: #64748b; }
            .rows-per-page { padding: 6px 28px 6px 12px; border: 1px solid #e2e8f0; border-radius: 6px; font-size: 0.85rem; color: #475569; background-color: #fff; appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%2364748b'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 8px center; background-size: 14px; outline: none; }

            @media (max-width: 1024px) {
                .admin-metric-grid { grid-template-columns: repeat(2, 1fr); }
            }
            @media (max-width: 768px) {
                .admin-header-row { flex-direction: column; gap: 16px; }
                .admin-metric-grid { grid-template-columns: 1fr; }
                .admin-filter-bar { flex-direction: column; }
                .search-wrapper { max-width: 100%; }
                .pagination-container { flex-direction: column; gap: 16px; align-items: flex-start; }
                .admin-table-wrap { overflow-x: auto; }
            }
        </style>

        <div class="admin-header-row">
            <div class="admin-header-text">
                <h1>Transactions</h1>
                <p>View and manage all payments and transactions.</p>
            </div>
            <button class="btn-export">
                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                Export
                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
            </button>
        </div>

        <div class="admin-metric-grid">
            <div class="insight-card">
                <div class="insight-icon blue">
                    <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path></svg>
                </div>
                <div class="insight-data">
                    <p class="insight-label">Total Transactions</p>
                    <p class="insight-value"><?= number_format((int)$stats['total']) ?></p>
                    <p class="insight-sub">All time</p>
                </div>
            </div>
            <div class="insight-card">
                <div class="insight-icon green">
                    <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                </div>
                <div class="insight-data">
                    <p class="insight-label">Total Amount</p>
                    <p class="insight-value">$<?= number_format((float)$totalRevenue, 2) ?></p>
                    <p class="insight-sub">All time</p>
                </div>
            </div>
            <div class="insight-card">
                <div class="insight-icon orange">
                    <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path></svg>
                </div>
                <div class="insight-data">
                    <p class="insight-label">Completed</p>
                    <p class="insight-value"><?= number_format((int)$stats['completed']) ?></p>
                    <?php $pctCompleted = $stats['total'] > 0 ? round(($stats['completed'] / $stats['total']) * 100, 1) : 0; ?>
                    <p class="insight-sub"><?= $pctCompleted ?>% of total</p>
                </div>
            </div>
            <div class="insight-card">
                <div class="insight-icon red">
                    <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                </div>
                <div class="insight-data">
                    <p class="insight-label">Failed / Refunded</p>
                    <p class="insight-value"><?= number_format((int)$stats['failed']) ?></p>
                    <?php $pctFailed = $stats['total'] > 0 ? round(($stats['failed'] / $stats['total']) * 100, 1) : 0; ?>
                    <p class="insight-sub"><?= $pctFailed ?>% of total</p>
                </div>
            </div>
        </div>

        <div class="admin-grid">
            <div class="admin-panel-card">
                
                <form method="GET" class="admin-filter-bar">
                    <div class="search-wrapper">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                        <input type="text" name="search" class="filter-input search-input" placeholder="Search by provider name or email..." value="<?= htmlspecialchars($transactionSearch) ?>">
                    </div>
                    <select name="status" class="filter-select">
                        <option value="">All Status</option>
                        <option value="pending" <?= $transactionStatus === 'pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="completed" <?= $transactionStatus === 'completed' ? 'selected' : '' ?>>Completed</option>
                        <option value="failed" <?= $transactionStatus === 'failed' ? 'selected' : '' ?>>Failed</option>
                    </select>
                    
                    <div class="date-wrapper" style="display: flex; align-items: center; border: 1px solid #e2e8f0; border-radius: 8px; padding: 0 16px; background: #fff; gap: 12px; font-size: 0.95rem; color: #334155;">
                        <span>05/01/2025 - 05/31/2025</span>
                        <svg width="18" height="18" fill="none" stroke="#94a3b8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
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
                                <th>Transaction ID <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="vertical-align: middle; margin-left:4px;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l4-4 4 4m0 6l-4 4-4-4"></path></svg></th>
                                <th>Booking ID <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="vertical-align: middle; margin-left:4px;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l4-4 4 4m0 6l-4 4-4-4"></path></svg></th>
                                <th>Customer <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="vertical-align: middle; margin-left:4px;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l4-4 4 4m0 6l-4 4-4-4"></path></svg></th>
                                <th>Provider <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="vertical-align: middle; margin-left:4px;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l4-4 4 4m0 6l-4 4-4-4"></path></svg></th>
                                <th>Amount <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="vertical-align: middle; margin-left:4px;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l4-4 4 4m0 6l-4 4-4-4"></path></svg></th>
                                <th>Status</th>
                                <th>Date <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="vertical-align: middle; margin-left:4px;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l4-4 4 4m0 6l-4 4-4-4"></path></svg></th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($transactions)): ?>
                                <tr>
                                    <td colspan="8">
                                        <div style="text-align: center; padding: 48px 0; color: #64748b;">
                                            <p style="margin: 0;">No transactions found.</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($transactions as $tx): ?>
                                <?php
                                    $statusClass = 'badge-pending';
                                    if ($tx['status'] === 'completed') $statusClass = 'badge-completed';
                                    if ($tx['status'] === 'failed') $statusClass = 'badge-failed';
                                ?>
                                <tr>
                                    <td>TXN-<?= str_pad((string)$tx['id'], 6, '0', STR_PAD_LEFT) ?></td>
                                    <td><a href="#" style="color: #4f46e5; text-decoration: none; font-weight: 500;">#BK-<?= str_pad((string)$tx['id'], 6, '0', STR_PAD_LEFT) ?></a></td>
                                    <td>
                                        <div class="user-profile-cell">
                                            <div class="user-avatar" style="background:#eef2ff; color:#4f46e5;">
                                                N
                                            </div>
                                            <div>
                                                <div style="font-weight: 500; color: #1e293b;">N/A</div>
                                                <div style="font-size: 0.8rem; color: #64748b;">-</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="user-profile-cell">
                                            <div class="user-avatar cyan">
                                                <?= htmlspecialchars(strtoupper(substr($tx['full_name'] ?? 'U', 0, 1))) ?>
                                            </div>
                                            <div>
                                                <div style="font-weight: 500; color: #1e293b;"><?= htmlspecialchars($tx['full_name'] ?? 'Unknown') ?></div>
                                                <div style="font-size: 0.8rem; color: #64748b;"><?= htmlspecialchars($tx['email'] ?? '') ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td style="font-weight: 500;">₱<?= number_format((float)$tx['amount'], 2) ?></td>
                                    <td>
                                        <span class="modern-badge <?= $statusClass ?>">
                                            <?= htmlspecialchars(ucfirst((string)$tx['status'])) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div style="color: #1e293b;"><?= htmlspecialchars(date('M d, Y', strtotime($tx['created_at']))) ?></div>
                                        <div style="font-size: 0.85rem; color: #64748b;"><?= htmlspecialchars(date('h:i A', strtotime($tx['created_at']))) ?></div>
                                    </td>
                                    <td>
                                        <button type="button" class="btn-action-kebab">
                                            <svg width="20" height="20" fill="currentColor" viewBox="0 0 24 24"><path d="M12 8c1.1 0 2-.9 2-2s-.9-2-2-2-2 .9-2 2 .9 2 2 2zm0 2c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zm0 6c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2z"/></svg>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="pagination-container">
                    <div class="pagination-info">
                        Showing <?= count($transactions) ?> of <?= count($transactions) ?> transactions
                    </div>
                    
                    <div class="pagination-flex">
                        <div class="pagination-controls">
                            <a class="page-btn" href="#" style="pointer-events: none; opacity: 0.5;">&lsaquo;</a>
                            <a class="page-btn active" href="#">1</a>
                            <a class="page-btn" href="#" style="pointer-events: none; opacity: 0.5;">&rsaquo;</a>
                        </div>
                        
                        <div class="rows-per-page-wrap">
                            Rows per page:
                            <select class="rows-per-page">
                                <option value="100">100</option>
                            </select>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</section>
<?php require_once 'includes/footer.php'; ?>
