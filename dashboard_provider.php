<?php
$pageTitle = 'Provider Dashboard';
require_once 'config/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'provider') {
    header('Location: login.php');
    exit;
}

require_provider_documents();

$pdo = getDBConnection();
$providerId = $_SESSION['provider_id'];
$userId = $_SESSION['user_id'];

$provStmt = $pdo->prepare("SELECT p.*, u.full_name, u.email, u.phone, COALESCE(p.credits, 0) as credits FROM providers p JOIN users u ON p.user_id = u.id WHERE p.id = ?");
$provStmt->execute([$providerId]);
$provider = $provStmt->fetch();

if (!$provider) {
    header('Location: logout.php');
    exit;
}

$services = $pdo->prepare("SELECT s.*, sc.name as category_name FROM services s JOIN service_categories sc ON s.category_id = sc.id WHERE s.provider_id = ?");
$services->execute([$providerId]);
$services = $services->fetchAll();

$categories = $pdo->query("SELECT * FROM service_categories ORDER BY name")->fetchAll();

$adStatus = $pdo->prepare("SELECT * FROM ads WHERE provider_id = ? ORDER BY created_at DESC LIMIT 1");
$adStatus->execute([$providerId]);
$adStatus = $adStatus->fetch();

$providerBookingsStmt = $pdo->prepare("
    SELECT b.id, b.customer_id, b.status, b.scheduled_date, b.notes, b.created_at,
           s.title AS service_title,
           u.full_name AS customer_name,
           COALESCE(NULLIF(u.city, ''), 'City not set') AS customer_city,
           COALESCE(NULLIF(u.barangay, ''), 'Barangay not set') AS customer_barangay
    FROM bookings b
    JOIN services s ON b.service_id = s.id
    JOIN users u ON b.customer_id = u.id
    WHERE b.provider_id = ?
    ORDER BY b.scheduled_date IS NULL, b.scheduled_date DESC, b.created_at DESC
");
$providerBookingsStmt->execute([$providerId]);
$providerBookings = $providerBookingsStmt->fetchAll();

$recentBookingsStmt = $pdo->prepare("
    SELECT b.id, b.status, b.scheduled_date, s.title AS service_title, u.full_name AS customer_name
    FROM bookings b
    JOIN services s ON b.service_id = s.id
    JOIN users u ON b.customer_id = u.id
    WHERE b.provider_id = ? AND b.status <> 'pending'
    ORDER BY b.updated_at DESC, b.id DESC
    LIMIT 6
");
$recentBookingsStmt->execute([$providerId]);
$recentBookings = $recentBookingsStmt->fetchAll();

$todayDate = date('Y-m-d');
$statusCounts = [
    'pending' => 0,
    'confirmed' => 0,
    'completed' => 0,
    'cancelled' => 0,
    'rejected' => 0,
];
foreach ($providerBookings as $booking) {
    $st = (string)($booking['status'] ?? '');
    if (isset($statusCounts[$st])) {
        $statusCounts[$st]++;
    }
}

$pdBookingsPayload = [];
foreach ($providerBookings as $b) {
    $pdBookingsPayload[] = [
        'id' => (int)$b['id'],
        'customer_id' => (int)$b['customer_id'],
        'status' => (string)$b['status'],
        'scheduled_date' => $b['scheduled_date'] !== null ? (string)$b['scheduled_date'] : null,
        'notes' => (string)($b['notes'] ?? ''),
        'created_at' => (string)($b['created_at'] ?? ''),
        'service_title' => (string)($b['service_title'] ?? ''),
        'customer_name' => (string)($b['customer_name'] ?? ''),
        'customer_city' => (string)($b['customer_city'] ?? ''),
        'customer_barangay' => (string)($b['customer_barangay'] ?? ''),
    ];
}
$pdBookingsJson = json_encode($pdBookingsPayload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
if ($pdBookingsJson === false) {
    $pdBookingsJson = '[]';
}

require_once 'includes/header.php';
?>
<style>
.provider-dashboard { padding: 2rem; background: #f8fafc; }
.provider-dashboard .section-title { margin-bottom: 1rem; }
.kpi-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 1rem; margin-bottom: 1.25rem; }
.kpi-card, .panel-card { background: #fff; border: 1px solid #e6edf5; border-radius: 14px; box-shadow: 0 8px 24px rgba(15, 23, 42, 0.06); }
.kpi-card { padding: 1rem 1.1rem; display: flex; align-items: center; gap: 0.8rem; }
.kpi-icon { width: 38px; height: 38px; border-radius: 10px; display: inline-flex; align-items: center; justify-content: center; font-size: 1rem; font-weight: 700; }
.kpi-title { margin: 0; font-size: 0.8rem; color: #64748b; }
.kpi-value { margin: 0.2rem 0 0; font-size: 1.1rem; color: #0f172a; font-weight: 700; }
.dashboard-two-col { display: grid; grid-template-columns: 340px 1fr; gap: 1rem; margin-bottom: 1.25rem; }
.panel-card { padding: 1.1rem; }
.panel-head { display: flex; align-items: center; justify-content: space-between; gap: 0.75rem; margin-bottom: 0.85rem; }
.panel-title { margin: 0; font-size: 1rem; color: #0f172a; }
.panel-subtitle { margin: 0; color: #64748b; font-size: 0.85rem; }
.calendar-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 0.35rem; }
.calendar-day-label { text-align: center; font-size: 0.74rem; color: #64748b; font-weight: 600; padding-bottom: 0.2rem; }
.calendar-day { border: 1px solid #e2e8f0; background: #fff; border-radius: 8px; min-height: 38px; display: flex; flex-direction: column; align-items: center; justify-content: center; font-size: 0.82rem; color: #1e293b; position: relative; padding: 2px 0 6px; }
.calendar-day.muted { background: #f8fafc; color: #94a3b8; pointer-events: none; }
.calendar-day.pd-cal-day { cursor: pointer; transition: background 0.15s ease, border-color 0.15s ease, color 0.15s ease; border: none; font: inherit; width: 100%; }
.calendar-day.pd-cal-day:hover { background: #eff6ff; }
.calendar-day.today:not(.selected) { border: 1px solid #3A86FF; box-shadow: 0 0 0 2px rgba(58, 134, 255, 0.14); font-weight: 700; }
.calendar-day.selected { background: #3A86FF; color: #fff; border-color: #3A86FF; font-weight: 700; box-shadow: none; }
.calendar-day.selected .pd-day-dots span { box-shadow: 0 0 0 1px rgba(255,255,255,0.6); }
.pd-day-dots { display: flex; gap: 3px; justify-content: center; flex-wrap: wrap; margin-top: 2px; min-height: 7px; }
.pd-day-dots span { width: 6px; height: 6px; border-radius: 50%; display: inline-block; }
.pd-dot-upcoming { background: #3A86FF; }
.pd-dot-done { background: #22c55e; }
.pd-dot-rejected { background: #ef4444; }
.pd-dot-canceled { background: #94a3b8; }
.pd-cal-nav-row { display: flex; align-items: center; justify-content: space-between; gap: 0.5rem; margin-bottom: 0.65rem; }
.pd-cal-nav-row .panel-subtitle { margin: 0; font-weight: 600; color: #0f172a; }
.pd-cal-nav-btns { display: flex; gap: 0.35rem; }
.pd-cal-nav-btns button { width: 34px; height: 34px; border-radius: 10px; border: 1px solid #e2e8f0; background: #fff; cursor: pointer; font-size: 1rem; color: #334155; }
.pd-cal-nav-btns button:hover { background: #f1f5f9; }
.pd-cal-legend { display: flex; flex-wrap: wrap; gap: 0.65rem 1rem; margin-top: 0.75rem; font-size: 0.72rem; color: #64748b; }
.pd-cal-legend span { display: inline-flex; align-items: center; gap: 0.35rem; }
.today-slots { margin-top: 0.95rem; border-top: 1px dashed #dbe5f0; padding-top: 0.85rem; }
.slot-row { display: flex; align-items: center; gap: 0.65rem; margin-bottom: 0.5rem; font-size: 0.84rem; color: #334155; }
.slot-time { min-width: 68px; color: #0f172a; font-weight: 600; }
.booking-review-grid { display: grid; gap: 0.8rem; }
.booking-review-card { border: 1px solid #e5eaf1; border-radius: 12px; padding: 0.95rem; background: #fff; display: grid; grid-template-columns: 1fr auto; gap: 0.7rem 1rem; transition: box-shadow .2s ease, transform .2s ease; }
.booking-review-card:hover { transform: translateY(-1px); box-shadow: 0 10px 22px rgba(15, 23, 42, 0.08); }
.booking-main-title { margin: 0; font-size: 0.99rem; color: #0f172a; font-weight: 700; }
.booking-review-meta { display: flex; flex-wrap: wrap; gap: 0.55rem 0.9rem; color: #64748b; font-size: 0.83rem; margin-top: 0.5rem; }
.booking-review-note { margin-top: 0.55rem; font-size: 0.84rem; color: #475569; }
.booking-review-actions { display: flex; align-items: center; flex-wrap: wrap; justify-content: flex-end; gap: 0.45rem; }
.booking-status-pill { display: inline-flex; align-items: center; padding: 0.2rem 0.62rem; border-radius: 999px; font-size: 0.72rem; font-weight: 700; letter-spacing: 0.02em; text-transform: capitalize; }
.status-done-pill { background: #dcfce7; color: #166534; }
.status-cancelled-pill { background: #fee2e2; color: #b91c1c; }
.status-pending-pill { background: #fef3c7; color: #92400e; }
.status-confirmed-pill { background: #dbeafe; color: #1d4ed8; }
.status-rejected-pill { background: #fee2e2; color: #b91c1c; }
.pd-filter-tabs { display: flex; flex-wrap: wrap; gap: 0.45rem; margin-bottom: 1rem; }
.pd-filter-tab { border: 1px solid #e2e8f0; background: #fff; border-radius: 999px; padding: 0.38rem 0.75rem; font-size: 0.78rem; font-weight: 600; color: #475569; cursor: pointer; transition: border-color 0.15s ease, background 0.15s ease, color 0.15s ease; }
.pd-filter-tab:hover { border-color: #cbd5e1; background: #f8fafc; }
.pd-filter-tab.is-active { border-color: #3A86FF; color: #1d4ed8; background: #eff6ff; box-shadow: 0 0 0 1px rgba(58,134,255,0.2); }
.pd-booking-empty { color: #64748b; font-size: 0.9rem; padding: 1rem 0; text-align: center; }
.pd-booking-empty.is-hidden { display: none; }
.btn-action { border-radius: 8px; font-size: 0.79rem; padding: 0.4rem 0.68rem; border: 1px solid transparent; cursor: pointer; font-weight: 600; }
.btn-confirm { background: #3A86FF; color: #fff; }
.btn-done { background: #16a34a; color: #fff; }
.btn-cancel { background: #fff; color: #dc2626; border-color: #fecaca; }
.btn-reject { background: #fff; color: #b91c1c; border-color: #fecaca; }
.btn-action[disabled] { opacity: 0.65; cursor: not-allowed; }
.quick-actions { display: flex; flex-wrap: wrap; gap: 0.6rem; margin-top: 0.9rem; }
.quick-actions .btn { border-radius: 9px; }
.bottom-grid { display: grid; grid-template-columns: 1.2fr 1fr; gap: 1rem; margin-bottom: 1.2rem; }
.service-list { display: grid; gap: 0.7rem; }
.service-item { border: 1px solid #e5eaf1; border-radius: 10px; padding: 0.75rem 0.8rem; display: flex; align-items: center; justify-content: space-between; gap: 0.7rem; }
.service-item h4 { margin: 0; font-size: 0.92rem; color: #0f172a; }
.service-item p { margin: 0.22rem 0 0; color: #64748b; font-size: 0.8rem; }
@media (max-width: 1100px) {
    .kpi-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .dashboard-two-col, .bottom-grid { grid-template-columns: 1fr; }
}
@media (max-width: 768px) {
    .provider-dashboard { padding: 1rem; }
    .kpi-grid { grid-template-columns: 1fr; }
    .booking-review-card { grid-template-columns: 1fr; }
    .booking-review-actions { justify-content: flex-start; }
}
</style>
<section class="provider-dashboard">
    <h1 class="section-title">Provider Dashboard</h1>

    <div class="kpi-grid">
        <div class="kpi-card">
            <span class="kpi-icon" style="background:#dcfce7;color:#15803d;">✓</span>
            <div>
                <p class="kpi-title">Account Status</p>
                <p class="kpi-value"><?= $provider['verification_status'] === 'approved' ? 'Verified' : ucfirst((string)$provider['verification_status']) ?></p>
            </div>
        </div>
        <div class="kpi-card">
            <span class="kpi-icon" style="background:#dbeafe;color:#1d4ed8;">₱</span>
            <div>
                <p class="kpi-title">Credits</p>
                <p class="kpi-value"><?= (int)($provider['credits'] ?? 0) ?></p>
            </div>
        </div>
        <div class="kpi-card">
            <span class="kpi-icon" style="background:#fef3c7;color:#a16207;">⏳</span>
            <div>
                <p class="kpi-title">Pending Requests</p>
                <p class="kpi-value"><?= (int)$statusCounts['pending'] ?></p>
            </div>
        </div>
        <div class="kpi-card">
            <span class="kpi-icon" style="background:#dbeafe;color:#1d4ed8;">✔</span>
            <div>
                <p class="kpi-title">Confirmed</p>
                <p class="kpi-value"><?= (int)$statusCounts['confirmed'] ?></p>
            </div>
        </div>
    </div>

    <div class="panel-card" style="margin-bottom: 1.25rem;">
        <div class="panel-head">
            <div>
                <h2 class="panel-title">Provider Controls</h2>
                <p class="panel-subtitle">Manage profile, credits, services, and communication faster.</p>
            </div>
        </div>
        <div class="quick-actions">
            <button type="button" class="btn btn-primary js-open-add-service-modal">Add Service</button>
            <a href="provider_dashboard.php#my-services" class="btn btn-ghost">Manage Services</a>
            <a href="chat.php" class="btn btn-ghost">View Messages</a>
            <a href="promote_service.php" class="btn btn-outline">Promote Service</a>
            <a href="buy_credits.php" class="btn btn-ghost">Buy Credits</a>
            <a href="provider_settings.php" class="btn btn-ghost">Profile Settings</a>
            <?php if ($provider['verification_status'] !== 'approved'): ?>
                <a href="face_verification.php" class="btn btn-primary">Verify Account</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="dashboard-two-col">
        <div class="panel-card" id="booking-schedule">
            <div class="panel-head">
                <div>
                    <h2 class="panel-title">Booking Schedule</h2>
                    <p class="panel-subtitle" id="pd-cal-month-label"></p>
                </div>
               
            </div>
            <div class="pd-cal-nav-row">
                <div class="pd-cal-nav-btns">
                    <button type="button" id="pd-cal-prev" aria-label="Previous month">&lsaquo;</button>
                    <button type="button" id="pd-cal-next" aria-label="Next month">&rsaquo;</button>
                </div>
            </div>
            <div class="calendar-grid" id="pd-cal-weekdays"></div>
            <div class="calendar-grid" id="pd-cal-days" style="margin-top:0;"></div>
            <div class="pd-cal-legend">
                <span><span class="pd-dot-upcoming" style="display:inline-block;width:6px;height:6px;border-radius:50%;background:#3A86FF;"></span> Upcoming</span>
                <span><span style="display:inline-block;width:6px;height:6px;border-radius:50%;background:#22c55e;border-radius:50%;"></span> Done</span>
                <span><span style="display:inline-block;width:6px;height:6px;border-radius:50%;background:#ef4444;"></span> Rejected</span>
                <span><span style="display:inline-block;width:6px;height:6px;border-radius:50%;background:#94a3b8;"></span> Canceled</span>
            </div>
            <div class="today-slots">
                <div class="panel-head" style="margin-bottom: 0.65rem;">
                    <h3 class="panel-title" style="font-size:0.92rem;" id="pd-selected-day-title">Selected day</h3>
                </div>
                <div id="pd-selected-day-slots"></div>
            </div>
        </div>

        <div class="panel-card" id="pd-booking-requests-panel">
            <div class="panel-head">
                <div>
                    <h2 class="panel-title">Booking Requests</h2>
                    <p class="panel-subtitle">Manage and update customer booking requests.</p>
                </div>
            </div>
            <div class="pd-filter-tabs" id="pd-filter-tabs" role="tablist">
                <button type="button" class="pd-filter-tab is-active" data-filter="all" role="tab">All <span class="pd-count" data-count-for="all">(0)</span></button>
                <button type="button" class="pd-filter-tab" data-filter="upcoming" role="tab">Upcoming <span class="pd-count" data-count-for="upcoming">(0)</span></button>
                <button type="button" class="pd-filter-tab" data-filter="done" role="tab">Done <span class="pd-count" data-count-for="done">(0)</span></button>
                <button type="button" class="pd-filter-tab" data-filter="rejected" role="tab">Rejected <span class="pd-count" data-count-for="rejected">(0)</span></button>
                <button type="button" class="pd-filter-tab" data-filter="canceled" role="tab">Canceled <span class="pd-count" data-count-for="canceled">(0)</span></button>
            </div>
            <p id="pd-booking-filter-hint" class="panel-subtitle" style="margin-bottom:0.75rem;"></p>
            <div class="booking-review-grid" id="pd-booking-list"></div>
            <p id="pd-booking-empty" class="pd-booking-empty is-hidden">No bookings for this date.</p>
        </div>
    </div>

    <div class="bottom-grid">
        <div class="panel-card">
            <div class="panel-head">
                <div>
                    <h2 class="panel-title">Recent Booking Decisions</h2>
                    <p class="panel-subtitle">Latest updates from your confirmed and completed jobs.</p>
                </div>
            </div>
            <?php if (empty($recentBookings)): ?>
                <p class="panel-subtitle">No booking decisions yet.</p>
            <?php else: ?>
                <div style="display: grid; gap: 0.65rem;">
                    <?php foreach ($recentBookings as $booking): ?>
                        <?php
                        $statusClass = $booking['status'] === 'confirmed'
                            ? 'status-confirmed-pill'
                            : ($booking['status'] === 'completed' ? 'status-done-pill' : 'status-cancelled-pill');
                        ?>
                        <div class="service-item">
                            <div>
                                <h4><?= htmlspecialchars($booking['service_title']) ?> - <?= htmlspecialchars($booking['customer_name']) ?></h4>
                                <p><?= !empty($booking['scheduled_date']) ? htmlspecialchars(date('M d, Y g:i A', strtotime($booking['scheduled_date']))) : 'No schedule set' ?></p>
                            </div>
                            <span class="booking-status-pill <?= $statusClass ?>"><?= htmlspecialchars($booking['status']) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="panel-card" id="my-services">
            <div class="panel-head">
                <div>
                    <h2 class="panel-title">My Services</h2>
                    <p class="panel-subtitle">Keep your offerings updated and ready for bookings.</p>
                </div>
                <button type="button" class="btn btn-primary js-open-add-service-modal">+ Add Service</button>
            </div>
            <?php if (empty($services)): ?>
                <p class="panel-subtitle">No services yet. Add your first service now.</p>
            <?php else: ?>
                <div class="service-list">
                    <?php foreach ($services as $s): ?>
                    <div class="service-item">
                        <div>
                            <h4><?= htmlspecialchars($s['title']) ?></h4>
                            <p><?= htmlspecialchars($s['category_name']) ?> • ₱<?= number_format($s['price_min']) ?> - ₱<?= number_format($s['price_max']) ?></p>
                        </div>
                        <a href="provider_edit_service.php?id=<?= (int)$s['id'] ?>" class="btn btn-ghost">Edit</a>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>
<script type="application/json" id="pd-initial-bookings"><?= $pdBookingsJson ?></script>
<script>
(function () {
    var elJson = document.getElementById('pd-initial-bookings');
    var bookings = [];
    try {
        bookings = elJson && elJson.textContent ? JSON.parse(elJson.textContent) : [];
    } catch (e) { bookings = []; }

    var state = {
        viewYear: new Date().getFullYear(),
        viewMonth: new Date().getMonth() + 1,
        selectedDateKey: null,
        activeFilter: 'all',
        todayKey: <?= json_encode($todayDate, JSON_HEX_TAG) ?>
    };

    function pad2(n) { return n < 10 ? '0' + n : String(n); }
    function dateKey(y, m, d) { return y + '-' + pad2(m) + '-' + pad2(d); }
    function parseBookingDateKey(b) {
        if (!b.scheduled_date) return null;
        var t = Date.parse(b.scheduled_date.replace(' ', 'T'));
        if (isNaN(t)) return null;
        var dt = new Date(t);
        return dateKey(dt.getFullYear(), dt.getMonth() + 1, dt.getDate());
    }
    function filterKind(status) {
        if (status === 'pending' || status === 'confirmed') return 'upcoming';
        if (status === 'completed') return 'done';
        if (status === 'rejected') return 'rejected';
        if (status === 'cancelled') return 'canceled';
        return 'other';
    }
    function bookingMatchesFilter(b, filter) {
        if (filter === 'all') return true;
        return filterKind(b.status) === filter;
    }
    function bookingInViewMonth(b) {
        if (!b.scheduled_date) return false;
        var t = Date.parse(b.scheduled_date.replace(' ', 'T'));
        if (isNaN(t)) return false;
        var dt = new Date(t);
        return dt.getFullYear() === state.viewYear && (dt.getMonth() + 1) === state.viewMonth;
    }
    function getFilteredBookings() {
        return bookings.filter(function (b) {
            if (!bookingMatchesFilter(b, state.activeFilter)) return false;
            if (state.selectedDateKey) {
                var dk = parseBookingDateKey(b);
                return dk === state.selectedDateKey;
            }
            return bookingInViewMonth(b) || (!b.scheduled_date && state.activeFilter === 'all');
        }).sort(function (a, b) {
            var ta = a.scheduled_date ? Date.parse(a.scheduled_date.replace(' ', 'T')) : 0;
            var tb = b.scheduled_date ? Date.parse(b.scheduled_date.replace(' ', 'T')) : 0;
            return ta - tb;
        });
    }
    function countByFilter(filter) {
        return bookings.filter(function (b) {
            if (filter === 'all') return true;
            return filterKind(b.status) === filter;
        }).length;
    }
    function dayDotKinds(dayKey) {
        var kinds = { upcoming: false, done: false, rejected: false, canceled: false };
        bookings.forEach(function (b) {
            if (parseBookingDateKey(b) !== dayKey) return;
            var fk = filterKind(b.status);
            if (fk === 'upcoming') kinds.upcoming = true;
            else if (fk === 'done') kinds.done = true;
            else if (fk === 'rejected') kinds.rejected = true;
            else if (fk === 'canceled') kinds.canceled = true;
        });
        return kinds;
    }
    function statusPillClass(status) {
        if (status === 'completed') return 'status-done-pill';
        if (status === 'cancelled') return 'status-cancelled-pill';
        if (status === 'rejected') return 'status-rejected-pill';
        if (status === 'confirmed') return 'status-confirmed-pill';
        return 'status-pending-pill';
    }
    function displayStatusLabel(status) {
        if (status === 'rejected') return 'Rejected';
        if (status === 'cancelled') return 'Canceled';
        if (status === 'completed') return 'Done';
        if (status === 'confirmed') return 'Confirmed';
        if (status === 'pending') return 'Pending';
        return status;
    }

    function renderTabCounts() {
        document.querySelectorAll('[data-count-for]').forEach(function (span) {
            var f = span.getAttribute('data-count-for');
            span.textContent = '(' + countByFilter(f) + ')';
        });
    }

    function renderCalendar() {
        var monthLabel = document.getElementById('pd-cal-month-label');
        var weekdays = document.getElementById('pd-cal-weekdays');
        var daysWrap = document.getElementById('pd-cal-days');
        if (!monthLabel || !weekdays || !daysWrap) return;

        var monthNames = ['January','February','March','April','May','June','July','August','September','October','November','December'];
        monthLabel.textContent = monthNames[state.viewMonth - 1] + ' ' + state.viewYear;

        var wds = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
        weekdays.innerHTML = '';
        wds.forEach(function (wd) {
            var d = document.createElement('div');
            d.className = 'calendar-day-label';
            d.textContent = wd;
            weekdays.appendChild(d);
        });

        var first = new Date(state.viewYear, state.viewMonth - 1, 1);
        var startWeekday = first.getDay();
        var daysInMonth = new Date(state.viewYear, state.viewMonth, 0).getDate();

        daysWrap.innerHTML = '';
        for (var b = 0; b < startWeekday; b++) {
            var blank = document.createElement('div');
            blank.className = 'calendar-day muted';
            daysWrap.appendChild(blank);
        }
        for (var day = 1; day <= daysInMonth; day++) {
            var key = dateKey(state.viewYear, state.viewMonth, day);
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'calendar-day pd-cal-day';
            if (key === state.todayKey) btn.classList.add('today');
            if (state.selectedDateKey && key === state.selectedDateKey) btn.classList.add('selected');
            btn.setAttribute('data-date-key', key);
            btn.appendChild(document.createTextNode(String(day)));

            var dots = document.createElement('div');
            dots.className = 'pd-day-dots';
            var kinds = dayDotKinds(key);
            if (kinds.upcoming) { var s = document.createElement('span'); s.className = 'pd-dot-upcoming'; dots.appendChild(s); }
            if (kinds.done) { var s2 = document.createElement('span'); s2.className = 'pd-dot-done'; dots.appendChild(s2); }
            if (kinds.rejected) { var s3 = document.createElement('span'); s3.className = 'pd-dot-rejected'; dots.appendChild(s3); }
            if (kinds.canceled) { var s4 = document.createElement('span'); s4.className = 'pd-dot-canceled'; dots.appendChild(s4); }
            btn.appendChild(dots);

            btn.addEventListener('click', function () {
                state.selectedDateKey = this.getAttribute('data-date-key');
                renderCalendar();
                renderSelectedDaySummary();
                renderBookingList();
            });
            daysWrap.appendChild(btn);
        }
    }

    function renderSelectedDaySummary() {
        var title = document.getElementById('pd-selected-day-title');
        var wrap = document.getElementById('pd-selected-day-slots');
        if (!title || !wrap) return;
        if (!state.selectedDateKey) {
            title.textContent = 'This month';
            var list = bookings.filter(function (b) { return bookingInViewMonth(b); });
            if (!list.length) {
                wrap.innerHTML = '<p class="panel-subtitle">No bookings in this month. Select a date on the calendar.</p>';
                return;
            }
            wrap.innerHTML = '<p class="panel-subtitle">' + list.length + ' booking(s) this month — pick a date to narrow down.</p>';
            return;
        }
        title.textContent = 'Bookings on ' + state.selectedDateKey;
        var dayBookings = bookings.filter(function (b) { return parseBookingDateKey(b) === state.selectedDateKey; });
        if (!dayBookings.length) {
            wrap.innerHTML = '<p class="panel-subtitle">No bookings on this day.</p>';
            return;
        }
        dayBookings.sort(function (a, b) {
            var ta = a.scheduled_date ? Date.parse(a.scheduled_date.replace(' ', 'T')) : 0;
            var tb = b.scheduled_date ? Date.parse(b.scheduled_date.replace(' ', 'T')) : 0;
            return ta - tb;
        });
        wrap.innerHTML = '';
        dayBookings.forEach(function (slot) {
            var row = document.createElement('div');
            row.className = 'slot-row';
            var time = document.createElement('span');
            time.className = 'slot-time';
            time.textContent = slot.scheduled_date
                ? new Date(slot.scheduled_date.replace(' ', 'T')).toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' })
                : '—';
            var rest = document.createElement('span');
            rest.textContent = slot.service_title + ' — ' + slot.customer_name + ' (' + displayStatusLabel(slot.status) + ')';
            row.appendChild(time);
            row.appendChild(rest);
            wrap.appendChild(row);
        });
    }

    function escapeHtml(s) {
        if (!s) return '';
        var d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }

    function renderBookingList() {
        var listEl = document.getElementById('pd-booking-list');
        var emptyEl = document.getElementById('pd-booking-empty');
        var hint = document.getElementById('pd-booking-filter-hint');
        if (!listEl || !emptyEl) return;

        var rows = getFilteredBookings();
        if (hint) {
            hint.textContent = state.selectedDateKey
                ? 'Showing ' + state.activeFilter + ' for ' + state.selectedDateKey + '.'
                : 'Showing ' + state.activeFilter + ' for ' + monthNamesShort() + ' (pick a date to filter by day).';
        }
        listEl.innerHTML = '';
        if (!rows.length) {
            emptyEl.classList.remove('is-hidden');
            emptyEl.textContent = state.selectedDateKey
                ? 'No bookings for this date.'
                : 'No bookings match this filter for the selected period.';
            return;
        }
        emptyEl.classList.add('is-hidden');

        rows.forEach(function (booking) {
            var status = booking.status;
            var pillClass = statusPillClass(status);
            var addressText = escapeHtml((booking.customer_barangay || '') + ', ' + (booking.customer_city || ''));
            var dtStr = booking.scheduled_date
                ? new Date(booking.scheduled_date.replace(' ', 'T')).toLocaleString([], { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit' })
                : 'Not set';

            var card = document.createElement('div');
            card.className = 'booking-review-card';
            card.id = 'booking-card-' + booking.id;
            var notesHtml = booking.notes ? '<p class="booking-review-note"><strong>Notes:</strong> ' + escapeHtml(booking.notes).replace(/\n/g, '<br>') + '</p>' : '';

            var actions = '';
            if (status === 'pending') {
                actions = '<button type="button" class="btn-action btn-confirm js-booking-decision" data-booking-id="' + booking.id + '" data-decision="confirm">Confirmed</button>' +
                    '<button type="button" class="btn-action btn-reject js-booking-decision" data-booking-id="' + booking.id + '" data-decision="reject">Reject</button>' +
                    '<button type="button" class="btn-action btn-cancel js-booking-decision" data-booking-id="' + booking.id + '" data-decision="cancel">Cancel</button>' +
                    '<button type="button" class="btn-action" disabled>Pending</button>';
            } else if (status === 'confirmed') {
                actions = '<button type="button" class="btn-action btn-done js-booking-decision" data-booking-id="' + booking.id + '" data-decision="done">Mark as Done</button>' +
                    '<button type="button" class="btn-action btn-cancel js-booking-decision" data-booking-id="' + booking.id + '" data-decision="cancel">Cancel</button>';
            }

            card.innerHTML =
                '<div>' +
                '<h3 class="booking-main-title">' + escapeHtml(booking.service_title) + '</h3>' +
                '<div class="booking-review-meta">' +
                '<span><strong>Customer:</strong> ' + escapeHtml(booking.customer_name) + '</span>' +
                '<span><strong>Date & Time:</strong> ' + escapeHtml(dtStr) + '</span>' +
                '<span><strong>Location:</strong> ' + addressText + '</span>' +
                '</div>' + notesHtml +
                '</div>' +
                '<div class="booking-review-actions">' +
                '<span class="booking-status-pill ' + pillClass + '">' + escapeHtml(displayStatusLabel(status)) + '</span>' +
                actions +
                '</div>';
            listEl.appendChild(card);
        });

        bindDecisionButtons(listEl);
    }

    function monthNamesShort() {
        var monthNames = ['January','February','March','April','May','June','July','August','September','October','November','December'];
        return monthNames[state.viewMonth - 1] + ' ' + state.viewYear;
    }

    function bindDecisionButtons(container) {
        container.querySelectorAll('.js-booking-decision').forEach(function (btn) {
            btn.addEventListener('click', onDecisionClick);
        });
    }

    function onDecisionClick() {
        var bookingId = this.getAttribute('data-booking-id');
        var decision = this.getAttribute('data-decision');
        if (decision === 'cancel' && !confirm('Cancel this booking?')) return;
        if (decision === 'reject' && !confirm('Reject this booking request?')) return;
        var fd = new FormData();
        fd.append('booking_id', bookingId);
        fd.append('decision', decision);
        var self = this;
        self.disabled = true;
        fetch('api/respond_booking.php', { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                self.disabled = false;
                if (!data.success) {
                    alert(data.error || 'Failed to update booking.');
                    return;
                }
                var id = parseInt(data.booking_id, 10);
                var nb = bookings.find(function (b) { return b.id === id; });
                if (nb && data.status) nb.status = data.status;
                renderTabCounts();
                renderCalendar();
                renderSelectedDaySummary();
                renderBookingList();
            })
            .catch(function () {
                self.disabled = false;
                alert('Failed to update booking.');
            });
    }

    document.getElementById('pd-cal-prev').addEventListener('click', function () {
        state.viewMonth--;
        if (state.viewMonth < 1) { state.viewMonth = 12; state.viewYear--; }
        state.selectedDateKey = null;
        renderCalendar();
        renderSelectedDaySummary();
        renderBookingList();
    });
    document.getElementById('pd-cal-next').addEventListener('click', function () {
        state.viewMonth++;
        if (state.viewMonth > 12) { state.viewMonth = 1; state.viewYear++; }
        state.selectedDateKey = null;
        renderCalendar();
        renderSelectedDaySummary();
        renderBookingList();
    });

    document.getElementById('pd-filter-tabs').addEventListener('click', function (e) {
        var tab = e.target.closest('.pd-filter-tab');
        if (!tab) return;
        var f = tab.getAttribute('data-filter');
        if (!f) return;
        state.activeFilter = f;
        document.querySelectorAll('.pd-filter-tab').forEach(function (t) { t.classList.toggle('is-active', t === tab); });
        renderBookingList();
    });

    state.selectedDateKey = state.todayKey;
    var tParts = state.todayKey.split('-');
    if (parseInt(tParts[0], 10) === state.viewYear && parseInt(tParts[1], 10) === state.viewMonth) {
        /* keep */
    } else {
        state.selectedDateKey = null;
    }

    renderTabCounts();
    renderCalendar();
    renderSelectedDaySummary();
    renderBookingList();
})();
</script>
<?php
$addServiceModalCategories = $categories;
if (!empty($addServiceModalCategories)) {
    require_once __DIR__ . '/includes/add_service_modal.php';
}
?>
<?php require_once 'includes/footer.php'; ?>
