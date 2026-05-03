<?php
// Template partial: expects $provider array with keys: id, name, avatar, location, rate, face_verified
if (!isset($provider) || !is_array($provider)) return;

$name = htmlspecialchars($provider['name'] ?? 'Unknown');
$rawAvatar = $provider['avatar'] ?? '';

// Determine avatar path
if (empty($rawAvatar)) {
    $avatarPath = 'assets/img/default-avatar.svg';
} elseif (preg_match('#^https?://#i', $rawAvatar)) {
    $avatarPath = $rawAvatar;
} else {
    $candidate = __DIR__ . '/../' . ltrim($rawAvatar, '/\\');
    if (file_exists($candidate)) {
        $avatarPath = $rawAvatar;
    } else {
        $avatarPath = 'assets/img/default-avatar.svg';
    }
}

$avatar = htmlspecialchars($avatarPath);
$location = isset($provider['location']) ? htmlspecialchars((string)$provider['location']) : '—';
$rate = isset($provider['rate']) ? number_format((float)$provider['rate'], 1) : '0.0';
$id = urlencode($provider['id'] ?? '');
$profileHref = 'provider_profile.php?id=' . $id;
$title = htmlspecialchars($provider['title'] ?? 'Service Professional');
$description = htmlspecialchars($provider['description'] ?? 'Expert in residential wiring & repairs.');
$isVerified = !empty($provider['face_verified']);
?>
<div class="card provider-card new-design-card">
    <div class="provider-card-image-wrap">
        <img src="<?= $avatar ?>" alt="<?= $name ?>" class="provider-card-image-full">
        <?php if ($isVerified): ?>
        <div class="badge-verified-top">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
            Verified
        </div>
        <div class="badge-verifizen-bottom">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon></svg>
            Verifizen
        </div>
        <?php endif; ?>
    </div>
    <div class="provider-card-content">
        <div class="card-header-row">
            <h3 class="provider-name"><?= $name ?></h3>
            <div class="provider-rating">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="#FFC107" stroke="#FFC107" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>
                <span class="rate-num"><?= $rate ?></span>
            </div>
        </div>
        
        <div class="provider-title-pill">
            <?= $title ?>
        </div>
        
        <div class="provider-location">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
            <?= $location ?>
        </div>
        
        <p class="provider-description">
            <?= $description ?>
        </p>
        
        <a href="<?= htmlspecialchars($profileHref) ?>" class="btn btn-primary btn-view-profile">
            View
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
        </a>
    </div>
</div>

