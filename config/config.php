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

/**
 * Ensure provider has uploaded selfie, ID, and business permit before using provider-only areas.
 */
function require_provider_documents() {
    if (empty($_SESSION['role']) || $_SESSION['role'] !== 'provider' || empty($_SESSION['provider_id'])) {
        return;
    }

    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare('SELECT selfie_path, id_image_path, business_permit_path, face_verified FROM providers WHERE id = ?');
        $stmt->execute([$_SESSION['provider_id']]);
        $provider = $stmt->fetch();

        if ($provider && empty($provider['face_verified']) && (empty($provider['selfie_path']) || empty($provider['id_image_path']) || empty($provider['business_permit_path']))) {
            header('Location: face_verification.php');
            exit;
        }
    } catch (Throwable $e) {
        // If DB errors happen, do not block by default.
    }
}
?>
