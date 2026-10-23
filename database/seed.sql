-- ====================================================================
-- KVM Hospital Management System — NexusCare
-- Seed Data: Demonstration & Testing Dataset
-- Default Password for all demo accounts: Password123!
-- ====================================================================

USE `kvm_hospital`;

SET FOREIGN_KEY_CHECKS = 0;

-- 1. Roles
INSERT INTO `roles` (`id`, `role_name`, `display_name`, `description`) VALUES
(1, 'super_admin', 'Super Admin', 'Full system configuration, security administration, and role-permission authority'),
(2, 'hospital_admin', 'Hospital Admin', 'Hospital operations, department oversight, staff assignments, and FlowLens bottleneck management'),
(3, 'doctor', 'Doctor', 'Clinical consultations, prescriptions, patient encounter management, and lab orders'),
(4, 'receptionist', 'Receptionist', 'Patient registration, appointment scheduling, and QueueFlow check-in desk'),
(5, 'nurse', 'Nurse', 'Bed management, patient observation, vital records, and inpatient care assistance'),
(6, 'lab_technician', 'Laboratory Technician', 'Lab specimen processing, test result entry, and quality approval'),
(7, 'pharmacist', 'Pharmacist', 'Medication inventory control, batch management, and prescription dispensing'),
(8, 'billing_staff', 'Billing Staff', 'Invoice generation, itemized charges, payments, and financial adjustments'),
(9, 'patient', 'Patient', 'Patient self-service portal: appointments, queue tokens, lab results, and CareTrail timeline');

-- 2. Permissions
INSERT INTO `permissions` (`id`, `permission_key`, `module`, `description`) VALUES
(1, 'system.manage_settings', 'admin', 'Manage global system settings'),
(2, 'users.manage', 'admin', 'Create, update, deactivate staff and user accounts'),
(3, 'roles.assign', 'admin', 'Assign and update user roles and permissions'),
(4, 'audit.view', 'trustledger', 'View system audit logs, security records, and changes'),
(5, 'flowlens.manage', 'flowlens', 'View and resolve operational bottlenecks and configure thresholds'),
(6, 'patients.view', 'patient', 'View patient profiles and demographic details'),
(7, 'patients.create', 'patient', 'Register new patients into the hospital'),
(8, 'patients.edit', 'patient', 'Update patient demographic information'),
(9, 'appointments.view', 'appointment', 'View hospital and doctor appointment calendars'),
(10, 'appointments.book', 'appointment', 'Book and reschedule appointments'),
(11, 'appointments.cancel', 'appointment', 'Cancel appointments with documented reason'),
(12, 'queue.manage', 'queueflow', 'Manage live QueueFlow tokens, call next patient, and update priority'),
(13, 'queue.view', 'queueflow', 'View live clinic queue monitors'),
(14, 'consultation.write', 'clinical', 'Record consultation notes, vitals, diagnoses, and treatment plans'),
(15, 'prescriptions.write', 'clinical', 'Issue medication prescriptions to patients'),
(16, 'prescriptions.dispense', 'pharmacy', 'Dispense prescriptions and deduct pharmacy stock'),
(17, 'pharmacy.inventory', 'pharmacy', 'Manage pharmacy catalog, stock levels, batches, and reorders'),
(18, 'lab.order', 'laboratory', 'Order diagnostic laboratory investigations'),
(19, 'lab.results_entry', 'laboratory', 'Enter laboratory test results and normal range values'),
(20, 'beds.manage', 'bedmatrix', 'Allocate, transfer, and release beds; manage cleaning status'),
(21, 'beds.view', 'bedmatrix', 'View interactive BedMatrix occupancy grid'),
(22, 'billing.create_invoice', 'billing', 'Generate invoices from consultations, labs, beds, and pharmacy'),
(23, 'billing.record_payment', 'billing', 'Record patient payments and financial adjustments'),
(24, 'reports.view', 'reports', 'View analytical reports, charts, and operational metrics'),
(25, 'caretrail.view', 'caretrail', 'View complete patient CareTrail journey history');

-- 3. Role-Permission Mappings
-- Super Admin (All Permissions 1 to 25)
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 1, id FROM `permissions`;

-- Hospital Admin
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES
(2, 2), (2, 4), (2, 5), (2, 6), (2, 7), (2, 8), (2, 9), (2, 10), (2, 11),
(2, 13), (2, 20), (2, 21), (2, 22), (2, 23), (2, 24), (2, 25);

-- Doctor
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES
(3, 6), (3, 9), (3, 12), (3, 13), (3, 14), (3, 15), (3, 18), (3, 21), (3, 25);

-- Receptionist
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES
(4, 6), (4, 7), (4, 8), (4, 9), (4, 10), (4, 11), (4, 12), (4, 13), (4, 21), (4, 25);

-- Nurse
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES
(5, 6), (5, 9), (5, 13), (5, 14), (5, 20), (5, 21), (5, 25);

-- Laboratory Technician
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES
(6, 6), (6, 18), (6, 19), (6, 25);

-- Pharmacist
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES
(7, 6), (7, 16), (7, 17), (7, 25);

-- Billing Staff
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES
(8, 6), (8, 22), (8, 23), (8, 24), (8, 25);

-- Patient
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES
(9, 6), (9, 9), (9, 13), (9, 25);

