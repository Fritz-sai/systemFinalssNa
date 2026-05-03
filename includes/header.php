<?php
if (!isset($pageTitle)) $pageTitle = 'ServiceLink';
$isLoggedIn = isset($_SESSION['user_id']);
$currentPage = basename(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: 'index.php');
$userRole = $isLoggedIn ? (string)($_SESSION['role'] ?? '') : '';
$roleLabel = $userRole !== '' ? ucfirst($userRole) : '';
$siteNameDynamic = 'ServiceLink';
$adminThemeMode = 'light';
$adminAccentColor = '#3A86FF';
$headerUser = ['full_name' => '', 'email' => '', 'profile_image_path' => ''];
$unreadNotifications = 0;
if ($isLoggedIn) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$_SESSION['user_id']]);
        $unreadNotifications = (int)$stmt->fetchColumn();
        $siteStmt = $pdo->prepare("SELECT setting_value FROM app_settings WHERE setting_key = 'site_name' LIMIT 1");
        $siteStmt->execute();
        $siteNameDynamic = (string)($siteStmt->fetchColumn() ?: 'ServiceLink');
        if ($userRole === 'admin') {
            $themeStmt = $pdo->prepare("SELECT setting_key, setting_value FROM app_settings WHERE setting_key IN ('admin_theme', 'admin_accent')");
            $themeStmt->execute();
            foreach ($themeStmt->fetchAll() as $opt) {
                if (($opt['setting_key'] ?? '') === 'admin_theme') {
                    $v = (string)($opt['setting_value'] ?? 'light');
                    if (in_array($v, ['light', 'dark', 'auto'], true)) $adminThemeMode = $v;
                }
                if (($opt['setting_key'] ?? '') === 'admin_accent') {
                    $c = (string)($opt['setting_value'] ?? '#3A86FF');
                    if (preg_match('/^#[0-9a-fA-F]{6}$/', $c)) $adminAccentColor = strtoupper($c);
                }
            }
        }
        $userStmt = $pdo->prepare("SELECT id, full_name, email, profile_image_path FROM users WHERE id = ? LIMIT 1");
        $userStmt->execute([$_SESSION['user_id']]);
        $headerUser = $userStmt->fetch() ?: $headerUser;
        
        if ($userRole === 'customer' && empty($headerUser['profile_image_path'])) {
            $allowed_pages = ['customer_face_setup.php', 'logout.php', 'install.php'];
            if (!in_array($currentPage, $allowed_pages, true)) {
                header('Location: customer_face_setup.php');
                exit;
            }
        }
        if ($userRole === 'provider' && !empty($_SESSION['provider_id'])) {
            $providerAvatarStmt = $pdo->prepare("SELECT profile_image_path FROM providers WHERE id = ? LIMIT 1");
            $providerAvatarStmt->execute([(int)$_SESSION['provider_id']]);
            $providerAvatar = (string)($providerAvatarStmt->fetchColumn() ?: '');
            if ($providerAvatar !== '') {
                $headerUser['profile_image_path'] = $providerAvatar;
            }
        }
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
    <title><?= htmlspecialchars($pageTitle) ?> - <?= htmlspecialchars($siteNameDynamic) ?></title>
    <link rel="stylesheet" href="assets/css/style.css?v=20260409">
    <script>
    // Check if face-api.js is available
    window.faceAPIAvailable = typeof faceapi !== 'undefined';
    </script>
    <script src="https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/dist/face-api.min.js"></script>
