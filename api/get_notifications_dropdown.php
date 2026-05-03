<?php
require_once __DIR__ . '/../config/config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['unread' => 0, 'items' => []]);
    exit;
}

$pdo = getDBConnection();
$userId = (int)$_SESSION['user_id'];

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
$countStmt->execute([$userId]);
$unread = (int)$countStmt->fetchColumn();

$listStmt = $pdo->prepare("
    SELECT id, type, title, body, chat_id, is_read, created_at
    FROM notifications
    WHERE user_id = ?
    ORDER BY created_at DESC, id DESC
    LIMIT 12
");
$listStmt->execute([$userId]);
$items = $listStmt->fetchAll();

// Admin-only synthetic alerts for open reports (real-time even if report rows were inserted elsewhere).
if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
    try {
        $reportStmt = $pdo->query("
            SELECT id, report_type, reason, created_at
            FROM reports
            WHERE status = 'open'
            ORDER BY created_at DESC
            LIMIT 3
        ");
        $openReports = $reportStmt->fetchAll();
        foreach ($openReports as $rep) {
            $items[] = [
                'id' => 0,
                'type' => 'report_alert',
                'title' => 'New/Open Report #' . (int)$rep['id'],
                'body' => trim((string)($rep['reason'] ?: $rep['report_type'])) ?: 'Complaint requires review',
                'chat_id' => null,
                'is_read' => 0,
                'created_at' => $rep['created_at'],
            ];
        }
        usort($items, static function ($a, $b) {
            return strtotime((string)$b['created_at']) <=> strtotime((string)$a['created_at']);
        });
        if (count($items) > 12) {
            $items = array_slice($items, 0, 12);
        }
    } catch (Throwable $e) {
        // reports table may not exist yet in some environments.
    }
}

echo json_encode([
    'unread' => $unread,
    'items' => $items
]);
?>
