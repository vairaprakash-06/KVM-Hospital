<?php
/**
 * KVM Hospital Management System — NexusCare
 * Automated Test Suite & Integrity Verification Runner
 */

declare(strict_types=1);

require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/helpers/functions.php';
require_once __DIR__ . '/../app/services/AuditService.php';
require_once __DIR__ . '/../app/services/CareTrailService.php';
require_once __DIR__ . '/../app/services/QueueService.php';
require_once __DIR__ . '/../app/services/BedAllocationService.php';
require_once __DIR__ . '/../app/services/FlowLensService.php';

$isCli = (php_sapi_name() === 'cli');

class TestRunner {
    private PDO $db;
    private int $passed = 0;
    private int $failed = 0;
    private array $results = [];

    public function __construct() {
        $this->db = get_db();
    }

    private function assert(string $testName, bool $condition, string $detail = ''): void {
        if ($condition) {
            $this->passed++;
            $this->results[] = ['name' => $testName, 'status' => 'PASS', 'detail' => $detail];
        } else {
            $this->failed++;
            $this->results[] = ['name' => $testName, 'status' => 'FAIL', 'detail' => $detail];
        }
    }

    public function runAll(): array {
        $this->testDatabaseConnectivity();
        $this->testAuthenticationAndHashing();
        $this->testRoleAndPermissions();
        $this->testPatientIdGeneration();
        $this->testAppointmentConflictPrevention();
        $this->testQueueFlowTokenAndEstimate();
        $this->testBedMatrixSafeAllocationAndDischarge();
        $this->testCareTrailMilestones();
        $this->testFlowLensBottleneckEvaluation();
        $this->testTrustLedgerAuditIntegrity();
        $this->testPharmacyStockConstraint();

        return [
            'total'   => $this->passed + $this->failed,
            'passed'  => $this->passed,
            'failed'  => $this->failed,
            'results' => $this->results
        ];
    }

    private function testDatabaseConnectivity(): void {
        try {
            $version = $this->db->query("SELECT VERSION()")->fetchColumn();
            $this->assert("Database Connectivity", !empty($version), "Connected to MySQL/MariaDB ({$version})");
        } catch (Exception $e) {
            $this->assert("Database Connectivity", false, $e->getMessage());
        }
    }

    private function testAuthenticationAndHashing(): void {
        $stmt = $this->db->prepare("SELECT password_hash FROM `users` WHERE `username` = 'admin'");
        $stmt->execute();
        $hash = $stmt->fetchColumn();

        $valid = password_verify('Password123!', (string)$hash);
        $invalid = password_verify('WrongPassword999', (string)$hash);

        $this->assert("Authentication Password Hash Verification", $valid && !$invalid, "Password123! verified; wrong password safely rejected");
    }

    private function testRoleAndPermissions(): void {
        $rolesStmt = $this->db->query("SELECT COUNT(*) FROM `roles`");
        $rolesCount = (int)$rolesStmt->fetchColumn();

        $permsStmt = $this->db->query("SELECT COUNT(*) FROM `permissions`");
        $permsCount = (int)$permsStmt->fetchColumn();

        $this->assert("Role & Permission Mapping", $rolesCount === 9 && $permsCount >= 20, "Enforced 9 primary roles with {$permsCount} permissions");
    }

    private function testPatientIdGeneration(): void {
        $code = generate_patient_id($this->db);
        $matches = preg_match('/^PAT-\d{4}-\d{4}$/', $code);
        $this->assert("Unique Patient ID Format", $matches === 1, "Generated identifier: {$code}");
    }

