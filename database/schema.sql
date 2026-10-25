-- ====================================================================
-- KVM Hospital Management System — NexusCare
-- Centralized Relational Database Schema
-- Database: kvm_hospital
-- Storage Engine: InnoDB | Charset: utf8mb4 | Collation: utf8mb4_unicode_ci
-- ====================================================================

CREATE DATABASE IF NOT EXISTS `kvm_hospital` 
  CHARACTER SET utf8mb4 
  COLLATE utf8mb4_unicode_ci;

USE `kvm_hospital`;

SET FOREIGN_KEY_CHECKS = 0;

-- --------------------------------------------------------------------
-- A. Authentication, Authorization & Access Control
-- --------------------------------------------------------------------

DROP TABLE IF EXISTS `financial_adjustments`;
DROP TABLE IF EXISTS `payments`;
DROP TABLE IF EXISTS `invoice_items`;
DROP TABLE IF EXISTS `invoices`;
DROP TABLE IF EXISTS `role_change_logs`;
DROP TABLE IF EXISTS `login_attempts`;
DROP TABLE IF EXISTS `audit_logs`;
DROP TABLE IF EXISTS `flowlens_alert_history`;
DROP TABLE IF EXISTS `flowlens_alerts`;
DROP TABLE IF EXISTS `operational_metrics`;
DROP TABLE IF EXISTS `patient_journey_events`;
DROP TABLE IF EXISTS `queue_priority_events`;
DROP TABLE IF EXISTS `queue_status_history`;
DROP TABLE IF EXISTS `queue_entries`;
DROP TABLE IF EXISTS `doctor_availability`;
DROP TABLE IF EXISTS `bed_assignment_resources`;
DROP TABLE IF EXISTS `bed_resources`;
DROP TABLE IF EXISTS `bed_status_history`;
DROP TABLE IF EXISTS `bed_assignments`;
DROP TABLE IF EXISTS `beds`;
DROP TABLE IF EXISTS `rooms`;
DROP TABLE IF EXISTS `wards`;
DROP TABLE IF EXISTS `inventory_transactions`;
DROP TABLE IF EXISTS `pharmacy_dispensing_records`;
DROP TABLE IF EXISTS `pharmacy_order_items`;
DROP TABLE IF EXISTS `pharmacy_orders`;
DROP TABLE IF EXISTS `medicine_batches`;
DROP TABLE IF EXISTS `medicines`;
DROP TABLE IF EXISTS `lab_order_items`;
DROP TABLE IF EXISTS `lab_orders`;
DROP TABLE IF EXISTS `lab_tests`;
DROP TABLE IF EXISTS `prescription_items`;
DROP TABLE IF EXISTS `prescriptions`;
DROP TABLE IF EXISTS `diagnoses`;
DROP TABLE IF EXISTS `consultations`;
DROP TABLE IF EXISTS `patient_encounters`;
DROP TABLE IF EXISTS `appointment_status_history`;
DROP TABLE IF EXISTS `appointments`;
DROP TABLE IF EXISTS `patient_profiles`;
DROP TABLE IF EXISTS `doctor_departments`;
DROP TABLE IF EXISTS `doctor_profiles`;
DROP TABLE IF EXISTS `staff_profiles`;
DROP TABLE IF EXISTS `departments`;
DROP TABLE IF EXISTS `user_role_assignments`;
DROP TABLE IF EXISTS `role_permissions`;
DROP TABLE IF EXISTS `permissions`;
DROP TABLE IF EXISTS `roles`;
DROP TABLE IF EXISTS `users`;

-- 1. Roles
CREATE TABLE `roles` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `role_name` VARCHAR(50) NOT NULL UNIQUE,
  `display_name` VARCHAR(100) NOT NULL,
  `description` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 2. Permissions
CREATE TABLE `permissions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `permission_key` VARCHAR(100) NOT NULL UNIQUE,
  `module` VARCHAR(50) NOT NULL,
  `description` TEXT NULL
) ENGINE=InnoDB;

-- 3. Role Permissions Mapping
CREATE TABLE `role_permissions` (
  `role_id` INT NOT NULL,
  `permission_id` INT NOT NULL,
  PRIMARY KEY (`role_id`, `permission_id`),
  FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`permission_id`) REFERENCES `permissions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 4. Users
CREATE TABLE `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `email` VARCHAR(100) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `account_status` ENUM('active', 'inactive', 'suspended', 'locked') DEFAULT 'active',
  `failed_login_attempts` INT DEFAULT 0,
  `lockout_until` DATETIME NULL,
  `last_login_at` DATETIME NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_users_email` (`email`),
  INDEX `idx_users_status` (`account_status`)
) ENGINE=InnoDB;

