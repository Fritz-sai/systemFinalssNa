<?php
if (!isset($pageTitle)) $pageTitle = 'ServiceLink';
$isLoggedIn = isset($_SESSION['user_id']);
$currentPage = basename(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: 'index.php');
$userRole = $isLoggedIn ? (string)($_SESSION['role'] ?? '') : '';
$roleLabel = $userRole !== '' ? ucfirst($userRole) : '';
$unreadNotifications = 0;
if ($isLoggedIn) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$_SESSION['user_id']]);
        $unreadNotifications = (int)$stmt->fetchColumn();
    } catch (Throwable $e) {
        $unreadNotifications = 0;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> - ServiceLink</title>
    <link rel="stylesheet" href="assets/css/style.css?v=20260409">
    <script>
    // Check if face-api.js is available
    window.faceAPIAvailable = typeof faceapi !== 'undefined';
    </script>
    <script src="https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/dist/face-api.min.js"></script>
</head>
<body>
<nav class="navbar">
    <div class="navbar-brand-wrap">
        <?php if ($roleLabel !== ''): ?>
            <span class="role-pill role-<?= htmlspecialchars($userRole) ?>"><?= htmlspecialchars($roleLabel) ?></span>
        <?php endif; ?>
        <a href="index.php" class="navbar-brand">Service<span>Link</span></a>
    </div>
    <div class="nav-links">
        <a class="nav-link <?= $currentPage === 'index.php' ? 'active' : '' ?>" href="index.php">Home</a>
        <a class="nav-link <?= $currentPage === 'filter_results.php' ? 'active' : '' ?>" href="filter_results.php">Find Services</a>
        <?php if (!$isLoggedIn): ?>
            <a class="nav-link <?= $currentPage === 'register.php' ? 'active' : '' ?>" href="register.php">Become a Provider</a>
        <?php endif; ?>
        <?php if ($isLoggedIn): ?>
            <?php if ($_SESSION['role'] === 'customer'): ?>
                <a class="nav-link <?= $currentPage === 'dashboard_customer.php' ? 'active' : '' ?>" href="dashboard_customer.php">My Profile</a>
            <?php elseif ($_SESSION['role'] === 'provider'): ?>
                <a class="nav-link <?= $currentPage === 'provider_profile.php' ? 'active' : '' ?>" href="provider_profile.php?id=<?= $_SESSION['provider_id'] ?>">My Profile</a>
                <?php
                $headerCredits = 0;
                try {
                    $hc = $pdo->prepare("SELECT p.credits FROM providers p WHERE p.user_id = ?");
                    $hc->execute([$_SESSION['user_id']]);
                    $headerCredits = (int)($hc->fetchColumn() ?: 0);
                } catch (Throwable $e) { }
                ?>
                <a class="nav-link <?= $currentPage === 'buy_credits.php' ? 'active' : '' ?>" href="buy_credits.php">Credits: <strong><?= $headerCredits ?></strong></a>
                <a class="nav-link <?= $currentPage === 'face_verification.php' ? 'active' : '' ?>" href="face_verification.php">Get Verified</a>
            <?php elseif ($_SESSION['role'] === 'admin'): ?>
                <a class="nav-link <?= $currentPage === 'admin_panel.php' ? 'active' : '' ?>" href="admin_panel.php">Admin</a>
            <?php endif; ?>
            <a class="nav-link <?= $currentPage === 'chat.php' ? 'active' : '' ?>" href="chat.php" style="position: relative;">
                Chat
                <span id="chat-unread-badge" class="badge-notif" style="<?= $unreadNotifications > 0 ? '' : 'display:none' ?>">
                    <?= (int)$unreadNotifications ?>
                </span>
            </a>
            <a href="logout.php" class="btn btn-ghost">Logout</a>
        <?php else: ?>
            <a href="login.php" class="btn btn-primary">Login / Register</a>
        <?php endif; ?>
    </div>
</nav>
<main class="main-content">
<?php if ($isLoggedIn): ?>
<script>
(function () {
    const badge = document.getElementById('chat-unread-badge');
    if (!badge) return;

    async function refreshUnread() {
        try {
            const res = await fetch('api/get_unread_notifications.php', { cache: 'no-store' });
            const data = await res.json();
            const count = Number(data.count || 0);
            if (count > 0) {
                badge.style.display = 'inline-flex';
                badge.textContent = String(count);
            } else {
                badge.style.display = 'none';
                badge.textContent = '0';
            }
        } catch (e) {
            // ignore
        }
    }

    refreshUnread();
    setInterval(refreshUnread, 8000);
})();
</script>
<?php endif; ?>
