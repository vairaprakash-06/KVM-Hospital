<?php
/**
 * Test Suite: Doctor & Staff Salary Management Integrity Verification
 */

declare(strict_types=1);

require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/helpers/functions.php';
require_once __DIR__ . '/../app/services/SalaryService.php';

$db = get_db();
$salaryService = new SalaryService($db);

echo "========================================================\n";
echo " NEXUSCARE SALARY MANAGEMENT COMPREHENSIVE TEST SUITE \n";
echo "========================================================\n";

$passedCount = 0;
$failedCount = 0;

function assertTest(string $name, bool $condition, string $detail = ''): void {
    global $passedCount, $failedCount;
    if ($condition) {
        $passedCount++;
        echo "[PASS] {$name} — {$detail}\n";
    } else {
        $failedCount++;
        echo "[FAIL] {$name} — {$detail}\n";
    }
}

// Ensure seed was performed
$salaryService->seedInitialSalaries();

// 1. Total Doctors and Staff Counts
$totalDoctors = (int)$db->query("SELECT COUNT(*) FROM `doctor_profiles`")->fetchColumn();
$totalStaff   = (int)$db->query("SELECT COUNT(*) FROM `staff_profiles`")->fetchColumn();

assertTest("Total Doctor Profiles", $totalDoctors === 178, "Count: {$totalDoctors} (Expected: 178)");
assertTest("Total Staff Profiles", $totalStaff === 356, "Count: {$totalStaff} (Expected: 356)");