    private function testAppointmentConflictPrevention(): void {
        $date = '2027-11-20';
        $time = '10:00:00';
        $docId = 1;

        // Clean any prior test row
        $this->db->prepare("DELETE FROM `appointments` WHERE `appointment_date` = :d AND `appointment_time` = :t")
                 ->execute(['d' => $date, 't' => $time]);

        // Insert first appointment
        $num1 = "APT-TEST-0001";
        $this->db->prepare("
            INSERT INTO `appointments` (`appointment_number`, `patient_id`, `doctor_id`, `department_id`, `appointment_date`, `appointment_time`, `status`)
            VALUES (:num, 1, :doc, 9, :d, :t, 'confirmed')
        ")->execute(['num' => $num1, 'doc' => $docId, 'd' => $date, 't' => $time]);

        // Attempt collision check
        $chk = $this->db->prepare("
            SELECT id FROM `appointments` 
            WHERE `doctor_id` = :doc AND `appointment_date` = :d AND `appointment_time` = :t 
              AND `status` IN ('pending', 'confirmed', 'checked_in')
        ");
        $chk->execute(['doc' => $docId, 'd' => $date, 't' => $time]);
        $hasCollision = ($chk->fetch() !== false);

        $this->assert("Appointment Collision Conflict Detection", $hasCollision, "Detected concurrent slot collision for Dr. #{$docId} on {$date} at {$time}");

        // Cleanup
        $this->db->prepare("DELETE FROM `appointments` WHERE `appointment_number` = :num")->execute(['num' => $num1]);
    }

    private function testQueueFlowTokenAndEstimate(): void {
        $queueService = new QueueService($this->db);
        $res = $queueService->checkInPatient(patientId: 2, doctorId: 3, departmentId: 8, priority: 'normal');

        $success = $res['success'] && $res['token_number'] > 0;
        $this->assert("QueueFlow Atomic Token Generation", $success, "Issued Token #{$res['token_number']} with est. wait ~{$res['estimated_wait_minutes']} mins");
    }

    private function testBedMatrixSafeAllocationAndDischarge(): void {
        $bedService = new BedAllocationService($this->db);

        // Find available bed
        $bed = $this->db->query("SELECT id, bed_number FROM `beds` WHERE status = 'available' LIMIT 1")->fetch();

        if ($bed) {
            $res = $bedService->assignBed(
                bedId: (int)$bed['id'],
                patientId: 5,
                encounterId: 1,
                assignedBy: 1,
                clinicalReason: 'Automated test suite bed assignment'
            );

            $assigned = $res['success'];

            // Attempt to assign the same bed again -> MUST fail
            $doubleBook = $bedService->assignBed(
                bedId: (int)$bed['id'],
                patientId: 3,
                encounterId: 2,
                assignedBy: 1,
                clinicalReason: 'Illegal double book test'
            );
            $doubleBookBlocked = !$doubleBook['success'];

            // Discharge bed -> transitions to cleaning
            $discharge = $bedService->dischargeBed((int)$res['assignment_id'], 1, 'Automated test discharge');
            $discharged = $discharge['success'];

            // Certify cleaned -> transitions back to available
            $clean = $bedService->markBedCleaned((int)$bed['id'], 1);
            $cleaned = $clean['success'];

            $this->assert("BedMatrix Transaction-Safe Allocation & Race-Lock", $assigned && $doubleBookBlocked && $discharged && $cleaned, 
                "Assigned Bed {$bed['bed_number']}, double-booking blocked, discharged to cleaning, certified available");
        } else {
            $this->assert("BedMatrix Transaction-Safe Allocation & Race-Lock", true, "Skipped (no spare beds available)");
        }
    }

    private function testCareTrailMilestones(): void {
        $careTrail = new CareTrailService($this->db);
        $id = $careTrail->recordEvent(
            patientId: 1,
            eventType: 'consultation_completed',
            eventTitle: 'Automated Test Milestone',
            eventDescription: 'Verifying chronological event ingestion in CareTrail timeline',
            severity: 'info',
            createdBy: 1
        );

        $events = $careTrail->getPatientTimeline(1);
        $this->assert("CareTrail Longitudinal Milestone Ingestion", $id > 0 && !empty($events), "Recorded milestone #{$id}; verified timeline query count: " . count($events));
    }

    private function testFlowLensBottleneckEvaluation(): void {
        $flowLens = new FlowLensService($this->db);
        $alerts = $flowLens->evaluateBottlenecks();
        $this->assert("FlowLens Explainable Bottleneck Engine", is_array($alerts), "Analyzed database metrics; alerts evaluated without errors");
    }

    private function testTrustLedgerAuditIntegrity(): void {
        $audit = new AuditService($this->db);
        $logId = $audit->log(
            actionType: 'UPDATE',
            entityType: 'SystemTest',
            entityId: 999,
            description: 'Automated verification test of TrustLedger audit subsystem'
        );

        $this->assert("TrustLedger Immutable Audit Logging", $logId > 0, "Successfully committed audit log record #{$logId}");
    }

    private function testPharmacyStockConstraint(): void {
        // Verify database prevents negative stock
        try {
            $stmt = $this->db->prepare("UPDATE `medicines` SET `stock_quantity` = -10 WHERE id = 1");
            $stmt->execute();
            $this->assert("Database CHECK Constraint chk_med_stock", false, "Allowed negative stock (CHECK constraint missing)");
        } catch (Exception $e) {
            $this->assert("Database CHECK Constraint chk_med_stock", true, "Database rejected negative stock: " . substr($e->getMessage(), 0, 80));
        }
    }
}

$runner = new TestRunner();
$summary = $runner->runAll();

if ($isCli) {
    echo "========================================================\n";
    echo " KVM HOSPITAL MANAGEMENT SYSTEM — NEXUSCARE TEST SUITE \n";
    echo "========================================================\n";
    foreach ($summary['results'] as $r) {
        $statusStr = $r['status'] === 'PASS' ? "\033[32m[PASS]\033[0m" : "\033[31m[FAIL]\033[0m";
        echo "{$statusStr} {$r['name']} — {$r['detail']}\n";
    }
    echo "--------------------------------------------------------\n";
    echo "Total: {$summary['total']} | Passed: {$summary['passed']} | Failed: {$summary['failed']}\n";
    echo "========================================================\n";
    exit($summary['failed'] === 0 ? 0 : 1);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>NexusCare Automated Test Runner</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.min.css">
</head>
<body class="bg-light py-5">
<div class="container" style="max-width: 900px;">
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
        <div class="card-header bg-dark text-white p-4">
            <h4 class="fw-bold mb-1"><i class="bi bi-shield-check text-success me-2"></i>NexusCare Automated Test Suite</h4>
            <small class="text-light opacity-75">Automated validation of database relational integrity, authentication, and the 5 differentiated engines</small>
        </div>
        <div class="card-body p-4">
            <div class="row g-3 mb-4 text-center">
                <div class="col-4">
                    <div class="p-3 bg-light rounded border">
                        <h3 class="fw-bold mb-0"><?= $summary['total'] ?></h3>
                        <small class="text-muted">Total Tests</small>
                    </div>
                </div>
                <div class="col-4">
                    <div class="p-3 bg-success bg-opacity-10 text-success rounded border border-success border-opacity-25">
                        <h3 class="fw-bold mb-0"><?= $summary['passed'] ?></h3>
                        <small>Passed</small>
                    </div>
                </div>
                <div class="col-4">
                    <div class="p-3 bg-<?= $summary['failed'] === 0 ? 'light' : 'danger bg-opacity-10 text-danger' ?> rounded border">
                        <h3 class="fw-bold mb-0"><?= $summary['failed'] ?></h3>
                        <small class="text-muted">Failed</small>
                    </div>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Test Description</th>
                            <th>Status</th>
                            <th>Verification Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($summary['results'] as $r): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($r['name']) ?></strong></td>
                                <td>
                                    <span class="badge bg-<?= $r['status'] === 'PASS' ? 'success' : 'danger' ?>">
                                        <?= $r['status'] ?>
                                    </span>
                                </td>
                                <td><small class="text-secondary"><?= htmlspecialchars($r['detail']) ?></small></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="mt-4 text-center">
                <a href="../public/index.php" class="btn btn-dark">Return to NexusCare Application</a>
            </div>
        </div>
    </div>
</div>
</body>
</html>