</head>
<body class="<?= $userRole === 'admin' ? 'admin-theme-' . htmlspecialchars($adminThemeMode) : '' ?>" style="<?= $userRole === 'admin' ? '--admin-accent: ' . htmlspecialchars($adminAccentColor) . ';' : '' ?>">
<nav class="navbar">
    <div class="navbar-brand-wrap">
        <?php if ($roleLabel !== ''): ?>
            <span class="role-pill role-<?= htmlspecialchars($userRole) ?>"><?= htmlspecialchars($roleLabel) ?></span>
        <?php endif; ?>
        <a href="index.php" class="navbar-brand"><?= htmlspecialchars($siteNameDynamic) ?></a>
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
            <div class="notif-wrap">
                <button type="button" id="notif-toggle-btn" class="notif-toggle-btn" aria-label="Notifications">
                    <span class="notif-icon">🔔</span>
                    <span id="notif-unread-badge" class="badge-notif" style="display:none;">0</span>
                </button>
                <div id="notif-dropdown" class="notif-dropdown" hidden>
                    <div class="notif-dropdown-head">
                        <strong>Notifications</strong>
                        <button type="button" id="mark-all-read-btn" class="btn btn-ghost notif-mark-read">Mark all as read</button>
                    </div>
                    <div id="notif-dropdown-list" class="notif-dropdown-list">
                        <div class="notif-empty">No notifications yet.</div>
                    </div>
                </div>
            </div>
            <?php
            $avatarInitial = strtoupper(substr((string)($headerUser['full_name'] ?? 'U'), 0, 1));
            $bookingHistoryLink = 'dashboard_customer.php';
            if ($userRole === 'provider') $bookingHistoryLink = 'dashboard_provider.php';
            if ($userRole === 'admin') $bookingHistoryLink = 'admin_bookings.php';
            $viewProfileLink = $userRole === 'provider'
                ? ('provider_profile.php?id=' . (int)($_SESSION['provider_id'] ?? 0))
                : ($userRole === 'admin' ? 'admin_settings.php' : 'dashboard_customer.php');
            ?>
            <div class="profile-menu-wrap">
                <button type="button" id="profile-menu-toggle" class="profile-menu-toggle" aria-label="Open profile menu">
                    <?php if (!empty($headerUser['profile_image_path'])): ?>
                        <img src="<?= htmlspecialchars((string)$headerUser['profile_image_path']) ?>" alt="<?= htmlspecialchars((string)$headerUser['full_name']) ?>">
                    <?php else: ?>
                        <span><?= htmlspecialchars($avatarInitial) ?></span>
                    <?php endif; ?>
                </button>
                <div id="profile-menu-dropdown" class="profile-menu-dropdown" hidden>
                    <div class="profile-menu-head">
                        <strong><?= htmlspecialchars((string)($headerUser['full_name'] ?: 'User')) ?></strong>
                        <small><?= htmlspecialchars((string)($headerUser['email'] ?? '')) ?></small>
                    </div>
                    <div class="profile-menu-links">
                        <a href="<?= htmlspecialchars($viewProfileLink) ?>">View Profile</a>
                        <a href="profile_settings.php#edit-profile">Edit Profile</a>
                        <a href="profile_settings.php#account-settings">Account Settings</a>
                        <a href="profile_settings.php#security-settings">Security Settings</a>
                        <a href="profile_settings.php#privacy-settings">Privacy Settings</a>
                        <a href="profile_settings.php#notification-settings">Notifications Settings</a>
                        <a href="<?= htmlspecialchars($bookingHistoryLink) ?>">Booking History</a>
                        <a href="favorites.php">Saved Providers/Favorites</a>
                        <button type="button" id="toggle-site-theme-btn" class="profile-menu-action-btn">Switch Dark/Light Mode</button>
                        <a href="logout.php" class="profile-menu-logout">Logout</a>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <a href="login.php" class="btn btn-primary">Login / Register</a>
        <?php endif; ?>
    </div>
</nav>
<div id="globalPageLoader" class="page-loader-overlay" hidden>
    <div class="page-loader-spinner"></div>