// 2. Doctor Salary Range (₹50,000 - ₹90,000) & Assignment Completeness
$docSalaries = $db->query("
    SELECT es.employee_id, es.monthly_salary 
    FROM `employee_salaries` es 
    WHERE es.employee_type = 'doctor' AND es.status = 'active'
")->fetchAll(PDO::FETCH_ASSOC);

$docCountWithSalary = count($docSalaries);
$allDocsInRange = true;
$docSalaryValues = [];
$outOfRangeDocDetails = '';

foreach ($docSalaries as $ds) {
    $sal = (float)$ds['monthly_salary'];
    $docSalaryValues[] = $sal;
    if ($sal < SalaryService::DOCTOR_MIN_SALARY || $sal > SalaryService::DOCTOR_MAX_SALARY) {
        $allDocsInRange = false;
        $outOfRangeDocDetails = "Doc #{$ds['employee_id']} has salary {$sal}";
    }
}

assertTest("Doctor Salary Completeness", $docCountWithSalary === 178, "178 of 178 doctors assigned an active salary");
assertTest("Doctor Salary Range Enforcement (₹50k - ₹90k)", $allDocsInRange, $allDocsInRange ? "All 178 doctors fall strictly within ₹50,000 to ₹90,000" : $outOfRangeDocDetails);

// 3. Doctor Salary Uniqueness (No two doctors have same salary)
$uniqueDocSalaries = array_unique($docSalaryValues);
$uniqueDocCount = count($uniqueDocSalaries);
assertTest("Doctor Salary Strict Uniqueness", $uniqueDocCount === 178, "All 178 doctor salaries are distinct ({$uniqueDocCount}/178 unique amounts)");

// 4. Staff Salary Range (₹30,000 - ₹60,000) & Assignment Completeness
$staffSalaries = $db->query("
    SELECT es.employee_id, es.monthly_salary 
    FROM `employee_salaries` es 
    WHERE es.employee_type = 'staff' AND es.status = 'active'
")->fetchAll(PDO::FETCH_ASSOC);

$staffCountWithSalary = count($staffSalaries);
$allStaffInRange = true;
$staffSalaryValues = [];
$outOfRangeStaffDetails = '';

foreach ($staffSalaries as $ss) {
    $sal = (float)$ss['monthly_salary'];
    $staffSalaryValues[] = $sal;
    if ($sal < SalaryService::STAFF_MIN_SALARY || $sal > SalaryService::STAFF_MAX_SALARY) {
        $allStaffInRange = false;
        $outOfRangeStaffDetails = "Staff #{$ss['employee_id']} has salary {$sal}";
    }
}

assertTest("Staff Salary Completeness", $staffCountWithSalary === 356, "356 of 356 staff members assigned an active salary");
assertTest("Staff Salary Range Enforcement (₹30k - ₹60k)", $allStaffInRange, $allStaffInRange ? "All 356 staff members fall strictly within ₹30,000 to ₹60,000" : $outOfRangeStaffDetails);

// 5. Staff Salary Uniqueness (No two staff have same salary)
$uniqueStaffSalaries = array_unique($staffSalaryValues);
$uniqueStaffCount = count($uniqueStaffSalaries);
assertTest("Staff Salary Strict Uniqueness", $uniqueStaffCount === 356, "All 356 staff salaries are distinct ({$uniqueStaffCount}/356 unique amounts)");

// 6. Dynamic Payroll Expenditure & Average Calculation Integrity
$stats = $salaryService->getPayrollStats();
$expectedDocSum = array_sum($docSalaryValues);
$expectedStaffSum = array_sum($staffSalaryValues);
$expectedCombined = $expectedDocSum + $expectedStaffSum;
$expectedDocAvg = $expectedDocSum / 178;
$expectedStaffAvg = $expectedStaffSum / 356;

$sumMatches = (abs($stats['doctor_payroll'] - $expectedDocSum) < 0.01) &&
              (abs($stats['staff_payroll'] - $expectedStaffSum) < 0.01) &&
              (abs($stats['combined_monthly_payroll'] - $expectedCombined) < 0.01);

$avgMatches = (abs($stats['avg_doctor_salary'] - $expectedDocAvg) < 0.01) &&
              (abs($stats['avg_staff_salary'] - $expectedStaffAvg) < 0.01);

assertTest("Payroll Totals Calculation Integrity", $sumMatches, "Doctor: " . format_currency($stats['doctor_payroll']) . ", Staff: " . format_currency($stats['staff_payroll']) . ", Combined: " . format_currency($stats['combined_monthly_payroll']));
assertTest("Payroll Averages Calculation Integrity", $avgMatches, "Doctor Avg: " . format_currency($stats['avg_doctor_salary']) . ", Staff Avg: " . format_currency($stats['avg_staff_salary']));

// 7. Server-Side Validation: Invalid Salaries Rejected
$invalidDocLow = $salaryService->validateSalaryRange('doctor', 45000.00);
$invalidDocHigh = $salaryService->validateSalaryRange('doctor', 95000.00);
$invalidStaffLow = $salaryService->validateSalaryRange('staff', 25000.00);
$invalidStaffHigh = $salaryService->validateSalaryRange('staff', 65000.00);

$validationWorks = (!$invalidDocLow['valid']) && (!$invalidDocHigh['valid']) &&
                   (!$invalidStaffLow['valid']) && (!$invalidStaffHigh['valid']);
assertTest("Server-Side Boundary Validation", $validationWorks, "Rejects doctor <50k, doctor >90k, staff <30k, staff >60k");

// 8. Edit Salary & Salary History Audit Recording
$testDocId = 1; // Dr. Rajesh Sharma
$origSalaryStmt = $db->prepare("SELECT monthly_salary FROM `employee_salaries` WHERE employee_id = ? AND employee_type = 'doctor'");
$origSalaryStmt->execute([$testDocId]);
$origSalary = (float)$origSalaryStmt->fetchColumn();

// Choose a unique new salary within range [50k, 90k] that isn't currently used
$newTestSalary = 88888.00;
while (in_array($newTestSalary, $docSalaryValues, true)) {
    $newTestSalary -= 1.00;
}

$updateRes = $salaryService->updateSalary($testDocId, 'doctor', $newTestSalary, 'Merit Appraisal Test', 2);
assertTest("Salary Update Execution", $updateRes['success'], $updateRes['message']);

// Verify salary changed in DB
$checkStmt = $db->prepare("SELECT monthly_salary FROM `employee_salaries` WHERE employee_id = ? AND employee_type = 'doctor'");
$checkStmt->execute([$testDocId]);
$updatedSalary = (float)$checkStmt->fetchColumn();
assertTest("Database Salary Value Updated", abs($updatedSalary - $newTestSalary) < 0.01, "New salary in DB: " . format_currency($updatedSalary));

// Verify record inserted into salary_history
$hist = $salaryService->getSalaryHistory($testDocId, 'doctor');
$latestHist = $hist[0] ?? null;
assertTest("Salary History Record Created", $latestHist !== null && abs((float)$latestHist['new_salary'] - $newTestSalary) < 0.01, "Recorded revision from " . format_currency($origSalary) . " to " . format_currency($newTestSalary));

// 9. Idempotency Check: Running seed process again does NOT reset manually adjusted salary
$salaryService->seedInitialSalaries();
$checkAfterSeedStmt = $db->prepare("SELECT monthly_salary FROM `employee_salaries` WHERE employee_id = ? AND employee_type = 'doctor'");
$checkAfterSeedStmt->execute([$testDocId]);
$salaryAfterReseed = (float)$checkAfterSeedStmt->fetchColumn();
assertTest("Idempotent Seeder Preserves Manual Adjustments", abs($salaryAfterReseed - $newTestSalary) < 0.01, "Manual salary of " . format_currency($salaryAfterReseed) . " preserved untouched across reseed");

// 10. All 89 Departments Intact and Linked
$deptCount = (int)$db->query("SELECT COUNT(*) FROM `departments`")->fetchColumn();
$salDeptsCount = (int)$db->query("SELECT COUNT(DISTINCT department_id) FROM `employee_salaries` WHERE department_id IS NOT NULL")->fetchColumn();
assertTest("All 89 Departments Active & Linked", $deptCount === 89 && $salDeptsCount === 89, "All 89 departments have associated employee salaries");

echo "--------------------------------------------------------\n";
echo "TOTAL TESTS: " . ($passedCount + $failedCount) . " | PASSED: {$passedCount} | FAILED: {$failedCount}\n";
echo "========================================================\n";

if ($failedCount > 0) {
    exit(1);
}
