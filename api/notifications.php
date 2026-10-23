<?php
// api/notifications.php - Real-time notification feeder
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';

$pdo = getDBConnection();
if (!$pdo) {
    echo json_encode(['success' => false, 'notifications' => []]);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? 'get';
$recipient_type = $_GET['recipient_type'] ?? 'frontdesk'; // 'frontdesk' or 'doctor'
$doctor_id = isset($_GET['doctor_id']) ? (int)$_GET['doctor_id'] : null;
$last_id = isset($_GET['last_id']) ? (int)$_GET['last_id'] : 0;

if ($action === 'mark_read') {
    $notif_id = (int)($_POST['id'] ?? 0);
    if ($notif_id > 0) {
        $stmt = $pdo->prepare("UPDATE `notifications` SET `is_read` = 1 WHERE `id` = ?");
        $stmt->execute([$notif_id]);
    } else {
        // Mark all for this recipient
        if ($recipient_type === 'doctor' && $doctor_id) {
            $stmt = $pdo->prepare("UPDATE `notifications` SET `is_read` = 1 WHERE (`recipient_type` = 'doctor' AND `doctor_id` = ?) OR `recipient_type` = 'all'");
            $stmt->execute([$doctor_id]);
        } else {
            $stmt = $pdo->prepare("UPDATE `notifications` SET `is_read` = 1 WHERE `recipient_type` = 'frontdesk' OR `recipient_type` = 'all'");
            $stmt->execute();
        }
    }
    echo json_encode(['success' => true]);
    exit;
}

// Fetch notifications
$query = "SELECT * FROM `notifications` WHERE ";
$params = [];

if ($recipient_type === 'doctor' && $doctor_id) {
    $query .= "((`recipient_type` = 'doctor' AND `doctor_id` = ?) OR `recipient_type` = 'all') ";
    $params[] = $doctor_id;
} else {
    $query .= "(`recipient_type` = 'frontdesk' OR `recipient_type` = 'all') ";
}

if ($last_id > 0) {
    $query .= "AND `id` > ? ";
    $params[] = $last_id;
}

$query .= "ORDER BY `id` DESC LIMIT 15";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$notifs = $stmt->fetchAll();

// Count total unread
$countQuery = "SELECT COUNT(*) FROM `notifications` WHERE `is_read` = 0 AND ";
$countParams = [];
if ($recipient_type === 'doctor' && $doctor_id) {
    $countQuery .= "((`recipient_type` = 'doctor' AND `doctor_id` = ?) OR `recipient_type` = 'all')";
    $countParams[] = $doctor_id;
} else {
    $countQuery .= "(`recipient_type` = 'frontdesk' OR `recipient_type` = 'all')";
}
$cStmt = $pdo->prepare($countQuery);
$cStmt->execute($countParams);
$unreadCount = $cStmt->fetchColumn();

echo json_encode([
    'success' => true,
    'notifications' => $notifs,
    'unread_count' => (int)$unreadCount
]);
