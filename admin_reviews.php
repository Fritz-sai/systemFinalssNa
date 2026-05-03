<?php
$pageTitle = 'Admin Reviews';
require_once 'config/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

$pdo = getDBConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $bookingId = (int)($_POST['booking_id'] ?? 0);
    if ($action === 'delete_review' && $bookingId > 0) {
        $pdo->prepare("
            UPDATE bookings
            SET review = NULL, rating = NULL, review_photo_path = NULL
            WHERE id = ?
        ")->execute([$bookingId]);
    }
}

$search = trim((string)($_GET['q'] ?? ''));
$ratingFilter = trim((string)($_GET['rating'] ?? ''));
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;
$offset = ($page - 1) * $perPage;

$where = " WHERE b.review IS NOT NULL ";
$params = [];
if ($search !== '') {
    $where .= " AND (cu.full_name LIKE ? OR pu.full_name LIKE ? OR s.title LIKE ? OR b.review LIKE ? OR b.id = ?) ";
    $kw = '%' . $search . '%';
    $params[] = $kw;
    $params[] = $kw;
    $params[] = $kw;
    $params[] = $kw;
    $params[] = ctype_digit($search) ? (int)$search : 0;
}
if ($ratingFilter !== '') {
    $where .= " AND b.rating = ? ";
    $params[] = (int)$ratingFilter;
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
$totalReviews = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalReviews / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
    $offset = ($page - 1) * $perPage;
}

