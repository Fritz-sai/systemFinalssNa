<?php
$pageTitle = 'Admin Users';
require_once 'config/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

$pdo = getDBConnection();

// Ensure account_state column exists for suspend/ban management
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
    // Ignore schema-update issues and continue with current structure.
}

// Handle user actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $userId = (int)($_POST['user_id'] ?? 0);
    if ($userId > 0) {
        if ($action === 'delete_user') {
            $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'customer'")->execute([$userId]);
        } elseif ($action === 'suspend_user') {
            $pdo->prepare("UPDATE users SET account_state = 'suspended' WHERE id = ? AND role = 'customer'")->execute([$userId]);
        } elseif ($action === 'ban_user') {
            $pdo->prepare("UPDATE users SET account_state = 'banned' WHERE id = ? AND role = 'customer'")->execute([$userId]);
        } elseif ($action === 'activate_user') {
            $pdo->prepare("UPDATE users SET account_state = 'active' WHERE id = ? AND role = 'customer'")->execute([$userId]);
        }
    }
}

// Customers list for admin management
$userSearch = trim((string)($_GET['user_q'] ?? ''));
$userStatus = trim((string)($_GET['user_status'] ?? ''));
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;
$offset = ($page - 1) * $perPage;

$whereClause = " WHERE role = 'customer' ";
$customerParams = [];

if ($userSearch !== '') {
    $whereClause .= " AND (full_name LIKE ? OR email LIKE ? OR phone LIKE ?)";
    $kw = '%' . $userSearch . '%';
    $customerParams[] = $kw;
    $customerParams[] = $kw;
    $customerParams[] = $kw;
}

if ($userStatus === 'active') {
    $whereClause .= " AND COALESCE(account_state, 'active') = 'active'";
} elseif ($userStatus === 'suspended') {
    $whereClause .= " AND account_state = 'suspended'";
} elseif ($userStatus === 'banned') {
    $whereClause .= " AND account_state = 'banned'";
} elseif ($userStatus === 'verified') {
    $whereClause .= " AND email_verified = 1";
} elseif ($userStatus === 'unverified') {
    $whereClause .= " AND email_verified = 0";
}

$countSql = "SELECT COUNT(*) FROM users" . $whereClause;
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($customerParams);
$totalCustomers = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalCustomers / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
    $offset = ($page - 1) * $perPage;
}

$customersSql = "
    SELECT id, email, full_name, phone, created_at, email_verified, COALESCE(account_state, 'active') AS account_state, city, barangay,
           (
               SELECT COUNT(*)
               FROM bookings b
               WHERE b.customer_id = users.id
           ) AS booking_count,
           (
               SELECT MAX(b2.created_at)
               FROM bookings b2
               WHERE b2.customer_id = users.id
           ) AS last_booking_at
    FROM users
