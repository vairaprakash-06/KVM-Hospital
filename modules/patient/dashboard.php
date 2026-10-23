<?php
/**
 * KVM Hospital Management System — NexusCare
 * Patient Self-Service Portal Dashboard
 */

declare(strict_types=1);

require_once __DIR__ . '/../../app/config/app.php';
require_once __DIR__ . '/../../app/config/database.php';
require_once __DIR__ . '/../../app/helpers/functions.php';
require_once __DIR__ . '/../../app/middleware/permissions.php';

require_role('patient');

$db = get_db();
$currentUser = current_user();

// Strict security isolation: Patient can ONLY query their own patient_id
$patientId = $currentUser['patient_id'] ?? null;

if (!$patientId) {
    // If patient profile not linked, check by user_id
    $patStmt = $db->prepare("SELECT id FROM `patient_profiles` WHERE user_id = :uid");
    $patStmt->execute(['uid' => $currentUser['id']]);
    $patientId = (int)$patStmt->fetchColumn();
}

if (!$patientId) {
    set_flash('danger', 'Your patient medical dossier could not be linked. Contact reception.');
    redirect(BASE_URL . '/unauthorized.php');
}

// Fetch Patient Details
$patStmt = $db->prepare("SELECT *, TIMESTAMPDIFF(YEAR, dob, CURDATE()) as age FROM `patient_profiles` WHERE id = :id");
$patStmt->execute(['id' => $patientId]);
$patient = $patStmt->fetch();