-- 5. User Role Assignments
CREATE TABLE `user_role_assignments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `role_id` INT NOT NULL,
  `assigned_by` INT NULL,
  `assigned_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uk_user_role` (`user_id`, `role_id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`assigned_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

-- --------------------------------------------------------------------
-- B. Hospital Organization, Departments & Staff
-- --------------------------------------------------------------------

-- 6. Departments
CREATE TABLE `departments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `dept_code` VARCHAR(20) NOT NULL UNIQUE,
  `name` VARCHAR(100) NOT NULL,
  `description` TEXT NULL,
  `location` VARCHAR(100) NULL,
  `phone_extension` VARCHAR(20) NULL,
  `status` ENUM('active', 'inactive') DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 7. Staff Profiles
CREATE TABLE `staff_profiles` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL UNIQUE,
  `employee_id` VARCHAR(30) NOT NULL UNIQUE,
  `first_name` VARCHAR(60) NOT NULL,
  `last_name` VARCHAR(60) NOT NULL,
  `gender` ENUM('male', 'female', 'other') NOT NULL,
  `phone` VARCHAR(30) NOT NULL,
  `department_id` INT NULL,
  `designation` VARCHAR(100) NOT NULL,
  `join_date` DATE NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`) ON DELETE SET NULL,
  INDEX `idx_staff_dept` (`department_id`)
) ENGINE=InnoDB;

-- 8. Doctor Profiles
CREATE TABLE `doctor_profiles` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL UNIQUE,
  `doctor_code` VARCHAR(30) NOT NULL UNIQUE,
  `first_name` VARCHAR(60) NOT NULL,
  `last_name` VARCHAR(60) NOT NULL,
  `specialization` VARCHAR(100) NOT NULL,
  `qualification` VARCHAR(150) NOT NULL,
  `license_number` VARCHAR(50) NOT NULL UNIQUE,
  `consultation_fee` DECIMAL(10,2) NOT NULL DEFAULT 500.00,
  `department_id` INT NOT NULL,
  `room_no` VARCHAR(30) NULL,
  `availability_status` ENUM('available', 'busy', 'on_break', 'off_duty') DEFAULT 'available',
  `avg_consultation_time_mins` INT DEFAULT 15,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`) ON DELETE RESTRICT,
  INDEX `idx_doc_dept` (`department_id`),
  INDEX `idx_doc_status` (`availability_status`)
) ENGINE=InnoDB;

-- 9. Doctor Secondary Department Affiliations
CREATE TABLE `doctor_departments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `doctor_id` INT NOT NULL,
  `department_id` INT NOT NULL,
  UNIQUE KEY `uk_doc_dept` (`doctor_id`, `department_id`),
  FOREIGN KEY (`doctor_id`) REFERENCES `doctor_profiles`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 10. Patient Profiles
CREATE TABLE `patient_profiles` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NULL UNIQUE,
  `patient_id` VARCHAR(30) NOT NULL UNIQUE, -- e.g. PAT-2026-0001
  `first_name` VARCHAR(60) NOT NULL,
  `last_name` VARCHAR(60) NOT NULL,
  `dob` DATE NOT NULL,
  `gender` ENUM('male', 'female', 'other') NOT NULL,
  `blood_group` ENUM('A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-', 'Unknown') DEFAULT 'Unknown',
  `phone` VARCHAR(30) NOT NULL,
  `email` VARCHAR(100) NULL,
  `address` TEXT NULL,
  `emergency_contact_name` VARCHAR(100) NOT NULL,
  `emergency_contact_phone` VARCHAR(30) NOT NULL,
  `allergies` TEXT NULL,
  `medical_history` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  INDEX `idx_patient_code` (`patient_id`),
  INDEX `idx_patient_phone` (`phone`),
  INDEX `idx_patient_name` (`last_name`, `first_name`)
) ENGINE=InnoDB;

-- --------------------------------------------------------------------
-- C. Clinical Care, Appointments & Consultations
-- --------------------------------------------------------------------

-- 11. Appointments
CREATE TABLE `appointments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `appointment_number` VARCHAR(30) NOT NULL UNIQUE,
  `patient_id` INT NOT NULL,
  `doctor_id` INT NOT NULL,
  `department_id` INT NOT NULL,
  `appointment_date` DATE NOT NULL,
  `appointment_time` TIME NOT NULL,
  `reason` VARCHAR(255) NULL,
  `status` ENUM('pending', 'confirmed', 'checked_in', 'in_consultation', 'completed', 'cancelled', 'no_show') DEFAULT 'pending',
  `created_by` INT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`patient_id`) REFERENCES `patient_profiles`(`id`) ON DELETE RESTRICT,
  FOREIGN KEY (`doctor_id`) REFERENCES `doctor_profiles`(`id`) ON DELETE RESTRICT,
  FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`) ON DELETE RESTRICT,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  INDEX `idx_app_date_doc` (`appointment_date`, `doctor_id`, `appointment_time`),
  INDEX `idx_app_status` (`status`)
) ENGINE=InnoDB;

-- 12. Appointment Status History
CREATE TABLE `appointment_status_history` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `appointment_id` INT NOT NULL,
  `old_status` VARCHAR(30) NOT NULL,
  `new_status` VARCHAR(30) NOT NULL,
  `changed_by` INT NULL,
  `change_reason` VARCHAR(255) NULL,
  `changed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`appointment_id`) REFERENCES `appointments`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`changed_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

-- 13. Patient Encounters
CREATE TABLE `patient_encounters` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `encounter_number` VARCHAR(30) NOT NULL UNIQUE,
  `patient_id` INT NOT NULL,
  `appointment_id` INT NULL,
  `doctor_id` INT NOT NULL,
  `department_id` INT NOT NULL,
  `encounter_type` ENUM('outpatient', 'inpatient', 'emergency') DEFAULT 'outpatient',
  `status` ENUM('active', 'completed', 'cancelled') DEFAULT 'active',
  `started_at` DATETIME NOT NULL,
  `ended_at` DATETIME NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`patient_id`) REFERENCES `patient_profiles`(`id`) ON DELETE RESTRICT,
  FOREIGN KEY (`appointment_id`) REFERENCES `appointments`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`doctor_id`) REFERENCES `doctor_profiles`(`id`) ON DELETE RESTRICT,
  FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`) ON DELETE RESTRICT,
  INDEX `idx_encounter_patient` (`patient_id`),
  INDEX `idx_encounter_status` (`status`)
) ENGINE=InnoDB;

