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
        $pdo->prepare("INSERT INTO bookings (customer_id, provider_id, service_id, scheduled_date, notes) VALUES (?, ?, ?, ?, ?)")
            ->execute([$_SESSION['user_id'], $providerId, $serviceId, $scheduledDateTime, $notes]);
        header('Location: providers.php');
        exit;
    }
}

require_once 'includes/header.php';
?>

<style>
/* ── Book Service – Premium Redesign ─────────────────────────── */
.bs-page{padding:2.5rem 1.25rem 4rem;max-width:640px;margin:0 auto;animation:bsFadeUp .5s ease both}
@keyframes bsFadeUp{from{opacity:0;transform:translateY(18px)}to{opacity:1;transform:translateY(0)}}

/* Header */
.bs-header{display:flex;align-items:center;gap:1rem;margin-bottom:1.75rem}
.bs-header-icon{width:52px;height:52px;border-radius:14px;background:linear-gradient(135deg,#3A86FF 0%,#6C5CE7 100%);display:flex;align-items:center;justify-content:center;flex-shrink:0;box-shadow:0 6px 20px rgba(58,134,255,.28)}
.bs-header-icon svg{width:26px;height:26px;color:#fff}
.bs-header-text h1{font-size:1.55rem;font-weight:700;color:var(--text-dark);line-height:1.25}
.bs-header-text p{font-size:.92rem;color:var(--text-muted);margin-top:2px}

/* Card */
.bs-card{background:var(--bg-white);border-radius:16px;border:1px solid var(--border-color);box-shadow:0 4px 24px rgba(0,0,0,.06);overflow:hidden;animation:bsFadeUp .55s ease both;animation-delay:.08s;transition:box-shadow .3s ease}
.bs-card:hover{box-shadow:0 8px 32px rgba(0,0,0,.09)}

/* Provider banner */
.bs-provider-banner{background:linear-gradient(135deg,#3A86FF 0%,#6C5CE7 50%,#a855f7 100%);padding:1.35rem 1.5rem;display:flex;align-items:center;gap:1rem;position:relative;overflow:hidden}
.bs-provider-banner::before{content:'';position:absolute;inset:0;background:url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.06'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E")}
.bs-provider-avatar{width:48px;height:48px;border-radius:50%;border:2.5px solid rgba(255,255,255,.45);background:rgba(255,255,255,.2);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:1.15rem;flex-shrink:0;overflow:hidden;position:relative;z-index:1}
.bs-provider-avatar img{width:100%;height:100%;object-fit:cover}
.bs-provider-info{position:relative;z-index:1}
.bs-provider-info h2{color:#fff;font-size:1.08rem;font-weight:600}
.bs-provider-info span{color:rgba(255,255,255,.78);font-size:.82rem;display:flex;align-items:center;gap:4px;margin-top:2px}
.bs-provider-info span svg{width:13px;height:13px}

/* Form body */
.bs-form-body{padding:1.75rem 1.5rem 1.5rem}

/* Form groups */
.bs-form-group{margin-bottom:1.35rem}
.bs-form-group label{display:flex;align-items:center;gap:6px;margin-bottom:.45rem;font-weight:600;font-size:.88rem;color:var(--text-dark);letter-spacing:.01em}
.bs-form-group label .bs-required{color:#e74c3c;font-size:.8rem}
.bs-form-group label svg{width:16px;height:16px;color:var(--accent);flex-shrink:0}

.bs-input-wrap{position:relative}
.bs-input-wrap svg.bs-field-icon{position:absolute;left:14px;top:50%;transform:translateY(-50%);width:18px;height:18px;color:#9ca3af;pointer-events:none;transition:color .2s ease;z-index:1}
.bs-input-wrap:focus-within svg.bs-field-icon{color:var(--accent)}
.bs-input-wrap select,
.bs-input-wrap input,
.bs-input-wrap textarea{width:100%;padding:.78rem .9rem .78rem 2.65rem;border:1.5px solid var(--border-color);border-radius:10px;font-size:.95rem;font-family:inherit;background:var(--bg-white);color:var(--text-dark);transition:border-color .25s ease,box-shadow .25s ease;-webkit-appearance:none;appearance:none}
.bs-input-wrap select{background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%236c757d' viewBox='0 0 16 16'%3E%3Cpath d='M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708z'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 14px center;background-size:14px;padding-right:2.5rem;cursor:pointer}
.bs-input-wrap select:focus,
.bs-input-wrap input:focus,
.bs-input-wrap textarea:focus{outline:none;border-color:var(--accent);box-shadow:0 0 0 3.5px rgba(58,134,255,.12)}
.bs-input-wrap textarea{padding-left:.9rem;min-height:96px;resize:vertical}
.bs-form-hint{font-size:.78rem;color:var(--text-muted);margin-top:5px;padding-left:2px}

/* Divider */
.bs-divider{height:1px;background:var(--border-color);margin:.35rem 0 1.35rem}

/* Action buttons */
.bs-actions{display:flex;align-items:center;gap:.75rem;margin-top:.25rem}
.bs-btn-book{display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:.78rem 1.75rem;border:none;border-radius:11px;background:linear-gradient(135deg,#3A86FF 0%,#6C5CE7 100%);color:#fff;font-weight:600;font-size:.95rem;cursor:pointer;transition:transform .2s ease,box-shadow .2s ease;position:relative;overflow:hidden}
.bs-btn-book::before{content:'';position:absolute;inset:0;background:linear-gradient(135deg,rgba(255,255,255,.15) 0%,transparent 60%);opacity:0;transition:opacity .25s ease}
.bs-btn-book:hover{transform:translateY(-2px);box-shadow:0 8px 24px rgba(58,134,255,.35)}
.bs-btn-book:hover::before{opacity:1}
.bs-btn-book:active{transform:translateY(0)}
.bs-btn-book svg{width:18px;height:18px}

.bs-btn-back{display:inline-flex;align-items:center;gap:6px;padding:.78rem 1.35rem;border:1.5px solid var(--border-color);border-radius:11px;background:var(--bg-white);color:var(--text-soft);font-weight:500;font-size:.95rem;cursor:pointer;transition:all .25s ease;text-decoration:none}
.bs-btn-back:hover{background:var(--bg-gray);border-color:#d1d5db;color:var(--text-dark)}
.bs-btn-back svg{width:16px;height:16px}

/* Trust footer */
.bs-trust{display:flex;align-items:center;gap:8px;padding:1rem 1.5rem;background:linear-gradient(135deg,#f0f5ff 0%,#f5f0ff 100%);border-top:1px solid rgba(58,134,255,.08)}
.bs-trust svg{width:18px;height:18px;color:var(--accent);flex-shrink:0}
.bs-trust span{font-size:.82rem;color:var(--text-muted);font-weight:500}

/* Empty state */
.bs-empty{padding:3rem 2rem;text-align:center}
.bs-empty-icon{width:64px;height:64px;margin:0 auto 1rem;border-radius:50%;background:linear-gradient(135deg,#f0f5ff 0%,#f5f0ff 100%);display:flex;align-items:center;justify-content:center}
.bs-empty-icon svg{width:28px;height:28px;color:var(--accent)}
.bs-empty h3{font-size:1.1rem;font-weight:600;color:var(--text-dark);margin-bottom:.35rem}
.bs-empty p{font-size:.9rem;color:var(--text-muted);margin-bottom:1.25rem}

@media(max-width:520px){
    .bs-page{padding:1.5rem 1rem 3rem}
    .bs-form-body{padding:1.25rem 1rem 1rem}
    .bs-provider-banner{padding:1rem}
    .bs-actions{flex-direction:column}
    .bs-btn-book,.bs-btn-back{width:100%;justify-content:center}
}
</style>

<section class="bs-page">
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
        <!-- Empty state -->
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
    <form method="POST" class="bs-card">
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
                    <select name="service_id" required>
                        <option value="">Choose a service...</option>
                        <?php foreach ($services as $s): ?>
                            <option value="<?= $s['id'] ?>" <?= $serviceId && (int)$s['id'] === $serviceId ? 'selected' : '' ?>>
                                <?= htmlspecialchars($s['category_name']) ?> — ₱<?= number_format($s['price_min']) ?> – ₱<?= number_format($s['price_max']) ?>
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

            <!-- Action buttons -->
            <div class="bs-actions">
                <button type="submit" class="bs-btn-book">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5m-9-6h.008v.008H12v-.008ZM12 15h.008v.008H12V15Zm0 2.25h.008v.008H12v-.008Z"/></svg>
                    Confirm Booking
                </button>
                <a href="provider_profile.php?id=<?= $providerId ?>" class="bs-btn-back">
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
    <?php endif; ?>
</section>
<?php require_once 'includes/footer.php'; ?>
