<?php
session_start();
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['user_id'])) {
    $_SESSION['user_city'] = trim($_POST['city'] ?? '');
}
$role = $_SESSION['role'] ?? 'customer';
header('Location: ../' . ($role === 'customer' ? 'dashboard_customer.php' : 'index.php'));
exit;