-- 4. Users (Hash = Password123!)
INSERT INTO `users` (`id`, `username`, `email`, `password_hash`, `account_status`, `created_at`) VALUES
(1, 'superadmin', 'superadmin@nexuscare.org', '$2y$10$dPrT4l7TQ.D5rVR/QyJHVeU.qhRtzadZ9PWu7rRfBzidVB1cJwbHK', 'active', NOW()),
(2, 'admin', 'admin@nexuscare.org', '$2y$10$dPrT4l7TQ.D5rVR/QyJHVeU.qhRtzadZ9PWu7rRfBzidVB1cJwbHK', 'active', NOW()),
(3, 'dr.sharma', 'rajesh.sharma@nexuscare.org', '$2y$10$dPrT4l7TQ.D5rVR/QyJHVeU.qhRtzadZ9PWu7rRfBzidVB1cJwbHK', 'active', NOW()),
(4, 'dr.priya', 'priya.patel@nexuscare.org', '$2y$10$dPrT4l7TQ.D5rVR/QyJHVeU.qhRtzadZ9PWu7rRfBzidVB1cJwbHK', 'active', NOW()),
(5, 'dr.anand', 'anand.kumar@nexuscare.org', '$2y$10$dPrT4l7TQ.D5rVR/QyJHVeU.qhRtzadZ9PWu7rRfBzidVB1cJwbHK', 'active', NOW()),
(6, 'receptionist', 'reception@nexuscare.org', '$2y$10$dPrT4l7TQ.D5rVR/QyJHVeU.qhRtzadZ9PWu7rRfBzidVB1cJwbHK', 'active', NOW()),
(7, 'nurse.sarah', 'nurse.sarah@nexuscare.org', '$2y$10$dPrT4l7TQ.D5rVR/QyJHVeU.qhRtzadZ9PWu7rRfBzidVB1cJwbHK', 'active', NOW()),
(8, 'labtech.vikram', 'lab@nexuscare.org', '$2y$10$dPrT4l7TQ.D5rVR/QyJHVeU.qhRtzadZ9PWu7rRfBzidVB1cJwbHK', 'active', NOW()),
(9, 'pharma.rohit', 'pharmacy@nexuscare.org', '$2y$10$dPrT4l7TQ.D5rVR/QyJHVeU.qhRtzadZ9PWu7rRfBzidVB1cJwbHK', 'active', NOW()),
(10, 'billing.sneha', 'billing@nexuscare.org', '$2y$10$dPrT4l7TQ.D5rVR/QyJHVeU.qhRtzadZ9PWu7rRfBzidVB1cJwbHK', 'active', NOW()),
(11, 'patient.arun', 'arun.verma@example.com', '$2y$10$dPrT4l7TQ.D5rVR/QyJHVeU.qhRtzadZ9PWu7rRfBzidVB1cJwbHK', 'active', NOW()),
(12, 'patient.sunita', 'sunita.rao@example.com', '$2y$10$dPrT4l7TQ.D5rVR/QyJHVeU.qhRtzadZ9PWu7rRfBzidVB1cJwbHK', 'active', NOW());

-- 5. User Role Assignments
INSERT INTO `user_role_assignments` (`user_id`, `role_id`, `assigned_by`) VALUES
(1, 1, 1), -- Super Admin
(2, 2, 1), -- Hospital Admin
(3, 3, 2), -- Dr. Rajesh Sharma (Doctor)
(4, 3, 2), -- Dr. Priya Patel (Doctor)
(5, 3, 2), -- Dr. Anand Kumar (Doctor)
(6, 4, 2), -- Receptionist
(7, 5, 2), -- Nurse Sarah
(8, 6, 2), -- Lab Tech Vikram
(9, 7, 2), -- Pharmacist Rohit
(10, 8, 2), -- Billing Sneha
(11, 9, 6), -- Patient Arun Verma
(12, 9, 6); -- Patient Sunita Rao

-- 6. Departments
INSERT INTO `departments` (`id`, `dept_code`, `name`, `description`, `location`, `phone_extension`, `status`) VALUES
(1, 'EMERG', 'Emergency Department', '24/7 Acute trauma, resuscitation, and critical emergency response', 'Ground Floor, Wing A', '101', 'active'),
(2, 'OPD', 'Outpatient Department', 'General consultations, follow-ups, and ambulatory patient care', 'Ground Floor, Wing B', '102', 'active'),
(3, 'IPD', 'Inpatient Department', 'Wards, acute medical/surgical monitoring, and post-op care', 'Floors 2 & 3', '103', 'active'),
(4, 'GENMED', 'General Medicine', 'Internal medicine, adult illnesses, lifestyle diseases, and checkups', 'First Floor, Suite 101', '104', 'active'),
(5, 'SURG', 'General Surgery', 'Elective and emergency surgical procedures and perioperative care', 'Floor 2, Surgical Wing', '105', 'active'),
(6, 'PED', 'Pediatrics', 'Comprehensive neonatal, child, and adolescent healthcare', 'First Floor, Wing C', '106', 'active'),
(7, 'OBGYN', 'Gynecology and Obstetrics', 'Maternal health, delivery suites, and gynecological surgeries', 'Floor 3, Maternity Wing', '107', 'active'),
(8, 'ORTHO', 'Orthopedics', 'Musculoskeletal disorders, bone fractures, and joint replacement', 'First Floor, Suite 108', '108', 'active'),
(9, 'CARD', 'Cardiology', 'Cardiac care, ECG, echocardiography, and hypertension management', 'First Floor, Heart Center', '109', 'active'),
(10, 'NEURO', 'Neurology', 'Neurological disorders, stroke intervention, and brain health', 'Floor 2, Neuro Suite', '110', 'active'),
(11, 'DERM', 'Dermatology', 'Skin conditions, allergy screenings, and dermatological care', 'Ground Floor, Suite 111', '111', 'active'),
(12, 'OPHTH', 'Ophthalmology', 'Vision testing, cataract evaluation, and eye health', 'Ground Floor, Suite 112', '112', 'active'),
(13, 'RAD', 'Radiology', 'X-Ray, CT Scan, Ultrasound, and diagnostic medical imaging', 'Basement 1, Diagnostic Hub', '113', 'active'),
(14, 'LAB', 'Laboratory', 'Automated hematology, biochemistry, and pathology testing', 'Basement 1, Core Lab', '114', 'active'),
(15, 'PHARM', 'Pharmacy', 'Inpatient and outpatient prescription dispensing and inventory', 'Ground Floor, Main Atrium', '115', 'active');