$sql = "
    SELECT b.id, b.rating, b.review, b.review_photo_path, b.created_at,
           cu.full_name AS customer_name,
           pu.full_name AS provider_name,
           s.title AS service_title
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
    $stmt->bindValue($idx + 1, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$stmt->bindValue(count($params) + 1, $perPage, PDO::PARAM_INT);
$stmt->bindValue(count($params) + 2, $offset, PDO::PARAM_INT);
$stmt->execute();
$reviews = $stmt->fetchAll();

$avgRating = (float)($pdo->query("SELECT COALESCE(AVG(rating), 0) FROM bookings WHERE review IS NOT NULL AND rating IS NOT NULL")->fetchColumn() ?: 0);
$totalRated = (int)($pdo->query("SELECT COUNT(*) FROM bookings WHERE review IS NOT NULL AND rating IS NOT NULL")->fetchColumn() ?: 0);
$distributionRows = $pdo->query("
    SELECT rating, COUNT(*) AS total
    FROM bookings
    WHERE review IS NOT NULL AND rating IS NOT NULL
    GROUP BY rating
")->fetchAll();
$distribution = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
foreach ($distributionRows as $row) {
    $r = (int)$row['rating'];
    if (isset($distribution[$r])) {
        $distribution[$r] = (int)$row['total'];
    }
}

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
        <a href="admin_reports.php" class="admin-nav-link">Reports</a>
        <a href="admin_reviews.php" class="admin-nav-link active">Reviews</a>
        <a href="admin_transactions.php" class="admin-nav-link">Transactions</a>
        <a href="admin_settings.php" class="admin-nav-link">Settings</a>
        <a href="logout.php" class="admin-nav-link">Log out</a>
        <div class="admin-side-user">Admin</div>
    </aside>

    <div class="admin-main">
        <div class="admin-topbar">
            <h1>Reviews Moderation</h1>
            <div class="admin-user-chip">Admin</div>
        </div>

        <style>
            /* Admin Reviews UI Improvements */
            .admin-panel-card {
                background: #ffffff;
                border-radius: 12px;
                box-shadow: 0 4px 20px rgba(0,0,0,0.03);
                border: 1px solid #f1f5f9;
                padding: 32px;
                margin-bottom: 24px;
            }
            .admin-card-head {
                margin-bottom: 24px;
                display: flex;
                align-items: center;
                gap: 12px;
            }
            .admin-card-head h2 {
                font-size: 1.25rem;
                font-weight: 600;
                color: #0f172a;
                margin: 0;
                display: flex;
                align-items: center;
                gap: 10px;
            }
            .admin-card-head h2 svg {
                color: #4f46e5;
                width: 20px;
                height: 20px;
            }

            /* Analytics Card Layout */
            .analytics-grid {
                display: grid;
                grid-template-columns: 1fr 1.5fr;
                gap: 48px;
            }
            
            .rating-overview {
                display: flex;
                flex-direction: column;
                gap: 16px;
            }
            .rating-overview-title {
                color: #64748b;
                font-size: 0.95rem;
                margin: 0;
            }
            .rating-score {
                display: flex;
                align-items: baseline;
                gap: 4px;
            }
            .rating-score h1 {
                font-size: 3rem;
                font-weight: 700;
                color: #0f172a;
                margin: 0;
                line-height: 1;
            }
            .rating-score span {
                font-size: 1.25rem;
                color: #64748b;
                font-weight: 500;
            }
            .rating-stars {
                display: flex;
                gap: 4px;
                color: #e2e8f0;
            }
            .rating-stars .active {
                color: #cbd5e1; /* Mockup shows grey stars if 0 */
            }
            .rating-stars .filled {
                color: #fbbf24;
            }
            
            .rating-mini-cards {
                display: flex;
                gap: 16px;
                margin-top: 16px;
            }
            .mini-card {
                flex: 1;
                background: #f8fafc;
                border-radius: 12px;
                padding: 16px;
                display: flex;
                align-items: center;
                gap: 12px;
            }
            .mini-card.blue { background: #f5f8ff; }
            .mini-card.green { background: #f0fdf4; }
            .mini-icon {
                width: 32px;
                height: 32px;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            .mini-card.blue .mini-icon { background: #e0e7ff; color: #4f46e5; }
            .mini-card.green .mini-icon { background: #dcfce7; color: #16a34a; }
            .mini-details p { margin: 0; font-size: 0.8rem; color: #64748b; }
            .mini-details h4 { margin: 4px 0 0 0; font-size: 1.1rem; color: #0f172a; font-weight: 600; }

            .rating-distribution h3 {
                font-size: 1.05rem;
                color: #475569;
                font-weight: 500;
                margin: 0 0 20px 0;
            }
            .dist-row {
                display: grid;
                grid-template-columns: 50px 1fr 50px;
                gap: 16px;
                align-items: center;
                margin-bottom: 12px;
            }
            .dist-label {
                font-size: 0.9rem;
                color: #475569;
            }
            .dist-bar-wrap {
                height: 8px;
                background: #f1f5f9;
                border-radius: 999px;
                overflow: hidden;
            }
            .dist-bar {
                height: 100%;
                background: #eef2ff;
            }
            .dist-bar.filled {
                background: linear-gradient(90deg, #818cf8, #4f46e5);
            }
            .dist-val {
                font-size: 0.85rem;
                color: #64748b;
                text-align: right;
            }

            /* Moderation Tips Layout */
            .tips-grid {
                display: grid;
                grid-template-columns: repeat(3, 1fr);
                gap: 24px;
            }
            .tip-item {
                display: flex;
                gap: 16px;
                padding-right: 24px;
                border-right: 1px solid #f1f5f9;
            }
            .tip-item:last-child {
                border-right: none;
                padding-right: 0;
            }
            .tip-icon {
                width: 40px;
                height: 40px;
                border-radius: 50%;
                background: #fff7ed;
                color: #f97316;
                display: flex;
                align-items: center;
                justify-content: center;
                flex-shrink: 0;
            }
            .tip-content h4 {
                margin: 0 0 8px 0;
                font-size: 0.95rem;
                font-weight: 600;
                color: #1e293b;
            }
            .tip-content p {
                margin: 0;
                font-size: 0.85rem;
                color: #64748b;
                line-height: 1.5;
            }

            /* Filters and Table */
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
            .btn-filter {
                display: flex; align-items: center; gap: 8px; padding: 10px 20px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 0.95rem; font-weight: 500; color: #4f46e5; background: #eef2ff; cursor: pointer; transition: all 0.2s;
            }
            .btn-filter:hover { background: #e0e7ff; border-color: #c7d2fe; }

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

            /* Empty state */
            .empty-state { text-align: center; padding: 48px 0; color: #64748b; }
            .empty-state svg { width: 48px; height: 48px; color: #cbd5e1; margin-bottom: 16px; }
            .empty-state p { margin: 0; font-size: 1rem; }

            /* Star Badge */
            .star-badge {
                display: inline-flex;
                align-items: center;
                gap: 4px;
                background: #fffbeb;
                color: #d97706;
                padding: 4px 10px;
                border-radius: 999px;
                font-size: 0.85rem;
                font-weight: 600;
            }

            .btn-delete {
                background: #fff;
                border: 1px solid #e2e8f0;
                color: #ef4444;
                padding: 6px 12px;
                border-radius: 6px;
                font-size: 0.85rem;
                font-weight: 500;
                cursor: pointer;
                transition: all 0.2s;
            }
            .btn-delete:hover {
                background: #fef2f2;
                border-color: #fca5a5;
            }

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
                .analytics-grid { grid-template-columns: 1fr; gap: 32px; }
                .tips-grid { grid-template-columns: 1fr; gap: 24px; }
                .tip-item { border-right: none; padding-right: 0; padding-bottom: 24px; border-bottom: 1px solid #f1f5f9; }
                .tip-item:last-child { border-bottom: none; padding-bottom: 0; }
            }
            @media (max-width: 768px) {
                .admin-filter-bar { flex-direction: column; }
                .search-wrapper { max-width: 100%; }
                .pagination-container { flex-direction: column; gap: 16px; align-items: flex-start; }
                .admin-table-wrap { overflow-x: auto; }
            }
        </style>

        <div class="admin-panel-card">
            <div class="admin-card-head">
                <h2>
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
                    Ratings Analytics
                </h2>
            </div>
            
            <div class="analytics-grid">
                <div>
                    <div class="rating-overview">
                        <p class="rating-overview-title">Average Rating</p>
                        <div class="rating-score">
                            <h1><?= number_format($avgRating, 2) ?></h1>
                            <span>/5</span>
                        </div>
                        <div class="rating-stars">
                            <?php 
                            $roundedAvg = round($avgRating);
                            for ($i = 1; $i <= 5; $i++): 
                                $activeClass = $i <= $roundedAvg && $avgRating > 0 ? 'filled' : 'active';
                            ?>
                            <svg class="<?= $activeClass ?>" width="24" height="24" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                            <?php endfor; ?>
                        </div>
                    </div>
                    <div class="rating-mini-cards">
                        <div class="mini-card blue">
                            <div class="mini-icon">
                                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                            </div>
                            <div class="mini-details">
                                <p>Total Rated Reviews</p>
                                <h4><?= number_format($totalRated) ?></h4>
                            </div>
                        </div>
                        <div class="mini-card green">
                            <div class="mini-icon">
                                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            </div>
                            <div class="mini-details">
                                <p>Visible Reviews</p>
                                <h4><?= number_format($totalReviews) ?></h4>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="rating-distribution">
                    <h3>Rating Distribution</h3>
                    <?php for ($star = 5; $star >= 1; $star--): ?>
                        <?php
                        $count = $distribution[$star];
                        $pct = $totalRated > 0 ? (int)round(($count / $totalRated) * 100) : 0;
                        ?>
                        <div class="dist-row">
                            <span class="dist-label"><?= $star ?> star</span>
                            <div class="dist-bar-wrap">
                                <div class="dist-bar <?= $count > 0 ? 'filled' : '' ?>" style="width: <?= $pct ?>%;"></div>
                            </div>
                            <span class="dist-val"><?= $count ?> (<?= $pct ?>%)</span>
                        </div>
                    <?php endfor; ?>
                </div>
            </div>
        </div>

        <div class="admin-panel-card">
            <div class="admin-card-head">
                <h2>
                    <svg fill="none" stroke="#f97316" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                    Moderation Tips
                </h2>
            </div>
            <div class="tips-grid">
                <div class="tip-item">
                    <div class="tip-icon"><svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg></div>
                    <div class="tip-content">
                        <h4>Remove Fake or Offensive Reviews</h4>
                        <p>Delete only fake or offensive reviews to preserve trust.</p>
                    </div>
                </div>
                <div class="tip-item">
                    <div class="tip-icon"><svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg></div>
                    <div class="tip-content">
                        <h4>Check for Spam</h4>
                        <p>Use search to check repeated spam patterns from same user/service.</p>
                    </div>
                </div>
                <div class="tip-item">
                    <div class="tip-icon"><svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg></div>
                    <div class="tip-content">
                        <h4>Coordinate Actions</h4>
                        <p>Coordinate with reports tab for provider warning/suspension actions.</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="admin-panel-card">
            <div class="admin-card-head">
                <h2>
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03-8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path></svg>
                    Reviews Table
                </h2>
            </div>
            
            <form method="GET" class="admin-filter-bar">
                <div class="search-wrapper">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    <input type="text" name="q" class="filter-input search-input" placeholder="Search by customer, provider, or service" value="<?= htmlspecialchars($search) ?>">
                </div>
                <select name="rating" class="filter-select">
                    <option value="">All Ratings</option>
                    <option value="5" <?= $ratingFilter === '5' ? 'selected' : '' ?>>5 Stars</option>
                    <option value="4" <?= $ratingFilter === '4' ? 'selected' : '' ?>>4 Stars</option>
                    <option value="3" <?= $ratingFilter === '3' ? 'selected' : '' ?>>3 Stars</option>
                    <option value="2" <?= $ratingFilter === '2' ? 'selected' : '' ?>>2 Stars</option>
                    <option value="1" <?= $ratingFilter === '1' ? 'selected' : '' ?>>1 Star</option>
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
                            <th>Booking <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="vertical-align: middle; margin-left:4px;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l4-4 4 4m0 6l-4 4-4-4"></path></svg></th>
                            <th>Customer <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="vertical-align: middle; margin-left:4px;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l4-4 4 4m0 6l-4 4-4-4"></path></svg></th>
                            <th>Provider <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="vertical-align: middle; margin-left:4px;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l4-4 4 4m0 6l-4 4-4-4"></path></svg></th>
                            <th>Service <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="vertical-align: middle; margin-left:4px;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l4-4 4 4m0 6l-4 4-4-4"></path></svg></th>
                            <th>Rating <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="vertical-align: middle; margin-left:4px;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l4-4 4 4m0 6l-4 4-4-4"></path></svg></th>
                            <th>Review <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="vertical-align: middle; margin-left:4px;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l4-4 4 4m0 6l-4 4-4-4"></path></svg></th>
                            <th>Action <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="vertical-align: middle; margin-left:4px;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l4-4 4 4m0 6l-4 4-4-4"></path></svg></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($reviews)): ?>
                            <tr>
                                <td colspan="7">
                                    <div class="empty-state">
                                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                                        <p>No reviews found.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($reviews as $review): ?>
                                <tr>
                                    <td>#<?= (int)$review['id'] ?></td>
                                    <td><?= htmlspecialchars((string)$review['customer_name']) ?></td>
                                    <td><?= htmlspecialchars((string)$review['provider_name']) ?></td>
                                    <td><?= htmlspecialchars((string)($review['service_title'] ?? 'Unknown service')) ?></td>
                                    <td>
                                        <span class="star-badge">
                                            <svg width="14" height="14" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                                            <?= number_format((int)($review['rating'] ?? 0)) ?>/5
                                        </span>
                                    </td>
                                    <td>
                                        <div style="max-width: 360px;">
                                            <?= htmlspecialchars((string)$review['review']) ?>
                                            <?php if (!empty($review['review_photo_path'])): ?>
                                                <div class="small-muted" style="margin-top:0.25rem;"><a href="<?= htmlspecialchars($review['review_photo_path']) ?>" target="_blank">View Photo</a></div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <form method="POST" onsubmit="return confirm('Delete this review? This will remove text, rating, and review image.');" style="margin:0;">
                                            <input type="hidden" name="booking_id" value="<?= (int)$review['id'] ?>">
                                            <button type="submit" name="action" value="delete_review" class="btn-delete">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="pagination-container">
                <div class="pagination-info">
                    <?php if (count($reviews) > 0): ?>
                        Showing <?= min($offset + 1, $totalReviews) ?> to <?= min($offset + count($reviews), $totalReviews) ?> of <?= number_format($totalReviews) ?> reviews
                    <?php else: ?>
                        No reviews to display
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
</section>
<?php require_once 'includes/footer.php'; ?>
