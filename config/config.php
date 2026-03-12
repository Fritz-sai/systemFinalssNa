<?php
/**
 * ServiceLink - Application Configuration
 */

session_start();

define('SITE_NAME', 'ServiceLink');
define('SITE_URL', 'http://localhost/system');
define('UPLOAD_PATH', __DIR__ . '/../uploads/');
define('ACCENT_COLOR', '#3A86FF');

// Payments (GCash)
define('GCASH_NUMBER', '09XXXXXXXXX');
define('GCASH_ACCOUNT_NAME', 'ServiceLink');
// Put your GCash payment link here (QR/link page, payment page, etc.)
define('GCASH_PAY_URL', 'https://m.gcash.com/');

// Credits system (contact unlock)
define('CREDITS_PER_UNLOCK', 5);
define('CREDIT_PACKAGES', [
    ['credits' => 10, 'price' => 50],
    ['credits' => 25, 'price' => 100],
    ['credits' => 50, 'price' => 180],
    ['credits' => 100, 'price' => 300],
]);

// Create upload directories if they don't exist
$uploadDirs = ['uploads', 'uploads/selfies', 'uploads/ids', 'uploads/profiles', 'uploads/payments'];
foreach ($uploadDirs as $dir) {
    $path = __DIR__ . '/../' . $dir;
    if (!is_dir($path)) {
        mkdir($path, 0755, true);
    }
}

// Include database
require_once __DIR__ . '/database.php';
?>
