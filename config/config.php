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
define('SMTP_ENABLED', true); // set to true after configuring below
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'navarrofritz4@gmail.com');
define('SMTP_PASS', 'vgfaklfjwgpiswpo'); // for Gmail use an App Password
define('SMTP_SECURE', 'tls'); // 'tls' or 'ssl'
define('MAIL_FROM_EMAIL', SMTP_USER);
define('MAIL_FROM_NAME', SITE_NAME);

// SMS settings (disabled - phone verification removed)
define('SMS_ENABLED', false);

/**
 * Send an email using PHPMailer (if available) or fallback to mail().
 * For reliable Gmail delivery, enable SMTP and provide credentials above.
 */
function send_email($to, $subject, $body, $isHtml = false)
{
    // Try to use PHPMailer if SMTP_ENABLED
    $phpmailerPath = __DIR__ . '/../vendor/phpmailer/src/PHPMailer.php';
    if (SMTP_ENABLED && file_exists($phpmailerPath)) {
        require_once $phpmailerPath;
        require_once __DIR__ . '/../vendor/phpmailer/src/SMTP.php';
        require_once __DIR__ . '/../vendor/phpmailer/src/Exception.php';
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
        } catch (PHPMailer\PHPMailer\Exception $e) {
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
 * Send an SMS using Textbelt (free) or Twilio.
 */
function send_sms($to, $message)
{
    if (!SMS_ENABLED) {
        error_log('SMS sending disabled. Message: ' . $message);
        return false;
    }

    // For Philippine numbers, ensure +63 format
    if (preg_match('/^09\d{9}$/', $to)) {
        $to = '+63' . substr($to, 1);
    } elseif (!preg_match('/^\+63\d{10}$/', $to)) {
        error_log('Invalid phone number format: ' . $to);
        return false;
    }

    try {
        if (SMS_PROVIDER === 'textbelt') {
            // Textbelt - free for development (200 messages/month)
            $url = 'https://textbelt.com/text';
            $data = [
                'phone' => $to,
                'message' => $message,
                'key' => TEXTBELT_KEY
            ];

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            $result = json_decode($response, true);
            if ($result && isset($result['success']) && $result['success']) {
                return true;
            } else {
                error_log('Textbelt SMS failed. Response: ' . $response);
                return false;
            }
        } else {
            // Twilio - paid service
            $url = 'https://api.twilio.com/2010-04-01/Accounts/' . TWILIO_SID . '/Messages.json';
            $data = [
                'From' => TWILIO_FROM,
                'To' => $to,
                'Body' => $message
            ];

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
            curl_setopt($ch, CURLOPT_USERPWD, TWILIO_SID . ':' . TWILIO_TOKEN);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode == 201) {
                return true;
            } else {
                error_log('Twilio SMS failed. HTTP: ' . $httpCode . ' Response: ' . $response);
                return false;
            }
        }
    } catch (Exception $e) {
        error_log('SMS error: ' . $e->getMessage());
        return false;
    }
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