-- 7. Staff Profiles
INSERT INTO `staff_profiles` (`id`, `user_id`, `employee_id`, `first_name`, `last_name`, `gender`, `phone`, `department_id`, `designation`, `join_date`) VALUES
(1, 1, 'EMP-SA-001', 'Alexander', 'Wright', 'male', '+91 98765 00001', 2, 'Chief Information Officer', '2022-01-15'),
(2, 2, 'EMP-HA-002', 'Meenakshi', 'Sundaram', 'female', '+91 98765 00002', 2, 'Hospital Medical Director', '2022-03-01'),
(3, 6, 'EMP-REC-006', 'Pooja', 'Nair', 'female', '+91 98765 00006', 2, 'Head Receptionist', '2023-02-10'),
(4, 7, 'EMP-NUR-007', 'Sarah', 'Mathew', 'female', '+91 98765 00007', 3, 'Senior Charge Nurse', '2023-04-15'),
(5, 8, 'EMP-LAB-008', 'Vikram', 'Singh', 'male', '+91 98765 00008', 14, 'Senior Lab Technologist', '2023-01-20'),
(6, 9, 'EMP-PH-009', 'Rohit', 'Agarwal', 'male', '+91 98765 00009', 15, 'Chief Pharmacist', '2023-03-12'),
(7, 10, 'EMP-BIL-010', 'Sneha', 'Kapoor', 'female', '+91 98765 00010', 2, 'Senior Billing Executive', '2023-05-18');

-- 8. Doctor Profiles
INSERT INTO `doctor_profiles` (`id`, `user_id`, `doctor_code`, `first_name`, `last_name`, `specialization`, `qualification`, `license_number`, `consultation_fee`, `department_id`, `room_no`, `availability_status`, `avg_consultation_time_mins`) VALUES
(1, 3, 'DOC-CARD-001', 'Rajesh', 'Sharma', 'Interventional Cardiology', 'MBBS, MD (Medicine), DM (Cardiology)', 'MCI-CARD-44120', 800.00, 9, 'Room 109', 'available', 18),
(2, 4, 'DOC-MED-002', 'Priya', 'Patel', 'Internal Medicine & Diabetology', 'MBBS, MD (General Medicine)', 'MCI-MED-55210', 500.00, 4, 'Room 104', 'available', 15),
(3, 5, 'DOC-ORTHO-003', 'Anand', 'Kumar', 'Orthopedic Surgery & Traumatology', 'MBBS, MS (Orthopedics), MCh', 'MCI-ORT-77891', 750.00, 8, 'Room 108', 'available', 20);

-- Doctor secondary affiliations
INSERT INTO `doctor_departments` (`doctor_id`, `department_id`) VALUES
(1, 1), -- Dr Sharma on-call for Emergency
(2, 2), -- Dr Priya on-call for OPD
(3, 1); -- Dr Anand on-call for Emergency trauma

-- Doctor Schedules
INSERT INTO `doctor_availability` (`doctor_id`, `day_of_week`, `start_time`, `end_time`, `slot_duration_minutes`, `max_patients_per_session`, `status`) VALUES
(1, 'Monday', '09:00:00', '14:00:00', 20, 15, 'available'),
(1, 'Tuesday', '09:00:00', '14:00:00', 20, 15, 'available'),
(1, 'Wednesday', '09:00:00', '14:00:00', 20, 15, 'available'),
(1, 'Thursday', '09:00:00', '14:00:00', 20, 15, 'available'),
(1, 'Friday', '09:00:00', '14:00:00', 20, 15, 'available'),
(2, 'Monday', '08:30:00', '13:30:00', 15, 20, 'available'),
(2, 'Tuesday', '08:30:00', '13:30:00', 15, 20, 'available'),
(2, 'Wednesday', '08:30:00', '13:30:00', 15, 20, 'available'),
(2, 'Thursday', '08:30:00', '13:30:00', 15, 20, 'available'),
(2, 'Friday', '08:30:00', '13:30:00', 15, 20, 'available'),
(3, 'Monday', '10:00:00', '15:00:00', 20, 15, 'available'),
(3, 'Wednesday', '10:00:00', '15:00:00', 20, 15, 'available'),
(3, 'Friday', '10:00:00', '15:00:00', 20, 15, 'available');

-- 9. Patient Profiles
INSERT INTO `patient_profiles` (`id`, `user_id`, `patient_id`, `first_name`, `last_name`, `dob`, `gender`, `blood_group`, `phone`, `email`, `address`, `emergency_contact_name`, `emergency_contact_phone`, `allergies`, `medical_history`, `created_at`) VALUES
(1, 11, 'PAT-2026-0001', 'Arun', 'Verma', '1985-06-14', 'male', 'B+', '+91 98111 22334', 'arun.verma@example.com', '45 MG Road, Indiranagar, Bengaluru', 'Sunita Verma (Wife)', '+91 98111 99887', 'Penicillin', 'Mild hypertension since 2021', '2026-01-10 10:00:00'),
(2, 12, 'PAT-2026-0002', 'Sunita', 'Rao', '1992-09-22', 'female', 'O+', '+91 98222 33445', 'sunita.rao@example.com', '12 Lake View Road, Koramangala, Bengaluru', 'Kavita Rao (Mother)', '+91 98222 88776', 'Sulfa drugs', 'Seasonal asthma', '2026-01-15 11:30:00'),
(3, NULL, 'PAT-2026-0003', 'Mohammed', 'Farooq', '1974-03-08', 'male', 'A+', '+91 98333 44556', 'm.farooq@example.com', '88 Commercial Street, Bengaluru', 'Amina Farooq (Daughter)', '+91 98333 77665', 'None', 'Type 2 Diabetes Mellitus, Dyslipidemia', '2026-02-01 09:15:00'),
(4, NULL, 'PAT-2026-0004', 'Anita', 'Desai', '1968-11-30', 'female', 'AB+', '+91 98444 55667', 'anita.desai@example.com', '104 Brigade Gateway, Malleshwaram, Bengaluru', 'Ramesh Desai (Husband)', '+91 98444 66554', 'NSAIDs / Aspirin', 'Coronary artery disease, stent placed 2023', '2026-02-12 14:20:00'),
(5, NULL, 'PAT-2026-0005', 'Vikram', 'Malhotra', '2001-07-19', 'male', 'O-', '+91 98555 66778', 'vikram.m@example.com', '23 HSR Layout Sector 2, Bengaluru', 'Sanjay Malhotra (Father)', '+91 98555 55443', 'None', 'Sports fracture left tibia, under orthopedic rehab', '2026-02-20 16:45:00');

