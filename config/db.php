<?php
// config/db.php - Database Configuration & Auto-Installer for XAMPP

define('DB_HOST', '127.0.0.1');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'clinic_scheduler');
define('DB_PORT', 3306);

function getDBConnection() {
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    try {
        // First connect without database to ensure DB exists
        $dsnInitial = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";charset=utf8mb4";
        $initialPdo = new PDO($dsnInitial, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);

        // Auto-create database if not exists
        $initialPdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

        // Connect to the specific database
        $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);

        // Auto-create tables if they do not exist
        initializeTables($pdo);

        return $pdo;
    } catch (PDOException $e) {
        // Return null or die with helpful XAMPP instructions
        return null;
    }
}

function initializeTables($pdo) {
    // 1. Doctors table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `doctors` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `name` VARCHAR(100) NOT NULL,
        `specialty` VARCHAR(100) NOT NULL,
        `room_number` VARCHAR(50) NOT NULL,
        `status` ENUM('Available', 'Busy', 'On Break', 'Off Duty') DEFAULT 'Available',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // 2. Appointments table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `appointments` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `token_number` VARCHAR(20) NOT NULL,
        `patient_name` VARCHAR(120) NOT NULL,
        `patient_phone` VARCHAR(30) NOT NULL,
        `patient_age` INT DEFAULT NULL,
        `patient_gender` VARCHAR(20) DEFAULT 'Other',
        `doctor_id` INT NOT NULL,
        `appointment_date` DATE NOT NULL,
        `appointment_time` TIME NOT NULL,
        `status` ENUM('Scheduled', 'Waiting', 'In-Consultation', 'Completed', 'Cancelled') DEFAULT 'Scheduled',
        `symptoms` TEXT DEFAULT NULL,
        `doctor_notes` TEXT DEFAULT NULL,
        `prescriptions` TEXT DEFAULT NULL,
        `called_at` DATETIME DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX (`appointment_date`),
        INDEX (`doctor_id`),
        INDEX (`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // 3. Notifications table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `notifications` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `recipient_type` ENUM('frontdesk', 'doctor', 'all') NOT NULL,
        `doctor_id` INT DEFAULT NULL,
        `title` VARCHAR(150) NOT NULL,
        `message` TEXT NOT NULL,
        `type` ENUM('info', 'call', 'checkin', 'completed', 'new') DEFAULT 'info',
        `is_read` TINYINT(1) DEFAULT 0,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (`recipient_type`),
        INDEX (`doctor_id`),
        INDEX (`is_read`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // Seed sample doctors if table is empty
    $stmt = $pdo->query("SELECT COUNT(*) FROM `doctors`");
    if ($stmt->fetchColumn() == 0) {
        $insert = $pdo->prepare("INSERT INTO `doctors` (`name`, `specialty`, `room_number`, `status`) VALUES (?, ?, ?, ?)");
        $sampleDoctors = [
            ['Dr. Sophia Patel', 'General Physician', 'Room 101', 'Available'],
            ['Dr. Marcus Vance', 'Cardiologist', 'Room 102', 'Available'],
            ['Dr. Elena Rostova', 'Pediatrician', 'Room 103', 'Available'],
            ['Dr. James Chen', 'Orthopedic Surgeon', 'Room 104', 'Available']
        ];
        foreach ($sampleDoctors as $doc) {
            $insert->execute($doc);
        }

        // Seed initial sample appointments for today
        $today = date('Y-m-d');
        $sampleAppointments = [
            ['T-101', 'Alice Robinson', '+1 555-0101', 32, 'Female', 1, $today, '09:30:00', 'Waiting', 'Mild fever & seasonal cough', null, null],
            ['T-102', 'David Miller', '+1 555-0102', 45, 'Male', 2, $today, '10:00:00', 'Scheduled', 'Routine blood pressure checkup', null, null],
            ['T-103', 'Emma Watson', '+1 555-0103', 8, 'Female', 3, $today, '10:15:00', 'Waiting', 'Vaccination consultation', null, null],
            ['T-104', 'Robert Garcia', '+1 555-0104', 58, 'Male', 1, $today, '11:00:00', 'Scheduled', 'Joint stiffness in right knee', null, null]
        ];

        $appInsert = $pdo->prepare("INSERT INTO `appointments` 
            (`token_number`, `patient_name`, `patient_phone`, `patient_age`, `patient_gender`, `doctor_id`, `appointment_date`, `appointment_time`, `status`, `symptoms`, `doctor_notes`, `prescriptions`) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        foreach ($sampleAppointments as $app) {
            $appInsert->execute($app);
        }

        // Seed initial notification
        $pdo->exec("INSERT INTO `notifications` (`recipient_type`, `doctor_id`, `title`, `message`, `type`) VALUES 
            ('frontdesk', NULL, 'System Ready', 'Clinic Scheduler initialized successfully.', 'info'),
            ('doctor', 1, 'Patient Waiting', 'Patient Alice Robinson (T-101) checked in for General Physician.', 'checkin')");
    }
}
