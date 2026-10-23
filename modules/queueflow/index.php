<?php
/**
 * KVM Hospital Management System — NexusCare
 * Advanced Feature 1: QueueFlow — Live Patient Flow Intelligence
 */

declare(strict_types=1);

require_once __DIR__ . '/../../app/config/app.php';
require_once __DIR__ . '/../../app/config/database.php';
require_once __DIR__ . '/../../app/helpers/functions.php';
require_once __DIR__ . '/../../app/middleware/auth.php';
require_once __DIR__ . '/../../app/middleware/csrf.php';
require_once __DIR__ . '/../../app/services/QueueService.php';

require_auth();

$db = get_db();
$queueService = new QueueService($db);
$currentUser = current_user();

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    // Action 1: Check-in patient & generate token
    if ($action === 'checkin') {
        $patientId    = (int)($_POST['patient_id'] ?? 0);
        $doctorId     = (int)($_POST['doctor_id'] ?? 0);
        $departmentId = (int)($_POST['department_id'] ?? 0);
        $appointmentId = !empty($_POST['appointment_id']) ? (int)$_POST['appointment_id'] : null;
        $priority     = $_POST['priority_level'] ?? 'normal';
        $reason       = !empty($_POST['priority_reason']) ? sanitize_input($_POST['priority_reason']) : null;

        if ($patientId && $doctorId && $departmentId) {
            $res = $queueService->checkInPatient($patientId, $doctorId, $departmentId, $appointmentId, $priority, $reason);
            if ($res['success']) {
                set_flash('success', $res['message']);
            } else {
                set_flash('warning', $res['message']);
            }
        } else {
            set_flash('danger', 'Please select valid Patient, Doctor, and Department.');
        }
        redirect('index.php');
    }

    // Action 2: Call patient
    if ($action === 'call_patient') {
        $queueId  = (int)($_POST['queue_entry_id'] ?? 0);
        $doctorId = (int)($_POST['doctor_id'] ?? 0);
        if ($queueService->callPatient($queueId, $doctorId)) {
            set_flash('info', "Patient called into consultation room.");
        }
        redirect('index.php');
    }

    // Action 3: Start Consultation
    if ($action === 'start_consultation') {
        $queueId  = (int)($_POST['queue_entry_id'] ?? 0);
        $doctorId = (int)($_POST['doctor_id'] ?? 0);
        if ($queueService->startConsultation($queueId, $doctorId)) {
            set_flash('success', "Consultation session started.");
        }
        redirect('index.php');
    }

    // Action 4: Complete Consultation
    if ($action === 'complete_consultation') {
        $queueId  = (int)($_POST['queue_entry_id'] ?? 0);
        $doctorId = (int)($_POST['doctor_id'] ?? 0);
        if ($queueService->completeConsultation($queueId, $doctorId)) {
            set_flash('success', "Consultation completed successfully.");
        }
        redirect('index.php');
    }

    // Action 5: Priority Override
    if ($action === 'update_priority') {
        $queueId  = (int)($_POST['queue_entry_id'] ?? 0);
        $priority = $_POST['priority_level'] ?? 'urgent';
        $reason   = sanitize_input($_POST['override_reason'] ?? '');

        if ($queueId && !empty($reason)) {
            if ($queueService->updatePriority($queueId, $priority, $reason, $currentUser['id'])) {
                set_flash('warning', "Queue priority updated to " . strtoupper($priority) . ". Reason recorded in TrustLedger.");
            } else {
                set_flash('danger', "Failed to update queue priority.");
            }
        } else {
            set_flash('danger', "Documented justification reason is mandatory for priority escalation.");
        }
        redirect('index.php');
    }
}

// Fetch filter parameters
$filterDoctor = !empty($_GET['doctor_id']) ? (int)$_GET['doctor_id'] : null;
$filterDept   = !empty($_GET['department_id']) ? (int)$_GET['department_id'] : null;

// If user is a Doctor, prefilter to themselves by default
if (has_role('doctor') && !empty($currentUser['doctor_id']) && !$filterDoctor) {
    $filterDoctor = $currentUser['doctor_id'];
}

$entries = $queueService->getLiveQueue($filterDoctor, $filterDept);