-- 10. Appointments
INSERT INTO `appointments` (`id`, `appointment_number`, `patient_id`, `doctor_id`, `department_id`, `appointment_date`, `appointment_time`, `reason`, `status`, `created_by`, `created_at`) VALUES
(1, 'APT-2026-0001', 1, 1, 9, CURDATE(), '09:30:00', 'Chest heaviness and routine cardiac review', 'checked_in', 6, NOW() - INTERVAL 2 HOUR),
(2, 'APT-2026-0002', 2, 2, 4, CURDATE(), '10:00:00', 'Fever, sore throat and fatigue for 3 days', 'in_consultation', 6, NOW() - INTERVAL 1 HOUR),
(3, 'APT-2026-0003', 3, 2, 4, CURDATE(), '10:30:00', 'Fasting blood glucose followup and prescription refill', 'confirmed', 6, NOW() - INTERVAL 3 HOUR),
(4, 'APT-2026-0004', 4, 1, 9, CURDATE(), '11:00:00', 'Post-stent annual cardiology evaluation', 'confirmed', 6, NOW() - INTERVAL 4 HOUR),
(5, 'APT-2026-0005', 5, 3, 8, CURDATE(), '11:30:00', 'Knee pain and left tibial rehab assessment', 'confirmed', 6, NOW() - INTERVAL 5 HOUR),
(6, 'APT-2026-0006', 1, 2, 4, CURDATE() + INTERVAL 2 DAY, '09:00:00', 'General health checkup', 'pending', 6, NOW());

-- 11. Patient Encounters
INSERT INTO `patient_encounters` (`id`, `encounter_number`, `patient_id`, `appointment_id`, `doctor_id`, `department_id`, `encounter_type`, `status`, `started_at`) VALUES
(1, 'ENC-2026-0001', 1, 1, 1, 9, 'outpatient', 'active', NOW() - INTERVAL 45 MINUTE),
(2, 'ENC-2026-0002', 2, 2, 2, 4, 'outpatient', 'active', NOW() - INTERVAL 20 MINUTE),
(3, 'ENC-2026-0003', 4, NULL, 1, 9, 'inpatient', 'active', NOW() - INTERVAL 2 DAY);

-- 12. Consultations & Diagnoses
INSERT INTO `consultations` (`id`, `encounter_id`, `patient_id`, `doctor_id`, `vitals_bp`, `vitals_pulse`, `vitals_temp`, `vitals_weight`, `vitals_spo2`, `clinical_notes`, `treatment_plan`, `follow_up_date`, `created_at`) VALUES
(1, 3, 4, 1, '138/88', 76, 98.4, 68.5, 98, 'Patient presented with mild exertional angina and shortness of breath. History of coronary stenting 2023. ECG showed sinus rhythm with non-specific ST changes.', 'Continue antiplatelet and statin regimen. Prescribe nitrates for emergency relief. Ordered Lipid Panel, Troponin-I, and 2D Echo.', CURDATE() + INTERVAL 14 DAY, NOW() - INTERVAL 2 DAY);

INSERT INTO `diagnoses` (`id`, `consultation_id`, `patient_id`, `diagnosis_code`, `diagnosis_title`, `diagnosis_type`, `notes`, `created_at`) VALUES
(1, 1, 4, 'I25.10', 'Atherosclerotic heart disease of native coronary artery', 'primary', 'Post-PCI follow-up with exertional symptoms', NOW() - INTERVAL 2 DAY),
(2, 1, 4, 'I10', 'Essential (primary) hypertension', 'secondary', 'Controlled on dual antihypertensives', NOW() - INTERVAL 2 DAY);

-- 13. Laboratory Catalog
INSERT INTO `lab_tests` (`id`, `test_code`, `test_name`, `department_id`, `standard_charge`, `normal_range`, `units`, `description`) VALUES
(1, 'LAB-CBC', 'Complete Blood Count (CBC)', 14, 450.00, 'Hb: 13-17 (M), 12-15 (F)', 'g/dL', 'RBC, WBC, Hemoglobin, Platelets, Hematocrit indices'),
(2, 'LAB-LIPID', 'Comprehensive Lipid Profile', 14, 850.00, 'Total Chol: <200, LDL: <100', 'mg/dL', 'Total Cholesterol, HDL, LDL, VLDL, Triglycerides'),
(3, 'LAB-FBS', 'Fasting Blood Sugar (FBS)', 14, 150.00, '70 - 99', 'mg/dL', 'Fasting blood glucose level after 8-10 hour fast'),
(4, 'LAB-HBA1C', 'Glycated Hemoglobin (HbA1c)', 14, 600.00, '< 5.7 (Normal), 5.7-6.4 (Prediabetes)', '%', '3-month average blood glucose control indicator'),
(5, 'LAB-LFT', 'Liver Function Test (LFT)', 14, 950.00, 'SGPT: <45, SGOT: <40, Bilirubin: <1.2', 'U/L', 'Bilirubin, SGOT, SGPT, ALP, Total Proteins, Albumin'),
(6, 'LAB-RFT', 'Renal Function Test (KFT/RFT)', 14, 750.00, 'Creatinine: 0.6-1.2, Urea: 15-45', 'mg/dL', 'Serum Creatinine, Blood Urea, Electrolytes, Uric Acid'),
(7, 'LAB-TROP', 'Cardiac Troponin-I Quantitative', 14, 1400.00, '< 0.04 (Negative)', 'ng/mL', 'Specific cardiac muscle biomarker for acute myocardial injury'),
(8, 'RAD-XRAY', 'Digital Chest X-Ray PA View', 13, 650.00, 'Cardiothoracic ratio normal', 'Visual', 'Evaluation of lung parenchyma, pleura, and cardiac silhouette');

