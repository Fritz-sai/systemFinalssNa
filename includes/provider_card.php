<?php
// Template partial: expects $provider array with keys: id,name,avatar,title,bio,service,location,rate,sponsored,face_verified
if (!isset($provider) || !is_array($provider)) return;
$name = htmlspecialchars($provider['name'] ?? 'Unknown');
$rawAvatar = $provider['avatar'] ?? '';
// Determine avatar path: prefer provided URL/path if non-empty and exists, otherwise use local default SVG
if (empty($rawAvatar)) {
    $avatarPath = 'assets/img/default-avatar.svg';
} elseif (preg_match('#^https?://#i', $rawAvatar)) {
    $avatarPath = $rawAvatar;
} else {
    $candidate = __DIR__ . '/../' . ltrim($rawAvatar, '/\\');
    if (file_exists($candidate)) {
        // keep the web-relative path as provided
        $avatarPath = $rawAvatar;
    } else {
        $avatarPath = 'assets/img/default-avatar.svg';
    }
}
$avatar = htmlspecialchars($avatarPath);
$title = htmlspecialchars($provider['title'] ?? '');
$bio = htmlspecialchars($provider['bio'] ?? '');
$service = isset($provider['service']) ? htmlspecialchars((string)$provider['service']) : '—';
$location = isset($provider['location']) ? htmlspecialchars((string)$provider['location']) : '—';
$rate = isset($provider['rate']) ? number_format((float)$provider['rate'], 1) : '0.0';
$id = urlencode($provider['id'] ?? '');
?>
<div class="card provider-card">
    <?php if (!empty($provider['sponsored'])): ?>
        <div class="badge-sponsored">Featured</div>
    <?php endif; ?>
    <div class="card-inner">
        <div class="card-header">
            <h4><?= $name ?> <?php if (!empty($provider['face_verified'])): ?><span class="badge-verified">✓</span><?php endif; ?></h4>
            <?php if ($title): ?><div class="small-muted"><?= $title ?></div><?php endif; ?>
        </div>

        <div class="card-top">
            <div class="avatar-wrap">
                <img src="<?= $avatar ?>" alt="<?= $name ?>">
            </div>
            <div class="card-meta">
                <?php if ($bio): ?><p class="small-muted"><?= $bio ?></p><?php endif; ?>
            </div>
            <div class="stats">
                <div class="stat"><span class="icon">🧰</span><div><div class="small-muted">Service</div><div><?= $service ?></div></div></div>
                <div class="stat"><span class="icon">📍</span><div><div class="small-muted">Location</div><div><?= $location ?></div></div></div>
                <div class="stat"><span class="icon">★</span><div><div class="small-muted">Rate</div><div><?= $rate ?></div></div></div>
            </div>
        </div>

        <div class="card-body">
            <div class="card-actions">
                <a href="provider_profile.php?id=<?= $id ?>" class="btn btn-primary btn-block">View profile</a>
                <a href="chat.php?to=<?= $id ?>" class="btn-icon" title="Contact">💬</a>
            </div>
        </div>
    </div>
</div>
