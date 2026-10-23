<?php
// api/actions.php - Handles state transitions and operations
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';

$pdo = getDBConnection();
if (!$pdo) {
    echo json_encode(['success' => false, 'error' => 'Database connection failed. Please ensure MySQL is running in XAMPP.']);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'book_appointment':
            $patient_name = trim($_POST['patient_name'] ?? '');
            $patient_phone = trim($_POST['patient_phone'] ?? '');
            $patient_age = !empty($_POST['patient_age']) ? (int)$_POST['patient_age'] : null;
            $patient_gender = $_POST['patient_gender'] ?? 'Other';
            $doctor_id = (int)($_POST['doctor_id'] ?? 0);
            $appointment_date = $_POST['appointment_date'] ?? date('Y-m-d');
            $appointment_time = $_POST['appointment_time'] ?? date('H:i');
            $symptoms = trim($_POST['symptoms'] ?? '');
            $immediate_checkin = isset($_POST['immediate_checkin']) && $_POST['immediate_checkin'] == '1';

            if (empty($patient_name) || empty($patient_phone) || $doctor_id <= 0) {
                echo json_encode(['success' => false, 'error' => 'Patient name, phone number, and doctor are required.']);
                exit;
            }

            // Generate token number for the day
            $tokenStmt = $pdo->prepare("SELECT COUNT(*) FROM `appointments` WHERE `appointment_date` = ?");
            $tokenStmt->execute([$appointment_date]);
            $countToday = $tokenStmt->fetchColumn();
            $token_number = 'T-' . str_pad($countToday + 1, 3, '0', STR_PAD_LEFT);

            $status = $immediate_checkin ? 'Waiting' : 'Scheduled';

            $insert = $pdo->prepare("INSERT INTO `appointments` 
                (`token_number`, `patient_name`, `patient_phone`, `patient_age`, `patient_gender`, `doctor_id`, `appointment_date`, `appointment_time`, `status`, `symptoms`) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $insert->execute([$token_number, $patient_name, $patient_phone, $patient_age, $patient_gender, $doctor_id, $appointment_date, $appointment_time, $status, $symptoms]);
            $newAppId = $pdo->lastInsertId();

            // Fetch Doctor Name
            $dStmt = $pdo->prepare("SELECT name FROM doctors WHERE id = ?");
            $dStmt->execute([$doctor_id]);
            $doctorName = $dStmt->fetchColumn() ?: 'Doctor';

            // Create notification for Doctor
            $notifTitle = $immediate_checkin ? "Walk-in Patient Waiting ({$token_number})" : "New Appointment Booked ({$token_number})";
            $notifMsg = "Patient {$patient_name} scheduled for {$appointment_date} at {$appointment_time}.";
            $notifType = $immediate_checkin ? 'checkin' : 'new';

            $notifStmt = $pdo->prepare("INSERT INTO `notifications` (`recipient_type`, `doctor_id`, `title`, `message`, `type`) VALUES (?, ?, ?, ?, ?)");
            $notifStmt->execute(['doctor', $doctor_id, $notifTitle, $notifMsg, $notifType]);

            echo json_encode([
                'success' => true, 
                'token' => $token_number, 
                'appointment_id' => $newAppId,
                'message' => "Appointment {$token_number} booked successfully for {$patient_name}!"
            ]);
            break;

        case 'check_in':
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) {
                echo json_encode(['success' => false, 'error' => 'Invalid appointment ID']);
                exit;
            }

            $stmt = $pdo->prepare("SELECT a.*, d.name as doctor_name FROM `appointments` a JOIN `doctors` d ON a.doctor_id = d.id WHERE a.id = ?");
            $stmt->execute([$id]);
            $app = $stmt->fetch();

            if (!$app) {
                echo json_encode(['success' => false, 'error' => 'Appointment not found.']);
                exit;
            }

            $update = $pdo->prepare("UPDATE `appointments` SET `status` = 'Waiting' WHERE `id` = ?");
            $update->execute([$id]);

            // Notify Doctor that patient arrived
            $notifStmt = $pdo->prepare("INSERT INTO `notifications` (`recipient_type`, `doctor_id`, `title`, `message`, `type`) VALUES (?, ?, ?, ?, ?)");
            $notifStmt->execute([
                'doctor', 
                $app['doctor_id'], 
                "Patient Arrived: {$app['token_number']}", 
                "{$app['patient_name']} is now waiting in the queue for {$app['doctor_name']}.",
                'checkin'
            ]);

            echo json_encode(['success' => true, 'message' => "Patient {$app['patient_name']} marked as Waiting!"]);
            break;

        case 'call_patient':
            $id = (int)($_POST['id'] ?? 0);
            $stmt = $pdo->prepare("SELECT a.*, d.name as doctor_name, d.room_number FROM `appointments` a JOIN `doctors` d ON a.doctor_id = d.id WHERE a.id = ?");
            $stmt->execute([$id]);
            $app = $stmt->fetch();

            if (!$app) {
                echo json_encode(['success' => false, 'error' => 'Appointment not found.']);
                exit;
            }

            // Update appointment to In-Consultation and set called_at
            $update = $pdo->prepare("UPDATE `appointments` SET `status` = 'In-Consultation', `called_at` = NOW() WHERE `id` = ?");
            $update->execute([$id]);

            // Update doctor status to Busy
            $docUpdate = $pdo->prepare("UPDATE `doctors` SET `status` = 'Busy' WHERE `id` = ?");
            $docUpdate->execute([$app['doctor_id']]);

            // Notify Front Desk & Waiting Display
            $notifStmt = $pdo->prepare("INSERT INTO `notifications` (`recipient_type`, `doctor_id`, `title`, `message`, `type`) VALUES (?, ?, ?, ?, ?)");
            $notifStmt->execute([
                'frontdesk', 
                null, 
                "Token {$app['token_number']} Called!", 
                "{$app['doctor_name']} ({$app['room_number']}) is now calling {$app['patient_name']}.",
                'call'
            ]);

            echo json_encode([
                'success' => true, 
                'patient_name' => $app['patient_name'], 
                'token' => $app['token_number'],
                'message' => "Called Token {$app['token_number']} ({$app['patient_name']}) to {$app['room_number']}."
            ]);
            break;

        case 'complete_consultation':
            $id = (int)($_POST['id'] ?? 0);
            $notes = trim($_POST['doctor_notes'] ?? '');
            $prescriptions = trim($_POST['prescriptions'] ?? '');

            $stmt = $pdo->prepare("SELECT a.*, d.name as doctor_name FROM `appointments` a JOIN `doctors` d ON a.doctor_id = d.id WHERE a.id = ?");
            $stmt->execute([$id]);
            $app = $stmt->fetch();

            if (!$app) {
                echo json_encode(['success' => false, 'error' => 'Appointment not found.']);
                exit;
            }

            $update = $pdo->prepare("UPDATE `appointments` SET `status` = 'Completed', `doctor_notes` = ?, `prescriptions` = ? WHERE `id` = ?");
            $update->execute([$notes, $prescriptions, $id]);

            // Set doctor status back to Available
            $docUpdate = $pdo->prepare("UPDATE `doctors` SET `status` = 'Available' WHERE `id` = ?");
            $docUpdate->execute([$app['doctor_id']]);

            // Notify Front Desk
            $notifStmt = $pdo->prepare("INSERT INTO `notifications` (`recipient_type`, `doctor_id`, `title`, `message`, `type`) VALUES (?, ?, ?, ?, ?)");
            $notifStmt->execute([
                'frontdesk', 
                null, 
                "Consultation Done: {$app['token_number']}", 
                "{$app['doctor_name']} has completed consultation for {$app['patient_name']}.",
                'completed'
            ]);

            echo json_encode(['success' => true, 'message' => "Consultation completed for {$app['patient_name']}!"]);
            break;

        case 'cancel_appointment':
            $id = (int)($_POST['id'] ?? 0);
            $stmt = $pdo->prepare("SELECT a.*, d.name as doctor_name FROM `appointments` a JOIN `doctors` d ON a.doctor_id = d.id WHERE a.id = ?");
            $stmt->execute([$id]);
            $app = $stmt->fetch();

            if (!$app) {
                echo json_encode(['success' => false, 'error' => 'Appointment not found.']);
                exit;
            }

            $update = $pdo->prepare("UPDATE `appointments` SET `status` = 'Cancelled' WHERE `id` = ?");
            $update->execute([$id]);

            // Notify Doctor if it was waiting or scheduled
            $notifStmt = $pdo->prepare("INSERT INTO `notifications` (`recipient_type`, `doctor_id`, `title`, `message`, `type`) VALUES (?, ?, ?, ?, ?)");
            $notifStmt->execute([
                'doctor', 
                $app['doctor_id'], 
                "Appointment Cancelled ({$app['token_number']})", 
                "Front Desk cancelled appointment for {$app['patient_name']}.",
                'info'
            ]);

            echo json_encode(['success' => true, 'message' => "Appointment {$app['token_number']} cancelled."]);
            break;

        case 'set_doctor_status':
            $doctor_id = (int)($_POST['doctor_id'] ?? 0);
            $new_status = $_POST['status'] ?? 'Available';
            $valid = ['Available', 'Busy', 'On Break', 'Off Duty'];
            if (!in_array($new_status, $valid)) {
                $new_status = 'Available';
            }

            $update = $pdo->prepare("UPDATE `doctors` SET `status` = ? WHERE `id` = ?");
            $update->execute([$new_status, $doctor_id]);

            echo json_encode(['success' => true, 'status' => $new_status]);
            break;

        default:
            echo json_encode(['success' => false, 'error' => 'Invalid action.']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