-- Lab Orders & Results
INSERT INTO `lab_orders` (`id`, `order_number`, `patient_id`, `encounter_id`, `ordered_by_doctor_id`, `priority`, `status`, `clinical_notes`, `created_at`) VALUES
(1, 'LBO-2026-0001', 4, 3, 1, 'urgent', 'completed', 'Rule out acute coronary syndrome; evaluate lipid target', NOW() - INTERVAL 1 DAY),
(2, 'LBO-2026-0002', 1, 1, 1, 'routine', 'processing', 'Routine lipid screening and cardiac check', NOW() - INTERVAL 35 MINUTE);

INSERT INTO `lab_order_items` (`id`, `lab_order_id`, `test_id`, `status`, `result_value`, `reference_range`, `result_notes`, `tested_by`, `verified_by`, `completed_at`) VALUES
(1, 1, 7, 'completed', '0.01', '< 0.04', 'Negative for acute myocardial necrosis', 8, 2, NOW() - INTERVAL 22 HOUR),
(2, 1, 2, 'completed', 'Total Chol: 218, LDL: 132, HDL: 44, TG: 185', 'Total <200, LDL <100', 'Borderline elevated LDL cholesterol', 8, 2, NOW() - INTERVAL 22 HOUR),
(3, 2, 2, 'analyzing', NULL, 'Total <200, LDL <100', 'Sample in centrifuge and autoanalyzer', 8, NULL, NULL);

-- 14. Pharmacy Catalog & Stock
INSERT INTO `medicines` (`id`, `medicine_code`, `name`, `generic_name`, `category`, `unit`, `unit_price`, `stock_quantity`, `reorder_level`, `status`) VALUES
(1, 'MED-PCM-500', 'Paracetamol 500mg (Crocin)', 'Paracetamol', 'Tablet', 'strip (10s)', 35.00, 350, 50, 'active'),
(2, 'MED-AMX-500', 'Amoxicillin & Clavulanate 625mg (Augmentin)', 'Amoxicillin + Clavulanic Acid', 'Tablet', 'strip (6s)', 160.00, 180, 40, 'active'),
(3, 'MED-MET-500', 'Metformin 500mg SR (Glycomet)', 'Metformin Hydrochloride', 'Tablet', 'strip (10s)', 45.00, 240, 50, 'active'),
(4, 'MED-ATO-20', 'Atorvastatin 20mg (Atorva)', 'Atorvastatin Calcium', 'Tablet', 'strip (10s)', 120.00, 195, 30, 'active'),
(5, 'MED-PAN-40', 'Pantoprazole 40mg (Pan-40)', 'Pantoprazole Sodium', 'Tablet', 'strip (10s)', 85.00, 310, 40, 'active'),
(6, 'MED-AZI-500', 'Azithromycin 500mg (Azee)', 'Azithromycin', 'Tablet', 'strip (5s)', 130.00, 12, 30, 'active'), -- Low stock alert!
(7, 'MED-SOR-10', 'Sorbitrate 10mg (Isordil)', 'Isosorbide Dinitrate', 'Tablet', 'strip (10s)', 40.00, 85, 20, 'active'),
(8, 'MED-CEF-1G', 'Ceftriaxone 1g Injection (Monocef)', 'Ceftriaxone Sodium', 'Injection', 'vial', 95.00, 75, 25, 'active');

-- Medicine Batches
INSERT INTO `medicine_batches` (`id`, `medicine_id`, `batch_number`, `expiry_date`, `quantity`, `purchase_cost`, `created_at`) VALUES
(1, 1, 'PCM-2026-A1', '2027-12-31', 350, 22.00, NOW() - INTERVAL 30 DAY),
(2, 2, 'AUG-2026-B4', '2027-08-31', 180, 110.00, NOW() - INTERVAL 20 DAY),
(3, 4, 'ATV-2026-C2', '2027-05-31', 195, 75.00, NOW() - INTERVAL 15 DAY),
(4, 6, 'AZI-2025-E9', '2026-11-15', 12, 85.00, NOW() - INTERVAL 60 DAY); -- Near expiry alert!

-- 15. Prescriptions
INSERT INTO `prescriptions` (`id`, `encounter_id`, `consultation_id`, `patient_id`, `doctor_id`, `notes`, `status`, `created_at`) VALUES
(1, 3, 1, 4, 1, 'Take statin at bedtime. Use Sorbitrate sublingually only if acute chest discomfort occurs.', 'dispensed', NOW() - INTERVAL 2 DAY);

INSERT INTO `prescription_items` (`id`, `prescription_id`, `medicine_id`, `dosage`, `frequency`, `duration_days`, `quantity`, `instructions`) VALUES
(1, 1, 4, '20mg', '0-0-1 (Night)', 30, 3, 'After dinner before sleep'),
(2, 1, 7, '10mg', 'SOS (As needed)', 10, 1, 'Place sublingually under tongue for sudden chest tightness');

-- Pharmacy Orders & Dispensing
INSERT INTO `pharmacy_orders` (`id`, `order_number`, `prescription_id`, `patient_id`, `status`, `total_amount`, `created_at`) VALUES
(1, 'PHO-2026-0001', 1, 4, 'dispensed', 400.00, NOW() - INTERVAL 2 DAY);

INSERT INTO `pharmacy_order_items` (`id`, `pharmacy_order_id`, `medicine_id`, `batch_id`, `quantity`, `unit_price`, `total_price`) VALUES
(1, 1, 4, 3, 3, 120.00, 360.00),
(2, 1, 7, NULL, 1, 40.00, 40.00);

INSERT INTO `pharmacy_dispensing_records` (`id`, `pharmacy_order_id`, `dispensed_by`, `dispensing_notes`, `dispensed_at`) VALUES
(1, 1, 9, 'Dispensed with proper dosage counseling on bedtime statin adherence.', NOW() - INTERVAL 2 DAY);