// 1. Patient's Live Queue Token Today
$queueStmt = $db->prepare("
    SELECT qe.*, d.first_name AS dfn, d.last_name AS dln, d.room_no, dep.name AS dept_name
    FROM `queue_entries` qe
    JOIN `doctor_profiles` d ON qe.doctor_id = d.id
    JOIN `departments` dep ON qe.department_id = dep.id
    WHERE qe.patient_id = :pid AND qe.queue_date = CURDATE()
    ORDER BY qe.id DESC
    LIMIT 1
");
$queueStmt->execute(['pid' => $patientId]);
$activeToken = $queueStmt->fetch();

// 2. Upcoming Appointments
$apptStmt = $db->prepare("
    SELECT a.*, d.first_name AS dfn, d.last_name AS dln, dep.name AS dept_name
    FROM `appointments` a
    JOIN `doctor_profiles` d ON a.doctor_id = d.id
    JOIN `departments` dep ON a.department_id = dep.id
    WHERE a.patient_id = :pid AND a.appointment_date >= CURDATE()
    ORDER BY a.appointment_date ASC, a.appointment_time ASC
");
$apptStmt->execute(['pid' => $patientId]);
$appointments = $apptStmt->fetchAll();

// 3. Lab Results
$labStmt = $db->prepare("
    SELECT loi.*, lt.test_name, lt.units, lo.order_number, lo.created_at AS order_date
    FROM `lab_order_items` loi
    JOIN `lab_tests` lt ON loi.test_id = lt.id
    JOIN `lab_orders` lo ON loi.lab_order_id = lo.id
    WHERE lo.patient_id = :pid AND loi.status = 'completed'
    ORDER BY loi.completed_at DESC
    LIMIT 5
");
$labStmt->execute(['pid' => $patientId]);
$labResults = $labStmt->fetchAll();

// 4. Prescriptions
$prescStmt = $db->prepare("
    SELECT pr.*, d.first_name AS dfn, d.last_name AS dln
    FROM `prescriptions` pr
    JOIN `doctor_profiles` d ON pr.doctor_id = d.id
    WHERE pr.patient_id = :pid
    ORDER BY pr.id DESC
    LIMIT 5
");
$prescStmt->execute(['pid' => $patientId]);
$prescriptions = $prescStmt->fetchAll();

// 5. Invoices
$invStmt = $db->prepare("
    SELECT * FROM `invoices` WHERE patient_id = :pid ORDER BY id DESC LIMIT 5
");
$invStmt->execute(['pid' => $patientId]);
$invoices = $invStmt->fetchAll();

$pageTitle = 'My Health Portal — ' . ($patient['first_name'] ?? '') . ' ' . ($patient['last_name'] ?? '');
require_once __DIR__ . '/../../app/views/layouts/header.php';
?>

<!-- Patient Welcome Card -->
<div class="nexus-card mb-4 p-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
        <div class="d-flex align-items-center gap-3">
            <div class="stat-icon blue" style="width: 58px; height: 58px; font-size: 1.8rem;">
                <i class="bi bi-person-badge"></i>
            </div>
            <div>
                <h4 class="fw-bold mb-1 text-dark">Welcome, <?= e($patient['first_name'] . ' ' . $patient['last_name']) ?></h4>
                <div class="d-flex flex-wrap gap-2 small text-secondary">
                    <span>Patient ID: <strong><?= e($patient['patient_id']) ?></strong></span>
                    <span>&bull;</span>
                    <span><?= e($patient['age']) ?> Years &bull; <?= ucfirst(e($patient['gender'])) ?></span>
                    <span>&bull;</span>
                    <span>Blood Group: <strong class="text-danger"><?= e($patient['blood_group']) ?></strong></span>
                </div>
            </div>
        </div>

        <div class="d-flex align-items-center gap-2">
            <a href="../caretrail/index.php" class="btn btn-nexus-primary btn-sm">
                <i class="bi bi-signpost-split me-1"></i>View My CareTrail Timeline
            </a>
        </div>
    </div>
</div>

<!-- Active Queue Token Alert if at hospital today -->
<?php if ($activeToken): ?>
    <div class="nexus-card mb-4 border-primary">
        <div class="nexus-card-body p-4 bg-primary bg-opacity-10">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                <div>
                    <span class="badge bg-primary text-uppercase mb-1">QueueFlow Live Token</span>
                    <h2 class="fw-bold mb-1 text-primary">Token #<?= e($activeToken['token_number']) ?></h2>
                    <p class="text-secondary small mb-0">
                        Doctor: <strong>Dr. <?= e($activeToken['dfn'] . ' ' . $activeToken['dln']) ?></strong> 
                        &bull; Room: <strong><?= e($activeToken['room_no'] ?? 'OPD') ?></strong> (<?= e($activeToken['dept_name']) ?>)
                    </p>
                </div>

                <div class="text-md-end">
                    <span class="badge badge-nexus badge-status-<?= e($activeToken['status']) ?> fs-6 mb-1">
                        <?= ucwords(str_replace('_', ' ', e($activeToken['status']))) ?>
                    </span>
                    <div class="small text-secondary">
                        <?php if ($activeToken['status'] === 'waiting'): ?>
                            Estimated waiting time: <strong>~<?= (int)$activeToken['estimated_wait_time_minutes'] ?> minutes</strong>
                        <?php elseif ($activeToken['status'] === 'called'): ?>
                            <span class="text-warning fw-bold"><i class="bi bi-bell-fill me-1"></i>Please proceed to consultation room now</span>
                        <?php elseif ($activeToken['status'] === 'in_consultation'): ?>
                            <span class="text-success fw-bold"><i class="bi bi-check2-circle me-1"></i>Currently with physician</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Two-Column Health Feed -->
<div class="row g-4 mb-4">
    <!-- Upcoming Appointments -->
    <div class="col-lg-6">
        <div class="nexus-card h-100">
            <div class="nexus-card-header">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-calendar2-check text-primary"></i>
                    <h6 class="fw-bold mb-0">My Consultations & Appointments</h6>
                </div>
            </div>
            <div class="nexus-card-body p-0">
                <?php if (empty($appointments)): ?>
                    <div class="text-center py-4 text-muted small">No upcoming scheduled appointments.</div>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($appointments as $a): ?>
                            <div class="list-group-item p-3 d-flex justify-content-between align-items-center">
                                <div>
                                    <strong class="text-dark small d-block">Dr. <?= e($a['dfn'] . ' ' . $a['dln']) ?> (<?= e($a['dept_name']) ?>)</strong>
                                    <small class="text-muted"><?= format_datetime($a['appointment_date'], 'd M Y') ?> at <?= format_datetime($a['appointment_time'], 'h:i A') ?></small>
                                </div>
                                <span class="badge badge-nexus badge-status-<?= e($a['status']) ?>">
                                    <?= ucfirst(e($a['status'])) ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Diagnostic Lab Results -->
    <div class="col-lg-6">
        <div class="nexus-card h-100">
            <div class="nexus-card-header">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-eyedropper text-primary"></i>
                    <h6 class="fw-bold mb-0">Certified Laboratory Reports</h6>
                </div>
            </div>
            <div class="nexus-card-body p-0">
                <?php if (empty($labResults)): ?>
                    <div class="text-center py-4 text-muted small">No completed lab reports available.</div>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($labResults as $lr): ?>
                            <div class="list-group-item p-3 d-flex justify-content-between align-items-center">
                                <div>
                                    <strong class="text-dark small d-block"><?= e($lr['test_name']) ?></strong>
                                    <small class="text-muted">Certified: <?= format_datetime($lr['completed_at'], 'd M Y') ?> &bull; Ref: <?= e($lr['reference_range']) ?> <?= e($lr['units']) ?></small>
                                </div>
                                <span class="fs-6 fw-bold text-success"><?= e($lr['result_value']) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Invoices & Prescriptions -->
<div class="row g-4">
    <!-- Prescriptions -->
    <div class="col-lg-6">
        <div class="nexus-card h-100">
            <div class="nexus-card-header">
                <h6 class="fw-bold mb-0">Doctor Prescriptions</h6>
            </div>
            <div class="nexus-card-body p-0">
                <?php if (empty($prescriptions)): ?>
                    <div class="text-center py-4 text-muted small">No prescriptions on record.</div>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($prescriptions as $p): ?>
                            <div class="list-group-item p-3">
                                <div class="d-flex justify-content-between">
                                    <strong class="text-dark small">Dr. <?= e($p['dfn'] . ' ' . $p['dln']) ?></strong>
                                    <small class="text-muted"><?= format_datetime($p['created_at'], 'd M Y') ?></small>
                                </div>
                                <p class="text-secondary small mb-0 mt-1"><?= e($p['notes'] ?: 'Take as instructed') ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Bills -->
    <div class="col-lg-6">
        <div class="nexus-card h-100">
            <div class="nexus-card-header">
                <h6 class="fw-bold mb-0">Hospital Invoices & Receipts</h6>
            </div>
            <div class="nexus-card-body p-0">
                <?php if (empty($invoices)): ?>
                    <div class="text-center py-4 text-muted small">No invoices on record.</div>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($invoices as $inv): ?>
                            <div class="list-group-item p-3 d-flex justify-content-between align-items-center">
                                <div>
                                    <strong class="text-dark small d-block"><?= e($inv['invoice_number']) ?></strong>
                                    <small class="text-muted">Total: <?= format_currency($inv['total_amount']) ?> &bull; Paid: <?= format_currency($inv['paid_amount']) ?></small>
                                </div>
                                <div class="text-end">
                                    <span class="badge bg-<?= $inv['status'] === 'paid' ? 'success' : 'danger' ?> mb-1 d-block">
                                        <?= strtoupper($inv['status']) ?>
                                    </span>
                                    <?php if ((float)$inv['balance_amount'] > 0): ?>
                                        <small class="text-danger fw-bold">Due: <?= format_currency($inv['balance_amount']) ?></small>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../app/views/layouts/footer.php'; ?>
