<?php
/**
 * KVM Hospital Management System — NexusCare
 * Core Module: Appointment Scheduling & Conflict Protection
 */

declare(strict_types=1);

require_once __DIR__ . '/../../app/config/app.php';
require_once __DIR__ . '/../../app/config/database.php';
require_once __DIR__ . '/../../app/helpers/functions.php';
require_once __DIR__ . '/../../app/middleware/auth.php';
require_once __DIR__ . '/../../app/middleware/csrf.php';
require_once __DIR__ . '/../../app/services/AuditService.php';
require_once __DIR__ . '/../../app/services/CareTrailService.php';
require_once __DIR__ . '/../../app/services/QueueService.php';

require_auth();

$db = get_db();
$audit = new AuditService($db);
$careTrail = new CareTrailService($db);
$queueService = new QueueService($db);
$currentUser = current_user();

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    // Action 1: Book Appointment with Collision / Double-Booking Prevention
    if ($action === 'book_appointment') {
        $patientId    = (int)($_POST['patient_id'] ?? 0);
        $doctorId     = (int)($_POST['doctor_id'] ?? 0);
        $departmentId = (int)($_POST['department_id'] ?? 0);
        $appDate      = trim($_POST['appointment_date'] ?? '');
        $appTime      = trim($_POST['appointment_time'] ?? '');
        $reason       = sanitize_input($_POST['reason'] ?? '');

        if ($patientId && $doctorId && $departmentId && $appDate && $appTime) {
            $db->beginTransaction();
            try {
                // Check for overlapping active appointment for the doctor at that exact time slot
                $chkSlot = $db->prepare("
                    SELECT id, appointment_number 
                    FROM `appointments`
                    WHERE `doctor_id` = :doc_id 
                      AND `appointment_date` = :app_date 
                      AND `appointment_time` = :app_time
                      AND `status` IN ('pending', 'confirmed', 'checked_in')
                    FOR UPDATE
                ");
                $chkSlot->execute([
                    'doc_id'   => $doctorId,
                    'app_date' => $appDate,
                    'app_time' => $appTime
                ]);
                $existingSlot = $chkSlot->fetch();

                if ($existingSlot) {
                    $db->rollBack();
                    set_flash('danger', "Scheduling Conflict: Dr. is already booked at {$appTime} on {$appDate} (#{$existingSlot['appointment_number']}). Please select another time slot.");
                } else {
                    $appNumber = generate_appointment_number($db);
                    $insApp = $db->prepare("
                        INSERT INTO `appointments` (
                            `appointment_number`, `patient_id`, `doctor_id`, `department_id`,
                            `appointment_date`, `appointment_time`, `reason`, `status`,
                            `created_by`, `created_at`
                        ) VALUES (
                            :code, :pid, :doc_id, :dept_id,
                            :app_date, :app_time, :reason, 'confirmed',
                            :by, NOW()
                        )
                    ");
                    $insApp->execute([
                        'code'     => $appNumber,
                        'pid'      => $patientId,
                        'doc_id'   => $doctorId,
                        'dept_id'  => $departmentId,
                        'app_date' => $appDate,
                        'app_time' => $appTime,
                        'reason'   => $reason,
                        'by'       => $currentUser['id']
                    ]);
                    $appId = (int)$db->lastInsertId();

                    // Status history
                    $db->prepare("
                        INSERT INTO `appointment_status_history` (`appointment_id`, `old_status`, `new_status`, `changed_by`, `change_reason`)
                        VALUES (:aid, 'none', 'confirmed', :by, 'Initial booking')
                    ")->execute(['aid' => $appId, 'by' => $currentUser['id']]);

                    // CareTrail Milestone
                    $careTrail->recordEvent(
                        patientId: $patientId,
                        eventType: 'appointment_booked',
                        eventTitle: "Appointment Booked: {$appNumber}",
                        eventDescription: "Appointment confirmed for {$appDate} at {$appTime}. Reason: " . ($reason ?: 'General consultation'),
                        departmentId: $departmentId,
                        sourceTable: 'appointments',
                        sourceId: $appId,
                        createdBy: $currentUser['id']
                    );

                    // TrustLedger
                    $audit->log('CREATE', 'Appointment', $appId, "Scheduled appointment {$appNumber} for Patient #{$patientId} on {$appDate} {$appTime}", userId: $currentUser['id']);

                    $db->commit();
                    set_flash('success', "Appointment {$appNumber} successfully confirmed!");
                }
            } catch (Exception $e) {
                $db->rollBack();
                set_flash('danger', "Booking failed: " . $e->getMessage());
            }
        } else {
            set_flash('danger', "Please provide all required appointment scheduling fields.");
        }
        redirect('index.php');
    }

    // Action 2: Direct Check-In from Appointment -> QueueFlow
    if ($action === 'checkin_appointment') {
        $appId = (int)($_POST['appointment_id'] ?? 0);
        $sel = $db->prepare("SELECT * FROM `appointments` WHERE id = :id");
        $sel->execute(['id' => $appId]);
        $app = $sel->fetch();

        if ($app) {
            $res = $queueService->checkInPatient(
                patientId: (int)$app['patient_id'],
                doctorId: (int)$app['doctor_id'],
                departmentId: (int)$app['department_id'],
                appointmentId: $appId,
                priority: 'normal',
                createdBy: $currentUser['id']
            );
            if ($res['success']) {
                set_flash('success', "Appointment checked in! Token #{$res['token_number']} issued. Redirected to QueueFlow.");
                redirect('../queueflow/index.php');
            } else {
                set_flash('warning', $res['message']);
            }
        }
        redirect('index.php');
    }

    // Action 3: Cancel Appointment
    if ($action === 'cancel_appointment') {
        $appId  = (int)($_POST['appointment_id'] ?? 0);
        $reason = sanitize_input($_POST['cancel_reason'] ?? 'Cancelled by staff');

        $upd = $db->prepare("UPDATE `appointments` SET `status` = 'cancelled' WHERE id = :id");
        $upd->execute(['id' => $appId]);

        $db->prepare("
            INSERT INTO `appointment_status_history` (`appointment_id`, `old_status`, `new_status`, `changed_by`, `change_reason`)
            VALUES (:aid, 'confirmed', 'cancelled', :by, :reason)
        ")->execute(['aid' => $appId, 'by' => $currentUser['id'], 'reason' => $reason]);

        $audit->log('STATUS_CHANGE', 'Appointment', $appId, "Cancelled appointment #{$appId}. Reason: {$reason}", userId: $currentUser['id']);
        set_flash('info', "Appointment marked as cancelled.");
        redirect('index.php');
    }
}

// Filters
$filterDate = $_GET['date'] ?? '';
$filterStatus = $_GET['status'] ?? '';
$filterDoctor = !empty($_GET['doctor_id']) ? (int)$_GET['doctor_id'] : null;
$filterDept   = !empty($_GET['dept_id']) ? (int)$_GET['dept_id'] : null;

// Patient filter (if coming from patient list or logged in as patient)
$filterPatient = !empty($_GET['patient_id']) ? (int)$_GET['patient_id'] : null;
if (has_role('patient') && !empty($currentUser['patient_id'])) {
    $filterPatient = $currentUser['patient_id'];
}

$sql = "
    SELECT 
        a.*,
        p.patient_id AS patient_code,
        p.first_name AS patient_first_name,
        p.last_name AS patient_last_name,
        p.phone AS patient_phone,
        d.first_name AS doctor_first_name,
        d.last_name AS doctor_last_name,
        d.room_no AS doctor_room,
        dep.name AS department_name
    FROM `appointments` a
    JOIN `patient_profiles` p ON a.patient_id = p.id
    JOIN `doctor_profiles` d ON a.doctor_id = d.id
    JOIN `departments` dep ON a.department_id = dep.id
    WHERE 1=1
";
$params = [];

if ($filterPatient) {
    $sql .= " AND a.patient_id = :pid";
    $params['pid'] = $filterPatient;
}
if ($filterDoctor) {
    $sql .= " AND a.doctor_id = :doc_id";
    $params['doc_id'] = $filterDoctor;
}
if ($filterDept) {
    $sql .= " AND a.department_id = :dept_id";
    $params['dept_id'] = $filterDept;
}
if ($filterDate) {
    $sql .= " AND a.appointment_date = :app_date";
    $params['app_date'] = $filterDate;
}
if ($filterStatus) {
    $sql .= " AND a.status = :status";
    $params['status'] = $filterStatus;
}

$sql .= " ORDER BY a.appointment_date DESC, a.appointment_time ASC LIMIT 60";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$appointments = $stmt->fetchAll();

// Lists for modal & filters (only active departments)
$patientsList = $db->query("SELECT id, patient_id, first_name, last_name, phone FROM `patient_profiles` ORDER BY first_name ASC")->fetchAll();
$activeDepartmentsList = $db->query("SELECT id, dept_code, name FROM `departments` WHERE `status` = 'active' ORDER BY name ASC")->fetchAll();
$doctorsList = $db->query("
    SELECT d.id, d.first_name, d.last_name, dep.name AS dept_name, dep.id AS dept_id 
    FROM `doctor_profiles` d 
    JOIN `departments` dep ON d.department_id = dep.id 
    WHERE d.availability_status != 'off_duty' AND dep.status = 'active'
")->fetchAll();

$pageTitle = 'Appointment Management & Booking';
require_once __DIR__ . '/../../app/views/layouts/header.php';
?>

<!-- Title & Action Bar -->
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div>
        <h4 class="fw-bold mb-1">Appointment Management & Scheduling</h4>
        <p class="text-secondary small mb-0">Doctor schedule coordination, concurrency collision protection, and QueueFlow token issuance</p>
    </div>
    
    <div class="d-flex align-items-center gap-2">
        <button type="button" class="btn btn-nexus-primary" data-bs-toggle="modal" data-bs-target="#bookAppointmentModal">
            <i class="bi bi-calendar-plus-fill me-1"></i>Book New Appointment
        </button>
    </div>
</div>

<!-- Filters Bar -->
<div class="nexus-card mb-4 p-3">
    <form method="GET" action="index.php" class="row g-2 align-items-center">
        <div class="col-md-2">
            <label class="form-label small text-secondary fw-semibold mb-1">Filter Date</label>
            <input type="date" name="date" class="form-control" value="<?= e($filterDate) ?>" onchange="this.form.submit()">
        </div>
        <div class="col-md-2">
            <label class="form-label small text-secondary fw-semibold mb-1">Status</label>
            <select name="status" class="form-select" onchange="this.form.submit()">
                <option value="">All Statuses</option>
                <option value="pending" <?= $filterStatus === 'pending' ? 'selected' : '' ?>>Pending</option>
                <option value="confirmed" <?= $filterStatus === 'confirmed' ? 'selected' : '' ?>>Confirmed</option>
                <option value="checked_in" <?= $filterStatus === 'checked_in' ? 'selected' : '' ?>>Checked In</option>
                <option value="in_consultation" <?= $filterStatus === 'in_consultation' ? 'selected' : '' ?>>In Consultation</option>
                <option value="completed" <?= $filterStatus === 'completed' ? 'selected' : '' ?>>Completed</option>
                <option value="cancelled" <?= $filterStatus === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label small text-secondary fw-semibold mb-1">Department</label>
            <select name="dept_id" class="form-select" onchange="this.form.submit()">
                <option value="">All Active Departments (<?= count($activeDepartmentsList) ?>)</option>
                <?php foreach ($activeDepartmentsList as $ad): ?>
                    <option value="<?= $ad['id'] ?>" <?= $filterDept === (int)$ad['id'] ? 'selected' : '' ?>>
                        <?= e($ad['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label small text-secondary fw-semibold mb-1">Live Search Table</label>
            <input type="text" class="form-control" placeholder="Search patient, doctor, or code..." data-table-search="appointmentsTable">
        </div>
        <div class="col-md-2 d-flex align-items-end">
            <a href="index.php" class="btn btn-outline-secondary w-100" style="margin-top: 1.5rem;">Reset</a>
        </div>
    </form>
</div>

<!-- Appointments Table Card -->
<div class="nexus-card">
    <div class="nexus-card-header">
        <h6 class="fw-bold mb-0">Scheduled Consultations (<?= count($appointments) ?>)</h6>
        <span class="badge bg-light text-secondary border">Synchronized Schedule</span>
    </div>

    <div class="table-responsive">
        <table class="table-nexus" id="appointmentsTable">
            <thead>
                <tr>
                    <th>Appt #</th>
                    <th>Patient Details</th>
                    <th>Doctor & Clinic</th>
                    <th>Date & Time</th>
                    <th>Reason for Visit</th>
                    <th>Status</th>
                    <th class="text-end" style="width: 170px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($appointments)): ?>
                    <tr>
                        <td colspan="7" class="text-center py-4 text-muted">No appointments found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($appointments as $a): ?>
                        <tr>
                            <td>
                                <code><?= e($a['appointment_number']) ?></code>
                            </td>
                            <td>
                                <strong><?= e($a['patient_first_name'] . ' ' . $a['patient_last_name']) ?></strong>
                                <small class="text-muted d-block"><?= e($a['patient_code']) ?> &bull; <?= e($a['patient_phone']) ?></small>
                            </td>
                            <td>
                                <strong>Dr. <?= e($a['doctor_first_name'] . ' ' . $a['doctor_last_name']) ?></strong>
                                <small class="text-muted d-block"><?= e($a['doctor_room'] ?? 'OPD') ?> &bull; <?= e($a['department_name']) ?></small>
                            </td>
                            <td>
                                <strong><?= format_datetime($a['appointment_date'], 'd M Y') ?></strong>
                                <small class="text-muted d-block"><?= format_datetime($a['appointment_time'], 'h:i A') ?></small>
                            </td>
                            <td>
                                <span class="text-secondary small"><?= e($a['reason'] ?: 'Routine consultation') ?></span>
                            </td>
                            <td>
                                <span class="badge badge-nexus badge-status-<?= e($a['status']) ?>">
                                    <?= ucwords(str_replace('_', ' ', e($a['status']))) ?>
                                </span>
                            </td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    <?php if ($a['status'] === 'confirmed'): ?>
                                        <form method="POST" action="index.php" class="d-inline">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="checkin_appointment">
                                            <input type="hidden" name="appointment_id" value="<?= $a['id'] ?>">
                                            <button type="submit" class="btn btn-primary btn-sm" title="Check-In to QueueFlow">
                                                <i class="bi bi-box-arrow-in-right"></i> Check In
                                            </button>
                                        </form>

                                        <button type="button" class="btn btn-outline-danger btn-sm" 
                                                onclick="openCancelModal(<?= $a['id'] ?>, '<?= e($a['appointment_number']) ?>')">
                                            <i class="bi bi-x-circle"></i>
                                        </button>
                                    <?php elseif ($a['status'] === 'checked_in'): ?>
                                        <a href="../queueflow/index.php" class="btn btn-outline-info btn-sm">
                                            <i class="bi bi-hourglass-split"></i> In Queue
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted small">—</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: Book New Appointment -->
<div class="modal fade" id="bookAppointmentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title fw-bold fs-6">
                    <i class="bi bi-calendar-plus text-info me-2"></i>Schedule Patient Appointment
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="index.php">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="book_appointment">

                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Select Patient *</label>
                        <select name="patient_id" class="form-select" required>
                            <option value="">Choose Patient...</option>
                            <?php foreach ($patientsList as $p): ?>
                                <option value="<?= $p['id'] ?>" <?= $filterPatient === (int)$p['id'] ? 'selected' : '' ?>>
                                    <?= e($p['first_name'] . ' ' . $p['last_name']) ?> (<?= e($p['patient_id']) ?>) &bull; <?= e($p['phone']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Select Doctor & Department *</label>
                        <select name="doctor_id" id="apptDoctorSelect" class="form-select" required onchange="updateApptDeptField(this)">
                            <option value="">Choose Doctor...</option>
                            <?php foreach ($doctorsList as $doc): ?>
                                <option value="<?= $doc['id'] ?>" data-dept="<?= $doc['dept_id'] ?>">
                                    Dr. <?= e($doc['first_name'] . ' ' . $doc['last_name']) ?> — <?= e($doc['dept_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <input type="hidden" name="department_id" id="apptDeptId" value="">
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Appointment Date *</label>
                            <input type="date" name="appointment_date" class="form-control" required min="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Time Slot *</label>
                            <select name="appointment_time" class="form-select" required>
                                <option value="09:00:00">09:00 AM</option>
                                <option value="09:30:00">09:30 AM</option>
                                <option value="10:00:00" selected>10:00 AM</option>
                                <option value="10:30:00">10:30 AM</option>
                                <option value="11:00:00">11:00 AM</option>
                                <option value="11:30:00">11:30 AM</option>
                                <option value="12:00:00">12:00 PM</option>
                                <option value="14:00:00">02:00 PM</option>
                                <option value="14:30:00">02:30 PM</option>
                                <option value="15:00:00">03:00 PM</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Reason for Consultation</label>
                        <input type="text" name="reason" class="form-control" placeholder="e.g. Chest heaviness, routine cardiac review, seasonal fever">
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-nexus-primary btn-sm px-4">Confirm Appointment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Cancel Appointment -->
<div class="modal fade" id="cancelApptModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title fw-bold fs-6">Cancel Appointment</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="index.php">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="cancel_appointment">
                <input type="hidden" name="appointment_id" id="cancelApptId" value="">

                <div class="modal-body p-4">
                    <p class="text-secondary small">Are you sure you want to cancel appointment <strong id="cancelApptCode"></strong>?</p>
                    <div class="mb-2">
                        <label class="form-label small fw-semibold">Cancellation Reason *</label>
                        <input type="text" name="cancel_reason" class="form-control" required placeholder="e.g. Patient requested reschedule, doctor unavailable">
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-danger btn-sm">Confirm Cancellation</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function updateApptDeptField(select) {
        const selectedOpt = select.options[select.selectedIndex];
        const deptId = selectedOpt.getAttribute('data-dept') || '';
        document.getElementById('apptDeptId').value = deptId;
    }

    function openCancelModal(id, code) {
        document.getElementById('cancelApptId').value = id;
        document.getElementById('cancelApptCode').textContent = code;
        const modal = new bootstrap.Modal(document.getElementById('cancelApptModal'));
        modal.show();
    }
</script>

<?php require_once __DIR__ . '/../../app/views/layouts/footer.php'; ?>