</div>
<main class="main-content">
<?php if ($isLoggedIn): ?>
<script>
(function () {
    const badge = document.getElementById('chat-unread-badge');
    const notifBadge = document.getElementById('notif-unread-badge');
    const notifToggleBtn = document.getElementById('notif-toggle-btn');
    const notifDropdown = document.getElementById('notif-dropdown');
    const notifList = document.getElementById('notif-dropdown-list');
    const markAllBtn = document.getElementById('mark-all-read-btn');
    const profileToggle = document.getElementById('profile-menu-toggle');
    const profileDropdown = document.getElementById('profile-menu-dropdown');
    const themeToggleBtn = document.getElementById('toggle-site-theme-btn');
    if (!badge) return;

    function typeClass(type) {
        if (type === 'booking_alert') return 'notif-booking';
        if (type === 'verification_alert') return 'notif-verification';
        if (type === 'report_alert') return 'notif-report';
        return 'notif-general';
    }

    function timeAgo(value) {
        const ts = new Date(value).getTime();
        if (!Number.isFinite(ts)) return '';
        const diff = Math.max(0, Date.now() - ts);
        const mins = Math.floor(diff / 60000);
        if (mins < 1) return 'Just now';
        if (mins < 60) return mins + 'm ago';
        const hrs = Math.floor(mins / 60);
        if (hrs < 24) return hrs + 'h ago';
        const days = Math.floor(hrs / 24);
        return days + 'd ago';
    }

    function renderNotifications(items) {
        if (!notifList) return;
        if (!Array.isArray(items) || items.length === 0) {
            notifList.innerHTML = '<div class="notif-empty">No notifications yet.</div>';
            return;
        }
        notifList.innerHTML = items.map(function (item) {
            const title = String(item.title || 'Notification');
            const body = String(item.body || '');
            const when = timeAgo(item.created_at);
            const unreadClass = Number(item.is_read || 0) === 0 ? ' unread' : '';
            const typeTagClass = typeClass(String(item.type || ''));
            return ''
                + '<button type="button" class="notif-item' + unreadClass + '" data-id="' + Number(item.id || 0) + '">'
                + '<span class="notif-type-tag ' + typeTagClass + '"></span>'
                + '<span class="notif-content">'
                + '<strong>' + title + '</strong>'
                + '<small>' + body + '</small>'
                + '<em>' + when + '</em>'
                + '</span>'
                + '</button>';
        }).join('');
    }

    async function fetchDropdown() {
        if (!notifBadge) return;
        try {
            const res = await fetch('api/get_notifications_dropdown.php', { cache: 'no-store' });
            const data = await res.json();
            const unread = Number(data.unread || 0);
            if (unread > 0) {
                notifBadge.style.display = 'inline-flex';
                notifBadge.textContent = String(unread);
            } else {
                notifBadge.style.display = 'none';
                notifBadge.textContent = '0';
            }
            renderNotifications(data.items || []);
        } catch (e) {
            // ignore
        }
    }

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

    async function markRead(id) {
        const body = new URLSearchParams();
        if (id) body.set('id', String(id));
        try {
            await fetch('api/mark_notifications_read.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body.toString()
            });
        } catch (e) {
            // ignore
        }
    }

    if (notifToggleBtn && notifDropdown) {
        notifToggleBtn.addEventListener('click', function () {
            notifDropdown.hidden = !notifDropdown.hidden;
            if (!notifDropdown.hidden) fetchDropdown();
        });
        document.addEventListener('click', function (e) {
            if (!notifDropdown.hidden && !notifDropdown.contains(e.target) && !notifToggleBtn.contains(e.target)) {
                notifDropdown.hidden = true;
            }
            if (profileDropdown && profileToggle && !profileDropdown.hidden && !profileDropdown.contains(e.target) && !profileToggle.contains(e.target)) {
                profileDropdown.hidden = true;
            }
        });
    }
    if (profileToggle && profileDropdown) {
        profileToggle.addEventListener('click', function () {
            profileDropdown.hidden = !profileDropdown.hidden;
        });
    }
    function applySiteTheme(theme) {
        document.body.classList.remove('site-theme-light', 'site-theme-dark');
        document.body.classList.add(theme === 'dark' ? 'site-theme-dark' : 'site-theme-light');
    }
    (function initTheme() {
        const savedTheme = localStorage.getItem('site_theme_mode') || 'light';
        applySiteTheme(savedTheme);
    })();
    if (themeToggleBtn) {
        themeToggleBtn.addEventListener('click', async function () {
            const dark = document.body.classList.contains('site-theme-dark');
            const next = dark ? 'light' : 'dark';
            applySiteTheme(next);
            localStorage.setItem('site_theme_mode', next);
            try {
                await fetch('api/save_user_preferences.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'pref_key=theme_mode&pref_value=' + encodeURIComponent(next)
                });
            } catch (e) {
                // ignore
            }
        });
    }
    if (markAllBtn) {
        markAllBtn.addEventListener('click', async function () {
            await markRead(0);
            await fetchDropdown();
            await refreshUnread();
        });
    }
    if (notifList) {
        notifList.addEventListener('click', async function (e) {
            const btn = e.target.closest('.notif-item');
            if (!btn) return;
            const id = Number(btn.getAttribute('data-id') || 0);
            if (id > 0) {
                await markRead(id);
                await fetchDropdown();
                await refreshUnread();
            }
        });
    }

    fetchDropdown();
    refreshUnread();
    setInterval(function () {
        refreshUnread();
        fetchDropdown();
    }, 8000);
})();
</script>
<?php if ($userRole === 'admin'): ?>
<script>
(function () {
    const body = document.body;
    const loader = document.getElementById('globalPageLoader');
    const adminShell = document.querySelector('.admin-shell');

    function showLoader() {
        if (!loader) return;
        loader.hidden = false;
        loader.classList.add('active');
    }

    if (adminShell) {
        const toggle = document.createElement('button');
        toggle.type = 'button';
        toggle.className = 'admin-sidebar-toggle';
        toggle.setAttribute('aria-label', 'Toggle sidebar');
        toggle.textContent = '☰';
        document.body.appendChild(toggle);

        const saved = localStorage.getItem('admin_sidebar_collapsed');
        if (saved === '1') body.classList.add('admin-sidebar-collapsed');

        toggle.addEventListener('click', function () {
            body.classList.toggle('admin-sidebar-collapsed');
            localStorage.setItem('admin_sidebar_collapsed', body.classList.contains('admin-sidebar-collapsed') ? '1' : '0');
        });

        const filterForms = document.querySelectorAll('form.admin-provider-filters');
        filterForms.forEach(function (form) {
            form.addEventListener('submit', function (e) {
                e.preventDefault();
                const tableWrap = form.closest('.admin-panel-card') ? form.closest('.admin-panel-card').querySelector('.admin-table-wrap') : null;
                if (tableWrap) tableWrap.classList.add('skeleton-block');
                showLoader();
                setTimeout(function () { form.submit(); }, 260);
            });
        });
    }

    document.addEventListener('click', function (e) {
        const link = e.target.closest('a[href]');
        if (!link) return;
        const href = String(link.getAttribute('href') || '');
        if (!href || href.startsWith('#') || href.startsWith('javascript:') || link.target === '_blank') return;
        showLoader();
    });

    document.addEventListener('submit', function () {
        showLoader();
    });

    window.addEventListener('pageshow', function () {
        if (!loader) return;
        loader.classList.remove('active');
        loader.hidden = true;
    });
})();
</script>
<?php endif; ?>
<?php endif; ?>
