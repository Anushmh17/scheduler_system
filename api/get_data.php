<?php
// api/get_data.php - Live data feed for appointments and queue
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';

$pdo = getDBConnection();
if (!$pdo) {
    echo json_encode(['success' => false, 'error' => 'Database connection failed.']);
    exit;
}

$type = $_GET['type'] ?? 'queue';
$doctor_id = isset($_GET['doctor_id']) ? (int)$_GET['doctor_id'] : null;
$date = $_GET['date'] ?? date('Y-m-d');

try {
    // 1. Fetch Doctors
    $doctorsStmt = $pdo->query("SELECT * FROM `doctors` ORDER BY `name` ASC");
    $doctors = $doctorsStmt->fetchAll();

    // 2. Fetch Appointments for the date
    $appQuery = "SELECT a.*, d.name as doctor_name, d.specialty as doctor_specialty, d.room_number 
                 FROM `appointments` a 
                 JOIN `doctors` d ON a.doctor_id = d.id 
                 WHERE a.appointment_date = ? ";
    $params = [$date];

    if ($doctor_id && $doctor_id > 0) {
        $appQuery .= "AND a.doctor_id = ? ";
        $params[] = $doctor_id;
    }

    $appQuery .= "ORDER BY 
        CASE 
            WHEN a.status = 'In-Consultation' THEN 1
            WHEN a.status = 'Waiting' THEN 2
            WHEN a.status = 'Scheduled' THEN 3
            WHEN a.status = 'Completed' THEN 4
            ELSE 5 
        END, 
        a.appointment_time ASC, 
        a.id ASC";

    $stmt = $pdo->prepare($appQuery);
    $stmt->execute($params);
    $appointments = $stmt->fetchAll();

    // Stats
    $stats = [
        'total' => count($appointments),
        'waiting' => 0,
        'consulting' => 0,
        'completed' => 0,
        'scheduled' => 0
    ];

    foreach ($appointments as $app) {
        if ($app['status'] === 'Waiting') $stats['waiting']++;
        elseif ($app['status'] === 'In-Consultation') $stats['consulting']++;
        elseif ($app['status'] === 'Completed') $stats['completed']++;
        elseif ($app['status'] === 'Scheduled') $stats['scheduled']++;
    }

    echo json_encode([
        'success' => true,
        'date' => $date,
        'doctors' => $doctors,
        'stats' => $stats,
        'appointments' => $appointments
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
