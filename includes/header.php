<?php
if (!isset($pageTitle)) $pageTitle = 'ServiceLink';
$isLoggedIn = isset($_SESSION['user_id']);
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
    <link rel="stylesheet" href="assets/css/style.css">
    <script>
    // Check if face-api.js is available
    window.faceAPIAvailable = typeof faceapi !== 'undefined';
    </script>
    <script src="https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/dist/face-api.min.js"></script>
</head>
<body>
<nav class="navbar">
    <a href="index.php" class="navbar-brand">Service<span>Link</span></a>
    <div class="nav-links">
        <a href="index.php">Home</a>
        <a href="filter_results.php">Find Services</a>
        <?php if (!$isLoggedIn): ?>
            <a href="register.php">Become a Provider</a>
        <?php endif; ?>
        <?php if ($isLoggedIn): ?>
            <?php if ($_SESSION['role'] === 'customer'): ?>
                <a href="dashboard_customer.php">My Profile</a>
            <?php elseif ($_SESSION['role'] === 'provider'): ?>
                <a href="provider_profile.php?id=<?= $_SESSION['provider_id'] ?>">My Profile</a>
                <?php
                $headerCredits = 0;
                try {
                    $hc = $pdo->prepare("SELECT p.credits FROM providers p WHERE p.user_id = ?");
                    $hc->execute([$_SESSION['user_id']]);
                    $headerCredits = (int)($hc->fetchColumn() ?: 0);
                } catch (Throwable $e) { }
                ?>
                <a href="buy_credits.php">Credits: <strong><?= $headerCredits ?></strong></a>
                <a href="face_verification.php">Get Verified</a>
            <?php elseif ($_SESSION['role'] === 'admin'): ?>
                <a href="admin_panel.php">Admin</a>
            <?php endif; ?>
            <a href="chat.php" style="position: relative;">
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
