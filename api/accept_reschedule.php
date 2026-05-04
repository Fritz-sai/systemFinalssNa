<?php
require_once __DIR__ . '/../config/config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer' || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$customerId = (int)($_SESSION['user_id'] ?? 0);
$bookingId = (int)($_POST['booking_id'] ?? 0);

if ($customerId <= 0 || $bookingId <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit;
}

$pdo = getDBConnection();

try {
    $pdo->beginTransaction();

    $bookingStmt = $pdo->prepare("
        SELECT b.id, b.status, b.suggested_reschedule_date, b.provider_id, s.title AS service_title
        FROM bookings b
        JOIN services s ON b.service_id = s.id
        WHERE b.id = ? AND b.customer_id = ?
        LIMIT 1
    ");
    $bookingStmt->execute([$bookingId, $customerId]);
    $booking = $bookingStmt->fetch();

    if (!$booking) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'error' => 'Booking not found']);
        exit;
    }

    if ($booking['status'] !== 'rejected' || empty($booking['suggested_reschedule_date'])) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'error' => 'No reschedule date available or booking is not rejected']);
        exit;
    }

    $newDate = $booking['suggested_reschedule_date'];

    // Update the booking status to confirmed and the scheduled date to the new date.
    $pdo->prepare("UPDATE bookings SET status = 'confirmed', scheduled_date = ?, suggested_reschedule_date = NULL, rejection_reason = NULL WHERE id = ?")
        ->execute([$newDate, $bookingId]);

    $pdo->prepare("
        INSERT INTO notifications (user_id, type, title, body, is_read)
        VALUES ((SELECT user_id FROM providers WHERE id = ?), 'booking_status', 'Reschedule Accepted', ?, 0)
    ")->execute([$booking['provider_id'], 'The customer has accepted your suggested new schedule for "' . $booking['service_title'] . '".']);

    $pdo->commit();
    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'error' => 'Failed to accept reschedule']);
}
