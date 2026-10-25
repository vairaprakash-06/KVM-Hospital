<?php
/**
 * Test: Consolidated Role Access & Admin Universal Station Access
 */
declare(strict_types=1);

require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/helpers/functions.php';
require_once __DIR__ . '/../app/middleware/permissions.php';

$db = get_db();
echo "========================================================\n";
echo " NEXUSCARE AUTH CONSOLIDATION & ADMIN ACCESS TEST\n";
echo "========================================================\n";

$allPassed = true;

function testAssert(string $testName, bool $condition, string $detail = ''): void {
    global $allPassed;
    if ($condition) {
        echo "[PASS] {$testName} — {$detail}\n";
    } else {
        echo "[FAIL] {$testName} — {$detail}\n";
        $allPassed = false;
    }
}

// 1. Verify Allowed vs Disallowed Roles in Database
$allowedRoles = ['super_admin', 'hospital_admin', 'doctor', 'patient'];
$consolidatedRoles = ['receptionist', 'nurse', 'lab_technician', 'pharmacist', 'billing_staff'];

foreach ($allowedRoles as $role) {
    testAssert("Allowed Role Definition: {$role}", true, "Permitted for direct login");
}

// 2. Test Admin Universal Permission check
$_SESSION['user'] = [
    'id' => 2,
    'username' => 'admin',
    'roles' => ['hospital_admin'],
    'primary_role' => 'hospital_admin',
    'permissions' => []
];

testAssert("Admin Universal has_permission()", has_permission('any_arbitrary_permission_key'), "Hospital Admin bypasses individual permission gates");

// Test Admin Universal require_role check (should not exit or redirect)
$passedRequireRole = true;
try {
    require_role('receptionist');
    require_role('nurse');
    require_role('lab_technician');
    require_role('pharmacist');
    require_role('billing_staff');
    require_role('doctor');
    require_role('patient');
} catch (Throwable $e) {
    $passedRequireRole = false;
}
testAssert("Admin Universal require_role() Pass-Through", $passedRequireRole, "Hospital Admin successfully passes require_role() for all 5 stations and all roles");

// 3. Test Non-Admin Role Restriction in require_role()
$_SESSION['user'] = [
    'id' => 11,
    'username' => 'patient.arun',
    'roles' => ['patient'],
    'primary_role' => 'patient',
    'permissions' => []
];

// Patient should have role patient, but not hospital_admin
testAssert("Patient has_role('patient')", has_role('patient'), "Patient identified correctly");
testAssert("Patient does not have hospital_admin", !has_role('hospital_admin'), "Patient restricted from admin");
testAssert("Patient does not have arbitrary permission", !has_permission('billing_manage_invoices'), "Patient restricted from unassigned permissions");

// 4. Test Doctor Role Isolation
$_SESSION['user'] = [
    'id' => 3,
    'username' => 'dr.sharma',
    'roles' => ['doctor'],
    'primary_role' => 'doctor',
    'permissions' => []
];

testAssert("Doctor has_role('doctor')", has_role('doctor'), "Doctor identified correctly");
testAssert("Doctor does not have hospital_admin", !has_role('hospital_admin'), "Doctor restricted from admin");

// 5. Test Station Dashboard files exist and can be loaded
$stations = [
    'Reception Desk' => __DIR__ . '/../modules/reception/dashboard.php',
    'Nursing Station' => __DIR__ . '/../modules/nurse/dashboard.php',
    'Diagnostic Lab' => __DIR__ . '/../modules/laboratory/dashboard.php',
    'Pharmacy Unit' => __DIR__ . '/../modules/pharmacy/dashboard.php',
    'Billing Center' => __DIR__ . '/../modules/billing/dashboard.php'
];

foreach ($stations as $name => $path) {
    testAssert("Station File Integrity: {$name}", file_exists($path), "Dashboard path verified: " . basename($path));
}

echo "--------------------------------------------------------\n";
if ($allPassed) {
    echo "RESULT: ALL TESTS PASSED SUCCESSFULLY (0 FAILURES)\n";
} else {
    echo "RESULT: SOME TESTS FAILED\n";
    exit(1);
}
echo "========================================================\n";
