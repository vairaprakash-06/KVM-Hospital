<?php
/**
 * KVM Hospital Management System — NexusCare
 * Reception Desk Operations Dashboard
 */

declare(strict_types=1);

require_once __DIR__ . '/../../app/config/app.php';
require_once __DIR__ . '/../../app/config/database.php';
require_once __DIR__ . '/../../app/helpers/functions.php';
require_once __DIR__ . '/../../app/middleware/permissions.php';

require_role('receptionist', 'hospital_admin', 'super_admin');

$db = get_db();

$todayApptsCount = (int)$db->query("SELECT COUNT(*) FROM `appointments` WHERE appointment_date = CURDATE()")->fetchColumn();
$waitingQueueCount = (int)$db->query("SELECT COUNT(*) FROM `queue_entries` WHERE queue_date = CURDATE() AND status = 'waiting'")->fetchColumn();
$totalPatients = (int)$db->query("SELECT COUNT(*) FROM `patient_profiles`")->fetchColumn();

// Today's incoming appointments awaiting check-in
$pendingCheckins = $db->query("
    SELECT a.*, p.first_name, p.last_name, p.patient_id AS pcode, p.phone, d.first_name AS dfn, d.last_name AS dln, dep.name AS dept_name
    FROM `appointments` a
    JOIN `patient_profiles` p ON a.patient_id = p.id
    JOIN `doctor_profiles` d ON a.doctor_id = d.id
    JOIN `departments` dep ON a.department_id = dep.id
    WHERE a.appointment_date = CURDATE() AND a.status = 'confirmed'
    ORDER BY a.appointment_time ASC
")->fetchAll();

$pageTitle = 'Front Desk & Reception Operations';
require_once __DIR__ . '/../../app/views/layouts/header.php';
?>

<!-- Title & Quick Actions -->
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div>
        <h4 class="fw-bold mb-1">Front Desk & Reception Operations</h4>
        <p class="text-secondary small mb-0">Patient registration triage, appointment coordination, and live QueueFlow token dispatch</p>
    </div>
    
    <div class="d-flex align-items-center gap-2">
        <a href="../patients/index.php" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-person-plus-fill me-1"></i>Register Patient
        </a>
        <a href="../appointments/index.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-calendar-plus-fill me-1"></i>Book Appointment
        </a>
        <a href="../queueflow/index.php" class="btn btn-nexus-primary btn-sm">
            <i class="bi bi-hourglass-split me-1"></i>QueueFlow Live Tokens
        </a>
    </div>
</div>

<!-- KPI Cards -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-lg-4">
        <div class="stat-widget">
            <div class="stat-icon blue">
                <i class="bi bi-calendar2-check-fill"></i>
            </div>
            <div>
                <div class="stat-value text-primary"><?= $todayApptsCount ?></div>
                <div class="stat-label">Today's Total Scheduled Appointments</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-4">
        <div class="stat-widget">
            <div class="stat-icon amber">
                <i class="bi bi-people-fill"></i>
            </div>
            <div>
                <div class="stat-value text-warning"><?= $waitingQueueCount ?></div>
                <div class="stat-label">Patients in Waiting Area</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-4">
        <div class="stat-widget">
            <div class="stat-icon teal">
                <i class="bi bi-person-badge-fill"></i>
            </div>
            <div>
                <div class="stat-value"><?= $totalPatients ?></div>
                <div class="stat-label">Hospital Registered Dossiers</div>
            </div>
        </div>
    </div>
</div>

<!-- Today's Pending Check-ins Table -->
<div class="nexus-card">
    <div class="nexus-card-header">
        <div class="d-flex align-items-center gap-2">
            <i class="bi bi-box-arrow-in-right text-primary"></i>
            <h6 class="fw-bold mb-0">Scheduled Patients Awaiting Check-In (<?= count($pendingCheckins) ?>)</h6>
        </div>
        <span class="badge bg-light text-secondary border">QueueFlow Direct Dispatch</span>
    </div>

    <div class="table-responsive">
        <table class="table-nexus">
            <thead>
                <tr>
                    <th>Time</th>
                    <th>Patient Details</th>
                    <th>Attending Doctor</th>
                    <th>Department</th>
                    <th>Reason</th>
                    <th class="text-end" style="width: 160px;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($pendingCheckins)): ?>
                    <tr>
                        <td colspan="6" class="text-center py-4 text-muted">
                            <i class="bi bi-check2-circle text-success fs-3 d-block mb-1"></i>
                            All scheduled patients for today have been checked in or no more pending arrivals.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($pendingCheckins as $pc): ?>
                        <tr>
                            <td>
                                <strong class="fs-6 text-primary"><?= format_datetime($pc['appointment_time'], 'h:i A') ?></strong>
                            </td>
                            <td>
                                <strong><?= e($pc['first_name'] . ' ' . $pc['last_name']) ?></strong>
                                <small class="text-muted d-block"><?= e($pc['pcode']) ?> &bull; <?= e($pc['phone']) ?></small>
                            </td>
                            <td>Dr. <?= e($pc['dfn'] . ' ' . $pc['dln']) ?></td>
                            <td><span class="badge bg-light text-dark border"><?= e($pc['dept_name']) ?></span></td>
                            <td><span class="text-secondary small"><?= e($pc['reason'] ?: 'Routine') ?></span></td>
                            <td class="text-end">
                                <form method="POST" action="../appointments/index.php">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="checkin_appointment">
                                    <input type="hidden" name="appointment_id" value="<?= $pc['id'] ?>">
                                    <button type="submit" class="btn btn-nexus-primary btn-sm">
                                        <i class="bi bi-ticket-perforated me-1"></i>Issue Token
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../../app/views/layouts/footer.php'; ?>
