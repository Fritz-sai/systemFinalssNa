<?php
require_once __DIR__ . '/config/config.php';
$pdo = getDBConnection();
$stmt = $pdo->query("SELECT b.id, b.service_id, s.id as s_id FROM bookings b LEFT JOIN services s ON b.service_id = s.id WHERE b.status = 'pending'");
$res = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($res);
