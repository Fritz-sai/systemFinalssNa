<?php
require_once __DIR__ . '/../config/config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'provider' || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$providerId = (int)($_SESSION['provider_id'] ?? 0);
$bookingId = (int)($_POST['booking_id'] ?? 0);
$decision = trim((string)($_POST['decision'] ?? ''));

$reason = trim((string)($_POST['reason'] ?? ''));
$rescheduleDate = trim((string)($_POST['reschedule_date'] ?? ''));
$rescheduleTime = trim((string)($_POST['reschedule_time'] ?? ''));

if ($providerId <= 0 || $bookingId <= 0 || !in_array($decision, ['approve', 'decline', 'confirm', 'done', 'cancel', 'reject'], true)) {
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit;
}

if ($decision === 'reject') {
    if (strlen($reason) < 10) {
        echo json_encode(['success' => false, 'error' => 'A rejection reason of at least 10 characters is required.']);
        exit;
    }
}

$pdo = getDBConnection();

try {
    $pdo->beginTransaction();

    $bookingStmt = $pdo->prepare("
        SELECT b.id, b.customer_id, b.status, s.title AS service_title
        FROM bookings b
        JOIN services s ON b.service_id = s.id
        WHERE b.id = ? AND b.provider_id = ?
        LIMIT 1
    ");
    $bookingStmt->execute([$bookingId, $providerId]);
    $booking = $bookingStmt->fetch();

    if (!$booking) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'error' => 'Booking not found']);
        exit;
    }

    $currentStatus = (string)$booking['status'];
    $statusMap = [
        'approve' => 'confirmed',
        'decline' => 'cancelled',
        'confirm' => 'confirmed',
        'done' => 'completed',
        'cancel' => 'cancelled',
        'reject' => 'rejected',
    ];
    $allowedTransitions = [
        'approve' => ['pending'],
        'decline' => ['pending'],
        'confirm' => ['pending'],
        'done' => ['confirmed'],
        'cancel' => ['pending', 'confirmed'],
        'reject' => ['pending'],
    ];

    if (!in_array($currentStatus, $allowedTransitions[$decision], true)) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'error' => 'Action not allowed for this booking status']);
        exit;
    }

    $newStatus = $statusMap[$decision];
    
    $suggestedReschedule = null;
    if ($decision === 'reject' && !empty($rescheduleDate) && !empty($rescheduleTime)) {
        $suggestedReschedule = $rescheduleDate . ' ' . $rescheduleTime . ':00';
    }

    if ($decision === 'reject') {
        $pdo->prepare("UPDATE bookings SET status = ?, rejection_reason = ?, suggested_reschedule_date = ? WHERE id = ?")
            ->execute([$newStatus, $reason, $suggestedReschedule, $bookingId]);
    } else {
        $pdo->prepare("UPDATE bookings SET status = ? WHERE id = ?")
            ->execute([$newStatus, $bookingId]);
    }

    if ($newStatus === 'confirmed') {
        $title = 'Booking Confirmed';
        $body = 'Your booking for "' . $booking['service_title'] . '" was confirmed by the provider.';
    } elseif ($newStatus === 'completed') {
        $title = 'Service Marked as Done';
        $body = 'Your provider marked "' . $booking['service_title'] . '" as completed.';
    } elseif ($newStatus === 'rejected') {
        $title = 'Booking Declined';
        $body = 'Your booking request for "' . $booking['service_title'] . '" was declined by the provider. Reason: ' . htmlspecialchars($reason);
        if ($suggestedReschedule) {
            $formattedReschedule = date('M j, Y g:i A', strtotime($suggestedReschedule));
            $body .= " | Suggested new schedule: $formattedReschedule";
        }
    } else {
        $title = 'Booking Cancelled';
        $body = 'Your booking for "' . $booking['service_title'] . '" was cancelled by the provider.';
    }

    $pdo->prepare("
        INSERT INTO notifications (user_id, type, title, body, is_read)
        VALUES (?, 'booking_status', ?, ?, 0)
    ")->execute([(int)$booking['customer_id'], $title, $body]);

    $pdo->commit();
    echo json_encode(['success' => true, 'status' => $newStatus, 'booking_id' => $bookingId]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'error' => 'Failed to update booking: ' . $e->getMessage()]);
}
