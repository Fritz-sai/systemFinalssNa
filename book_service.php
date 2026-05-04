<?php
$pageTitle = 'Book Service';
require_once 'config/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    header('Location: login.php');
    exit;
}

$providerId = (int)($_GET['provider'] ?? 0);
$serviceId = (int)($_GET['service'] ?? 0);
$date = '';
$time = '';
$notes = '';
$pdo = getDBConnection();

$provStmt = $pdo->prepare("SELECT p.*, u.full_name FROM providers p JOIN users u ON p.user_id = u.id WHERE p.id = ? AND p.verification_status = 'approved'");
$provStmt->execute([$providerId]);
$provider = $provStmt->fetch();

if (!$provider) {
    header('Location: index.php');
    exit;
}

$servicesStmt = $pdo->prepare("SELECT s.*, c.name as category_name FROM services s JOIN service_categories c ON s.category_id = c.id WHERE s.provider_id = ?");
$servicesStmt->execute([$providerId]);
$services = $servicesStmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $serviceId = (int)($_POST['service_id'] ?? 0);
    $date = $_POST['scheduled_date'] ?? '';
    $time = $_POST['scheduled_time'] ?? '';
    $notes = trim($_POST['notes'] ?? '');
    
    if ($serviceId && $date && $time) {
        $scheduledDateTime = $date . ' ' . $time . ':00';
        
        if (isset($_POST['ajax'])) {
            // Validate service
            $stmt = $pdo->prepare("SELECT price_min, c.name as category_name FROM services s JOIN service_categories c ON s.category_id = c.id WHERE s.id = ?");
            $stmt->execute([$serviceId]);
            $svc = $stmt->fetch();
            
            if (!$svc) {
                echo json_encode(['success' => false, 'error' => 'Invalid service selected.']);
                exit;
            }
            
            $downPayment = $svc['price_min'] * 0.30;
            $reference = 'GC' . date('YmdHis') . rand(1000, 9999);
            
            $pdo->prepare("INSERT INTO bookings (customer_id, provider_id, service_id, scheduled_date, notes) VALUES (?, ?, ?, ?, ?)")
                ->execute([$_SESSION['user_id'], $providerId, $serviceId, $scheduledDateTime, $notes]);
            
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'reference' => $reference,
                'amount' => $downPayment,
                'service_name' => $svc['category_name'],
                'date' => date('M d, Y', strtotime($date)) . ' ' . date('h:i A', strtotime($time))
            ]);
            exit;
        }

        // Fallback for non-ajax
        $pdo->prepare("INSERT INTO bookings (customer_id, provider_id, service_id, scheduled_date, notes) VALUES (?, ?, ?, ?, ?)")
            ->execute([$_SESSION['user_id'], $providerId, $serviceId, $scheduledDateTime, $notes]);
        header('Location: providers.php');
        exit;
    } else {
        if (isset($_POST['ajax'])) {
            echo json_encode(['success' => false, 'error' => 'Please fill in all required fields.']);
            exit;
        }
    }
}

require_once 'includes/header.php';
$servicesJson = json_encode($services);
?>

<style>
/* ── Book Service – Premium Redesign ─────────────────────────── */
.bs-wrapper { padding: 2.5rem 1.25rem 4rem; max-width: 1000px; margin: 0 auto; animation: bsFadeUp .5s ease both; }
@keyframes bsFadeUp { from { opacity: 0; transform: translateY(18px); } to { opacity: 1; transform: translateY(0); } }

.booking-layout { display: grid; grid-template-columns: 1fr 340px; gap: 2rem; align-items: start; }
@media (max-width: 860px) { .booking-layout { grid-template-columns: 1fr; } }

