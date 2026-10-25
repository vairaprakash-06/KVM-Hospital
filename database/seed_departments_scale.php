<?php
/**
 * KVM Hospital Management System — NexusCare
 * Enterprise Scale Seeder: 89 Departments x (2 Doctors + 4 Staff + 30 Appointments)
 * Total Targets:
 * - 89 Departments
 * - 178 Doctors (Exactly 2 per department)
 * - 356 Staff Members (Exactly 4 per department)
 * - 2,670 Appointments (Exactly 30 per department)
 * - Authentic Tamil Nadu Fictional Patients & conflict-free doctor schedules
 *
 * Idempotent: Safe to execute repeatedly without generating duplicate records.
 */

declare(strict_types=1);

require_once __DIR__ . '/../app/config/database.php';

function seed_departments_scale(?PDO $db = null, bool $quiet = false): array {
    $db = $db ?? get_db();
    $isSqlite = ($db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite');

    $out = function (string $msg) use ($quiet) {
        if (!$quiet) {
            echo $msg . "\n";
        }
    };

    $out("=== NEXUSCARE ENTERPRISE SCALE SEEDER ===");
    $out("Target: 89 Departments | 178 Doctors | 356 Staff | 2,670 Appointments");

    // Default password hash for demo users (Password123!)
    $defaultPassHash = '$2y$10$dPrT4l7TQ.D5rVR/QyJHVeU.qhRtzadZ9PWu7rRfBzidVB1cJwbHK';

    // -------------------------------------------------------------------------
    // 1. TAMIL NADU NAME CATALOGS
    // -------------------------------------------------------------------------
    $tamilMaleFirst = [
        'Arjun', 'Karthik', 'Saravanan', 'Muthuvel', 'Vigneshwaran',
        'Senthil', 'Balaji', 'Gokul', 'Murugan', 'Arun', 'Prakash',
        'Vijay', 'Dinesh', 'Suresh', 'Ramesh', 'Manikandan', 'Sivakumar',
        'Prabhu', 'Selvam', 'Kumaran', 'Vetrivel', 'Anbarasan', 'Ilango',
        'Ezhil', 'Kavin', 'Madhavan', 'Naveen', 'Praveen', 'Surya',
        'Vasanth', 'Aravind', 'Gunasekar', 'Thirumalai', 'Jayakumar',
        'Pandian', 'Shanmugam', 'Thangaraj', 'Natarajan', 'Ramasamy',
        'Kannan', 'Boopathi', 'Chandran', 'Dhanush', 'Elumalai', 'Ganesan'
    ];

    $tamilFemaleFirst = [
        'Saranya', 'Priya', 'Kavitha', 'Nandhini', 'Meenakshi',
        'Yazhini', 'Thenmozhi', 'Revathi', 'Lakshmi', 'Divya', 'Meena',
        'Deepa', 'Anitha', 'Bhuvaneshwari', 'Gayathri', 'Gomathi',
        'Janani', 'Kalaivani', 'Malathi', 'Pavithra', 'Radhika',
        'Sandhya', 'Sangeetha', 'Sowmya', 'Subhashini', 'Sujatha',
        'Uma', 'Vanitha', 'Vidya', 'Yamuna', 'Keerthana', 'Hema',
        'Nithya', 'Poornima', 'Shanthi', 'Vasanthi', 'Abirami',
        'Dharshini', 'Harini', 'Indhu', 'Jeevitha', 'Karpagam', 'Lavanya'
    ];

    $tamilSurnames = [
        'Kumar', 'Raj', 'Devi', 'Lakshmi', 'Rani', 'Krishnan', 'Prakash',
        'Kumari', 'Srinivasan', 'Murugan', 'Saravanan', 'Sundaram', 'Pandian',
        'Selvam', 'Balan', 'Velu', 'Ganesan', 'Swamy', 'Moorthy', 'Mani',
        'Natarajan', 'Pillai', 'Iyer', 'Chettiar', 'Mudaliar', 'Thevar',
        'Nadar', 'Naidu', 'Reddiar', 'Gounder', 'Subramanian', 'Venkatraman',
        'Ramanathan', 'Chandrasekar', 'Narayanan', 'Mahalingam', 'Sethuraman'
    ];

    $tamilCities = [
        'Chennai', 'Coimbatore', 'Madurai', 'Tiruchirappalli', 'Salem',
        'Tirunelveli', 'Tiruppur', 'Erode', 'Vellore', 'Thanjavur',
        'Dindigul', 'Kanchipuram', 'Nagercoil', 'Cuddalore', 'Kumbakonam',
        'Tuticorin', 'Karur', 'Hosur', 'Neyveli', 'Pudukkottai'
    ];

    $bloodGroups = ['O+', 'A+', 'B+', 'AB+', 'O-', 'A-', 'B-'];

    // -------------------------------------------------------------------------
    // 2. VERIFY / SEED FICTIONAL PATIENTS (Ensure at least 500 patients)
    // -------------------------------------------------------------------------
    $out("1. Verifying and expanding Tamil Nadu fictional patients pool...");
    $existingPatients = $db->query("SELECT id, patient_id FROM `patient_profiles`")->fetchAll(PDO::FETCH_ASSOC);
    $patientMap = [];
    foreach ($existingPatients as $ep) {
        $patientMap[$ep['patient_id']] = (int)$ep['id'];
    }

    $insertPatientStmt = $db->prepare("
        INSERT INTO `patient_profiles` (
            `patient_id`, `first_name`, `last_name`, `dob`, `gender`,
            `blood_group`, `phone`, `email`, `address`, `emergency_contact_name`,
            `emergency_contact_phone`, `allergies`, `medical_history`, `created_at`
        ) VALUES (
            :pid, :fn, :ln, :dob, :gender,
            :bg, :phone, :email, :addr, :em_name,
            :em_phone, :allergies, :history, :created_at
        )
    ");

    $targetPatientCount = 500;
    $createdPatients = 0;

    for ($i = 1; $i <= $targetPatientCount; $i++) {
        $pCode = sprintf('PAT-2026-%04d', $i);
        if (isset($patientMap[$pCode])) {
            continue;
        }

        $isFemale = ($i % 2 === 0);
        $fn = $isFemale 
            ? $tamilFemaleFirst[($i * 3) % count($tamilFemaleFirst)] 
            : $tamilMaleFirst[($i * 5) % count($tamilMaleFirst)];
        $ln = $tamilSurnames[($i * 7) % count($tamilSurnames)];
        $gender = $isFemale ? 'female' : 'male';

        $age = 18 + ($i * 7 % 62); // 18 to 79
        $birthYear = 2026 - $age;
        $month = sprintf('%02d', ($i % 12) + 1);
        $day = sprintf('%02d', ($i % 28) + 1);
        $dob = "{$birthYear}-{$month}-{$day}";

        $bg = $bloodGroups[$i % count($bloodGroups)];
        $city = $tamilCities[$i % count($tamilCities)];
        $streetNum = ($i * 11 % 220) + 1;
        $address = "{$streetNum}, Main Road, {$city}, Tamil Nadu";
        $phone = '+91 9' . sprintf('%04d', 4400 + ($i % 5500)) . ' ' . sprintf('%05d', ($i * 137 % 90000) + 10000);
        
        $cleanFn = strtolower(preg_replace('/[^a-z0-9]/i', '', $fn));
        $cleanLn = strtolower(preg_replace('/[^a-z0-9]/i', '', $ln));
        $email = "{$cleanFn}.{$cleanLn}{$i}@example.com";

        $emName = ($isFemale ? 'Muthuvel ' : 'Saranya ') . $ln;
        $emPhone = '+91 9' . sprintf('%04d', 8400 + ($i % 1500)) . ' ' . sprintf('%05d', ($i * 97 % 90000) + 10000);
        $allergies = ($i % 8 === 0) ? 'Penicillin allergy' : (($i % 15 === 0) ? 'Sulfa drugs' : 'None reported');
        $history = ($i % 4 === 0) ? 'Type 2 Diabetes, controlled with oral hypoglycemics.' : 
                   (($i % 6 === 0) ? 'Mild primary hypertension, regular lifestyle titration.' : 'No significant chronic medical illness.');

        $createdAt = date('Y-m-d H:i:s', strtotime("-" . ($i * 6) . " hours"));

        $insertPatientStmt->execute([
            'pid'        => $pCode,
            'fn'         => $fn,
            'ln'         => $ln,
            'dob'        => $dob,
            'gender'     => $gender,
            'bg'         => $bg,
            'phone'      => $phone,
            'email'      => $email,
            'addr'       => $address,
            'em_name'    => $emName,
            'em_phone'   => $emPhone,
            'allergies'  => $allergies,
            'history'    => $history,
            'created_at' => $createdAt
        ]);

        $newPId = (int)$db->lastInsertId();
        $patientMap[$pCode] = $newPId;
        $createdPatients++;
    }

    $allPatientIds = array_values($patientMap);
    $totalPatients = count($allPatientIds);
    $out("   Patient pool ready: {$totalPatients} patients available (+{$createdPatients} created).");

    // -------------------------------------------------------------------------
    // 3. FETCH ALL 89 DEPARTMENTS
    // -------------------------------------------------------------------------
    $departments = $db->query("SELECT id, dept_code, name, description, location FROM `departments` ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
    $deptCount = count($departments);
    $out("2. Processing all {$deptCount} departments for doctors, staff, and appointments...");

    if ($deptCount !== 89) {
        $out("   Notice: Currently {$deptCount} departments found (Expected 89). Seeding will cover all available.");
    }

    // Helper to get or create a user account
    $getUserOrInsert = function (string $username, string $email, int $roleId) use ($db, $defaultPassHash, $isSqlite): int {
        $stmt = $db->prepare("SELECT id FROM `users` WHERE `username` = :u OR `email` = :e LIMIT 1");
        $stmt->execute(['u' => $username, 'e' => $email]);
        $uid = $stmt->fetchColumn();
        if ($uid) {
            return (int)$uid;
        }

        $ins = $db->prepare("
            INSERT INTO `users` (`username`, `email`, `password_hash`, `account_status`, `created_at`)
            VALUES (:u, :e, :p, 'active', CURRENT_TIMESTAMP)
        ");
        $ins->execute([
            'u' => $username,
            'e' => $email,
            'p' => $defaultPassHash
        ]);
        $newUid = (int)$db->lastInsertId();

        // Assign role
        $roleSql = $isSqlite 
            ? "INSERT OR IGNORE INTO `user_role_assignments` (`user_id`, `role_id`, `assigned_by`) VALUES (:u, :r, 1)"
            : "INSERT IGNORE INTO `user_role_assignments` (`user_id`, `role_id`, `assigned_by`) VALUES (:u, :r, 1)";
        $db->prepare($roleSql)->execute(['u' => $newUid, 'r' => $roleId]);

        return $newUid;
    };

    // -------------------------------------------------------------------------
    // 4. SEED EXACTLY 2 DOCTORS PER DEPARTMENT (TARGET: 178 TOTAL)
    // -------------------------------------------------------------------------
    $out("3. Ensuring exactly 2 Doctors per department (Target: 178)...");
    $totalDoctorsAdded = 0;

    $qualifications = [
        'MBBS, MD (General Medicine), DM',
        'MBBS, MS (General Surgery), MCh',
        'MBBS, MD, DNB (Super Specialty)',
        'MBBS, MS, Fellowship (Royal College)'
    ];

    foreach ($departments as $deptIdx => $dept) {
        $deptId = (int)$dept['id'];
        $deptCode = strtoupper(trim($dept['dept_code']));

        // Check how many doctors currently belong to this department
        $currentDocs = $db->prepare("SELECT id, doctor_code, first_name, last_name FROM `doctor_profiles` WHERE `department_id` = :d ORDER BY id ASC");
        $currentDocs->execute(['d' => $deptId]);
        $existingDocs = $currentDocs->fetchAll(PDO::FETCH_ASSOC);

        $neededDocs = 2 - count($existingDocs);
        if ($neededDocs <= 0) {
            continue; // Already has 2 doctors
        }

        for ($k = 1; $k <= $neededDocs; $k++) {
            $docNum = count($existingDocs) + $k;
            $docCode = sprintf('DOC-%s-%02d', $deptCode, $docNum);

            // Double check code does not clash
            $chkCode = $db->prepare("SELECT id FROM `doctor_profiles` WHERE `doctor_code` = :c");
            $chkCode->execute(['c' => $docCode]);
            if ($chkCode->fetchColumn()) {
                $docCode .= '-' . $deptId;
            }

            $isDocFemale = (($deptId + $docNum) % 2 === 0);
            $docFn = $isDocFemale 
                ? $tamilFemaleFirst[($deptId * 3 + $docNum * 7) % count($tamilFemaleFirst)] 
                : $tamilMaleFirst[($deptId * 5 + $docNum * 11) % count($tamilMaleFirst)];
            $docLn = $tamilSurnames[($deptId * 2 + $docNum * 13) % count($tamilSurnames)];

            $cleanDocFn = strtolower(preg_replace('/[^a-z0-9]/i', '', $docFn));
            $cleanDocLn = strtolower(preg_replace('/[^a-z0-9]/i', '', $docLn));
            $docUsername = "dr.{$cleanDocFn}.{$deptId}_{$docNum}";
            $docEmail = "dr.{$cleanDocFn}.{$cleanDocLn}.d{$deptId}@nexuscare.org";

            $docUserId = $getUserOrInsert($docUsername, $docEmail, 3); // Role 3 = Doctor

            $licenseNo = sprintf('TMC-TN-%03d-%02d', $deptId, $docNum);
            $spec = "Consultant " . $dept['name'] . ($docNum === 1 ? ' Specialist' : ' Clinical Fellow');
            $qual = $qualifications[($deptId + $docNum) % count($qualifications)];
            $fee = 500.00 + (($deptId % 5) * 100.00); // 500 to 900
            $roomNo = "Suite " . ($deptId % 40 + 101) . ($docNum === 1 ? 'A' : 'B');

            $insertDoc = $db->prepare("
                INSERT INTO `doctor_profiles` (
                    `user_id`, `doctor_code`, `first_name`, `last_name`, `specialization`,
                    `qualification`, `license_number`, `consultation_fee`, `department_id`,
                    `room_no`, `availability_status`, `avg_consultation_time_mins`, `created_at`
                ) VALUES (
                    :uid, :code, :fn, :ln, :spec,
                    :qual, :lic, :fee, :dept,
                    :room, 'available', 15, CURRENT_TIMESTAMP
                )
            ");
            $insertDoc->execute([
                'uid'  => $docUserId,
                'code' => $docCode,
                'fn'   => $docFn,
                'ln'   => $docLn,
                'spec' => $spec,
                'qual' => $qual,
                'lic'  => $licenseNo,
                'fee'  => $fee,
                'dept' => $deptId,
                'room' => $roomNo
            ]);

            $newDocId = (int)$db->lastInsertId();

            // Link secondary affiliation
            $affSql = $isSqlite
                ? "INSERT OR IGNORE INTO `doctor_departments` (`doctor_id`, `department_id`) VALUES (:doc, :dept)"
                : "INSERT IGNORE INTO `doctor_departments` (`doctor_id`, `department_id`) VALUES (:doc, :dept)";
            $db->prepare($affSql)->execute(['doc' => $newDocId, 'dept' => $deptId]);

            $totalDoctorsAdded++;
        }
    }
    $finalDocTotal = (int)$db->query("SELECT COUNT(*) FROM `doctor_profiles`")->fetchColumn();
    $out("   Doctors verified: Total {$finalDocTotal} across all departments (+{$totalDoctorsAdded} added).");

    // -------------------------------------------------------------------------
    // 5. SEED EXACTLY 4 STAFF MEMBERS PER DEPARTMENT (TARGET: 356 TOTAL)
    // -------------------------------------------------------------------------
    $out("4. Ensuring exactly 4 Staff members per department (Target: 356)...");
    $totalStaffAdded = 0;

    $staffDesignations = [
        1 => 'Senior Department Nurse',
        2 => 'Clinical Care Coordinator',
        3 => 'Ward Nurse Supervisor',
        4 => 'Administrative Patient Liaison'
    ];

    foreach ($departments as $dept) {
        $deptId = (int)$dept['id'];
        $deptCode = strtoupper(trim($dept['dept_code']));

        $currentStaff = $db->prepare("SELECT id, employee_id FROM `staff_profiles` WHERE `department_id` = :d ORDER BY id ASC");
        $currentStaff->execute(['d' => $deptId]);
        $existingStaff = $currentStaff->fetchAll(PDO::FETCH_ASSOC);

        $neededStaff = 4 - count($existingStaff);
        if ($neededStaff <= 0) {
            continue; // Already has 4 staff
        }

        for ($s = 1; $s <= $neededStaff; $s++) {
            $staffNum = count($existingStaff) + $s;
            $empId = sprintf('EMP-%s-%02d', $deptCode, $staffNum);

            // Double check code does not clash
            $chkEmp = $db->prepare("SELECT id FROM `staff_profiles` WHERE `employee_id` = :e");
            $chkEmp->execute(['e' => $empId]);
            if ($chkEmp->fetchColumn()) {
                $empId .= '-' . $deptId;
            }

            $isStaffFemale = (($deptId + $staffNum) % 3 !== 0);
            $staffFn = $isStaffFemale 
                ? $tamilFemaleFirst[($deptId * 7 + $staffNum * 3) % count($tamilFemaleFirst)]
                : $tamilMaleFirst[($deptId * 3 + $staffNum * 5) % count($tamilMaleFirst)];
            $staffLn = $tamilSurnames[($deptId * 5 + $staffNum * 11) % count($tamilSurnames)];
            $gender = $isStaffFemale ? 'female' : 'male';

            $cleanStaffFn = strtolower(preg_replace('/[^a-z0-9]/i', '', $staffFn));
            $cleanStaffLn = strtolower(preg_replace('/[^a-z0-9]/i', '', $staffLn));
            $staffUsername = "staff.{$cleanStaffFn}.{$deptId}_{$staffNum}";
            $staffEmail = "{$cleanStaffFn}.{$cleanStaffLn}.s{$deptId}@nexuscare.org";

            // Staff role: 5 = Nurse for clinical, 4 = Receptionist for general, 2 = Admin
            $staffRole = ($staffNum <= 2) ? 5 : (($staffNum === 3) ? 4 : 2);
            $staffUserId = $getUserOrInsert($staffUsername, $staffEmail, $staffRole);

            $designation = $staffDesignations[$staffNum] ?? 'Clinical Support Staff';
            $phone = '+91 9' . sprintf('%04d', 8410 + ($deptId % 500)) . ' ' . sprintf('%05d', ($deptId * 33 + $staffNum * 77) % 90000 + 10000);
            $joinYear = 2021 + (($deptId + $staffNum) % 4);
            $joinDate = sprintf('%04d-%02d-%02d', $joinYear, (($staffNum * 2) % 12) + 1, (($staffNum * 5) % 28) + 1);

            $insStaff = $db->prepare("
                INSERT INTO `staff_profiles` (
                    `user_id`, `employee_id`, `first_name`, `last_name`, `gender`,
                    `phone`, `department_id`, `designation`, `join_date`, `created_at`
                ) VALUES (
                    :uid, :emp, :fn, :ln, :gender,
                    :phone, :dept, :desig, :join, CURRENT_TIMESTAMP
                )
            ");
            $insStaff->execute([
                'uid'    => $staffUserId,
                'emp'    => $empId,
                'fn'     => $staffFn,
                'ln'     => $staffLn,
                'gender' => $gender,
                'phone'  => $phone,
                'dept'   => $deptId,
                'desig'  => $designation,
                'join'   => $joinDate
            ]);

            $totalStaffAdded++;
        }
    }
    $finalStaffTotal = (int)$db->query("SELECT COUNT(*) FROM `staff_profiles`")->fetchColumn();
    $out("   Staff verified: Total {$finalStaffTotal} across all departments (+{$totalStaffAdded} added).");

    // -------------------------------------------------------------------------
    // 6. SEED EXACTLY 30 APPOINTMENTS PER DEPARTMENT (TARGET: 2,670 TOTAL)
    // -------------------------------------------------------------------------
    $out("5. Ensuring exactly 30 Appointments per department (Target: 2,670)...");
    $totalApptsAdded = 0;

    // Clinic appointment dates relative to base date 2026-10-25
    $clinicDaysDocA = [
        ['date' => '2026-10-20', 'slots' => ['09:00:00', '11:00:00', '14:00:00'], 'status' => 'completed'],
        ['date' => '2026-10-22', 'slots' => ['09:30:00', '11:30:00', '14:30:00'], 'status' => 'completed'],
        ['date' => '2026-10-24', 'slots' => ['10:00:00', '12:00:00', '15:00:00'], 'status' => 'completed'],
        ['date' => '2026-10-25', 'slots' => ['09:00:00', '11:00:00', '15:00:00'], 'status' => ['in_consultation', 'checked_in', 'confirmed']],
        ['date' => '2026-10-27', 'slots' => ['09:30:00', '11:30:00', '14:30:00'], 'status' => ['confirmed', 'confirmed', 'pending']]
    ];

    $clinicDaysDocB = [
        ['date' => '2026-10-20', 'slots' => ['10:00:00', '12:00:00', '15:30:00'], 'status' => 'completed'],
        ['date' => '2026-10-22', 'slots' => ['10:30:00', '12:30:00', '15:00:00'], 'status' => 'completed'],
        ['date' => '2026-10-24', 'slots' => ['09:00:00', '11:00:00', '14:00:00'], 'status' => 'completed'],
        ['date' => '2026-10-25', 'slots' => ['10:00:00', '12:00:00', '16:00:00'], 'status' => ['in_consultation', 'checked_in', 'confirmed']],
        ['date' => '2026-10-28', 'slots' => ['10:00:00', '12:00:00', '15:00:00'], 'status' => ['confirmed', 'confirmed', 'pending']]
    ];

    $insertApptStmt = $db->prepare("
        INSERT INTO `appointments` (
            `appointment_number`, `patient_id`, `doctor_id`, `department_id`,
            `appointment_date`, `appointment_time`, `reason`, `status`, `created_by`, `created_at`
        ) VALUES (
            :num, :pid, :doc, :dept,
            :date, :time, :reason, :status, 6, :created
        )
    ");

    // Track taken doctor time slots to guarantee 0 concurrency collisions
    $takenSlots = [];
    $existingSlots = $db->query("SELECT doctor_id, appointment_date, appointment_time FROM `appointments`")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($existingSlots as $es) {
        $key = $es['doctor_id'] . '_' . $es['appointment_date'] . '_' . $es['appointment_time'];
        $takenSlots[$key] = true;
    }

    if ($isSqlite) {
        $db->beginTransaction();
    }

    foreach ($departments as $dept) {
        $deptId = (int)$dept['id'];
        $deptName = $dept['name'];
        $deptCode = strtoupper(trim($dept['dept_code']));

        // Get the 2 doctors for this department
        $docStmt = $db->prepare("SELECT id FROM `doctor_profiles` WHERE `department_id` = :d ORDER BY id ASC LIMIT 2");
        $docStmt->execute(['d' => $deptId]);
        $deptDocIds = $docStmt->fetchAll(PDO::FETCH_COLUMN);

        if (count($deptDocIds) < 2) {
            $out("   Warning: Department #{$deptId} has fewer than 2 doctors. Skipping appts.");
            continue;
        }

        $docA = (int)$deptDocIds[0];
        $docB = (int)$deptDocIds[1];

        // Existing appointments in this department
        $curAppts = $db->prepare("SELECT COUNT(*) FROM `appointments` WHERE `department_id` = :d");
        $curAppts->execute(['d' => $deptId]);
        $existingApptCount = (int)$curAppts->fetchColumn();

        $neededAppts = 30 - $existingApptCount;
        if ($neededAppts <= 0) {
            continue; // Already has 30 appointments
        }

        // Build list of 30 target appointment schedule slots for this department
        $scheduleList = [];

        // 15 slots for Doc A
        foreach ($clinicDaysDocA as $dayInfo) {
            $dDate = $dayInfo['date'];
            foreach ($dayInfo['slots'] as $sIdx => $sTime) {
                $statusVal = is_array($dayInfo['status']) ? $dayInfo['status'][$sIdx] : $dayInfo['status'];
                $scheduleList[] = [
                    'doctor_id' => $docA,
                    'date'      => $dDate,
                    'time'      => $sTime,
                    'status'    => $statusVal
                ];
            }
        }

        // 15 slots for Doc B
        foreach ($clinicDaysDocB as $dayInfo) {
            $dDate = $dayInfo['date'];
            foreach ($dayInfo['slots'] as $sIdx => $sTime) {
                $statusVal = is_array($dayInfo['status']) ? $dayInfo['status'][$sIdx] : $dayInfo['status'];
                $scheduleList[] = [
                    'doctor_id' => $docB,
                    'date'      => $dDate,
                    'time'      => $sTime,
                    'status'    => $statusVal
                ];
            }
        }

        // Generate reasons tailored to the department
        $reasons = [
            "Routine clinical consultation and checkup for {$deptName}",
            "Follow-up examination and symptom review for {$deptName}",
            "Specialized diagnostic assessment in {$deptName}",
            "Medication review and ongoing treatment planning for {$deptName}",
            "Pre-procedure clinical evaluation for {$deptName}",
            "Post-consultation follow-up and progress review for {$deptName}"
        ];

        // Insert missing appointments up to 30
        $currentDeptApptCount = $existingApptCount;
        for ($a = 0; $a < count($scheduleList) && $currentDeptApptCount < 30; $a++) {
            $slot = $scheduleList[$a];
            $currentDeptApptCount++;
            $apptIndex = $currentDeptApptCount;
            $apptNumber = sprintf('APT-D%02d-%03d', $deptId, $apptIndex);

            // Avoid doctor time collision: shift slot time if already taken
            $sDoc  = (int)$slot['doctor_id'];
            $sDate = $slot['date'];
            $sTime = $slot['time'];
            $slotKey = "{$sDoc}_{$sDate}_{$sTime}";

            $offsetMin = 0;
            while (isset($takenSlots[$slotKey])) {
                $offsetMin += 15;
                $sTime = date('H:i:s', strtotime("{$slot['time']} + {$offsetMin} minutes"));
                $slotKey = "{$sDoc}_{$sDate}_{$sTime}";
            }
            $takenSlots[$slotKey] = true;

            // Pick a patient cyclically from our 500 patient pool
            $patIdx = (($deptId * 30 + $apptIndex) % count($allPatientIds));
            $patId = $allPatientIds[$patIdx];

            $reason = $reasons[$apptIndex % count($reasons)];
            $createdAt = date('Y-m-d H:i:s', strtotime("{$sDate} - 2 days"));

            $insertApptStmt->execute([
                'num'     => $apptNumber,
                'pid'     => $patId,
                'doc'     => $sDoc,
                'dept'    => $deptId,
                'date'    => $sDate,
                'time'    => $sTime,
                'reason'  => $reason,
                'status'  => $slot['status'],
                'created' => $createdAt
            ]);

            $totalApptsAdded++;
        }
    }

    if ($isSqlite) {
        $db->commit();
    }

    $finalApptTotal = (int)$db->query("SELECT COUNT(*) FROM `appointments`")->fetchColumn();
    $out("   Appointments verified: Total {$finalApptTotal} across all departments (+{$totalApptsAdded} added).");

    // -------------------------------------------------------------------------
    // 7. FINAL VERIFICATION SUMMARY
    // -------------------------------------------------------------------------
    $vDepts = (int)$db->query("SELECT COUNT(*) FROM `departments`")->fetchColumn();
    $vDocs  = (int)$db->query("SELECT COUNT(*) FROM `doctor_profiles`")->fetchColumn();
    $vStaff = (int)$db->query("SELECT COUNT(*) FROM `staff_profiles`")->fetchColumn();
    $vAppts = (int)$db->query("SELECT COUNT(*) FROM `appointments`")->fetchColumn();

    $out("\n=== FINAL SEEDING AUDIT SUMMARY ===");
    $out("Departments:       {$vDepts} (Expected: 89)   " . ($vDepts === 89 ? "[PASS]" : "[CHECK]"));
    $out("Doctor Profiles:   {$vDocs} (Expected: 178)  " . ($vDocs === 178 ? "[PASS]" : "[CHECK]"));
    $out("Staff Profiles:    {$vStaff} (Expected: 356)  " . ($vStaff === 356 ? "[PASS]" : "[CHECK]"));
    $out("Appointments:      {$vAppts} (Expected: 2670) " . ($vAppts === 2670 ? "[PASS]" : "[CHECK]"));
    $out("Patients Pool:     {$totalPatients} Fictional Tamil Profiles");

    // Check every single department has exactly 2 doctors, 4 staff, 30 appointments
    $breakdown = $db->query("
        SELECT 
            d.id,
            d.dept_code,
            d.name,
            (SELECT COUNT(*) FROM `doctor_profiles` dp WHERE dp.department_id = d.id) AS docs,
            (SELECT COUNT(*) FROM `staff_profiles` sp WHERE sp.department_id = d.id) AS staff,
            (SELECT COUNT(*) FROM `appointments` a WHERE a.department_id = d.id) AS appts
        FROM `departments` d
        ORDER BY d.id ASC
    ")->fetchAll(PDO::FETCH_ASSOC);

    $allPassed = true;
    foreach ($breakdown as $row) {
        if ((int)$row['docs'] !== 2 || (int)$row['staff'] !== 4 || (int)$row['appts'] !== 30) {
            $allPassed = false;
            $out("   [MISMATCH] Dept #{$row['id']} ({$row['dept_code']}): Docs={$row['docs']}, Staff={$row['staff']}, Appts={$row['appts']}");
        }
    }

    if ($allPassed) {
        $out(">>> ALL 89 DEPARTMENTS MEET EXACT CRITERIA (2 Docs, 4 Staff, 30 Appts each) <<<");
    }

    return [
        'departments'  => $vDepts,
        'doctors'      => $vDocs,
        'staff'        => $vStaff,
        'appointments' => $vAppts,
        'patients'     => $totalPatients,
        'all_passed'   => $allPassed
    ];
}

// Allow CLI execution directly
if (php_sapi_name() === 'cli' && isset($argv[0]) && realpath($argv[0]) === realpath(__FILE__)) {
    seed_departments_scale();
}