";
$customersSql .= $whereClause;
$customersSql .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
$customersStmt = $pdo->prepare($customersSql);
foreach ($customerParams as $idx => $value) {
    $customersStmt->bindValue($idx + 1, $value, PDO::PARAM_STR);
}
$customersStmt->bindValue(count($customerParams) + 1, $perPage, PDO::PARAM_INT);
$customersStmt->bindValue(count($customerParams) + 2, $offset, PDO::PARAM_INT);
$customersStmt->execute();
$customers = $customersStmt->fetchAll();

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
        <a href="admin_users.php" class="admin-nav-link active">Users</a>
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
            <h1>Users</h1>
            <div class="admin-user-chip">Admin</div>
        </div>

        <style>
            /* Admin Users UI Improvements */
            .admin-panel-card {
                background: #ffffff;
                border-radius: 12px;
                box-shadow: 0 4px 20px rgba(0,0,0,0.03);
                border: 1px solid #f1f5f9;
                padding: 32px;
            }

            .admin-card-head {
                margin-bottom: 24px;
                display: flex;
                justify-content: space-between;
                align-items: flex-start;
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

            .header-action-btn {
                padding: 10px 20px;
                background: #3b82f6;
                color: #fff;
                border-radius: 8px;
                font-weight: 500;
                font-size: 0.95rem;
                display: inline-flex;
                align-items: center;
                gap: 8px;
                text-decoration: none;
                transition: background 0.2s;
                border: none;
                cursor: pointer;
            }
            .header-action-btn:hover {
                background: #2563eb;
            }

            .admin-filter-bar {
                display: flex;
                gap: 16px;
                margin-bottom: 24px;
                flex-wrap: wrap;
            }

            .search-wrapper {
                position: relative;
                flex-grow: 1;
                max-width: 400px;
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
            .search-input {
                width: 100%;
                padding: 10px 16px 10px 42px;
                border: 1px solid #e2e8f0;
                border-radius: 8px;
                font-size: 0.95rem;
                color: #334155;
                outline: none;
                transition: border-color 0.2s, box-shadow 0.2s;
            }
            .search-input:focus {
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
                min-width: 180px;
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
                color: #334155;
                background: #fff;
                cursor: pointer;
                transition: all 0.2s;
            }
            .btn-filter:hover {
                background: #f8fafc;
                border-color: #cbd5e1;
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
                font-size: 0.85rem;
                padding: 16px;
                border-bottom: 1px solid #e2e8f0;
                background: #fafbfc;
                text-align: left;
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
                width: 40px;
                height: 40px;
                border-radius: 50%;
                background: #e0e7ff;
                color: #4338ca;
                display: flex;
                align-items: center;
                justify-content: center;
                font-weight: 600;
                font-size: 1.1rem;
            }

            /* Badges */
            .modern-badge {
                display: inline-flex;
                align-items: center;
                gap: 6px;
                padding: 4px 10px;
                border-radius: 999px;
                font-size: 0.75rem;
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
            .badge-active { background: #ecfdf5; color: #059669; }
            .badge-active::before { background: #10b981; }
            
            .badge-suspended { background: #f1f5f9; color: #475569; }
            .badge-suspended::before { background: #64748b; }
            
            .badge-banned { background: #fef2f2; color: #dc2626; }
            .badge-banned::before { background: #ef4444; }

            .badge-verified { background: #ecfdf5; color: #059669; }
            .badge-verified::before { background: #10b981; }

            .badge-unverified { background: #fffbeb; color: #d97706; }
            .badge-unverified::before { background: #f59e0b; }

            .badge-wrapper {
                display: flex;
                flex-direction: column;
                gap: 6px;
                align-items: flex-start;
            }

            /* Kebab Menu Actions */
            .action-menu-wrapper {
                position: relative;
            }
            .btn-action-trigger {
                display: flex;
                align-items: center;
                gap: 8px;
                padding: 8px 12px;
                border: 1px solid #e2e8f0;
                border-radius: 6px;
                background: #fff;
                color: #475569;
                font-size: 0.85rem;
                cursor: pointer;
                transition: all 0.2s;
            }
            .btn-action-trigger:hover {
                background: #f8fafc;
                border-color: #cbd5e1;
            }
            .action-dropdown {
                position: absolute;
                top: calc(100% + 4px);
                right: 0;
                background: #fff;
                border: 1px solid #e2e8f0;
                border-radius: 8px;
                box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1), 0 4px 6px -2px rgba(0,0,0,0.05);
                min-width: 160px;
                z-index: 50;
                padding: 6px;
                display: none;
            }
            .action-dropdown.show {
                display: block;
            }
            .dropdown-item {
                display: flex;
                align-items: center;
                gap: 8px;
                width: 100%;
                text-align: left;
                padding: 8px 12px;
                font-size: 0.85rem;
                color: #334155;
                background: none;
                border: none;
                border-radius: 4px;
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
            .dropdown-item.danger {
                color: #ef4444;
            }
            .dropdown-item.danger svg {
                color: #ef4444;
            }
            .dropdown-item.danger:hover {
                background: #fef2f2;
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
                min-width: 36px;
                height: 36px;
                border: 1px solid #e2e8f0;
                border-radius: 6px;
                background: #fff;
                color: #475569;
                font-size: 0.9rem;
                font-weight: 500;
                text-decoration: none;
                transition: all 0.2s;
            }
            .page-btn:hover {
                background: #f8fafc;
                border-color: #cbd5e1;
            }
            .page-btn.active {
                background: #3b82f6;
                color: #fff;
                border-color: #3b82f6;
            }
            .rows-per-page {
                padding: 6px 30px 6px 12px;
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
                margin-left: 12px;
            }

            @media (max-width: 768px) {
                .admin-card-head { flex-direction: column; gap: 16px; }
                .admin-filter-bar { flex-direction: column; }
                .search-wrapper { max-width: 100%; }
                .pagination-container { flex-direction: column; gap: 16px; align-items: flex-start; }
                .admin-table-wrap { overflow-x: auto; }
            }
        </style>

        <div class="admin-grid">
            <div class="admin-panel-card">
                <div class="admin-card-head">
                    <div>
                        <h2>Customer Management</h2>
                        <p>Manage and monitor your customers in one place.</p>
                    </div>
                    <button class="header-action-btn">
                        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                        Add Customer
                    </button>
                </div>

                <form method="GET" class="admin-filter-bar">
                    <div class="search-wrapper">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                        <input type="text" name="user_q" class="search-input" placeholder="Search by name, email, or phone..." value="<?= htmlspecialchars($userSearch) ?>">
                    </div>
                    <select name="user_status" class="filter-select">
                        <option value="">All Status</option>
                        <option value="active" <?= $userStatus === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="suspended" <?= $userStatus === 'suspended' ? 'selected' : '' ?>>Suspended</option>
                        <option value="banned" <?= $userStatus === 'banned' ? 'selected' : '' ?>>Banned</option>
                        <option value="verified" <?= $userStatus === 'verified' ? 'selected' : '' ?>>Verified Email</option>
                        <option value="unverified" <?= $userStatus === 'unverified' ? 'selected' : '' ?>>Unverified Email</option>
                    </select>
                    <button type="submit" class="btn-filter">
                        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path></svg>
                        Filter
                    </button>
                </form>

                <div class="admin-table-wrap">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Status</th>
                                <th>Date Joined &darr;</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($customers)): ?>
                                <tr><td colspan="6" style="color: var(--text-muted); text-align: center;">No customers found.</td></tr>
                            <?php else: ?>
                                <?php foreach ($customers as $user): ?>
                                <tr>
                                    <td>
                                        <div class="user-profile-cell">
                                            <div class="user-avatar">
                                                <?= htmlspecialchars(strtoupper(substr($user['full_name'], 0, 1))) ?>
                                            </div>
                                            <strong><?= htmlspecialchars($user['full_name']) ?></strong>
                                        </div>
                                    </td>
                                    <td><?= htmlspecialchars($user['email']) ?></td>
                                    <td><?= htmlspecialchars($user['phone']) ?></td>
                                    <td>
                                        <div class="badge-wrapper">
                                            <?php
                                            $state = strtolower((string)$user['account_state']);
                                            $badgeClass = 'badge-active';
                                            $badgeLabel = 'Active';
                                            if ($state === 'suspended') {
                                                $badgeClass = 'badge-suspended';
                                                $badgeLabel = 'Suspended';
                                            } elseif ($state === 'banned') {
                                                $badgeClass = 'badge-banned';
                                                $badgeLabel = 'Banned';
                                            }
                                            ?>
                                            <span class="modern-badge <?= $badgeClass ?>">
                                                <?= htmlspecialchars($badgeLabel) ?>
                                            </span>
                                            <span class="modern-badge <?= !empty($user['email_verified']) ? 'badge-verified' : 'badge-unverified' ?>">
                                                <?= !empty($user['email_verified']) ? 'Verified Email' : 'Unverified Email' ?>
                                            </span>
                                        </div>
                                    </td>
                                    <td><?= htmlspecialchars(date('M d, Y', strtotime($user['created_at']))) ?></td>
                                    <td>
                                        <div class="action-menu-wrapper">
                                            <button type="button" class="btn-action-trigger" onclick="toggleDropdown(this)">
                                                Actions 
                                                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                                            </button>
                                            <div class="action-dropdown">
                                                <button
                                                    type="button"
                                                    class="dropdown-item btn-user-details"
                                                    data-user='<?= htmlspecialchars(json_encode([
                                                        'id' => (int)$user['id'],
                                                        'name' => (string)$user['full_name'],
                                                        'email' => (string)$user['email'],
                                                        'phone' => (string)$user['phone'],
                                                        'created_at' => (string)$user['created_at'],
                                                        'email_verified' => !empty($user['email_verified']) ? 'Yes' : 'No',
                                                        'account_state' => ucfirst((string)$user['account_state']),
                                                        'city' => (string)($user['city'] ?? ''),
                                                        'barangay' => (string)($user['barangay'] ?? ''),
                                                        'booking_count' => (int)($user['booking_count'] ?? 0),
                                                        'last_booking_at' => (string)($user['last_booking_at'] ?? '')
                                                    ]), ENT_QUOTES, 'UTF-8') ?>'
                                                >
                                                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                                                    View
                                                </button>

                                                <?php if ($state !== 'suspended'): ?>
                                                    <form method="POST" onsubmit="return confirm('Suspend this user account?');" style="margin:0;">
                                                        <input type="hidden" name="user_id" value="<?= (int)$user['id'] ?>">
                                                        <button type="submit" name="action" value="suspend_user" class="dropdown-item">
                                                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"></path></svg>
                                                            Suspend
                                                        </button>
                                                    </form>
                                                <?php endif; ?>

                                                <?php if ($state !== 'banned'): ?>
                                                    <form method="POST" onsubmit="return confirm('Ban this user account?');" style="margin:0;">
                                                        <input type="hidden" name="user_id" value="<?= (int)$user['id'] ?>">
                                                        <button type="submit" name="action" value="ban_user" class="dropdown-item">
                                                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                                                            Ban
                                                        </button>
                                                    </form>
                                                <?php endif; ?>

                                                <?php if ($state !== 'active'): ?>
                                                    <form method="POST" onsubmit="return confirm('Reactivate this user account?');" style="margin:0;">
                                                        <input type="hidden" name="user_id" value="<?= (int)$user['id'] ?>">
                                                        <button type="submit" name="action" value="activate_user" class="dropdown-item">
                                                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path></svg>
                                                            Activate
                                                        </button>
                                                    </form>
                                                <?php endif; ?>

                                                <form method="POST" onsubmit="return confirm('Delete this user? This cannot be undone.');" style="margin:0;">
                                                    <input type="hidden" name="user_id" value="<?= (int)$user['id'] ?>">
                                                    <button type="submit" name="action" value="delete_user" class="dropdown-item danger">
                                                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                                        Delete
                                                    </button>
                                                </form>
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
                        <?php if (count($customers) > 0): ?>
                            Showing <?= min($offset + 1, $totalCustomers) ?> to <?= min($offset + count($customers), $totalCustomers) ?> of <?= number_format($totalCustomers) ?> customers
                        <?php else: ?>
                            No customers to display
                        <?php endif; ?>
                    </div>
                    <div class="pagination-controls">
                        <a class="page-btn" href="?<?= htmlspecialchars($queryBase) ?>page=1" <?= $page <= 1 ? 'style="pointer-events: none; opacity: 0.5;"' : '' ?>>&laquo;</a>
                        <a class="page-btn" href="?<?= htmlspecialchars($queryBase) ?>page=<?= max(1, $page - 1) ?>" <?= $page <= 1 ? 'style="pointer-events: none; opacity: 0.5;"' : '' ?>>&lsaquo;</a>
                        
                        <a class="page-btn active" href="#"><?= $page ?></a>
                        
                        <a class="page-btn" href="?<?= htmlspecialchars($queryBase) ?>page=<?= min($totalPages, $page + 1) ?>" <?= $page >= $totalPages ? 'style="pointer-events: none; opacity: 0.5;"' : '' ?>>&rsaquo;</a>
                        <a class="page-btn" href="?<?= htmlspecialchars($queryBase) ?>page=<?= $totalPages ?>" <?= $page >= $totalPages ? 'style="pointer-events: none; opacity: 0.5;"' : '' ?>>&raquo;</a>
                        
                        <select class="rows-per-page">
                            <option value="10">10 / page</option>
                        </select>
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
    const modal = document.getElementById('userDetailsModal');
    const modalBody = document.getElementById('userDetailsBody');
    const closeBtn = document.getElementById('closeUserDetailsModal');
    const detailButtons = document.querySelectorAll('.btn-user-details');

    if (!modal || !modalBody || !closeBtn || detailButtons.length === 0) return;

    const row = (label, value) => '<div class="admin-modal-row"><strong>' + label + '</strong><span>' + value + '</span></div>';

    detailButtons.forEach(function (btn) {
        btn.addEventListener('click', function () {
            const payload = btn.getAttribute('data-user');
            if (!payload) return;
            let user;
            try {
                user = JSON.parse(payload);
            } catch (e) {
                return;
            }

            const lastBooking = user.last_booking_at ? user.last_booking_at : 'N/A';
            modalBody.innerHTML =
                row('User ID', String(user.id || '')) +
                row('Name', String(user.name || '')) +
                row('Email', String(user.email || '')) +
                row('Phone', String(user.phone || '')) +
                row('Account State', String(user.account_state || 'Active')) +
                row('Email Verified', String(user.email_verified || 'No')) +
                row('City', String(user.city || 'N/A')) +
                row('Barangay', String(user.barangay || 'N/A')) +
                row('Bookings', String(user.booking_count || 0)) +
                row('Last Booking', String(lastBooking)) +
                row('Joined', String(user.created_at || ''));

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