-- 14. Consultations
CREATE TABLE `consultations` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `encounter_id` INT NOT NULL,
  `patient_id` INT NOT NULL,
  `doctor_id` INT NOT NULL,
  `vitals_bp` VARCHAR(20) NULL,      -- e.g. 120/80
  `vitals_pulse` INT NULL,          -- bpm
  `vitals_temp` DECIMAL(4,1) NULL,  -- Fahrenheit e.g. 98.6
  `vitals_weight` DECIMAL(5,2) NULL,-- kg
  `vitals_spo2` INT NULL,           -- percentage e.g. 98
  `clinical_notes` TEXT NOT NULL,
  `treatment_plan` TEXT NULL,
  `follow_up_date` DATE NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`encounter_id`) REFERENCES `patient_encounters`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`patient_id`) REFERENCES `patient_profiles`(`id`) ON DELETE RESTRICT,
  FOREIGN KEY (`doctor_id`) REFERENCES `doctor_profiles`(`id`) ON DELETE RESTRICT,
  INDEX `idx_consult_patient` (`patient_id`)
) ENGINE=InnoDB;

-- 15. Diagnoses
CREATE TABLE `diagnoses` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `consultation_id` INT NOT NULL,
  `patient_id` INT NOT NULL,
  `diagnosis_code` VARCHAR(30) NULL, -- ICD-10 code e.g. J06.9
  `diagnosis_title` VARCHAR(150) NOT NULL,
  `diagnosis_type` ENUM('primary', 'secondary') DEFAULT 'primary',
  `notes` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`consultation_id`) REFERENCES `consultations`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`patient_id`) REFERENCES `patient_profiles`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- 16. Prescriptions
CREATE TABLE `prescriptions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `encounter_id` INT NOT NULL,
  `consultation_id` INT NOT NULL,
  `patient_id` INT NOT NULL,
  `doctor_id` INT NOT NULL,
  `notes` TEXT NULL,
  `status` ENUM('pending', 'dispensed', 'partially_dispensed', 'cancelled') DEFAULT 'pending',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`encounter_id`) REFERENCES `patient_encounters`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`consultation_id`) REFERENCES `consultations`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`patient_id`) REFERENCES `patient_profiles`(`id`) ON DELETE RESTRICT,
  FOREIGN KEY (`doctor_id`) REFERENCES `doctor_profiles`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- 17. Prescription Items
CREATE TABLE `prescription_items` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `prescription_id` INT NOT NULL,
  `medicine_id` INT NOT NULL,
  `dosage` VARCHAR(50) NOT NULL,      -- e.g. 500mg
  `frequency` VARCHAR(50) NOT NULL,   -- e.g. 1-0-1 (twice a day)
  `duration_days` INT NOT NULL,       -- e.g. 5
  `quantity` INT NOT NULL,            -- e.g. 10
  `instructions` VARCHAR(255) NULL,   -- e.g. After meals
  FOREIGN KEY (`prescription_id`) REFERENCES `prescriptions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- --------------------------------------------------------------------
-- D. Laboratory & Pharmacy Management
-- --------------------------------------------------------------------

-- 18. Laboratory Tests Catalog
CREATE TABLE `lab_tests` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `test_code` VARCHAR(30) NOT NULL UNIQUE,
  `test_name` VARCHAR(100) NOT NULL,
  `department_id` INT NULL,
  `standard_charge` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `normal_range` VARCHAR(100) NULL,
  `units` VARCHAR(30) NULL,
  `description` TEXT NULL,
  FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

