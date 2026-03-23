<?php
session_start();
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['user_id'])) {
    $_SESSION['user_city'] = trim($_POST['city'] ?? '');
}
$role = $_SESSION['role'] ?? 'customer';
header('Location: ../' . ($role === 'customer' ? 'providers.php' : 'provider_profile.php'));
exit;
