<?php
$pageTitle = 'Admin Dashboard';
require_once 'config/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

$pdo = getDBConnection();

function safeCount(PDO $pdo, string $sql, array $params = []): int
{
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return (int)($stmt->fetchColumn() ?: 0);
    } catch (Throwable $e) {
        return 0;
    }
}

function safeSum(PDO $pdo, string $sql, array $params = []): float
{
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return (float)($stmt->fetchColumn() ?: 0);
    } catch (Throwable $e) {
        return 0.0;
    }
}

$thisMonthStart = date('Y-m-01 00:00:00');
$nextMonthStart = date('Y-m-01 00:00:00', strtotime('+1 month'));
$lastMonthStart = date('Y-m-01 00:00:00', strtotime('-1 month'));

$stats = [
    'users' => safeCount($pdo, "SELECT COUNT(*) FROM users"),
    'providers' => safeCount($pdo, "SELECT COUNT(*) FROM providers"),
    'active_bookings' => safeCount($pdo, "
        SELECT COUNT(*) FROM bookings
        WHERE LOWER(COALESCE(status, '')) NOT IN ('completed', 'cancelled', 'rejected', 'declined')
    "),
    'pending_verifications' => safeCount($pdo, "SELECT COUNT(*) FROM providers WHERE verification_status = 'pending'"),
    'reports' => safeCount($pdo, "
        SELECT COUNT(*) FROM bookings
        WHERE completion_confirmed = 'disputed' OR LOWER(COALESCE(status, '')) = 'reported'
    "),
    'active_ads' => safeCount($pdo, "SELECT COUNT(*) FROM ads WHERE status = 'active'"),
];

$revenue = [
    'total' => safeSum($pdo, "SELECT COALESCE(SUM(amount), 0) FROM credit_purchases WHERE status = 'completed'"),
    'this_month' => safeSum(
        $pdo,
        "SELECT COALESCE(SUM(amount), 0) FROM credit_purchases WHERE status = 'completed' AND created_at >= ? AND created_at < ?",
        [$thisMonthStart, $nextMonthStart]
    ),
    'last_month' => safeSum(
        $pdo,
        "SELECT COALESCE(SUM(amount), 0) FROM credit_purchases WHERE status = 'completed' AND created_at >= ? AND created_at < ?",
        [$lastMonthStart, $thisMonthStart]
    ),
];

$monthlyRevenueRows = [];
try {
    $monthlyRevenueRows = $pdo->query("
        SELECT DATE_FORMAT(created_at, '%Y-%m') AS month_key, COALESCE(SUM(amount), 0) AS total
        FROM credit_purchases
        WHERE status = 'completed' AND created_at >= DATE_SUB(CURDATE(), INTERVAL 5 MONTH)
        GROUP BY month_key
        ORDER BY month_key ASC
    ")->fetchAll();
} catch (Throwable $e) {
    $monthlyRevenueRows = [];
}

$monthlyBookingRows = [];
try {
    $monthlyBookingRows = $pdo->query("
        SELECT DATE_FORMAT(created_at, '%Y-%m') AS month_key, COUNT(*) AS total
        FROM bookings
        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 5 MONTH)
        GROUP BY month_key
        ORDER BY month_key ASC
    ")->fetchAll();
} catch (Throwable $e) {
    $monthlyBookingRows = [];
}

$chartMonths = [];
for ($i = 5; $i >= 0; $i--) {
    $monthKey = date('Y-m', strtotime("-{$i} month"));
    $chartMonths[] = [
        'key' => $monthKey,
        'label' => date('M', strtotime($monthKey . '-01')),
    ];
}

$monthlyRevenueMap = [];
foreach ($monthlyRevenueRows as $row) {
    $monthlyRevenueMap[(string)$row['month_key']] = (float)$row['total'];
}
$monthlyBookingMap = [];
foreach ($monthlyBookingRows as $row) {
    $monthlyBookingMap[(string)$row['month_key']] = (int)$row['total'];
}

$analyticsData = [];
$revenuePeak = 1.0;
$bookingPeak = 1;
foreach ($chartMonths as $m) {
    $revValue = $monthlyRevenueMap[$m['key']] ?? 0.0;
    $bookValue = $monthlyBookingMap[$m['key']] ?? 0;
    $analyticsData[] = [
        'label' => $m['label'],
        'revenue' => $revValue,
        'bookings' => $bookValue,
    ];
    if ($revValue > $revenuePeak) {
        $revenuePeak = $revValue;
    }
    if ($bookValue > $bookingPeak) {
        $bookingPeak = $bookValue;
    }
}

$recentActivities = [];
try {
    $recentActivities = $pdo->query("
        (SELECT u.created_at AS happened_at, 'user' AS activity_type, CONCAT(u.full_name, ' joined as customer') AS activity_text
         FROM users u
         WHERE u.role = 'customer')
        UNION ALL
        (SELECT p.created_at AS happened_at, 'provider' AS activity_type, CONCAT(u.full_name, ' registered as provider') AS activity_text
         FROM providers p
         JOIN users u ON u.id = p.user_id)
        UNION ALL
        (SELECT b.created_at AS happened_at, 'booking' AS activity_type, CONCAT('Booking #', b.id, ' status: ', COALESCE(b.status, 'pending')) AS activity_text
         FROM bookings b)
        UNION ALL
        (SELECT cp.created_at AS happened_at, 'transaction' AS activity_type, CONCAT('Credit purchase #', cp.id, ' (', cp.status, ')') AS activity_text
         FROM credit_purchases cp)
        ORDER BY happened_at DESC
        LIMIT 10
    ")->fetchAll();
} catch (Throwable $e) {
    $recentActivities = [];
}

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
        <a href="admin_dashboard.php" class="admin-nav-link active">Dashboard</a>
        <a href="admin_providers.php" class="admin-nav-link">Providers</a>
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
            <h1>Dashboard</h1>
            <div class="admin-user-chip">Admin</div>
        </div>

        <div class="admin-metric-grid">
            <div class="admin-metric-card">
                <p class="label">Total Users</p>
                <h3><?= number_format((int)$stats['users']) ?></h3>
            </div>
            <div class="admin-metric-card">
                <p class="label">Total Providers</p>
                <h3><?= number_format((int)$stats['providers']) ?></h3>
            </div>
            <div class="admin-metric-card">
                <p class="label">Active Bookings</p>
                <h3><?= number_format((int)$stats['active_bookings']) ?></h3>
            </div>
            <div class="admin-metric-card">
                <p class="label">Pending Verifications</p>
                <h3><?= number_format((int)$stats['pending_verifications']) ?></h3>
            </div>
            <div class="admin-metric-card">
                <p class="label">Reports Count</p>
                <h3><?= number_format((int)$stats['reports']) ?></h3>
            </div>
            <div class="admin-metric-card">
                <p class="label">Revenue Overview</p>
                <h3>$<?= number_format((float)$revenue['total'], 2) ?></h3>
            </div>
        </div>

        <style>
            /* Modern Analytics & Activity Styles */
            .admin-dashboard-insights {
               display: flex;
    flex-direction: column;
                gap: 24px;
                margin-bottom: 24px;
            }
            .admin-panel-card {
                background: #ffffff;
                border-radius: 12px;
                box-shadow: 0 4px 20px rgba(0,0,0,0.03);
                border: 1px solid #f1f5f9;
                padding: 24px;
                transition: transform 0.2s, box-shadow 0.2s;
            }
            .admin-panel-card:hover {
                box-shadow: 0 8px 30px rgba(0,0,0,0.05);
            }
            .admin-card-head {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 20px;
            }
            .admin-card-head h2 {
                font-size: 1.15rem;
                font-weight: 600;
                color: #0f172a;
                margin: 0;
            }
            .admin-filter-group {
                display: flex;
                gap: 12px;
                align-items: center;
            }
            .admin-select-modern {
                padding: 6px 12px;
                border: 1px solid #e2e8f0;
                border-radius: 6px;
                font-size: 0.85rem;
                color: #475569;
                background: #f8fafc;
                cursor: pointer;
                outline: none;
            }
            .btn-export-modern {
                padding: 6px 12px;
                border: 1px solid #e0e7ff;
                border-radius: 6px;
                font-size: 0.85rem;
                color: #4338ca;
                background: #eef2ff;
                font-weight: 500;
                cursor: pointer;
                transition: all 0.2s;
            }
            .btn-export-modern:hover {
                background: #e0e7ff;
            }
            .admin-analytics-grid {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 20px;
                margin-bottom: 24px;
            }
            .admin-chart-card {
                border: 1px solid #f1f5f9;
                border-radius: 12px;
                padding: 16px;
                background: #ffffff;
            }
            .admin-chart-card h3 {
                font-size: 0.95rem;
                color: #475569;
                margin: 0 0 16px 0;
                font-weight: 500;
            }
            .chart-container {
                position: relative;
                height: 220px;
                width: 100%;
            }
            .admin-revenue-overview {
                display: grid;
                grid-template-columns: repeat(4, 1fr);
                gap: 16px;
                border-top: 1px solid #f1f5f9;
                padding-top: 20px;
            }
            .stat-mini-card {
                display: flex;
                flex-direction: column;
                gap: 4px;
            }
            .stat-mini-card .small-muted {
                font-size: 0.85rem;
                color: #64748b;
                font-weight: 500;
            }
            .stat-mini-card strong {
                font-size: 1.15rem;
                color: #0f172a;
            }
            .stat-mini-card strong.revenue-text {
                color: #3b82f6;
            }
            .stat-mini-card .trend {
                font-size: 0.75rem;
                font-weight: 600;
                display: flex;
                align-items: center;
                gap: 4px;
            }
            .trend.positive { color: #10b981; }
            .trend.negative { color: #ef4444; }
            
            /* Modern Timeline Activities */
            .modern-timeline {
                position: relative;
                padding-left: 28px;
                margin-top: 10px;
            }
            .modern-timeline::before {
                content: '';
                position: absolute;
                left: 11px;
                top: 0;
                bottom: 0;
                width: 2px;
                background: #e2e8f0;
            }
            .timeline-item {
                position: relative;
                margin-bottom: 20px;
                transition: transform 0.2s;
            }
            .timeline-item:last-child {
                margin-bottom: 0;
            }
            .timeline-item:hover {
                transform: translateX(4px);
            }
            .timeline-icon {
                position: absolute;
                left: -28px;
                top: 0;
                width: 24px;
                height: 24px;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                background: #ffffff;
                border: 2px solid #e2e8f0;
                z-index: 1;
            }
            .timeline-icon svg {
                width: 12px;
                height: 12px;
            }
            .timeline-content {
                display: flex;
                flex-direction: column;
                background: #f8fafc;
                padding: 12px 16px;
                border-radius: 8px;
                border: 1px solid #f1f5f9;
            }
            .timeline-title {
                font-size: 0.9rem;
                color: #0f172a;
                font-weight: 500;
                margin: 0;
            }
            .timeline-desc {
                font-size: 0.8rem;
                color: #64748b;
                margin: 4px 0 0 0;
            }
            .timeline-time {
                font-size: 0.75rem;
                color: #94a3b8;
                position: absolute;
                right: 12px;
                top: 12px;
            }
            
            /* Icon colors based on type */
            .activity-booking .timeline-icon { border-color: #10b981; color: #10b981; }
            .activity-transaction .timeline-icon { border-color: #3b82f6; color: #3b82f6; }
            .activity-user .timeline-icon { border-color: #8b5cf6; color: #8b5cf6; }
            .activity-provider .timeline-icon { border-color: #10b981; color: #10b981; }

            .btn-view-all {
                font-size: 0.85rem;
                color: #3b82f6;
                text-decoration: none;
                font-weight: 500;
                display: flex;
                align-items: center;
                gap: 4px;
            }
            .btn-view-all:hover {
                text-decoration: underline;
            }
            
            @media (max-width: 1200px) {
                .admin-dashboard-insights { grid-template-columns: 1fr; }
            }
            @media (max-width: 768px) {
                .admin-analytics-grid { grid-template-columns: 1fr; }
                .admin-revenue-overview { grid-template-columns: repeat(2, 1fr); }
            }
        </style>
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

        <div class="admin-dashboard-insights">
            <div class="card admin-panel-card">
                <div class="admin-card-head">
                    <h2>Analytics Overview</h2>
                    <div class="admin-filter-group">
                        <select class="admin-select-modern">
                            <option>Last 6 Months</option>
                            <option>Last 30 Days</option>
                            <option>Last 7 Days</option>
                            <option>Last Year</option>
                        </select>
                        <button class="btn-export-modern">Export Report</button>
                    </div>
                </div>
                <div class="admin-analytics-grid">
                    <div class="admin-chart-card">
                        <h3>Revenue (Last 6 Months)</h3>
                        <div class="chart-container">
                            <canvas id="revenueChart"></canvas>
                        </div>
                    </div>
                    <div class="admin-chart-card">
                        <h3>Bookings (Last 6 Months)</h3>
                        <div class="chart-container">
                            <canvas id="bookingsChart"></canvas>
                        </div>
                    </div>
                </div>
                <?php
                $growthPct = 0;
                if ($revenue['last_month'] > 0) {
                    $growthPct = (($revenue['this_month'] - $revenue['last_month']) / $revenue['last_month']) * 100;
                }
                ?>
                <div class="admin-revenue-overview">
                    <div class="stat-mini-card">
                        <span class="small-muted">This Month</span>
                        <strong class="revenue-text">$<?= number_format((float)$revenue['this_month'], 2) ?></strong>
                    </div>
                    <div class="stat-mini-card">
                        <span class="small-muted">Last Month</span>
                        <strong>$<?= number_format((float)$revenue['last_month'], 2) ?></strong>
                    </div>
                    <div class="stat-mini-card">
                        <span class="small-muted">Growth</span>
                        <strong class="trend <?= $growthPct >= 0 ? 'positive' : 'negative' ?>">
                            <?= number_format($growthPct, 2) ?>% <?= $growthPct >= 0 ? '↑' : '↓' ?>
                        </strong>
                    </div>
                    <div class="stat-mini-card">
                        <span class="small-muted">Active Deals</span>
                        <strong><?= number_format((int)$stats['active_bookings']) ?></strong>
                    </div>
                </div>
            </div>

            <div class="card admin-panel-card">
                <div class="admin-card-head">
                    <h2>Recent Activities</h2>
                    <a href="#" class="btn-view-all">View All Activities →</a>
                </div>
                <div class="modern-timeline">
                    <?php if (empty($recentActivities)): ?>
                        <p class="small-muted">No recent activities yet.</p>
                    <?php else: ?>
                        <?php foreach ($recentActivities as $activity): ?>
                            <div class="timeline-item activity-<?= htmlspecialchars((string)$activity['activity_type']) ?>">
                                <div class="timeline-icon">
                                    <?php if ($activity['activity_type'] === 'booking'): ?>
                                        <svg fill="currentColor" viewBox="0 0 20 20"><path d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z"></path></svg>
                                    <?php elseif ($activity['activity_type'] === 'transaction'): ?>
                                        <svg fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4 4a2 2 0 00-2 2v4a2 2 0 002 2V6h10a2 2 0 00-2-2H4zm2 6a2 2 0 012-2h8a2 2 0 012 2v4a2 2 0 01-2 2H8a2 2 0 01-2-2v-4zm6 4a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd"></path></svg>
                                    <?php elseif ($activity['activity_type'] === 'user'): ?>
                                        <svg fill="currentColor" viewBox="0 0 20 20"><path d="M13 6a3 3 0 11-6 0 3 3 0 016 0zM18 8a2 2 0 11-4 0 2 2 0 014 0zM14 15a4 4 0 00-8 0v3h8v-3zM6 8a2 2 0 11-4 0 2 2 0 014 0zM16 18v-3a5.972 5.972 0 00-.75-2.906A3.005 3.005 0 0119 15v3h-3zM4.75 12.094A5.973 5.973 0 004 15v3H1v-3a3 3 0 013.75-2.906z"></path></svg>
                                    <?php else: ?>
                                        <svg fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>
                                    <?php endif; ?>
                                </div>
                                <div class="timeline-content">
                                    <h4 class="timeline-title"><?= htmlspecialchars((string)$activity['activity_text']) ?></h4>
                                    <?php 
                                        $desc = "";
                                        if ($activity['activity_type'] === 'booking') $desc = "Booking has been updated.";
                                        if ($activity['activity_type'] === 'transaction') $desc = "Payment processing update.";
                                        if ($activity['activity_type'] === 'user') $desc = "New customer account created.";
                                        if ($activity['activity_type'] === 'provider') $desc = "New provider registration.";
                                    ?>
                                    <p class="timeline-desc"><?= $desc ?></p>
                                    <span class="timeline-time"><?= htmlspecialchars(date('M d, Y h:i A', strtotime((string)$activity['happened_at']))) ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const chartLabels = <?= json_encode(array_column($analyticsData, 'label')) ?>;
            const revenueData = <?= json_encode(array_column($analyticsData, 'revenue')) ?>;
            const bookingsData = <?= json_encode(array_column($analyticsData, 'bookings')) ?>;
            
            Chart.defaults.font.family = "'Inter', 'Roboto', sans-serif";
            Chart.defaults.color = '#64748b';

            // Revenue Line Chart
            const ctxRev = document.getElementById('revenueChart');
            if (ctxRev) {
                const ctx = ctxRev.getContext('2d');
                let gradientRev = ctx.createLinearGradient(0, 0, 0, 220);
                gradientRev.addColorStop(0, 'rgba(58, 134, 255, 0.4)');
                gradientRev.addColorStop(1, 'rgba(58, 134, 255, 0.0)');

                new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: chartLabels,
                        datasets: [{
                            label: 'Revenue',
                            data: revenueData,
                            borderColor: '#3A86FF',
                            backgroundColor: gradientRev,
                            borderWidth: 2,
                            pointBackgroundColor: '#ffffff',
                            pointBorderColor: '#3A86FF',
                            pointBorderWidth: 2,
                            pointRadius: 4,
                            pointHoverRadius: 6,
                            fill: true,
                            tension: 0.4
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                backgroundColor: '#1e293b',
                                padding: 10,
                                titleFont: { size: 13 },
                                bodyFont: { size: 13, weight: 'bold' },
                                displayColors: false,
                                callbacks: {
                                    label: function(context) {
                                        return '$' + context.parsed.y.toFixed(2);
                                    }
                                }
                            }
                        },
                        scales: {
                            x: { grid: { display: false, drawBorder: false } },
                            y: { grid: { color: '#f1f5f9', drawBorder: false }, ticks: { callback: function(value) { return '$' + value; } } }
                        }
                    }
                });
            }

            // Bookings Bar Chart
            const ctxBook = document.getElementById('bookingsChart');
            if (ctxBook) {
                const ctx = ctxBook.getContext('2d');
                let gradientBook = ctx.createLinearGradient(0, 0, 0, 220);
                gradientBook.addColorStop(0, '#8b5cf6');
                gradientBook.addColorStop(1, '#a78bfa');

                new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: chartLabels,
                        datasets: [{
                            label: 'Bookings',
                            data: bookingsData,
                            backgroundColor: gradientBook,
                            borderRadius: 4,
                            barThickness: 20
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                backgroundColor: '#1e293b',
                                padding: 10,
                                titleFont: { size: 13 },
                                bodyFont: { size: 13, weight: 'bold' },
                                displayColors: false
                            }
                        },
                        scales: {
                            x: { grid: { display: false, drawBorder: false } },
                            y: { grid: { color: '#f1f5f9', drawBorder: false }, ticks: { stepSize: 1 } }
                        }
                    }
                });
            }
        });
        </script>

    </div>
</section>
<?php require_once 'includes/footer.php'; ?>
