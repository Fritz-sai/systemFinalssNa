<?php
$pageTitle = 'Admin Panel';
require_once 'config/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

// Redirect to dashboard
header('Location: admin_dashboard.php');
exit;
?>
