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

// SMTP / Mail settings (set these to your SMTP provider credentials)
define('SMTP_ENABLED', false); // set to true after configuring below
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'navarrofritz4@gmail.com');
define('SMTP_PASS', 'vgfaklfjwgpiswpo'); // for Gmail use an App Password
define('SMTP_SECURE', 'tls'); // 'tls' or 'ssl'
define('MAIL_FROM_EMAIL', 'no-reply@example.com');
define('MAIL_FROM_NAME', SITE_NAME);

/**
 * Send an email using PHPMailer (if available) or fallback to mail().
 * For reliable Gmail delivery, enable SMTP and provide credentials above.
 */
function send_email($to, $subject, $body, $isHtml = false)
{
    // Try to use Composer autoload + PHPMailer if SMTP_ENABLED
    $autoload = __DIR__ . '/../vendor/autoload.php';
    if (SMTP_ENABLED && file_exists($autoload)) {
        require_once $autoload;
        try {
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = SMTP_HOST;
            $mail->SMTPAuth = true;
            $mail->Username = SMTP_USER;
            $mail->Password = SMTP_PASS;
            $mail->SMTPSecure = SMTP_SECURE;
            $mail->Port = SMTP_PORT;

            $mail->setFrom(MAIL_FROM_EMAIL, MAIL_FROM_NAME);
            $mail->addAddress($to);
            $mail->isHTML($isHtml);
            $mail->Subject = $subject;
            $mail->Body = $body;
            if (!$isHtml) {
                $mail->AltBody = strip_tags($body);
            }

            return $mail->send();
        } catch (Exception $e) {
            error_log('Mail error: ' . $e->getMessage());
            return false;
        }
    }

    // Fallback to PHP mail() — may require local SMTP setup to work reliably
    $headers = "From: " . MAIL_FROM_NAME . " <" . MAIL_FROM_EMAIL . ">\r\n";
    if ($isHtml) {
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    }

    return mail($to, $subject, $body, $headers);
}

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