-- 19. Laboratory Orders
CREATE TABLE `lab_orders` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `order_number` VARCHAR(30) NOT NULL UNIQUE,
  `patient_id` INT NOT NULL,
  `encounter_id` INT NULL,
  `ordered_by_doctor_id` INT NOT NULL,
  `priority` ENUM('routine', 'urgent', 'stat') DEFAULT 'routine',
  `status` ENUM('ordered', 'collected', 'processing', 'completed', 'cancelled') DEFAULT 'ordered',
  `clinical_notes` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`patient_id`) REFERENCES `patient_profiles`(`id`) ON DELETE RESTRICT,
  FOREIGN KEY (`encounter_id`) REFERENCES `patient_encounters`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`ordered_by_doctor_id`) REFERENCES `doctor_profiles`(`id`) ON DELETE RESTRICT,
  INDEX `idx_lab_status` (`status`)
) ENGINE=InnoDB;

-- 20. Laboratory Order Items & Results
CREATE TABLE `lab_order_items` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `lab_order_id` INT NOT NULL,
  `test_id` INT NOT NULL,
  `status` ENUM('pending', 'collected', 'analyzing', 'completed', 'cancelled') DEFAULT 'pending',
  `result_value` VARCHAR(150) NULL,
  `reference_range` VARCHAR(100) NULL,
  `result_notes` TEXT NULL,
  `tested_by` INT NULL,
  `verified_by` INT NULL,
  `completed_at` DATETIME NULL,
  FOREIGN KEY (`lab_order_id`) REFERENCES `lab_orders`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`test_id`) REFERENCES `lab_tests`(`id`) ON DELETE RESTRICT,
  FOREIGN KEY (`tested_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`verified_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

-- 21. Medicines Catalog
CREATE TABLE `medicines` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `medicine_code` VARCHAR(30) NOT NULL UNIQUE,
  `name` VARCHAR(100) NOT NULL,
  `generic_name` VARCHAR(100) NOT NULL,
  `category` VARCHAR(50) NOT NULL, -- Tablet, Syrup, Injection, Capsule, Ointment
  `unit` VARCHAR(20) NOT NULL,     -- strip, bottle, vial, box
  `unit_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `stock_quantity` INT NOT NULL DEFAULT 0,
  `reorder_level` INT NOT NULL DEFAULT 20,
  `status` ENUM('active', 'discontinued') DEFAULT 'active',
  INDEX `idx_med_code` (`medicine_code`),
  INDEX `idx_med_name` (`name`),
  CONSTRAINT `chk_med_stock` CHECK (`stock_quantity` >= 0)
) ENGINE=InnoDB;

-- 22. Medicine Batches
CREATE TABLE `medicine_batches` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `medicine_id` INT NOT NULL,
  `batch_number` VARCHAR(50) NOT NULL,
  `expiry_date` DATE NOT NULL,
  `quantity` INT NOT NULL DEFAULT 0,
  `purchase_cost` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`medicine_id`) REFERENCES `medicines`(`id`) ON DELETE CASCADE,
  INDEX `idx_batch_expiry` (`expiry_date`),
  CONSTRAINT `chk_batch_qty` CHECK (`quantity` >= 0)
) ENGINE=InnoDB;

-- 23. Pharmacy Orders
CREATE TABLE `pharmacy_orders` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `order_number` VARCHAR(30) NOT NULL UNIQUE,
  `prescription_id` INT NULL,
  `patient_id` INT NOT NULL,
  `status` ENUM('pending', 'dispensed', 'cancelled') DEFAULT 'pending',
  `total_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`prescription_id`) REFERENCES `prescriptions`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`patient_id`) REFERENCES `patient_profiles`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- 24. Pharmacy Order Items
CREATE TABLE `pharmacy_order_items` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `pharmacy_order_id` INT NOT NULL,
  `medicine_id` INT NOT NULL,
  `batch_id` INT NULL,
  `quantity` INT NOT NULL,
  `unit_price` DECIMAL(10,2) NOT NULL,
  `total_price` DECIMAL(10,2) NOT NULL,
  FOREIGN KEY (`pharmacy_order_id`) REFERENCES `pharmacy_orders`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`medicine_id`) REFERENCES `medicines`(`id`) ON DELETE RESTRICT,
  FOREIGN KEY (`batch_id`) REFERENCES `medicine_batches`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

-- 25. Pharmacy Dispensing Records
CREATE TABLE `pharmacy_dispensing_records` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `pharmacy_order_id` INT NOT NULL UNIQUE,
  `dispensed_by` INT NOT NULL,
  `dispensing_notes` TEXT NULL,
  `dispensed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`pharmacy_order_id`) REFERENCES `pharmacy_orders`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`dispensed_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- 26. Inventory Transactions