// Summary statistics
$waitingCount = 0;
$inConsultCount = 0;
$calledCount = 0;
$totalWait = 0;
foreach ($entries as $e) {
    if ($e['status'] === 'waiting') {
        $waitingCount++;
        $totalWait += (int)$e['estimated_wait_time_minutes'];
    } elseif ($e['status'] === 'in_consultation') {
        $inConsultCount++;
    } elseif ($e['status'] === 'called') {
        $calledCount++;
    }
}
$avgWait = $waitingCount > 0 ? round($totalWait / $waitingCount) : 0;

// Fetch lists for modals
$doctorsList = $db->query("
    SELECT d.id, d.first_name, d.last_name, d.doctor_code, dep.name AS dept_name, dep.id AS dept_id 
    FROM `doctor_profiles` d 
    JOIN `departments` dep ON d.department_id = dep.id 
    WHERE d.availability_status != 'off_duty'
")->fetchAll();

$patientsList = $db->query("SELECT id, patient_id, first_name, last_name, phone FROM `patient_profiles` ORDER BY first_name ASC LIMIT 50")->fetchAll();

$pageTitle = 'QueueFlow — Live Patient Flow Intelligence';
require_once __DIR__ . '/../../app/views/layouts/header.php';
?>

<!-- Title and Top Action Bar -->
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div>
        <h4 class="fw-bold mb-1">QueueFlow — Live Patient Flow Intelligence</h4>
        <p class="text-secondary small mb-0">Dynamic token ordering, wait-time prediction algorithm, and auditable priority controls</p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 py-2 px-3">
            <i class="bi bi-broadcast me-1"></i>Live Polling Active (12s)
        </span>
        <?php if (has_role('receptionist', 'nurse', 'hospital_admin', 'super_admin')): ?>
            <button type="button" class="btn btn-nexus-primary" data-bs-toggle="modal" data-bs-target="#checkInModal">
                <i class="bi bi-person-check-fill me-1"></i>Check-In / Issue Token
            </button>
        <?php endif; ?>
    </div>
</div>

<!-- QueueFlow KPI Cards -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-lg-3">
        <div class="stat-widget">
            <div class="stat-icon emerald">
                <i class="bi bi-person-video2"></i>
            </div>
            <div>
                <div class="stat-value text-success"><?= $inConsultCount ?></div>
                <div class="stat-label">In Consultation</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="stat-widget">
            <div class="stat-icon amber">
                <i class="bi bi-megaphone-fill"></i>
            </div>
            <div>
                <div class="stat-value text-warning"><?= $calledCount ?></div>
                <div class="stat-label">Currently Called</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="stat-widget">
            <div class="stat-icon blue">
                <i class="bi bi-people-fill"></i>
            </div>
            <div>
                <div class="stat-value text-primary"><?= $waitingCount ?></div>
                <div class="stat-label">Patients in Waiting Lounge</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="stat-widget">
            <div class="stat-icon teal">
                <i class="bi bi-clock-history"></i>
            </div>
            <div>
                <div class="stat-value"><?= $avgWait ?> <span class="fs-6 text-muted">mins</span></div>
                <div class="stat-label">Est. Average Wait</div>
            </div>
        </div>
    </div>
</div>

<!-- Filter Bar -->
<div class="nexus-card mb-4">
    <div class="nexus-card-body p-3">
        <form method="GET" action="index.php" class="row g-2 align-items-center">
            <div class="col-md-5">
                <label class="form-label small text-secondary fw-semibold mb-1">Filter by Attending Doctor</label>
                <select name="doctor_id" class="form-select" onchange="this.form.submit()">
                    <option value="">All Hospital Doctors & Clinics</option>
                    <?php foreach ($doctorsList as $doc): ?>
                        <option value="<?= $doc['id'] ?>" <?= $filterDoctor === (int)$doc['id'] ? 'selected' : '' ?>>
                            Dr. <?= e($doc['first_name'] . ' ' . $doc['last_name']) ?> (<?= e($doc['dept_name']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-5">
                <label class="form-label small text-secondary fw-semibold mb-1">Live Table Search</label>
                <input type="text" class="form-control" placeholder="Search patient name, token #, or code..." data-table-search="queueTable">
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <a href="index.php" class="btn btn-outline-secondary w-100" style="margin-top: 1.5rem;">Reset Filter</a>
            </div>
        </form>
    </div>
</div>

<!-- Main Live Queue Table Card -->
<div class="nexus-card">
    <div class="nexus-card-header">
        <div class="d-flex align-items-center gap-2">
            <i class="bi bi-table text-primary"></i>
            <h6 class="fw-bold mb-0">Live Operational Queue — Today (<?= date('d M Y') ?>)</h6>
        </div>
        <span class="badge bg-light text-secondary border">Total Tokens: <?= count($entries) ?></span>
    </div>
    <div class="table-responsive">
        <table class="table-nexus" id="queueTable">
            <thead>
                <tr>
                    <th style="width: 100px;">Token #</th>
                    <th>Patient Details</th>
                    <th>Priority Level</th>
                    <th>Doctor & Room</th>
                    <th>Queue Status</th>
                    <th>Est. Waiting</th>
                    <th>Check-In Time</th>
                    <th class="text-end" style="width: 190px;">Flow Actions</th>
                </tr>
            </thead>
            <tbody id="queueFlowMonitor" data-doctor-id="<?= e((string)$filterDoctor) ?>" data-dept-id="<?= e((string)$filterDept) ?>">
                <?php if (empty($entries)): ?>
                    <tr>
                        <td colspan="8" class="text-center py-4 text-muted">
                            <i class="bi bi-inbox fs-3 d-block mb-1"></i>
                            No active queue tokens for today. Click "Check-In / Issue Token" to generate a token.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($entries as $q): ?>
                        <tr>
                            <td>
                                <span class="fs-5 fw-bold <?= $q['status'] === 'in_consultation' ? 'text-success' : ($q['status'] === 'called' ? 'text-warning' : 'text-primary') ?>">
                                    #<?= e($q['token_number']) ?>
                                </span>
                            </td>
                            <td>
                                <strong><?= e($q['patient_first_name'] . ' ' . $q['patient_last_name']) ?></strong>
                                <small class="text-muted d-block"><?= e($q['patient_code']) ?> &bull; <?= e($q['patient_age']) ?>y / <?= ucfirst(e($q['gender'])) ?></small>
                            </td>
                            <td>
                                <span class="badge badge-nexus badge-priority-<?= e($q['priority_level']) ?>">
                                    <?= strtoupper(e($q['priority_level'])) ?>
                                </span>
                                <?php if ($q['priority_reason']): ?>
                                    <small class="text-danger d-block" style="font-size: 0.7rem;" title="<?= e($q['priority_reason']) ?>">
                                        <i class="bi bi-info-circle me-1"></i><?= e(substr($q['priority_reason'], 0, 30)) ?>...
                                    </small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong>Dr. <?= e($q['doctor_first_name'] . ' ' . $q['doctor_last_name']) ?></strong>
                                <small class="text-muted d-block"><?= e($q['doctor_room'] ?? 'OPD') ?> &bull; <?= e($q['department_name']) ?></small>
                            </td>
                            <td>
                                <span class="badge badge-nexus badge-status-<?= e($q['status']) ?>">
                                    <?= ucwords(str_replace('_', ' ', e($q['status']))) ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($q['status'] === 'in_consultation'): ?>
                                    <span class="text-success fw-bold"><i class="bi bi-check2-circle me-1"></i>In Room</span>
                                <?php elseif ($q['status'] === 'completed'): ?>
                                    <span class="text-muted">—</span>
                                <?php else: ?>
                                    <span class="fw-semibold text-secondary">~<?= (int)$q['estimated_wait_time_minutes'] ?> mins</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <small class="text-muted"><?= format_datetime($q['check_in_time'], 'h:i A') ?></small>
                            </td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    <?php if ($q['status'] === 'waiting'): ?>
                                        <form method="POST" action="index.php" class="d-inline">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="call_patient">
                                            <input type="hidden" name="queue_entry_id" value="<?= $q['id'] ?>">
                                            <input type="hidden" name="doctor_id" value="<?= $q['doctor_id'] ?>">
                                            <button type="submit" class="btn btn-outline-warning btn-sm" title="Call Patient">
                                                <i class="bi bi-megaphone"></i> Call
                                            </button>
                                        </form>
                                    <?php elseif ($q['status'] === 'called'): ?>
                                        <form method="POST" action="index.php" class="d-inline">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="start_consultation">
                                            <input type="hidden" name="queue_entry_id" value="<?= $q['id'] ?>">
                                            <input type="hidden" name="doctor_id" value="<?= $q['doctor_id'] ?>">
                                            <button type="submit" class="btn btn-outline-success btn-sm" title="Start Consultation">
                                                <i class="bi bi-play-circle"></i> Start
                                            </button>
                                        </form>
                                    <?php elseif ($q['status'] === 'in_consultation'): ?>
                                        <form method="POST" action="index.php" class="d-inline">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="complete_consultation">
                                            <input type="hidden" name="queue_entry_id" value="<?= $q['id'] ?>">
                                            <input type="hidden" name="doctor_id" value="<?= $q['doctor_id'] ?>">
                                            <button type="submit" class="btn btn-success btn-sm" title="Finish Consultation">
                                                <i class="bi bi-check-circle"></i> Finish
                                            </button>
                                        </form>
                                    <?php endif; ?>

                                    <button type="button" class="btn btn-outline-danger btn-sm" 
                                            onclick="openPriorityModal(<?= $q['id'] ?>, <?= $q['token_number'] ?>, '<?= e($q['priority_level']) ?>')">
                                        <i class="bi bi-arrow-up-circle"></i> Priority
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal 1: Check-in Patient / Generate Token -->
<div class="modal fade" id="checkInModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title fw-bold fs-6">
                    <i class="bi bi-ticket-perforated me-2 text-info"></i>Check-In Patient & Issue Token
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="index.php">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="checkin">

                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Select Patient *</label>
                        <select name="patient_id" class="form-select" required>
                            <option value="">Choose Patient...</option>
                            <?php foreach ($patientsList as $p): ?>
                                <option value="<?= $p['id'] ?>">
                                    <?= e($p['first_name'] . ' ' . $p['last_name']) ?> (<?= e($p['patient_id']) ?>) &bull; <?= e($p['phone']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Select Doctor & Department *</label>
                        <select name="doctor_id" id="modalDoctorSelect" class="form-select" required onchange="updateDeptHiddenField(this)">
                            <option value="">Choose Doctor...</option>
                            <?php foreach ($doctorsList as $doc): ?>
                                <option value="<?= $doc['id'] ?>" data-dept="<?= $doc['dept_id'] ?>">
                                    Dr. <?= e($doc['first_name'] . ' ' . $doc['last_name']) ?> — <?= e($doc['dept_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <input type="hidden" name="department_id" id="modalDepartmentId" value="">
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Triage Priority Level</label>
                        <select name="priority_level" class="form-select">
                            <option value="normal" selected>Normal Queue</option>
                            <option value="urgent">Urgent (Elevated vitals / acute pain)</option>
                            <option value="emergency">Emergency / Stat (Trauma / Resuscitation)</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Clinical Priority Note (Optional)</label>
                        <input type="text" name="priority_reason" class="form-control" placeholder="e.g. Fever 102F, severe cough, chest tightness">
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-nexus-primary btn-sm px-3">
                        <i class="bi bi-check-circle me-1"></i>Generate Token
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal 2: Override Priority with Mandatory Reason -->
<div class="modal fade" id="priorityModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title fw-bold fs-6">
                    <i class="bi bi-shield-exclamation me-2"></i>Override Queue Priority
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="index.php">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="update_priority">
                <input type="hidden" name="queue_entry_id" id="modalPriorityQid" value="">

                <div class="modal-body p-4">
                    <div class="alert alert-warning py-2 small mb-3">
                        <i class="bi bi-info-circle-fill me-1"></i>
                        Priority escalations bypass the sequential token order and are audited in TrustLedger.
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Escalation Target for Token #<span id="priorityTokenNum"></span></label>
                        <select name="priority_level" class="form-select" required>
                            <option value="urgent">Urgent</option>
                            <option value="emergency">Emergency</option>
                            <option value="normal">Normal (Downgrade)</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Mandatory Clinical Justification *</label>
                        <textarea name="override_reason" class="form-control" rows="3" required placeholder="State objective clinical criteria (e.g. Desaturation SpO2 88%, acute trauma, diaphoresis)"></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger btn-sm px-3">Confirm Priority Override</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function updateDeptHiddenField(select) {
        const selectedOpt = select.options[select.selectedIndex];
        const deptId = selectedOpt.getAttribute('data-dept') || '';
        document.getElementById('modalDepartmentId').value = deptId;
    }

    function openPriorityModal(qid, tokenNum, currentPriority) {
        document.getElementById('modalPriorityQid').value = qid;
        document.getElementById('priorityTokenNum').textContent = tokenNum;
        const modal = new bootstrap.Modal(document.getElementById('priorityModal'));
        modal.show();
    }
</script>

<?php require_once __DIR__ . '/../../app/views/layouts/footer.php'; ?>