/* Header */
.bs-header { display: flex; align-items: center; gap: 1rem; margin-bottom: 1.75rem; }
.bs-header-icon { width: 52px; height: 52px; border-radius: 14px; background: linear-gradient(135deg, #3A86FF 0%, #6C5CE7 100%); display: flex; align-items: center; justify-content: center; flex-shrink: 0; box-shadow: 0 6px 20px rgba(58,134,255,.28); }
.bs-header-icon svg { width: 26px; height: 26px; color: #fff; }
.bs-header-text h1 { font-size: 1.55rem; font-weight: 700; color: var(--text-dark); line-height: 1.25; margin: 0; }
.bs-header-text p { font-size: .92rem; color: var(--text-muted); margin-top: 2px; margin-bottom: 0; }

/* Card */
.bs-card { background: var(--bg-white); border-radius: 16px; border: 1px solid var(--border-color); box-shadow: 0 4px 24px rgba(0,0,0,.06); overflow: hidden; animation: bsFadeUp .55s ease both; animation-delay: .08s; transition: box-shadow .3s ease; }
.bs-card:hover { box-shadow: 0 8px 32px rgba(0,0,0,.09); }

/* Provider banner */
.bs-provider-banner { background: linear-gradient(135deg, #3A86FF 0%, #6C5CE7 50%, #a855f7 100%); padding: 1.35rem 1.5rem; display: flex; align-items: center; gap: 1rem; position: relative; overflow: hidden; }
.bs-provider-banner::before { content: ''; position: absolute; inset: 0; background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.06'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E"); }
.bs-provider-avatar { width: 48px; height: 48px; border-radius: 50%; border: 2.5px solid rgba(255,255,255,.45); background: rgba(255,255,255,.2); display: flex; align-items: center; justify-content: center; color: #fff; font-weight: 700; font-size: 1.15rem; flex-shrink: 0; overflow: hidden; position: relative; z-index: 1; }
.bs-provider-avatar img { width: 100%; height: 100%; object-fit: cover; }
.bs-provider-info { position: relative; z-index: 1; }
.bs-provider-info h2 { color: #fff; font-size: 1.08rem; font-weight: 600; margin: 0; }
.bs-provider-info span { color: rgba(255,255,255,.78); font-size: .82rem; display: flex; align-items: center; gap: 4px; margin-top: 2px; }
.bs-provider-info span svg { width: 13px; height: 13px; }

/* Form body */
.bs-form-body { padding: 1.75rem 1.5rem 1.5rem; }
.bs-form-group { margin-bottom: 1.35rem; }
.bs-form-group label { display: flex; align-items: center; gap: 6px; margin-bottom: .45rem; font-weight: 600; font-size: .88rem; color: var(--text-dark); letter-spacing: .01em; }
.bs-form-group label .bs-required { color: #e74c3c; font-size: .8rem; }
.bs-form-group label svg { width: 16px; height: 16px; color: var(--accent); flex-shrink: 0; }

.bs-input-wrap { position: relative; }
.bs-input-wrap svg.bs-field-icon { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); width: 18px; height: 18px; color: #9ca3af; pointer-events: none; transition: color .2s ease; z-index: 1; }
.bs-input-wrap:focus-within svg.bs-field-icon { color: var(--accent); }
.bs-input-wrap select, .bs-input-wrap input, .bs-input-wrap textarea { width: 100%; padding: .78rem .9rem .78rem 2.65rem; border: 1.5px solid var(--border-color); border-radius: 10px; font-size: .95rem; font-family: inherit; background: var(--bg-white); color: var(--text-dark); transition: border-color .25s ease, box-shadow .25s ease; -webkit-appearance: none; appearance: none; }
.bs-input-wrap select { background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%236c757d' viewBox='0 0 16 16'%3E%3Cpath d='M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708z'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 14px center; background-size: 14px; padding-right: 2.5rem; cursor: pointer; }
.bs-input-wrap select:focus, .bs-input-wrap input:focus, .bs-input-wrap textarea:focus { outline: none; border-color: var(--accent); box-shadow: 0 0 0 3.5px rgba(58,134,255,.12); }
.bs-input-wrap textarea { padding-left: .9rem; min-height: 96px; resize: vertical; }
.bs-form-hint { font-size: .78rem; color: var(--text-muted); margin-top: 5px; padding-left: 2px; }

/* Divider */
.bs-divider { height: 1px; background: var(--border-color); margin: .35rem 0 1.5rem; }

/* Action buttons */
.bs-actions { display: flex; align-items: center; gap: .75rem; margin-top: .25rem; }
.bs-btn-book { display: inline-flex; align-items: center; justify-content: center; gap: 8px; padding: .78rem 1.75rem; border: none; border-radius: 11px; background: linear-gradient(135deg, #3A86FF 0%, #6C5CE7 100%); color: #fff; font-weight: 600; font-size: .95rem; cursor: pointer; transition: transform .2s ease, box-shadow .2s ease; position: relative; overflow: hidden; }
.bs-btn-book::before { content: ''; position: absolute; inset: 0; background: linear-gradient(135deg, rgba(255,255,255,.15) 0%, transparent 60%); opacity: 0; transition: opacity .25s ease; }
.bs-btn-book:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(58,134,255,.35); }
.bs-btn-book:hover::before { opacity: 1; }
.bs-btn-book:active { transform: translateY(0); }
.bs-btn-book svg { width: 18px; height: 18px; }

.bs-btn-back { display: inline-flex; align-items: center; gap: 6px; padding: .78rem 1.35rem; border: 1.5px solid var(--border-color); border-radius: 11px; background: var(--bg-white); color: var(--text-soft); font-weight: 500; font-size: .95rem; cursor: pointer; transition: all .25s ease; text-decoration: none; }
.bs-btn-back:hover { background: var(--bg-gray); border-color: #d1d5db; color: var(--text-dark); }
.bs-btn-back svg { width: 16px; height: 16px; }

/* Trust footer */
.bs-trust { display: flex; align-items: center; gap: 8px; padding: 1rem 1.5rem; background: linear-gradient(135deg, #f0f5ff 0%, #f5f0ff 100%); border-top: 1px solid rgba(58,134,255,.08); }
.bs-trust svg { width: 18px; height: 18px; color: var(--accent); flex-shrink: 0; }
.bs-trust span { font-size: .82rem; color: var(--text-muted); font-weight: 500; }

/* Empty state */
.bs-empty { padding: 3rem 2rem; text-align: center; }
.bs-empty-icon { width: 64px; height: 64px; margin: 0 auto 1rem; border-radius: 50%; background: linear-gradient(135deg, #f0f5ff 0%, #f5f0ff 100%); display: flex; align-items: center; justify-content: center; }
.bs-empty-icon svg { width: 28px; height: 28px; color: var(--accent); }
.bs-empty h3 { font-size: 1.1rem; font-weight: 600; color: var(--text-dark); margin-bottom: .35rem; }
.bs-empty p { font-size: .9rem; color: var(--text-muted); margin-bottom: 1.25rem; }

/* Modal */
@keyframes modalPop { 0% { opacity: 0; transform: scale(0.9); } 100% { opacity: 1; transform: scale(1); } }
.modal-show { display: flex !important; animation: modalPop 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards; }

@media(max-width:520px){
    .bs-form-body { padding: 1.25rem 1rem 1rem; }
    .bs-provider-banner { padding: 1rem; }
    .bs-actions { flex-direction: column; }
    .bs-btn-book, .bs-btn-back { width: 100%; justify-content: center; }
}
</style>

<section class="bs-wrapper">
    <!-- Page header -->
    <div class="bs-header">
        <div class="bs-header-icon">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5m-9-6h.008v.008H12v-.008ZM12 15h.008v.008H12V15Zm0 2.25h.008v.008H12v-.008ZM9.75 15h.008v.008H9.75V15Zm0 2.25h.008v.008H9.75v-.008ZM7.5 15h.008v.008H7.5V15Zm0 2.25h.008v.008H7.5v-.008Zm6.75-4.5h.008v.008h-.008v-.008Zm0 2.25h.008v.008h-.008V15Zm0 2.25h.008v.008h-.008v-.008Zm2.25-4.5h.008v.008H16.5v-.008Zm0 2.25h.008v.008H16.5V15Z"/></svg>
        </div>
        <div class="bs-header-text">
            <h1>Book a Service</h1>
            <p>Fill in the details below to schedule your booking.</p>
        </div>
    </div>

    <?php if (empty($services)): ?>
        <div class="bs-card">
            <div class="bs-empty">
                <div class="bs-empty-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z"/></svg>
                </div>
                <h3>No Services Available</h3>
                <p>This provider has not added any services yet. Check back later!</p>
                <a href="provider_profile.php?id=<?= $providerId ?>" class="bs-btn-back">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18"/></svg>
                    Back to Profile
                </a>
            </div>
        </div>
    <?php else: ?>
    
    <div class="booking-layout">
        <!-- Main Form Column -->
        <form method="POST" class="bs-card" id="booking-form" onsubmit="handleBooking(event)">
            <input type="hidden" name="ajax" value="1">
            <!-- Provider banner -->
            <div class="bs-provider-banner">
                <div class="bs-provider-avatar">
                    <?= strtoupper(substr($provider['full_name'], 0, 1)) ?>
                </div>
                <div class="bs-provider-info">
                    <h2><?= htmlspecialchars($provider['full_name']) ?></h2>
                    <span>
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12c0 1.268-.63 2.39-1.593 3.068a3.745 3.745 0 0 1-1.043 3.296 3.745 3.745 0 0 1-3.296 1.043A3.745 3.745 0 0 1 12 21c-1.268 0-2.39-.63-3.068-1.593a3.746 3.746 0 0 1-3.296-1.043 3.745 3.745 0 0 1-1.043-3.296A3.745 3.745 0 0 1 3 12c0-1.268.63-2.39 1.593-3.068a3.745 3.745 0 0 1 1.043-3.296 3.746 3.746 0 0 1 3.296-1.043A3.746 3.746 0 0 1 12 3c1.268 0 2.39.63 3.068 1.593a3.746 3.746 0 0 1 3.296 1.043 3.746 3.746 0 0 1 1.043 3.296A3.745 3.745 0 0 1 21 12Z"/></svg>
                        Verified Provider
                    </span>
                </div>
            </div>

            <div class="bs-form-body">
                <!-- Service selection -->
                <div class="bs-form-group">
                    <label>
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9.568 3H5.25A2.25 2.25 0 0 0 3 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 0 0 5.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 0 0 9.568 3Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M6 6h.008v.008H6V6Z"/></svg>
                        Select Service <span class="bs-required">*</span>
                    </label>
                    <div class="bs-input-wrap">
                        <svg class="bs-field-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 12h16.5m-16.5 3.75h16.5M3.75 19.5h16.5M5.625 4.5h12.75a1.875 1.875 0 0 1 0 3.75H5.625a1.875 1.875 0 0 1 0-3.75Z"/></svg>
                        <select name="service_id" id="service_id" required onchange="updateCalculations()">
                            <option value="">Choose a service...</option>
                            <?php foreach ($services as $s): ?>
                                <option value="<?= $s['id'] ?>" <?= $serviceId && (int)$s['id'] === $serviceId ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($s['category_name']) ?> — ₱<?= number_format($s['price_min']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Preferred Date -->
                <div class="bs-form-group">
                    <label>
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5"/></svg>
                        Preferred Date <span class="bs-required">*</span>
                    </label>
                    <div class="bs-input-wrap">
                        <svg class="bs-field-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5"/></svg>
                        <input type="date" name="scheduled_date" required min="<?= date('Y-m-d') ?>" value="<?= htmlspecialchars($date) ?>">
                    </div>
                    <div class="bs-form-hint">Select your preferred service date</div>
                </div>

                <!-- Preferred Time -->
                <div class="bs-form-group">
                    <label>
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m6.75 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                        Preferred Time <span class="bs-required">*</span>
                    </label>
                    <div class="bs-input-wrap">
                        <svg class="bs-field-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m6.75 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                        <input type="time" name="scheduled_time" required value="<?= htmlspecialchars($time) ?>">
                    </div>
                    <div class="bs-form-hint">Select your preferred service time</div>
                </div>

                <div class="bs-divider"></div>

                <!-- Notes -->
                <div class="bs-form-group">
                    <label>
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M7.5 8.25h9m-9 3H12m-9.75 1.51c0 1.6 1.123 2.994 2.707 3.227 1.087.16 2.185.283 3.293.369V21l4.076-4.076a1.526 1.526 0 0 1 1.037-.443 48.261 48.261 0 0 0 5.048-.369c1.588-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0 0 12 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018Z"/></svg>
                        Notes <span style="font-weight:400;color:var(--text-muted);font-size:.8rem">(Optional)</span>
                    </label>
                    <div class="bs-input-wrap">
                        <textarea name="notes" placeholder="Any special requests or instructions..."><?= htmlspecialchars($notes) ?></textarea>
                    </div>
                    <div class="bs-form-hint">Let the provider know about any special requirements.</div>
                </div>

                <div class="bs-divider"></div>

                <!-- SECURE YOUR BOOKING -->
                <div style="margin-bottom: 1.5rem;" id="payment-section">
                    <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem;">
                        <svg style="width:24px;height:24px;color:#007bff;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                        <h3 style="margin: 0; font-size: 1.1rem; color: #0f172a;">Secure Your Booking</h3>
                        <span style="background: #dcfce7; color: #166534; font-size: 0.7rem; font-weight: 600; padding: 2px 6px; border-radius: 4px;">Required</span>
                    </div>
                    <p style="color: #64748b; font-size: 0.9rem; margin-bottom: 1.25rem;">To ensure a safe and smooth booking experience, a down payment is required to secure your booking.</p>

                    <!-- Downpayment Info Box -->
                    <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1.25rem; display: flex; flex-wrap: wrap; gap: 1.5rem; margin-bottom: 1.5rem;">
                        <div style="flex: 1; min-width: 140px; border-right: 1px solid #e2e8f0; text-align: center; padding-right: 1.5rem;">
                            <div style="font-size: 0.85rem; color: #475569; font-weight: 600; margin-bottom: 0.25rem;">Down Payment (30%)</div>
                            <div style="font-size: 1.5rem; font-weight: 700; color: #007bff;" id="dp-amount">₱0.00</div>
                            <div style="font-size: 0.8rem; color: #64748b; margin-top: 0.25rem;" id="dp-total-text">of ₱0.00 total price</div>
                        </div>
                        <div style="flex: 1; min-width: 140px; font-size: 0.85rem; color: #475569;">
                            <strong style="color: #0f172a; margin-bottom: 0.5rem; display: block;">Why Down Payment?</strong>
                            <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.25rem;">
                                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg> Helps ensure serious bookings only
                            </div>
                            <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.25rem;">
                                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg> Protects you from no-shows
                            </div>
                            <div style="display: flex; align-items: center; gap: 0.5rem;">
                                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg> Keeps our community trustworthy
                            </div>
                        </div>
                    </div>

                    <!-- GCash Form -->
                    <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1rem;">
                        <div style="background: #0052cc; width: 32px; height: 32px; border-radius: 8px; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; font-style: italic; font-size: 1.1rem; box-shadow: 0 4px 6px rgba(0,82,204,0.3);">G</div>
                        <div>
                            <h3 style="margin: 0; font-size: 1rem; color: #0f172a;">Pay with GCash</h3>
                            <p style="color: #64748b; font-size: 0.85rem; margin: 0;">Enter your GCash details to pay the down payment.</p>
                        </div>
                    </div>

                    <div style="margin-top: 1.25rem;">
                        <div class="bs-form-group">
                            <label style="font-weight: 600; color: #334155; margin-bottom: 0.5rem; display: block; font-size: 0.85rem;">Mobile Number</label>
                            <div class="bs-input-wrap" style="display: flex; border: 1.5px solid var(--border-color); border-radius: 10px; overflow: hidden; background: white; transition: all 0.2s;">
                                <div style="padding: 0.78rem 1rem; background: #f1f5f9; border-right: 1.5px solid var(--border-color); color: #475569; font-weight: 600; font-size: 0.95rem;">+63</div>
                                <input type="text" id="mobile_number" placeholder="912 345 6789" required style="border: none; padding: 0.78rem 1rem; width: 100%; outline: none; font-size: 0.95rem; color: #0f172a; border-radius: 0;" maxlength="10" oninput="this.value = this.value.replace(/[^0-9]/g, '')">
                            </div>
                            <div class="bs-form-hint">Enter the mobile number linked to your GCash account.</div>
                        </div>

                        <div class="bs-form-group" style="margin-bottom: 1.5rem;">
                            <label style="font-weight: 600; color: #334155; margin-bottom: 0.5rem; display: block; font-size: 0.85rem;">MPIN</label>
                            <div class="bs-input-wrap" style="display: flex; border: 1.5px solid var(--border-color); border-radius: 10px; overflow: hidden; background: white; transition: all 0.2s; position: relative;">
                                <input type="password" id="mpin" placeholder="Enter your 4-digit MPIN" required style="border: none; padding: 0.78rem 1rem; width: 100%; outline: none; font-size: 1rem; letter-spacing: 4px; color: #0f172a; font-weight: 600; border-radius: 0;" maxlength="4" oninput="this.value = this.value.replace(/[^0-9]/g, '')">
                                <button type="button" onclick="toggleMpin()" style="background: none; border: none; padding: 0 1rem; color: #64748b; cursor: pointer; display: flex; align-items: center; position: absolute; right: 0; top: 0; bottom: 0; transition: color 0.2s;" onmouseover="this.style.color='#007bff'" onmouseout="this.style.color='#64748b'">
                                    <svg id="eye-icon" width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                                </button>
                            </div>
                            <div class="bs-form-hint">Your MPIN is used to authorize the payment securely.</div>
                        </div>

                        <div style="background: #fffbeb; border: 1px solid #fde68a; border-radius: 10px; padding: 1rem; display: flex; gap: 0.75rem; align-items: flex-start; margin-bottom: 1.5rem;">
                            <svg style="width:20px;height:20px;color:#d97706;flex-shrink:0;margin-top:2px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                            <div>
                                <strong style="display: block; color: #92400e; font-size: 0.9rem; margin-bottom: 0.25rem;">Important Note</strong>
                                <p style="color: #b45309; font-size: 0.85rem; margin: 0; line-height: 1.4;">If the provider does not accept your booking within 3 hours, your down payment will be automatically refunded to your GCash account.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Action buttons -->
                <div class="bs-actions">
                    <button type="submit" id="pay-btn" class="bs-btn-book" style="width: 100%; display: flex; align-items: center; justify-content: center; gap: 0.5rem; background: linear-gradient(135deg, #0052cc 0%, #3A86FF 100%);" disabled>
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z"/></svg>
                        <span id="btn-text">Select a Service</span>
                    </button>
                    <a href="provider_profile.php?id=<?= $providerId ?>" class="bs-btn-back" style="width: auto; padding: .78rem 1.25rem;">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18"/></svg>
                        Back
                    </a>
                </div>
            </div>

            <!-- Trust footer -->
            <div class="bs-trust">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z"/></svg>
                <span>Your booking is secure and your information is safe with us.</span>
            </div>
        </form>

        <!-- Right Side Panel -->
        <div class="summary-panel">
            <!-- Booking Summary -->
            <div class="bs-card" style="padding: 1.5rem; margin-bottom: 1.25rem; position: sticky; top: 2rem;">
                <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 1.25rem; padding-bottom: 1rem; border-bottom: 1px solid var(--border-color);">
                    <svg style="width:20px;height:20px;color:var(--accent);" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                    <h3 style="margin: 0; font-size: 1.05rem; color: var(--text-dark); font-weight: 600;">Booking Summary</h3>
                </div>
                
                <div style="display: flex; justify-content: space-between; margin-bottom: 0.75rem; font-size: 0.9rem; color: var(--text-muted);">
                    <span>Service Price</span>
                    <strong id="sum-service-price" style="color: var(--text-dark);">₱0.00</strong>
                </div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 1.25rem; font-size: 0.9rem; color: var(--text-muted);">
                    <span>Down Payment (30%)</span>
                    <strong id="sum-dp" style="color: var(--text-dark);">₱0.00</strong>
                </div>
                
                <div style="display: flex; justify-content: space-between; align-items: center; padding-top: 1rem; border-top: 1px dashed #cbd5e1; margin-bottom: 1.25rem;">
                    <span style="font-weight: 600; color: var(--text-dark);">Total</span>
                    <strong id="sum-total" style="font-size: 1.25rem; color: var(--accent);">₱0.00</strong>
                </div>

                <div style="background: #ecfdf5; border-radius: 10px; padding: 1rem; display: flex; gap: 0.75rem; align-items: flex-start; margin-bottom: 1.5rem;">
                    <svg style="width:20px;height:20px;color:#10b981;flex-shrink:0;margin-top:2px;" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>
                    <div>
                        <strong style="display: block; color: #065f46; font-size: 0.9rem; margin-bottom: 0.25rem;">You Pay Now</strong>
                        <div id="sum-pay-now" style="font-size: 1.25rem; font-weight: 700; color: #059669; line-height: 1.2;">₱0.00</div>
                        <div style="font-size: 0.8rem; color: #047857; margin-top: 0.25rem;">30% down payment</div>
                    </div>
                </div>

                <!-- Info Cards -->
                <div style="background: #eff6ff; color: #3b82f6; padding: 1rem; border-radius: 10px; display: flex; gap: 0.75rem; align-items: flex-start; margin-bottom: 1rem;">
                    <svg style="width:20px;height:20px;flex-shrink:0;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    <div>
                        <strong style="display: block; color: #1e3a8a; font-size: 0.85rem; margin-bottom: 0.25rem;">Provider Acceptance</strong>
                        <p style="font-size: 0.8rem; color: #1e40af; margin: 0; line-height: 1.4;">The provider has 3 hours to accept your booking, or your payment will be refunded.</p>
                    </div>
                </div>

                <div style="background: #f8fafc; border: 1px solid #e2e8f0; padding: 1rem; border-radius: 10px; display: flex; gap: 0.75rem; align-items: flex-start;">
                    <svg style="width:20px;height:20px;color:#4f46e5;flex-shrink:0;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                    <div>
                        <strong style="display: block; color: var(--text-dark); font-size: 0.85rem; margin-bottom: 0.25rem;">Secure & Protected</strong>
                        <p style="font-size: 0.8rem; color: var(--text-muted); margin: 0; line-height: 1.4;">Your payment is held securely and released only when accepted.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</section>

<!-- Success Modal -->
<div id="success-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15,23,42,0.6); z-index: 1000; align-items: center; justify-content: center; backdrop-filter: blur(4px);">
    <div style="background: white; border-radius: 20px; padding: 2.5rem 2rem; width: 100%; max-width: 420px; text-align: center; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);">
        
        <div style="width: 70px; height: 70px; background: #10b981; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; margin: 0 auto 1.5rem; box-shadow: 0 0 0 10px #ecfdf5; position: relative;">
            <svg width="36" height="36" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
        </div>
        
        <h2 style="margin: 0 0 0.5rem; font-size: 1.5rem; color: #0f172a; font-weight: 700;">Booking Secured!</h2>
        <p style="color: #64748b; margin-bottom: 2rem; font-size: 0.95rem;">Your down payment was successful. Waiting for provider acceptance.</p>

        <div style="background: #f8fafc; border-radius: 12px; padding: 1.5rem; text-align: left; margin-bottom: 2rem; border: 1px solid #e2e8f0;">
            <div style="display: flex; justify-content: space-between; margin-bottom: 0.875rem; font-size: 0.9rem;">
                <span style="color: #64748b;">Reference No.</span>
                <strong style="color: #0f172a; font-family: monospace; font-size: 1rem;" id="modal-ref">...</strong>
            </div>
            <div style="display: flex; justify-content: space-between; margin-bottom: 0.875rem; font-size: 0.9rem;">
                <span style="color: #64748b;">Service</span>
                <strong style="color: #0f172a; text-align: right; max-width: 60%; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" id="modal-service">...</strong>
            </div>
            <div style="display: flex; justify-content: space-between; margin-bottom: 0.875rem; font-size: 0.9rem;">
                <span style="color: #64748b;">Amount Paid</span>
                <strong style="color: #059669; font-size: 1.05rem;" id="modal-amount">...</strong>
            </div>
            <div style="display: flex; justify-content: space-between; margin-bottom: 0.875rem; font-size: 0.9rem;">
                <span style="color: #64748b;">Date & Time</span>
                <strong style="color: #0f172a; text-align: right; max-width: 60%;" id="modal-date">...</strong>
            </div>
            <div style="display: flex; justify-content: space-between; font-size: 0.9rem;">
                <span style="color: #64748b;">Payment Method</span>
                <strong style="color: #0f172a; display: flex; align-items: center; gap: 0.35rem;">
                    <span style="background: #0052cc; color: white; font-size: 0.65rem; padding: 2px 5px; border-radius: 4px; font-weight: bold; font-style: italic;">G</span>
                    GCash
                </strong>
            </div>
        </div>

        <div style="display: flex; flex-direction: column; gap: 0.75rem;">
            <a href="my_bookings.php" class="bs-btn-book" style="width: 100%; text-decoration: none;">Go to My Bookings</a>
            <button onclick="window.print()" class="bs-btn-back" style="width: 100%; justify-content: center; border-color: #cbd5e1;">
                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                Download Receipt
            </button>
        </div>
    </div>
</div>

<style>
@keyframes spin { 100% { transform: rotate(360deg); } }
</style>

<script>
const services = <?= $servicesJson ?>;

function updateCalculations() {
    const serviceId = document.getElementById('service_id').value;
    const btn = document.getElementById('pay-btn');
    const btnText = document.getElementById('btn-text');
    
    if (!serviceId) {
        // Reset defaults
        document.getElementById('dp-amount').innerText = '₱0.00';
        document.getElementById('dp-total-text').innerText = 'of ₱0.00 total price';
        
        document.getElementById('sum-service-price').innerText = '₱0.00';
        document.getElementById('sum-dp').innerText = '₱0.00';
        document.getElementById('sum-total').innerText = '₱0.00';
        document.getElementById('sum-pay-now').innerText = '₱0.00';
        
        btn.disabled = true;
        btnText.innerText = 'Select a Service';
        return;
    }

    const svc = services.find(s => s.id == serviceId);
    if (svc) {
        const total = parseFloat(svc.price_min);
        const dp = total * 0.30;
        
        const formattedTotal = total.toFixed(2);
        const formattedDp = dp.toFixed(2);

        // Update form UI
        document.getElementById('dp-amount').innerText = '₱' + formattedDp;
        document.getElementById('dp-total-text').innerText = 'of ₱' + formattedTotal + ' total price';
        
        // Update summary
        document.getElementById('sum-service-price').innerText = '₱' + formattedTotal;
        document.getElementById('sum-dp').innerText = '₱' + formattedDp;
        document.getElementById('sum-total').innerText = '₱' + formattedTotal;
        document.getElementById('sum-pay-now').innerText = '₱' + formattedDp;
        
        // Update button
        btn.disabled = false;
        btnText.innerText = 'Pay Down Payment (₱' + formattedDp + ')';
    }
}

function toggleMpin() {
    const input = document.getElementById('mpin');
    const icon = document.getElementById('eye-icon');
    if (input.type === 'password') {
        input.type = 'text';
        icon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"></path>';
    } else {
        input.type = 'password';
        icon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>';
    }
}

async function handleBooking(e) {
    e.preventDefault();
    
    const serviceId = document.getElementById('service_id').value;
    if (!serviceId) return;

    const mobile = document.getElementById('mobile_number').value;
    const mpin = document.getElementById('mpin').value;

    if (mobile.length !== 10) {
        alert('Please enter a valid 10-digit mobile number.');
        return;
    }
    if (mpin.length !== 4) {
        alert('Please enter your 4-digit MPIN.');
        return;
    }

    const btn = document.getElementById('pay-btn');
    const originalContent = btn.innerHTML;
    
    // Loading state
    btn.innerHTML = '<svg style="width:20px;height:20px;animation:spin 1s linear infinite;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg> Processing Payment...';
    btn.disabled = true;
    btn.style.opacity = '0.8';

    try {
        const formData = new FormData(e.target);
        const response = await fetch('book_service.php?provider=<?= $providerId ?>', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            // Populate modal
            document.getElementById('modal-ref').innerText = result.reference;
            document.getElementById('modal-service').innerText = result.service_name;
            document.getElementById('modal-amount').innerText = '₱' + parseFloat(result.amount).toFixed(2);
            document.getElementById('modal-date').innerText = result.date;
            
            // Show success modal
            const modal = document.getElementById('success-modal');
            modal.style.display = 'flex';
            
            // Trigger reflow for animation
            void modal.offsetWidth;
            modal.classList.add('modal-show');
        } else {
            alert(result.error || 'Booking failed. Please try again.');
            btn.innerHTML = originalContent;
            btn.disabled = false;
            btn.style.opacity = '1';
        }
    } catch (error) {
        alert('An error occurred during booking. Please try again.');
        btn.innerHTML = originalContent;
        btn.disabled = false;
        btn.style.opacity = '1';
    }
}

// Trigger initial calc if there's a pre-selected service
document.addEventListener('DOMContentLoaded', () => {
    if(document.getElementById('service_id').value) {
        updateCalculations();
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>