CREATE TABLE `inventory_transactions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `medicine_id` INT NOT NULL,
  `batch_id` INT NULL,
  `transaction_type` ENUM('stock_in', 'dispensed', 'adjustment', 'return', 'expired') NOT NULL,
  `quantity` INT NOT NULL, -- positive for addition, negative for deduction
  `balance_after` INT NOT NULL,
  `reference_id` VARCHAR(50) NULL,
  `notes` VARCHAR(255) NULL,
  `created_by` INT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`medicine_id`) REFERENCES `medicines`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`batch_id`) REFERENCES `medicine_batches`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- --------------------------------------------------------------------
-- E. BedMatrix — Dynamic Bed & Inpatient Allocation
-- --------------------------------------------------------------------

-- 27. Wards
CREATE TABLE `wards` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `ward_code` VARCHAR(20) NOT NULL UNIQUE,
  `name` VARCHAR(100) NOT NULL,
  `ward_type` ENUM('general', 'icu', 'pediatric', 'maternity', 'isolation', 'post_op') DEFAULT 'general',
  `care_level` ENUM('standard', 'intermediate', 'critical') DEFAULT 'standard',
  `capacity` INT NOT NULL DEFAULT 10,
  `gender_designation` ENUM('all', 'male_only', 'female_only', 'pediatric') DEFAULT 'all',
  `description` VARCHAR(255) NULL
) ENGINE=InnoDB;

-- 28. Rooms
CREATE TABLE `rooms` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `ward_id` INT NOT NULL,
  `room_number` VARCHAR(30) NOT NULL,
  `room_type` ENUM('single', 'double', 'shared', 'suite') DEFAULT 'shared',
  `standard_rate_per_day` DECIMAL(10,2) NOT NULL DEFAULT 1000.00,
  FOREIGN KEY (`ward_id`) REFERENCES `wards`(`id`) ON DELETE CASCADE,
  UNIQUE KEY `uk_ward_room` (`ward_id`, `room_number`)
) ENGINE=InnoDB;

-- 29. Beds
CREATE TABLE `beds` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `room_id` INT NOT NULL,
  `ward_id` INT NOT NULL,
  `bed_number` VARCHAR(30) NOT NULL,
  `bed_type` ENUM('standard', 'icu_bed', 'bariatric', 'pediatric', 'ventilator_enabled') DEFAULT 'standard',
  `care_level` ENUM('standard', 'intermediate', 'critical') DEFAULT 'standard',
  `status` ENUM('available', 'occupied', 'reserved', 'cleaning', 'out_of_service') DEFAULT 'available',
  `daily_rate` DECIMAL(10,2) NOT NULL DEFAULT 1500.00,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`room_id`) REFERENCES `rooms`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`ward_id`) REFERENCES `wards`(`id`) ON DELETE CASCADE,
  UNIQUE KEY `uk_room_bed` (`room_id`, `bed_number`),
  INDEX `idx_bed_status` (`status`),
  INDEX `idx_bed_ward_status` (`ward_id`, `status`)
) ENGINE=InnoDB;

-- 30. Bed Assignments
CREATE TABLE `bed_assignments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `bed_id` INT NOT NULL,
  `patient_id` INT NOT NULL,
  `encounter_id` INT NOT NULL,
  `admission_date` DATETIME NOT NULL,
  `discharge_date` DATETIME NULL,
  `status` ENUM('active', 'transferred', 'discharged') DEFAULT 'active',
  `assigned_by` INT NOT NULL,
  `clinical_reason` VARCHAR(255) NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`bed_id`) REFERENCES `beds`(`id`) ON DELETE RESTRICT,
  FOREIGN KEY (`patient_id`) REFERENCES `patient_profiles`(`id`) ON DELETE RESTRICT,
  FOREIGN KEY (`encounter_id`) REFERENCES `patient_encounters`(`id`) ON DELETE RESTRICT,
  FOREIGN KEY (`assigned_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT,
  INDEX `idx_assignment_active` (`bed_id`, `status`),
  INDEX `idx_assignment_patient` (`patient_id`, `status`)
) ENGINE=InnoDB;

-- 31. Bed Status History
CREATE TABLE `bed_status_history` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `bed_id` INT NOT NULL,
  `old_status` VARCHAR(30) NOT NULL,
  `new_status` VARCHAR(30) NOT NULL,
  `changed_by` INT NOT NULL,
  `notes` VARCHAR(255) NULL,
  `changed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`bed_id`) REFERENCES `beds`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`changed_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- 32. Bed Resources & Equipment
CREATE TABLE `bed_resources` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `bed_id` INT NOT NULL,
  `resource_name` VARCHAR(100) NOT NULL, -- Ventilator, Cardiac Monitor, Oxygen Port, Infusion Pump
  `resource_type` VARCHAR(50) NOT NULL,
  `status` ENUM('operational', 'maintenance', 'detached') DEFAULT 'operational',
  FOREIGN KEY (`bed_id`) REFERENCES `beds`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- --------------------------------------------------------------------
-- F. QueueFlow — Live Patient Flow Intelligence
-- --------------------------------------------------------------------

-- 33. Doctor Availability Slots & Schedules
CREATE TABLE `doctor_availability` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `doctor_id` INT NOT NULL,
  `day_of_week` ENUM('Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday') NOT NULL,
  `start_time` TIME NOT NULL,
  `end_time` TIME NOT NULL,
  `slot_duration_minutes` INT DEFAULT 15,
  `max_patients_per_session` INT DEFAULT 25,
  `status` ENUM('available', 'on_break', 'in_surgery', 'off_duty') DEFAULT 'available',
  FOREIGN KEY (`doctor_id`) REFERENCES `doctor_profiles`(`id`) ON DELETE CASCADE,
  INDEX `idx_doc_avail` (`doctor_id`, `day_of_week`)
) ENGINE=InnoDB;

-- 34. Queue Entries
CREATE TABLE `queue_entries` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `token_number` INT NOT NULL,
  `patient_id` INT NOT NULL,
  `appointment_id` INT NULL,
  `doctor_id` INT NOT NULL,
  `department_id` INT NOT NULL,
  `queue_date` DATE NOT NULL,
  `check_in_time` DATETIME NOT NULL,
  `consultation_start_time` DATETIME NULL,
  `completion_time` DATETIME NULL,
  `status` ENUM('waiting', 'called', 'in_consultation', 'completed', 'no_show', 'cancelled') DEFAULT 'waiting',
  `priority_level` ENUM('normal', 'urgent', 'emergency') DEFAULT 'normal',
  `priority_reason` VARCHAR(255) NULL,
  `estimated_wait_time_minutes` INT DEFAULT 15,
  `created_by` INT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`patient_id`) REFERENCES `patient_profiles`(`id`) ON DELETE RESTRICT,
  FOREIGN KEY (`appointment_id`) REFERENCES `appointments`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`doctor_id`) REFERENCES `doctor_profiles`(`id`) ON DELETE RESTRICT,
  FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`) ON DELETE RESTRICT,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  UNIQUE KEY `uk_queue_token` (`queue_date`, `doctor_id`, `token_number`),
  INDEX `idx_queue_active` (`queue_date`, `doctor_id`, `status`, `priority_level`)
) ENGINE=InnoDB;