-- 16. BedMatrix: Wards, Rooms & Beds
INSERT INTO `wards` (`id`, `ward_code`, `name`, `ward_type`, `care_level`, `capacity`, `gender_designation`, `description`) VALUES
(1, 'ICU-CRIT', 'Intensive Coronary Care Unit (ICCU)', 'icu', 'critical', 6, 'all', 'Advanced continuous hemodynamic telemetry, mechanical ventilators, invasive arterial monitoring'),
(2, 'WARD-MGEN', 'Male General Ward', 'general', 'standard', 12, 'male_only', 'Post-operative and general medical recovery ward for adult male patients'),
(3, 'WARD-FGEN', 'Female General Ward', 'general', 'standard', 12, 'female_only', 'Inpatient medical and post-surgical recovery for adult female patients'),
(4, 'WARD-PED', 'Pediatric Care Ward', 'pediatric', 'intermediate', 8, 'pediatric', 'Child-friendly recovery rooms with 24/7 dedicated pediatric nursing'),
(5, 'WARD-POSTOP', 'Surgical Post-Op Recovery', 'post_op', 'intermediate', 10, 'all', 'Close surgical observation following major orthopedics and general surgeries');

-- Rooms
INSERT INTO `rooms` (`id`, `ward_id`, `room_number`, `room_type`, `standard_rate_per_day`) VALUES
(1, 1, 'ICU-101', 'single', 5000.00),
(2, 1, 'ICU-102', 'single', 5000.00),
(3, 2, 'MG-201', 'shared', 1200.00),
(4, 2, 'MG-202', 'shared', 1200.00),
(5, 3, 'FG-301', 'shared', 1200.00),
(6, 4, 'PED-401', 'double', 1800.00),
(7, 5, 'PO-501', 'single', 2500.00);

-- Beds (Statuses: available, occupied, reserved, cleaning, out_of_service)
INSERT INTO `beds` (`id`, `room_id`, `ward_id`, `bed_number`, `bed_type`, `care_level`, `status`, `daily_rate`, `created_at`) VALUES
-- ICU
(1, 1, 1, 'BED-ICU-01', 'ventilator_enabled', 'critical', 'occupied', 5500.00, NOW()),
(2, 1, 1, 'BED-ICU-02', 'ventilator_enabled', 'critical', 'available', 5500.00, NOW()),
(3, 2, 1, 'BED-ICU-03', 'icu_bed', 'critical', 'cleaning', 5000.00, NOW()),
(4, 2, 1, 'BED-ICU-04', 'icu_bed', 'critical', 'reserved', 5000.00, NOW()),
-- Male General
(5, 3, 2, 'BED-MG-01', 'standard', 'standard', 'available', 1200.00, NOW()),
(6, 3, 2, 'BED-MG-02', 'standard', 'standard', 'occupied', 1200.00, NOW()),
(7, 3, 2, 'BED-MG-03', 'standard', 'standard', 'available', 1200.00, NOW()),
(8, 4, 2, 'BED-MG-04', 'standard', 'standard', 'out_of_service', 1200.00, NOW()),
-- Female General
(9, 5, 3, 'BED-FG-01', 'standard', 'standard', 'available', 1200.00, NOW()),
(10, 5, 3, 'BED-FG-02', 'standard', 'standard', 'available', 1200.00, NOW()),
-- Post-Op
(11, 7, 5, 'BED-PO-01', 'standard', 'intermediate', 'available', 2500.00, NOW()),
(12, 7, 5, 'BED-PO-02', 'ventilator_enabled', 'critical', 'available', 3000.00, NOW());

-- Bed Resources
INSERT INTO `bed_resources` (`id`, `bed_id`, `resource_name`, `resource_type`, `status`) VALUES
(1, 1, 'Hamilton G5 Mechanical Ventilator', 'Ventilator', 'operational'),
(2, 1, 'Mindray BeneVision Multi-Parameter Monitor', 'Cardiac Monitor', 'operational'),
(3, 1, 'High Flow Oxygen Pipeline Port', 'Oxygen Port', 'operational'),
(4, 2, 'Dräger Evita Infinity V500 Ventilator', 'Ventilator', 'operational'),
(5, 6, 'Wall Oxygen Central Port', 'Oxygen Port', 'operational');

-- Active Bed Assignments
INSERT INTO `bed_assignments` (`id`, `bed_id`, `patient_id`, `encounter_id`, `admission_date`, `discharge_date`, `status`, `assigned_by`, `clinical_reason`) VALUES
(1, 1, 4, 3, NOW() - INTERVAL 2 DAY, NULL, 'active', 2, 'Critical post-stent hemodynamic monitoring and chest observation'),
(2, 6, 3, 2, NOW() - INTERVAL 1 DAY, NULL, 'active', 2, 'Hyperglycemic observation and insulin titration');

-- 17. QueueFlow Entries
INSERT INTO `queue_entries` (`id`, `token_number`, `patient_id`, `appointment_id`, `doctor_id`, `department_id`, `queue_date`, `check_in_time`, `consultation_start_time`, `completion_time`, `status`, `priority_level`, `priority_reason`, `estimated_wait_time_minutes`, `created_by`) VALUES
(1, 101, 2, 2, 2, 4, CURDATE(), NOW() - INTERVAL 25 MINUTE, NOW() - INTERVAL 10 MINUTE, NULL, 'in_consultation', 'normal', NULL, 0, 6),
(2, 102, 1, 1, 1, 9, CURDATE(), NOW() - INTERVAL 20 MINUTE, NULL, NULL, 'called', 'urgent', 'Exertional chest discomfort reported during vitals triage', 5, 6),
(3, 103, 3, 3, 2, 4, CURDATE(), NOW() - INTERVAL 15 MINUTE, NULL, NULL, 'waiting', 'normal', NULL, 15, 6),
(4, 104, 5, 5, 3, 8, CURDATE(), NOW() - INTERVAL 10 MINUTE, NULL, NULL, 'waiting', 'normal', NULL, 20, 6);

