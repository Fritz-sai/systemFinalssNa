<?php
require_once 'config/config.php';

// Test SMS sending
$testPhone = '09123456789'; // Replace with your actual phone number for testing
$testMessage = 'Test SMS from ' . SITE_NAME;

echo "Testing SMS sending...\n";
echo "Phone: $testPhone\n";
echo "Message: $testMessage\n";
echo "Provider: " . SMS_PROVIDER . "\n";
echo "Enabled: " . (SMS_ENABLED ? 'Yes' : 'No') . "\n\n";

if (SMS_ENABLED) {
    $result = send_sms($testPhone, $testMessage);
    echo "Result: " . ($result ? 'SUCCESS' : 'FAILED') . "\n";

    // Check error log
    $errorLog = ini_get('error_log');
    if (file_exists($errorLog)) {
        $lines = file($errorLog);
        $lastLines = array_slice($lines, -5);
        echo "\nLast 5 lines from error log:\n";
        foreach ($lastLines as $line) {
            echo $line;
        }
    }
} else {
    echo "SMS is disabled. Enable it in config.php\n";
}
?>