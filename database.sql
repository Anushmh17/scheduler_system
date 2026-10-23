-- Clinic Scheduling System Database Schema
-- Database: clinic_scheduler
-- Compatible with XAMPP MySQL / MariaDB

CREATE DATABASE IF NOT EXISTS `clinic_scheduler` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `clinic_scheduler`;

-- Table structure for `doctors`
CREATE TABLE IF NOT EXISTS `doctors` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `specialty` VARCHAR(100) NOT NULL,
    `room_number` VARCHAR(50) NOT NULL,
    `status` ENUM('Available', 'Busy', 'On Break', 'Off Duty') DEFAULT 'Available',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table structure for `appointments`
CREATE TABLE IF NOT EXISTS `appointments` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table structure for `notifications`
CREATE TABLE IF NOT EXISTS `notifications` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Sample Doctors
INSERT INTO `doctors` (`name`, `specialty`, `room_number`, `status`) VALUES
('Dr. Sophia Patel', 'General Physician', 'Room 101', 'Available'),
('Dr. Marcus Vance', 'Cardiologist', 'Room 102', 'Available'),
('Dr. Elena Rostova', 'Pediatrician', 'Room 103', 'Available'),
('Dr. James Chen', 'Orthopedic Surgeon', 'Room 104', 'Available');

-- Sample Appointments
INSERT INTO `appointments` 
(`token_number`, `patient_name`, `patient_phone`, `patient_age`, `patient_gender`, `doctor_id`, `appointment_date`, `appointment_time`, `status`, `symptoms`) 
VALUES
('T-101', 'Alice Robinson', '+1 555-0101', 32, 'Female', 1, CURDATE(), '09:30:00', 'Waiting', 'Mild fever & seasonal cough'),
('T-102', 'David Miller', '+1 555-0102', 45, 'Male', 2, CURDATE(), '10:00:00', 'Scheduled', 'Routine blood pressure checkup'),
('T-103', 'Emma Watson', '+1 555-0103', 8, 'Female', 3, CURDATE(), '10:15:00', 'Waiting', 'Vaccination consultation'),
('T-104', 'Robert Garcia', '+1 555-0104', 58, 'Male', 1, CURDATE(), '11:00:00', 'Scheduled', 'Joint stiffness in right knee');