INSERT INTO `queue_priority_events` (`id`, `queue_entry_id`, `old_priority`, `new_priority`, `changed_by`, `reason`, `created_at`) VALUES
(1, 2, 'normal', 'urgent', 6, 'Nurse reported elevated BP 142/92 with chest tightness at check-in counter', NOW() - INTERVAL 18 MINUTE);

-- 18. CareTrail: Patient Journey Events
INSERT INTO `patient_journey_events` (`id`, `patient_id`, `encounter_id`, `event_type`, `event_title`, `event_description`, `department_id`, `source_table`, `source_id`, `severity`, `created_by`, `event_time`) VALUES
-- Patient 4 Journey
(1, 4, 3, 'registration', 'Patient Enrolled in NexusCare', 'Demographics, medical history, and emergency contact registered.', 2, 'patient_profiles', 4, 'info', 6, NOW() - INTERVAL 2 DAY - INTERVAL 2 HOUR),
(2, 4, 3, 'check_in', 'Emergency Triaged & Checked-In', 'Patient arrived at Emergency complaining of exertional chest pain.', 1, 'patient_encounters', 3, 'warning', 6, NOW() - INTERVAL 2 DAY - INTERVAL 1 HOUR),
(3, 4, 3, 'admitted', 'Inpatient Admission to ICCU', 'Admitted to Intensive Coronary Care Unit under Dr. Rajesh Sharma.', 9, 'bed_assignments', 1, 'critical', 2, NOW() - INTERVAL 2 DAY),
(4, 4, 3, 'bed_assigned', 'Bed Matrix Allocation: BED-ICU-01', 'Assigned ventilator-enabled bed with 24/7 cardiac telemetry.', 1, 'beds', 1, 'info', 7, NOW() - INTERVAL 2 DAY),
(5, 4, 3, 'consultation_completed', 'Cardiology Clinical Evaluation', 'Dr. Sharma completed evaluation; ordered urgent cardiac biomarkers and ECG.', 9, 'consultations', 1, 'info', 3, NOW() - INTERVAL 2 DAY + INTERVAL 30 MINUTE),
(6, 4, 3, 'lab_ordered', 'Urgent Cardiac Lab Orders', 'Troponin-I and Comprehensive Lipid Profile dispatched to Core Lab.', 14, 'lab_orders', 1, 'warning', 3, NOW() - INTERVAL 1 DAY - INTERVAL 2 HOUR),
(7, 4, 3, 'lab_completed', 'Cardiac Biomarkers Completed', 'Troponin-I negative (0.01 ng/mL). Lipid panel shows LDL 132 mg/dL.', 14, 'lab_orders', 1, 'success', 8, NOW() - INTERVAL 22 HOUR),
(8, 4, 3, 'prescription_issued', 'Cardiology Prescription Generated', 'Prescribed Atorvastatin 20mg and emergency Sorbitrate.', 9, 'prescriptions', 1, 'info', 3, NOW() - INTERVAL 2 DAY),
(9, 4, 3, 'medicine_dispensed', 'Pharmacy Medication Dispensed', 'Chief Pharmacist Rohit Agarwal dispensed verified medicines.', 15, 'pharmacy_orders', 1, 'success', 9, NOW() - INTERVAL 2 DAY + INTERVAL 1 HOUR),
-- Patient 1 Journey (Today)
(10, 1, 1, 'appointment_booked', 'Cardiology Consultation Booked', 'Appointment APT-2026-0001 booked for Dr. Rajesh Sharma.', 9, 'appointments', 1, 'info', 6, NOW() - INTERVAL 2 HOUR),
(11, 1, 1, 'check_in', 'Checked In at Reception Desk', 'Queue token #102 issued. Patient triaged to Waiting Lounge A.', 9, 'queue_entries', 2, 'info', 6, NOW() - INTERVAL 20 MINUTE),
(12, 1, 1, 'lab_ordered', 'Lipid Profile Lab Order Dispatched', 'Dr. Sharma ordered routine lipid check.', 14, 'lab_orders', 2, 'info', 3, NOW() - INTERVAL 35 MINUTE);

-- 19. FlowLens: Bottleneck Alerts
INSERT INTO `flowlens_alerts` (`id`, `alert_code`, `alert_type`, `department_id`, `severity`, `triggering_metric`, `observed_value`, `threshold_value`, `alert_title`, `alert_description`, `suggested_action`, `status`, `created_at`, `resolved_by`, `resolution_notes`, `resolved_at`) VALUES
(1, 'FL-2026-001', 'bed_occupancy_critical', 1, 'critical', 'Bed Occupancy Percentage', 83.33, 80.00, 'ICCU High Bed Saturation Alert', 'Intensive Coronary Care Unit occupancy reached 83.33% (5 of 6 active critical beds occupied or reserved).', 'Review clinically stable patients for transfer to step-down intermediate post-op beds.', 'active', NOW() - INTERVAL 3 HOUR, NULL, NULL, NULL),
(2, 'FL-2026-002', 'queue_wait_time', 4, 'high', 'Average Queue Wait Time (mins)', 32.50, 25.00, 'General Medicine Consultation Queue Delay', 'Wait time in General Medicine OPD exceeds acceptable threshold of 25 minutes due to seasonal fever patient influx.', 'Deploy second attending physician or activate relief OPD consultation room.', 'acknowledged', NOW() - INTERVAL 1 HOUR, NULL, NULL, NULL),
(3, 'FL-2026-003', 'lab_delay', 14, 'medium', 'Lab Turnaround Time (mins)', 75.00, 60.00, 'Biochemistry Analyzer Batch Queue Delay', 'Electrolyte and lipid test processing backlog currently at 75 minutes.', 'Prioritize urgent STAT cardiology orders and balance autoanalyzer reagent racks.', 'resolved', NOW() - INTERVAL 5 HOUR, 2, 'Added secondary laboratory technician on duty; turnaround reduced to 38 minutes.', NOW() - INTERVAL 1 HOUR);

