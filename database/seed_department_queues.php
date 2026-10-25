<?php
/**
 * KVM Hospital Management System — NexusCare
 * Seeder: Department-Wise Patient Queues (9 Departments x 5 Patients = 45 Queue Records)
 *
 * Departments:
 * 1. General Medicine (Dept ID 4)
 * 2. Cardiology (Dept ID 9)
 * 3. Orthopedics (Dept ID 8)
 * 4. Pediatrics (Dept ID 6)
 * 5. Neurology (Dept ID 10)
 * 6. Emergency (Dept ID 1)
 * 7. Dermatology (Dept ID 11)
 * 8. ENT (Dept ID 23)
 * 9. Obstetrics & Gynecology (Dept ID 7)
 *
 * Idempotent: Can be run multiple times safely without generating duplicate tokens.
 */

declare(strict_types=1);

require_once __DIR__ . '/../app/config/database.php';

function seed_department_queues(?PDO $db = null, bool $quiet = false): array {
    $db = $db ?? get_db();
    $today = date('Y-m-d');

    $log = function(string $msg) use ($quiet) {
        if (!$quiet) echo $msg . "\n";
    };

    $log("=== SEEDING DEPARTMENT-WISE PATIENT QUEUES ===");
    $log("Target: 9 Departments x 5 Patients = 45 Queue Records for Date: {$today}");

    // Target 9 departments definition
    $departmentsConfig = [
        [
            'key' => 'general_medicine',
            'name' => 'General Medicine',
            'dept_id' => 4,
            'token_start' => 101,
            'doc_index' => 0, // primary doctor index
            'patients' => [
                ['name' => 'Arjun Kumar', 'age' => 42, 'gender' => 'male', 'phone' => '+91 98401 11201', 'priority' => 'emergency', 'status' => 'in_consultation', 'reason' => 'Acute chest tightness and high pyrexia 103F', 'wait' => 0, 'doc_offset' => 0, 'time' => '08:15:00'],
                ['name' => 'Saranya Devi', 'age' => 36, 'gender' => 'female', 'phone' => '+91 98401 11202', 'priority' => 'urgent', 'status' => 'called', 'reason' => 'Acute severe asthmatic wheeze SpO2 93%', 'wait' => 5, 'doc_offset' => 0, 'time' => '08:30:00'],
                ['name' => 'Muthuvel K.', 'age' => 58, 'gender' => 'male', 'phone' => '+91 98401 11203', 'priority' => 'normal', 'status' => 'waiting', 'reason' => 'Type-2 diabetes insulin adjustment review', 'wait' => 15, 'doc_offset' => 1, 'time' => '08:45:00'],
                ['name' => 'Kavitha Rani', 'age' => 29, 'gender' => 'female', 'phone' => '+91 98401 11204', 'priority' => 'normal', 'status' => 'waiting', 'reason' => 'Persistent hemipterous migraine evaluation', 'wait' => 30, 'doc_offset' => 1, 'time' => '09:00:00'],
                ['name' => 'Senthil Kumar', 'age' => 51, 'gender' => 'male', 'phone' => '+91 98401 11205', 'priority' => 'normal', 'status' => 'waiting', 'reason' => 'Hypertension maintenance medication review', 'wait' => 45, 'doc_offset' => 0, 'time' => '09:15:00']
            ]
        ],
        [
            'key' => 'cardiology',
            'name' => 'Cardiology',
            'dept_id' => 9,
            'token_start' => 201,
            'patients' => [
                ['name' => 'Karthik Raj', 'age' => 54, 'gender' => 'male', 'phone' => '+91 98402 22301', 'priority' => 'emergency', 'status' => 'in_consultation', 'reason' => 'Acute coronary syndrome ST-elevation triage', 'wait' => 0, 'doc_offset' => 0, 'time' => '08:20:00'],
                ['name' => 'Nandhini V.', 'age' => 48, 'gender' => 'female', 'phone' => '+91 98402 22302', 'priority' => 'urgent', 'status' => 'called', 'reason' => 'Paroxysmal supraventricular tachycardia with palpitations', 'wait' => 5, 'doc_offset' => 0, 'time' => '08:35:00'],
                ['name' => 'Balaji S.', 'age' => 62, 'gender' => 'male', 'phone' => '+91 98402 22303', 'priority' => 'normal', 'status' => 'waiting', 'reason' => 'Post-PTCA 6-month coronary angiogram review', 'wait' => 20, 'doc_offset' => 1, 'time' => '08:50:00'],
                ['name' => 'Thenmozhi M.', 'age' => 41, 'gender' => 'female', 'phone' => '+91 98402 22304', 'priority' => 'normal', 'status' => 'waiting', 'reason' => 'Transthoracic echocardiogram follow-up', 'wait' => 35, 'doc_offset' => 1, 'time' => '09:05:00'],
                ['name' => 'Gokul Krishnan', 'age' => 37, 'gender' => 'male', 'phone' => '+91 98402 22305', 'priority' => 'normal', 'status' => 'waiting', 'reason' => 'Dyslipidemia & treadmill stress test evaluation', 'wait' => 50, 'doc_offset' => 0, 'time' => '09:20:00']
            ]
        ],
        [
            'key' => 'orthopedics',
            'name' => 'Orthopedics',
            'dept_id' => 8,
            'token_start' => 301,
            'patients' => [
                ['name' => 'Murugan P.', 'age' => 45, 'gender' => 'male', 'phone' => '+91 98403 33401', 'priority' => 'urgent', 'status' => 'in_consultation', 'reason' => 'Colles fracture right wrist immobilization check', 'wait' => 0, 'doc_offset' => 0, 'time' => '08:25:00'],
                ['name' => 'Yazhini S.', 'age' => 31, 'gender' => 'female', 'phone' => '+91 98403 33402', 'priority' => 'urgent', 'status' => 'called', 'reason' => 'Anterior cruciate ligament tear acute effusion', 'wait' => 8, 'doc_offset' => 0, 'time' => '08:40:00'],
                ['name' => 'Vigneshwaran R.', 'age' => 26, 'gender' => 'male', 'phone' => '+91 98403 33403', 'priority' => 'normal', 'status' => 'waiting', 'reason' => 'Meniscal repair post-operative suture removal', 'wait' => 15, 'doc_offset' => 1, 'time' => '08:55:00'],
                ['name' => 'Revathi K.', 'age' => 60, 'gender' => 'female', 'phone' => '+91 98403 33404', 'priority' => 'normal', 'status' => 'waiting', 'reason' => 'Bilateral knee severe osteoarthritis joint injection review', 'wait' => 30, 'doc_offset' => 1, 'time' => '09:10:00'],
                ['name' => 'Saravanan M.', 'age' => 52, 'gender' => 'male', 'phone' => '+91 98403 33405', 'priority' => 'normal', 'status' => 'waiting', 'reason' => 'Lumbar canal stenosis rehabilitation review', 'wait' => 45, 'doc_offset' => 0, 'time' => '09:25:00']
            ]
        ],
        [
            'key' => 'pediatrics',
            'name' => 'Pediatrics',
            'dept_id' => 6,
            'token_start' => 401,
            'patients' => [
                ['name' => 'Master Kavin Anand', 'age' => 6, 'gender' => 'male', 'phone' => '+91 98404 44501', 'priority' => 'emergency', 'status' => 'in_consultation', 'reason' => 'Febrile convulsion episode at school', 'wait' => 0, 'doc_offset' => 0, 'time' => '08:30:00'],
                ['name' => 'Baby Dharshini', 'age' => 3, 'gender' => 'female', 'phone' => '+91 98404 44502', 'priority' => 'urgent', 'status' => 'called', 'reason' => 'Acute viral croup with barking cough and stridor', 'wait' => 5, 'doc_offset' => 0, 'time' => '08:45:00'],
                ['name' => 'Master Vetrivel', 'age' => 8, 'gender' => 'male', 'phone' => '+91 98404 44503', 'priority' => 'normal', 'status' => 'waiting', 'reason' => 'Pre-school mandatory vaccination and booster', 'wait' => 15, 'doc_offset' => 1, 'time' => '09:00:00'],
                ['name' => 'Baby Ananya', 'age' => 2, 'gender' => 'female', 'phone' => '+91 98404 44504', 'priority' => 'normal', 'status' => 'waiting', 'reason' => 'Toddler developmental milestone & nutrition check', 'wait' => 30, 'doc_offset' => 1, 'time' => '09:15:00'],
                ['name' => 'Master Dhanush', 'age' => 11, 'gender' => 'male', 'phone' => '+91 98404 44505', 'priority' => 'normal', 'status' => 'waiting', 'reason' => 'Childhood allergic bronchospasm follow-up', 'wait' => 40, 'doc_offset' => 0, 'time' => '09:30:00']
            ]
        ],
        [
            'key' => 'neurology',
            'name' => 'Neurology',
            'dept_id' => 10,
            'token_start' => 501,
            'patients' => [
                ['name' => 'Sundaramurthy N.', 'age' => 66, 'gender' => 'male', 'phone' => '+91 98405 55601', 'priority' => 'emergency', 'status' => 'in_consultation', 'reason' => 'Sudden onset facial droop and right arm weakness', 'wait' => 0, 'doc_offset' => 0, 'time' => '08:10:00'],
                ['name' => 'Meenakshi Sundaram', 'age' => 53, 'gender' => 'female', 'phone' => '+91 98405 55602', 'priority' => 'urgent', 'status' => 'called', 'reason' => 'Intractable trigeminal neuralgia refractory to meds', 'wait' => 10, 'doc_offset' => 0, 'time' => '08:25:00'],
                ['name' => 'Vetriselvan T.', 'age' => 47, 'gender' => 'male', 'phone' => '+91 98405 55603', 'priority' => 'normal', 'status' => 'waiting', 'reason' => 'Diabetic sensorimotor polyneuropathy review', 'wait' => 20, 'doc_offset' => 1, 'time' => '08:40:00'],
                ['name' => 'Karpagam R.', 'age' => 59, 'gender' => 'female', 'phone' => '+91 98405 55604', 'priority' => 'normal', 'status' => 'waiting', 'reason' => 'Parkinsonism resting tremor & levodopa titration', 'wait' => 35, 'doc_offset' => 1, 'time' => '08:55:00'],
                ['name' => 'Ezhilarasan M.', 'age' => 39, 'gender' => 'male', 'phone' => '+91 98405 55605', 'priority' => 'normal', 'status' => 'waiting', 'reason' => 'Video EEG report review for seizure threshold', 'wait' => 50, 'doc_offset' => 0, 'time' => '09:10:00']
            ]
        ],
        [
            'key' => 'emergency',
            'name' => 'Emergency',
            'dept_id' => 1,
            'token_start' => 601,
            'patients' => [
                ['name' => 'Natarajan K.', 'age' => 49, 'gender' => 'male', 'phone' => '+91 98406 66701', 'priority' => 'emergency', 'status' => 'in_consultation', 'reason' => 'Blunt abdominal trauma and hemoperitoneum alert', 'wait' => 0, 'doc_offset' => 0, 'time' => '08:00:00'],
                ['name' => 'Lavanya P.', 'age' => 33, 'gender' => 'female', 'phone' => '+91 98406 66702', 'priority' => 'emergency', 'status' => 'called', 'reason' => 'Severe acute systemic anaphylaxis following wasp sting', 'wait' => 0, 'doc_offset' => 0, 'time' => '08:15:00'],
                ['name' => 'Kumaran S.', 'age' => 28, 'gender' => 'male', 'phone' => '+91 98406 66703', 'priority' => 'urgent', 'status' => 'waiting', 'reason' => 'Deep palmar glass laceration requiring digital repair', 'wait' => 10, 'doc_offset' => 1, 'time' => '08:30:00'],
                ['name' => 'Vijay Anand', 'age' => 44, 'gender' => 'male', 'phone' => '+91 98406 66704', 'priority' => 'urgent', 'status' => 'waiting', 'reason' => 'Right lower quadrant guarding acute appendicitis suspicion', 'wait' => 15, 'doc_offset' => 1, 'time' => '08:45:00'],
                ['name' => 'Radhika M.', 'age' => 38, 'gender' => 'female', 'phone' => '+91 98406 66705', 'priority' => 'normal', 'status' => 'waiting', 'reason' => 'Partial thickness domestic scald burn left forearm', 'wait' => 25, 'doc_offset' => 0, 'time' => '09:00:00']
            ]
        ],
        [
            'key' => 'dermatology',
            'name' => 'Dermatology',
            'dept_id' => 11,
            'token_start' => 701,
            'patients' => [
                ['name' => 'Anbarasan K.', 'age' => 35, 'gender' => 'male', 'phone' => '+91 98407 77801', 'priority' => 'urgent', 'status' => 'in_consultation', 'reason' => 'Acute widespread drug eruption with angioedema', 'wait' => 0, 'doc_offset' => 0, 'time' => '08:20:00'],
                ['name' => 'Malathi S.', 'age' => 42, 'gender' => 'female', 'phone' => '+91 98407 77802', 'priority' => 'urgent', 'status' => 'called', 'reason' => 'Severe generalized plaque psoriasis flare', 'wait' => 10, 'doc_offset' => 0, 'time' => '08:35:00'],
                ['name' => 'Aravind Kumar', 'age' => 27, 'gender' => 'male', 'phone' => '+91 98407 77803', 'priority' => 'normal', 'status' => 'waiting', 'reason' => 'Recalcitrant cystic acne isotretinoin monitoring', 'wait' => 15, 'doc_offset' => 1, 'time' => '08:50:00'],
                ['name' => 'Pavithra R.', 'age' => 30, 'gender' => 'female', 'phone' => '+91 98407 77804', 'priority' => 'normal', 'status' => 'waiting', 'reason' => 'Alopecia universalis scalp trichoscopy follow-up', 'wait' => 30, 'doc_offset' => 1, 'time' => '09:05:00'],
                ['name' => 'Shanmugam B.', 'age' => 56, 'gender' => 'male', 'phone' => '+91 98407 77805', 'priority' => 'normal', 'status' => 'waiting', 'reason' => 'Hyperkeratotic hand eczema barrier review', 'wait' => 45, 'doc_offset' => 0, 'time' => '09:20:00']
            ]
        ],
        [
            'key' => 'ent',
            'name' => 'ENT',
            'dept_id' => 23,
            'token_start' => 801,
            'patients' => [
                ['name' => 'Boopathi R.', 'age' => 39, 'gender' => 'male', 'phone' => '+91 98408 88901', 'priority' => 'emergency', 'status' => 'in_consultation', 'reason' => 'Uncontrolled posterior epistaxis requiring anterior/posterior pack', 'wait' => 0, 'doc_offset' => 0, 'time' => '08:15:00'],
                ['name' => 'Hema Malini', 'age' => 46, 'gender' => 'female', 'phone' => '+91 98408 88902', 'priority' => 'urgent', 'status' => 'called', 'reason' => 'Right peritonsillar abscess with trismus and hot potato voice', 'wait' => 8, 'doc_offset' => 0, 'time' => '08:30:00'],
                ['name' => 'Vasanth Kumar', 'age' => 32, 'gender' => 'male', 'phone' => '+91 98408 88903', 'priority' => 'normal', 'status' => 'waiting', 'reason' => 'Severe nasal septum deviation with chronic sleep apnea', 'wait' => 20, 'doc_offset' => 1, 'time' => '08:45:00'],
                ['name' => 'Subhashini V.', 'age' => 51, 'gender' => 'female', 'phone' => '+91 98408 88904', 'priority' => 'normal', 'status' => 'waiting', 'reason' => 'Benign paroxysmal positional vertigo Dix-Hallpike testing', 'wait' => 35, 'doc_offset' => 1, 'time' => '09:00:00'],
                ['name' => 'Thangaraj S.', 'age' => 63, 'gender' => 'male', 'phone' => '+91 98408 88905', 'priority' => 'normal', 'status' => 'waiting', 'reason' => 'Bilateral progressive sensorineural hearing aid calibration', 'wait' => 50, 'doc_offset' => 0, 'time' => '09:15:00']
            ]
        ],
        [
            'key' => 'obstetrics_gynecology',
            'name' => 'Obstetrics & Gynecology',
            'dept_id' => 7,
            'token_start' => 901,
            'patients' => [
                ['name' => 'Keerthana S.', 'age' => 28, 'gender' => 'female', 'phone' => '+91 98409 99001', 'priority' => 'emergency', 'status' => 'in_consultation', 'reason' => 'Primigravida active stage-1 labor 38 weeks gestation', 'wait' => 0, 'doc_offset' => 0, 'time' => '08:05:00'],
                ['name' => 'Sowmya R.', 'age' => 32, 'gender' => 'female', 'phone' => '+91 98409 99002', 'priority' => 'urgent', 'status' => 'called', 'reason' => 'Severe hyperemesis gravidarum with ketonuria & dehydration', 'wait' => 5, 'doc_offset' => 0, 'time' => '08:20:00'],
                ['name' => 'Divya Srinivasan', 'age' => 26, 'gender' => 'female', 'phone' => '+91 98409 99003', 'priority' => 'normal', 'status' => 'waiting', 'reason' => 'Second trimester target anomaly ultrasound review', 'wait' => 15, 'doc_offset' => 1, 'time' => '08:35:00'],
                ['name' => 'Vanitha M.', 'age' => 34, 'gender' => 'female', 'phone' => '+91 98409 99004', 'priority' => 'normal', 'status' => 'waiting', 'reason' => 'Third trimester gestational diabetes profile review', 'wait' => 30, 'doc_offset' => 1, 'time' => '08:50:00'],
                ['name' => 'Sandhya K.', 'age' => 40, 'gender' => 'female', 'phone' => '+91 98409 99005', 'priority' => 'normal', 'status' => 'waiting', 'reason' => 'Symptomatic intramural uterine fibroid management', 'wait' => 45, 'doc_offset' => 0, 'time' => '09:05:00']
            ]
        ]
    ];

    $totalCreated = 0;
    $totalUpdated = 0;

    foreach ($departmentsConfig as $deptConfig) {
        $deptId = $deptConfig['dept_id'];
        $deptName = $deptConfig['name'];

        // Get doctors for this department
        $docStmt = $db->prepare("SELECT id, first_name, last_name, room_no FROM doctor_profiles WHERE department_id = ? ORDER BY id ASC");
        $docStmt->execute([$deptId]);
        $doctors = $docStmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($doctors)) {
            $log("Warning: No doctors found for {$deptName} (Dept ID {$deptId}). Skipping.");
            continue;
        }

        $log("\nProcessing {$deptName} (Dept ID {$deptId}) with " . count($doctors) . " attending doctors...");

        // Prune legacy records for this department outside the 5 designated tokens
        $minToken = $deptConfig['token_start'];
        $maxToken = $deptConfig['token_start'] + count($deptConfig['patients']) - 1;
        try {
            $db->prepare("DELETE FROM queue_priority_events WHERE queue_entry_id IN (SELECT id FROM queue_entries WHERE department_id = ? AND (token_number < ? OR token_number > ?))")->execute([$deptId, $minToken, $maxToken]);
            $db->prepare("DELETE FROM queue_entries WHERE department_id = ? AND (token_number < ? OR token_number > ?)")->execute([$deptId, $minToken, $maxToken]);
        } catch (Exception $e) {
            // Ignore if tables or cascades already handle it
        }

        foreach ($deptConfig['patients'] as $idx => $pData) {
            $tokenNum = $deptConfig['token_start'] + $idx;
            $doc = $doctors[$pData['doc_offset'] % count($doctors)];

            // 1. Ensure Patient Profile exists
            $nameParts = explode(' ', $pData['name']);
            $firstName = $nameParts[0];
            $lastName = isset($nameParts[1]) ? implode(' ', array_slice($nameParts, 1)) : 'Kumar';
            $pcode = sprintf('PAT-2026-Q%03d', $tokenNum);

            // Check if patient profile already exists by patient_id
            $chkP = $db->prepare("SELECT id FROM patient_profiles WHERE patient_id = ?");
            $chkP->execute([$pcode]);
            $patientId = $chkP->fetchColumn();

            if (!$patientId) {
                // Calculate birth year from age
                $birthYear = (int)date('Y') - (int)$pData['age'];
                $dob = sprintf('%04d-06-15', $birthYear);

                $now = date('Y-m-d H:i:s');
                $insP = $db->prepare("
                    INSERT INTO patient_profiles (
                        patient_id, first_name, last_name, dob, gender, blood_group,
                        phone, email, address, emergency_contact_name, emergency_contact_phone, created_at
                    ) VALUES (
                        ?, ?, ?, ?, ?, 'B+',
                        ?, ?, 'Chennai, Tamil Nadu', 'Family Guardian', ?, ?
                    )
                ");
                $email = strtolower($firstName . '.' . $tokenNum . '@patient.kvm');
                $insP->execute([
                    $pcode, $firstName, $lastName, $dob, $pData['gender'],
                    $pData['phone'], $email, $pData['phone'], $now
                ]);
                $patientId = (int)$db->lastInsertId();
            } else {
                $patientId = (int)$patientId;
            }

            // 2. Check if Queue Entry exists for today with this department and token_number
            $chkQ = $db->prepare("
                SELECT id FROM queue_entries 
                WHERE queue_date = ? AND department_id = ? AND token_number = ?
            ");
            $chkQ->execute([$today, $deptId, $tokenNum]);
            $existingQid = $chkQ->fetchColumn();

            $checkInDateTime = $today . ' ' . $pData['time'];
            $consultStart = ($pData['status'] === 'in_consultation') ? $today . ' ' . $pData['time'] : null;
            $now = date('Y-m-d H:i:s');

            if ($existingQid) {
                // Update record to preserve correct demo values
                $updQ = $db->prepare("
                    UPDATE queue_entries SET
                        patient_id = ?,
                        doctor_id = ?,
                        status = ?,
                        priority_level = ?,
                        priority_reason = ?,
                        estimated_wait_time_minutes = ?,
                        check_in_time = ?,
                        consultation_start_time = ?,
                        updated_at = ?
                    WHERE id = ?
                ");
                $updQ->execute([
                    $patientId,
                    $doc['id'],
                    $pData['status'],
                    $pData['priority'],
                    $pData['reason'],
                    $pData['wait'],
                    $checkInDateTime,
                    $consultStart,
                    $now,
                    $existingQid
                ]);
                $totalUpdated++;
            } else {
                // Insert new queue entry
                $insQ = $db->prepare("
                    INSERT INTO queue_entries (
                        token_number, patient_id, appointment_id, doctor_id, department_id,
                        queue_date, check_in_time, consultation_start_time, status,
                        priority_level, priority_reason, estimated_wait_time_minutes, created_at, updated_at
                    ) VALUES (
                        ?, ?, NULL, ?, ?,
                        ?, ?, ?, ?,
                        ?, ?, ?, ?, ?
                    )
                ");
                $insQ->execute([
                    $tokenNum,
                    $patientId,
                    $doc['id'],
                    $deptId,
                    $today,
                    $checkInDateTime,
                    $consultStart,
                    $pData['status'],
                    $pData['priority'],
                    $pData['reason'],
                    $pData['wait'],
                    $now,
                    $now
                ]);
                $totalCreated++;
            }

            $log("   ✓ Token #{$tokenNum}: {$pData['name']} ({$pcode}) -> Dr. {$doc['first_name']} {$doc['last_name']} [{$pData['status']} | {$pData['priority']}]");
        }
    }

    $log("\n=== SEEDING COMPLETED ===");
    $log("Queue records created: {$totalCreated}, updated: {$totalUpdated}");

    return [
        'success' => true,
        'created' => $totalCreated,
        'updated' => $totalUpdated,
        'total' => $totalCreated + $totalUpdated
    ];
}

// CLI execution
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    seed_department_queues();
}