-- 35. Queue Status History
CREATE TABLE `queue_status_history` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `queue_entry_id` INT NOT NULL,
  `old_status` VARCHAR(30) NOT NULL,
  `new_status` VARCHAR(30) NOT NULL,
  `changed_by` INT NULL,
  `changed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`queue_entry_id`) REFERENCES `queue_entries`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`changed_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

-- 36. Queue Priority Change Events
CREATE TABLE `queue_priority_events` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `queue_entry_id` INT NOT NULL,
  `old_priority` VARCHAR(30) NOT NULL,
  `new_priority` VARCHAR(30) NOT NULL,
  `changed_by` INT NOT NULL,
  `reason` VARCHAR(255) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`queue_entry_id`) REFERENCES `queue_entries`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`changed_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- --------------------------------------------------------------------
-- G. CareTrail — Unified Patient Journey Timeline
-- --------------------------------------------------------------------

-- 37. Patient Journey Events
CREATE TABLE `patient_journey_events` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `patient_id` INT NOT NULL,
  `encounter_id` INT NULL,
  `event_type` ENUM(
    'registration',
    'appointment_booked',
    'check_in',
    'queue_entered',
    'consultation_started',
    'consultation_completed',
    'lab_ordered',
    'lab_completed',
    'prescription_issued',
    'medicine_dispensed',
    'admitted',
    'bed_assigned',
    'bed_transferred',
    'invoice_generated',
    'payment_received',
    'discharged',
    'follow_up_scheduled'
  ) NOT NULL,
  `event_title` VARCHAR(150) NOT NULL,
  `event_description` TEXT NOT NULL,
  `department_id` INT NULL,
  `source_table` VARCHAR(50) NULL,
  `source_id` INT NULL,
  `severity` ENUM('info', 'warning', 'critical', 'success') DEFAULT 'info',
  `created_by` INT NULL,
  `event_time` DATETIME NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`patient_id`) REFERENCES `patient_profiles`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`encounter_id`) REFERENCES `patient_encounters`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  INDEX `idx_journey_patient_time` (`patient_id`, `event_time`),
  INDEX `idx_journey_encounter` (`encounter_id`)
) ENGINE=InnoDB;

-- --------------------------------------------------------------------
-- H. FlowLens — Hospital Bottleneck Detector
-- --------------------------------------------------------------------

-- 38. FlowLens Alerts
CREATE TABLE `flowlens_alerts` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `alert_code` VARCHAR(50) NOT NULL UNIQUE,
  `alert_type` ENUM(
    'queue_wait_time',
    'queue_overflow',
    'lab_delay',
    'bed_occupancy_critical',
    'doctor_workload_imbalance'
  ) NOT NULL,
  `department_id` INT NULL,
  `severity` ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
  `triggering_metric` VARCHAR(100) NOT NULL,
  `observed_value` DECIMAL(10,2) NOT NULL,
  `threshold_value` DECIMAL(10,2) NOT NULL,
  `alert_title` VARCHAR(150) NOT NULL,
  `alert_description` TEXT NOT NULL,
  `suggested_action` TEXT NOT NULL,
  `status` ENUM('active', 'acknowledged', 'resolved', 'dismissed') DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `resolved_by` INT NULL,
  `resolution_notes` TEXT NULL,
  `resolved_at` DATETIME NULL,
  FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`resolved_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  INDEX `idx_flowlens_status` (`status`, `severity`)
) ENGINE=InnoDB;

