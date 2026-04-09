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
?>
<div class="card provider-card">
    <div class="provider-card-image">
        <img src="<?= $avatar ?>" alt="<?= $name ?>">
    </div>
    <div class="provider-card-body">
        <h3><?= $name ?></h3>
        <div class="provider-rating-display">
            <span class="rate-num"><?= $rate ?></span>
            <span class="stars">★★★★★</span>
            <span class="rate-label">/ NCR</span>
        </div>
        <div class="provider-location-display"><?= $location ?></div>
        <a href="chat.php?to=<?= $id ?>" class="btn btn-primary btn-message">Message</a>
    </div>
</div>