INSERT INTO `flowlens_alert_history` (`id`, `alert_id`, `action_taken`, `action_by`, `notes`, `created_at`) VALUES
(1, 2, 'acknowledged', 2, 'Medical Director notified; checking physician roster.', NOW() - INTERVAL 45 MINUTE),
(2, 3, 'resolved', 2, 'Reagent racks adjusted; lab turnaround back under 40 mins.', NOW() - INTERVAL 1 HOUR);

-- Operational Metrics Snapshot
INSERT INTO `operational_metrics` (`metric_date`, `department_id`, `avg_wait_time_minutes`, `avg_consultation_time_minutes`, `total_patients_served`, `bed_occupancy_rate`, `pending_lab_orders_count`) VALUES
(CURDATE() - INTERVAL 1 DAY, 4, 22.50, 14.80, 42, 65.00, 4),
(CURDATE() - INTERVAL 1 DAY, 9, 18.20, 19.50, 28, 83.30, 2),
(CURDATE() - INTERVAL 1 DAY, 8, 16.00, 18.00, 24, 50.00, 1),
(CURDATE(), 4, 32.50, 15.20, 18, 70.00, 5),
(CURDATE(), 9, 19.00, 18.50, 12, 83.33, 3);

-- 20. Billing: Invoices, Items & Payments
INSERT INTO `invoices` (`id`, `invoice_number`, `patient_id`, `encounter_id`, `subtotal`, `discount_amount`, `tax_amount`, `total_amount`, `paid_amount`, `balance_amount`, `status`, `created_by`, `finalized_at`, `created_at`) VALUES
(1, 'INV-2026-0001', 4, 3, 14250.00, 500.00, 687.50, 14437.50, 10000.00, 4437.50, 'partially_paid', 10, NOW() - INTERVAL 1 DAY, NOW() - INTERVAL 1 DAY),
(2, 'INV-2026-0002', 1, 1, 1650.00, 0.00, 82.50, 1732.50, 1732.50, 0.00, 'paid', 10, NOW() - INTERVAL 1 HOUR, NOW() - INTERVAL 1 HOUR);

INSERT INTO `invoice_items` (`id`, `invoice_id`, `item_type`, `item_description`, `quantity`, `unit_price`, `total_price`, `reference_id`) VALUES
(1, 1, 'bed_charge', 'ICCU Bed Day Rate (BED-ICU-01)', 2, 5500.00, 11000.00, 1),
(2, 1, 'consultation', 'Senior Cardiologist Inpatient Rounds', 2, 800.00, 1600.00, 1),
(3, 1, 'lab_test', 'Cardiac Biomarker Troponin-I + Lipid Panel', 1, 1250.00, 1250.00, 1),
(4, 1, 'pharmacy', 'Dispensed Cardiac Prescription Meds', 1, 400.00, 400.00, 1),
(5, 2, 'consultation', 'Cardiology Consultation Fee (Dr. Rajesh Sharma)', 1, 800.00, 800.00, 1),
(6, 2, 'lab_test', 'Comprehensive Lipid Profile Panel', 1, 850.00, 850.00, 2);

INSERT INTO `payments` (`id`, `payment_number`, `invoice_id`, `patient_id`, `amount`, `payment_method`, `transaction_reference`, `notes`, `received_by`, `payment_date`) VALUES
(1, 'PAY-2026-0001', 1, 4, 10000.00, 'insurance', 'STAR-HLTH-CLM-88910', 'Interim cashless hospitalization pre-auth deposit', 10, NOW() - INTERVAL 1 DAY),
(2, 'PAY-2026-0002', 2, 1, 1732.50, 'upi', 'UPI-REF-9928172654', 'Full payment for OPD consultation & routine lab test', 10, NOW() - INTERVAL 50 MINUTE);

-- 21. TrustLedger: Audit Logs & Security
INSERT INTO `audit_logs` (`id`, `user_id`, `role_name`, `action_type`, `entity_type`, `entity_id`, `ip_address`, `user_agent`, `description`, `created_at`) VALUES
(1, 1, 'super_admin', 'LOGIN', 'UserSession', 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', 'Super Admin Alexander Wright logged in securely.', NOW() - INTERVAL 6 HOUR),
(2, 6, 'receptionist', 'CREATE', 'PatientProfile', 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', 'Registered new patient Arun Verma (PAT-2026-0001).', NOW() - INTERVAL 5 HOUR),
(3, 6, 'receptionist', 'CREATE', 'Appointment', 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', 'Booked appointment APT-2026-0001 for Dr. Rajesh Sharma.', NOW() - INTERVAL 2 HOUR),
(4, 6, 'receptionist', 'OVERRIDE', 'QueuePriority', 2, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', 'Queue priority elevated to urgent: Chest tightness reported at triage desk.', NOW() - INTERVAL 18 MINUTE),
(5, 3, 'doctor', 'CREATE', 'Prescription', 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', 'Issued prescription for Patient Anita Desai (4).', NOW() - INTERVAL 2 DAY),
(6, 9, 'pharmacist', 'STATUS_CHANGE', 'PharmacyOrder', 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', 'Dispensed medication and updated stock inventory for Atorvastatin.', NOW() - INTERVAL 2 DAY),
(7, 2, 'hospital_admin', 'STATUS_CHANGE', 'FlowLensAlert', 3, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', 'Resolved Biochemistry Analyzer delay alert with extra lab personnel.', NOW() - INTERVAL 1 HOUR);

INSERT INTO `login_attempts` (`username_or_email`, `ip_address`, `user_agent`, `status`, `attempt_time`) VALUES
('superadmin@nexuscare.org', '127.0.0.1', 'Mozilla/5.0 Chrome/120.0', 'success', NOW() - INTERVAL 6 HOUR),
('admin@nexuscare.org', '127.0.0.1', 'Mozilla/5.0 Chrome/120.0', 'success', NOW() - INTERVAL 4 HOUR),
('unknown_hacker@test.com', '192.168.1.105', 'Python-urllib/3.9', 'failed', NOW() - INTERVAL 3 HOUR);

SET FOREIGN_KEY_CHECKS = 1;