-- 39. FlowLens Alert Action History
CREATE TABLE `flowlens_alert_history` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `alert_id` INT NOT NULL,
  `action_taken` VARCHAR(50) NOT NULL, -- acknowledged, resolved, threshold_updated
  `action_by` INT NOT NULL,
  `notes` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`alert_id`) REFERENCES `flowlens_alerts`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`action_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- 40. Operational Metrics Snapshot (Historical Aggregation)
CREATE TABLE `operational_metrics` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `metric_date` DATE NOT NULL,
  `department_id` INT NOT NULL,
  `avg_wait_time_minutes` DECIMAL(6,2) DEFAULT 0.00,
  `avg_consultation_time_minutes` DECIMAL(6,2) DEFAULT 0.00,
  `total_patients_served` INT DEFAULT 0,
  `bed_occupancy_rate` DECIMAL(5,2) DEFAULT 0.00,
  `pending_lab_orders_count` INT DEFAULT 0,
  `captured_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`) ON DELETE CASCADE,
  UNIQUE KEY `uk_metric_dept_date` (`metric_date`, `department_id`)
) ENGINE=InnoDB;

-- --------------------------------------------------------------------
-- I. TrustLedger — Smart DBMS Audit & Data Integrity
-- --------------------------------------------------------------------

-- 41. Comprehensive Audit Logs
CREATE TABLE `audit_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NULL,
  `role_name` VARCHAR(50) NULL,
  `action_type` ENUM(
    'CREATE',
    'UPDATE',
    'DELETE',
    'VIEW',
    'LOGIN',
    'LOGOUT',
    'PERMISSION_CHANGE',
    'STATUS_CHANGE',
    'OVERRIDE'
  ) NOT NULL,
  `entity_type` VARCHAR(60) NOT NULL, -- e.g. Patient, Appointment, BedAssignment, Invoice
  `entity_id` INT NULL,
  `ip_address` VARCHAR(45) NULL,
  `user_agent` VARCHAR(255) NULL,
  `description` TEXT NOT NULL,
  `old_values` JSON NULL,
  `new_values` JSON NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  INDEX `idx_audit_user` (`user_id`),
  INDEX `idx_audit_entity` (`entity_type`, `entity_id`),
  INDEX `idx_audit_created` (`created_at`)
) ENGINE=InnoDB;

-- 42. Login Attempts (Brute Force / Security Log)
CREATE TABLE `login_attempts` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username_or_email` VARCHAR(100) NOT NULL,
  `ip_address` VARCHAR(45) NOT NULL,
  `user_agent` VARCHAR(255) NULL,
  `status` ENUM('success', 'failed', 'locked_out') NOT NULL,
  `attempt_time` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_login_ip` (`ip_address`, `attempt_time`),
  INDEX `idx_login_user` (`username_or_email`, `attempt_time`)
) ENGINE=InnoDB;

-- 43. Role Change Logs
CREATE TABLE `role_change_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `target_user_id` INT NOT NULL,
  `changed_by` INT NOT NULL,
  `old_roles` VARCHAR(255) NOT NULL,
  `new_roles` VARCHAR(255) NOT NULL,
  `reason` VARCHAR(255) NOT NULL,
  `changed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`target_user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`changed_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- --------------------------------------------------------------------
-- J. Billing, Invoices & Payments
-- --------------------------------------------------------------------

-- 44. Invoices
CREATE TABLE `invoices` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `invoice_number` VARCHAR(30) NOT NULL UNIQUE,
  `patient_id` INT NOT NULL,
  `encounter_id` INT NULL,
  `subtotal` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `discount_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `tax_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `total_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `paid_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `balance_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `status` ENUM('draft', 'finalized', 'partially_paid', 'paid', 'cancelled') DEFAULT 'draft',
  `created_by` INT NOT NULL,
  `finalized_at` DATETIME NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`patient_id`) REFERENCES `patient_profiles`(`id`) ON DELETE RESTRICT,
  FOREIGN KEY (`encounter_id`) REFERENCES `patient_encounters`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT,
  INDEX `idx_invoice_patient` (`patient_id`),
  INDEX `idx_invoice_status` (`status`)
) ENGINE=InnoDB;

-- 45. Invoice Items
CREATE TABLE `invoice_items` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `invoice_id` INT NOT NULL,
  `item_type` ENUM('consultation', 'lab_test', 'pharmacy', 'bed_charge', 'nursing', 'procedure', 'other') NOT NULL,
  `item_description` VARCHAR(255) NOT NULL,
  `quantity` INT NOT NULL DEFAULT 1,
  `unit_price` DECIMAL(10,2) NOT NULL,
  `total_price` DECIMAL(10,2) NOT NULL,
  `reference_id` INT NULL,
  FOREIGN KEY (`invoice_id`) REFERENCES `invoices`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 46. Payments
CREATE TABLE `payments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `payment_number` VARCHAR(30) NOT NULL UNIQUE,
  `invoice_id` INT NOT NULL,
  `patient_id` INT NOT NULL,
  `amount` DECIMAL(10,2) NOT NULL,
  `payment_method` ENUM('cash', 'card', 'bank_transfer', 'upi', 'insurance') DEFAULT 'cash',
  `transaction_reference` VARCHAR(100) NULL,
  `notes` VARCHAR(255) NULL,
  `received_by` INT NOT NULL,
  `payment_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`invoice_id`) REFERENCES `invoices`(`id`) ON DELETE RESTRICT,
  FOREIGN KEY (`patient_id`) REFERENCES `patient_profiles`(`id`) ON DELETE RESTRICT,
  FOREIGN KEY (`received_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT,
  INDEX `idx_payment_invoice` (`invoice_id`)
) ENGINE=InnoDB;

