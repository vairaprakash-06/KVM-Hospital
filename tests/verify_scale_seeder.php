<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/config/database.php';

$db = get_db();

echo "========================================================\n";
echo " KVM HOSPITAL — 89 DEPARTMENTS SCALE VERIFICATION\n";
echo "========================================================\n";

$totalDepts = (int)$db->query("SELECT COUNT(*) FROM departments")->fetchColumn();
$totalDocs  = (int)$db->query("SELECT COUNT(*) FROM doctor_profiles")->fetchColumn();
$totalStaff = (int)$db->query("SELECT COUNT(*) FROM staff_profiles")->fetchColumn();
$totalAppts = (int)$db->query("SELECT COUNT(*) FROM appointments")->fetchColumn();
$totalPats  = (int)$db->query("SELECT COUNT(*) FROM patient_profiles")->fetchColumn();

echo "Total Departments:  {$totalDepts} (Expected: 89)   " . ($totalDepts === 89 ? "[PASS]" : "[FAIL]") . "\n";
echo "Total Doctors:      {$totalDocs} (Expected: 178)  " . ($totalDocs === 178 ? "[PASS]" : "[FAIL]") . "\n";
echo "Total Staff:        {$totalStaff} (Expected: 356)  " . ($totalStaff === 356 ? "[PASS]" : "[FAIL]") . "\n";
echo "Total Appointments: {$totalAppts} (Expected: 2670) " . ($totalAppts === 2670 ? "[PASS]" : "[FAIL]") . "\n";
echo "Total Patients:     {$totalPats} (Expected: >= 500) " . ($totalPats >= 500 ? "[PASS]" : "[FAIL]") . "\n";

// Check conflict detection across all doctors
$conflicts = $db->query("
    SELECT doctor_id, appointment_date, appointment_time, COUNT(*) as c
    FROM appointments
    GROUP BY doctor_id, appointment_date, appointment_time
    HAVING c > 1
")->fetchAll();

echo "Doctor Slot Concurrency Collisions: " . count($conflicts) . " (Expected: 0) " . (count($conflicts) === 0 ? "[PASS]" : "[FAIL]") . "\n";
if (count($conflicts) > 0) {
    foreach ($conflicts as $cf) {
        echo "   Collision: Doc #{$cf['doctor_id']} on {$cf['appointment_date']} at {$cf['appointment_time']} ({$cf['c']} appointments)\n";
        $collRows = $db->query("SELECT id, appointment_number, patient_id, department_id FROM appointments WHERE doctor_id = {$cf['doctor_id']} AND appointment_date = '{$cf['appointment_date']}' AND appointment_time = '{$cf['appointment_time']}'")->fetchAll(PDO::FETCH_ASSOC);
        print_r($collRows);
    }
}


// Verify department-doctor consistency in appointments
$mismatches = $db->query("
    SELECT a.id, a.department_id AS appt_dept, dp.department_id AS doc_dept
    FROM appointments a
    JOIN doctor_profiles dp ON a.doctor_id = dp.id
    WHERE a.department_id != dp.department_id
")->fetchAll();

echo "Doctor-Department Relationship Alignment: " . (count($mismatches) === 0 ? "[PASS - 100% Aligned]" : "[FAIL - " . count($mismatches) . " mismatched]") . "\n";

// Detailed check on each department
$depts = $db->query("
    SELECT 
        d.id,
        d.dept_code,
        d.name,
        (SELECT COUNT(*) FROM doctor_profiles dp WHERE dp.department_id = d.id) AS doc_count,
        (SELECT COUNT(*) FROM staff_profiles sp WHERE sp.department_id = d.id) AS staff_count,
        (SELECT COUNT(*) FROM appointments a WHERE a.department_id = d.id) AS appt_count
    FROM departments d
    ORDER BY d.id ASC
")->fetchAll(PDO::FETCH_ASSOC);

$deptPass = 0;
foreach ($depts as $d) {
    if ((int)$d['doc_count'] === 2 && (int)$d['staff_count'] === 4 && (int)$d['appt_count'] === 30) {
        $deptPass++;
    } else {
        echo "FAIL on Dept #{$d['id']} ({$d['dept_code']}): Docs={$d['doc_count']}, Staff={$d['staff_count']}, Appts={$d['appt_count']}\n";
    }
}

echo "Departments with Exactly (2 Docs, 4 Staff, 30 Appts): {$deptPass} / 89 " . ($deptPass === 89 ? "[ALL 89 PASS]" : "[FAIL]") . "\n";

// Sample print first 5 and last 5 departments
echo "\nSample Inspection (First 5 Departments):\n";
for ($i = 0; $i < 5; $i++) {
    $row = $depts[$i];
    echo sprintf(" - Dept #%02d [%-9s] %-30s | Docs: %d | Staff: %d | Appts: %d\n", $row['id'], $row['dept_code'], $row['name'], $row['doc_count'], $row['staff_count'], $row['appt_count']);
}
echo "Sample Inspection (Last 5 Departments):\n";
for ($i = count($depts) - 5; $i < count($depts); $i++) {
    $row = $depts[$i];
    echo sprintf(" - Dept #%02d [%-9s] %-30s | Docs: %d | Staff: %d | Appts: %d\n", $row['id'], $row['dept_code'], $row['name'], $row['doc_count'], $row['staff_count'], $row['appt_count']);
}

echo "========================================================\n";