-- 47. Financial Adjustments
CREATE TABLE `financial_adjustments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `invoice_id` INT NOT NULL,
  `adjustment_type` ENUM('discount', 'waiver', 'refund', 'correction') NOT NULL,
  `amount` DECIMAL(10,2) NOT NULL,
  `reason` VARCHAR(255) NOT NULL,
  `approved_by` INT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`invoice_id`) REFERENCES `invoices`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`approved_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- --------------------------------------------------------------------
-- L. Salary & Payroll Management
-- --------------------------------------------------------------------

-- 48. Employee Salaries
CREATE TABLE `employee_salaries` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `employee_id` INT NOT NULL,
  `employee_type` ENUM('doctor', 'staff') NOT NULL,
  `department_id` INT NULL,
  `monthly_salary` DECIMAL(10,2) NOT NULL,
  `effective_from` DATE NOT NULL,
  `effective_to` DATE NULL,
  `status` ENUM('active', 'superseded') DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uk_emp_sal` (`employee_id`, `employee_type`),
  FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`) ON DELETE SET NULL,
  INDEX `idx_salary_dept` (`department_id`),
  INDEX `idx_salary_type` (`employee_type`),
  INDEX `idx_salary_amount` (`monthly_salary`)
) ENGINE=InnoDB;

-- 49. Salary History
CREATE TABLE `salary_history` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `salary_id` INT NOT NULL,
  `employee_id` INT NOT NULL,
  `employee_type` ENUM('doctor', 'staff') NOT NULL,
  `previous_salary` DECIMAL(10,2) NOT NULL,
  `new_salary` DECIMAL(10,2) NOT NULL,
  `change_reason` VARCHAR(255) NULL,
  `changed_by` INT NULL,
  `effective_date` DATE NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`salary_id`) REFERENCES `employee_salaries`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`changed_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  INDEX `idx_sal_hist_emp` (`employee_id`, `employee_type`)
) ENGINE=InnoDB;

-- --------------------------------------------------------------------
-- K. Views for Operational Intelligence & Fast Reporting
-- --------------------------------------------------------------------

CREATE OR REPLACE VIEW `vw_live_queue_status` AS
SELECT 
  qe.id AS queue_entry_id,
  qe.token_number,
  qe.queue_date,
  qe.status AS queue_status,
  qe.priority_level,
  qe.priority_reason,
  qe.check_in_time,
  qe.consultation_start_time,
  qe.estimated_wait_time_minutes,
  p.id AS patient_id,
  p.patient_id AS patient_code,
  CONCAT(p.first_name, ' ', p.last_name) AS patient_name,
  d.id AS doctor_id,
  CONCAT('Dr. ', d.first_name, ' ', d.last_name) AS doctor_name,
  d.room_no AS doctor_room,
  dep.name AS department_name
FROM `queue_entries` qe
JOIN `patient_profiles` p ON qe.patient_id = p.id
JOIN `doctor_profiles` d ON qe.doctor_id = d.id
JOIN `departments` dep ON qe.department_id = dep.id;

CREATE OR REPLACE VIEW `vw_bed_occupancy_matrix` AS
SELECT 
  b.id AS bed_id,
  b.bed_number,
  b.bed_type,
  b.care_level,
  b.status AS bed_status,
  b.daily_rate,
  r.room_number,
  r.room_type,
  w.id AS ward_id,
  w.name AS ward_name,
  w.ward_code,
  w.ward_type,
  ba.id AS active_assignment_id,
  ba.patient_id,
  CONCAT(p.first_name, ' ', p.last_name) AS occupied_patient_name,
  p.patient_id AS occupied_patient_code,
  ba.admission_date
FROM `beds` b
JOIN `rooms` r ON b.room_id = r.id
JOIN `wards` w ON b.ward_id = w.id
LEFT JOIN `bed_assignments` ba ON b.id = ba.bed_id AND ba.status = 'active'
LEFT JOIN `patient_profiles` p ON ba.patient_id = p.id;

SET FOREIGN_KEY_CHECKS = 1;
