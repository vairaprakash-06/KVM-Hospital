<?php
/**
 * KVM Hospital Management System — NexusCare
 * Doctor Clinical Workspace & Consultation Dashboard
 */

declare(strict_types=1);

require_once __DIR__ . '/../../app/config/app.php';
require_once __DIR__ . '/../../app/config/database.php';
require_once __DIR__ . '/../../app/helpers/functions.php';
require_once __DIR__ . '/../../app/middleware/permissions.php';
require_once __DIR__ . '/../../app/middleware/csrf.php';
require_once __DIR__ . '/../../app/services/QueueService.php';
require_once __DIR__ . '/../../app/services/CareTrailService.php';
require_once __DIR__ . '/../../app/services/AuditService.php';

require_role('doctor', 'hospital_admin', 'super_admin');

$db = get_db();
$currentUser = current_user();
$queueService = new QueueService($db);
$careTrail = new CareTrailService($db);
$audit = new AuditService($db);

// Determine active doctor profile
$doctorId = $currentUser['doctor_id'] ?? null;
if (!$doctorId) {
    // Fallback: pick first doctor
    $dStmt = $db->query("SELECT id FROM `doctor_profiles` LIMIT 1");
    $doctorId = (int)$dStmt->fetchColumn();
}

$docProfile = $db->prepare("
    SELECT dp.*, d.name AS dept_name 
    FROM `doctor_profiles` dp 
    JOIN `departments` d ON dp.department_id = d.id 
    WHERE dp.id = :id
");
$docProfile->execute(['id' => $doctorId]);
$doctor = $docProfile->fetch();

// Handle Consultation Recording POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'save_consultation') {
        $patientId = (int)($_POST['patient_id'] ?? 0);
        $queueId   = (int)($_POST['queue_entry_id'] ?? 0);
        $bp        = sanitize_input($_POST['vitals_bp'] ?? '');
        $pulse     = !empty($_POST['vitals_pulse']) ? (int)$_POST['vitals_pulse'] : null;
        $temp      = !empty($_POST['vitals_temp']) ? (float)$_POST['vitals_temp'] : null;
        $weight    = !empty($_POST['vitals_weight']) ? (float)$_POST['vitals_weight'] : null;
        $spo2      = !empty($_POST['vitals_spo2']) ? (int)$_POST['vitals_spo2'] : null;
        $notes     = sanitize_input($_POST['clinical_notes'] ?? '');
        $plan      = sanitize_input($_POST['treatment_plan'] ?? '');
        $diagCode  = sanitize_input($_POST['diagnosis_code'] ?? 'R69');
        $diagTitle = sanitize_input($_POST['diagnosis_title'] ?? 'Illness unspecified');
        $medId     = !empty($_POST['medicine_id']) ? (int)$_POST['medicine_id'] : null;
        $dosage    = sanitize_input($_POST['dosage'] ?? '500mg');
        $freq      = sanitize_input($_POST['frequency'] ?? '1-0-1');
        $duration  = (int)($_POST['duration_days'] ?? 5);

        if ($patientId && !empty($notes)) {
            $db->beginTransaction();
            try {
                // Find or create encounter
                $encStmt = $db->prepare("
                    SELECT id FROM `patient_encounters` 
                    WHERE `patient_id` = :pid AND `status` = 'active' 
                    ORDER BY id DESC LIMIT 1
                ");
                $encStmt->execute(['pid' => $patientId]);
                $encId = (int)$encStmt->fetchColumn();

                if ($encId === 0) {
                    $encCode = generate_encounter_number($db);
                    $insEnc = $db->prepare("
                        INSERT INTO `patient_encounters` (
                            `encounter_number`, `patient_id`, `doctor_id`, `department_id`,
                            `encounter_type`, `status`, `started_at`
                        ) VALUES (
                            :c, :pid, :doc, :dept, 'outpatient', 'active', NOW()
                        )
                    ");
                    $insEnc->execute([
                        'c' => $encCode, 'pid' => $patientId, 'doc' => $doctorId, 'dept' => (int)$doctor['department_id']
                    ]);
                    $encId = (int)$db->lastInsertId();
                }

                // Insert Consultation
                $insCon = $db->prepare("
                    INSERT INTO `consultations` (
                        `encounter_id`, `patient_id`, `doctor_id`, `vitals_bp`, `vitals_pulse`,
                        `vitals_temp`, `vitals_weight`, `vitals_spo2`, `clinical_notes`, `treatment_plan`, `created_at`
                    ) VALUES (
                        :enc, :pid, :doc, :bp, :pulse,
                        :temp, :weight, :spo2, :notes, :plan, NOW()
                    )
                ");
                $insCon->execute([
                    'enc'    => $encId,
                    'pid'    => $patientId,
                    'doc'    => $doctorId,
                    'bp'     => $bp,
                    'pulse'  => $pulse,
                    'temp'   => $temp,
                    'weight' => $weight,
                    'spo2'   => $spo2,
                    'notes'  => $notes,
                    'plan'   => $plan
                ]);
                $consultId = (int)$db->lastInsertId();

                // Insert Diagnosis
                $insDiag = $db->prepare("
                    INSERT INTO `diagnoses` (`consultation_id`, `patient_id`, `diagnosis_code`, `diagnosis_title`, `diagnosis_type`, `created_at`)
                    VALUES (:cid, :pid, :code, :title, 'primary', NOW())
                ");
                $insDiag->execute([
                    'cid'   => $consultId,
                    'pid'   => $patientId,
                    'code'  => $diagCode,
                    'title' => $diagTitle
                ]);

                // Insert Prescription if medicine selected
                if ($medId) {
                    $insPres = $db->prepare("
                        INSERT INTO `prescriptions` (`encounter_id`, `consultation_id`, `patient_id`, `doctor_id`, `notes`, `status`, `created_at`)
                        VALUES (:enc, :cid, :pid, :doc, :notes, 'pending', NOW())
                    ");
                    $insPres->execute([
                        'enc'   => $encId,
                        'cid'   => $consultId,
                        'pid'   => $patientId,
                        'doc'   => $doctorId,
                        'notes' => "Dispense as prescribed by Dr. " . ($doctor['last_name'] ?? '')
                    ]);
                    $presId = (int)$db->lastInsertId();

                    $insItem = $db->prepare("
                        INSERT INTO `prescription_items` (`prescription_id`, `medicine_id`, `dosage`, `frequency`, `duration_days`, `quantity`, `instructions`)
                        VALUES (:prid, :mid, :dos, :freq, :dur, 10, 'After meals')
                    ");
                    $insItem->execute([
                        'prid' => $presId,
                        'mid'  => $medId,
                        'dos'  => $dosage,
                        'freq' => $freq,
                        'dur'  => $duration
                    ]);
                }

                // If queue entry provided, mark complete in QueueFlow
                if ($queueId) {
                    $queueService->completeConsultation($queueId, $doctorId);
                } else {
                    // CareTrail Event
                    $careTrail->recordEvent(
                        patientId: $patientId,
                        eventType: 'consultation_completed',
                        eventTitle: "Clinical Consultation: Dr. " . ($doctor['last_name'] ?? ''),
                        eventDescription: "Diagnosis: {$diagTitle} ({$diagCode}). Notes: {$notes}",
                        departmentId: (int)$doctor['department_id'],
                        sourceTable: 'consultations',
                        sourceId: $consultId,
                        severity: 'success',
                        createdBy: $currentUser['id']
                    );
                }

                $audit->log('CREATE', 'Consultation', $consultId, "Recorded consultation for Patient #{$patientId}. Diagnosis: {$diagTitle}", userId: $currentUser['id']);

                $db->commit();
                set_flash('success', "Consultation notes, diagnosis, and prescription saved successfully.");
            } catch (Exception $e) {
                $db->rollBack();
                set_flash('danger', "Failed to record consultation: " . $e->getMessage());
            }
        }
        redirect('dashboard.php');
    }
}

// Fetch today's queue for this doctor
$queue = $queueService->getLiveQueue($doctorId);

// Today's appointments for this doctor
$appts = $db->prepare("
    SELECT a.*, p.patient_id AS pcode, p.first_name, p.last_name, p.phone, p.gender, p.dob 
    FROM `appointments` a
    JOIN `patient_profiles` p ON a.patient_id = p.id
    WHERE a.doctor_id = :doc AND a.appointment_date = CURDATE()
    ORDER BY a.appointment_time ASC
");
$appts->execute(['doc' => $doctorId]);
$todayAppointments = $appts->fetchAll();

// Medicines catalog for prescribing
$medicines = $db->query("SELECT id, name, generic_name, unit_price FROM `medicines` WHERE status = 'active' ORDER BY name ASC")->fetchAll();
$patientsList = $db->query("SELECT id, patient_id, first_name, last_name FROM `patient_profiles` ORDER BY first_name ASC")->fetchAll();

$pageTitle = 'Doctor Clinical Workspace — Dr. ' . ($doctor['first_name'] ?? '') . ' ' . ($doctor['last_name'] ?? '');
require_once __DIR__ . '/../../app/views/layouts/header.php';
?>

<!-- Doctor Profile Header -->
<div class="nexus-card mb-4 p-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
        <div class="d-flex align-items-center gap-3">
            <div class="stat-icon teal" style="width: 60px; height: 60px; font-size: 1.8rem;">
                <i class="bi bi-heart-pulse-fill"></i>
            </div>
            <div>
                <h4 class="fw-bold mb-1 text-dark">Dr. <?= e($doctor['first_name'] . ' ' . $doctor['last_name']) ?></h4>
                <div class="d-flex flex-wrap gap-2 small text-secondary">
                    <span><i class="bi bi-award me-1 text-primary"></i><?= e($doctor['specialization']) ?></span>
                    <span>&bull;</span>
                    <span><i class="bi bi-hospital me-1 text-primary"></i><?= e($doctor['dept_name']) ?> (<?= e($doctor['room_no'] ?? 'OPD') ?>)</span>
                    <span>&bull;</span>
                    <span>License: <code><?= e($doctor['license_number']) ?></code></span>
                </div>
            </div>
        </div>
        
        <div class="d-flex align-items-center gap-2">
            <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 py-2 px-3">
                <i class="bi bi-broadcast me-1"></i>QueueFlow Sync Active
            </span>
            <button type="button" class="btn btn-nexus-primary" data-bs-toggle="modal" data-bs-target="#newConsultationModal">
                <i class="bi bi-clipboard2-pulse me-1"></i>New Patient Encounter
            </button>
        </div>
    </div>
</div>

<!-- QueueFlow Live Section for Doctor -->
<div class="row g-4 mb-4">
    <div class="col-lg-7">
        <div class="nexus-card h-100">
            <div class="nexus-card-header">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-ticket-detailed text-primary"></i>
                    <h6 class="fw-bold mb-0">My Clinic Live Patient Queue</h6>
                </div>
                <a href="../queueflow/index.php" class="small text-decoration-none">QueueFlow Full Monitor</a>
            </div>
            <div class="table-responsive">
                <table class="table-nexus">
                    <thead>
                        <tr>
                            <th>Token #</th>
                            <th>Patient Name</th>
                            <th>Priority</th>
                            <th>Status</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($queue)): ?>
                            <tr><td colspan="5" class="text-center py-4 text-muted">No patients waiting in queue today.</td></tr>
                        <?php else: ?>
                            <?php foreach ($queue as $q): ?>
                                <tr>
                                    <td><strong class="fs-6 text-primary">#<?= e($q['token_number']) ?></strong></td>
                                    <td>
                                        <strong><?= e($q['patient_first_name'] . ' ' . $q['patient_last_name']) ?></strong>
                                        <small class="text-muted d-block"><?= e($q['patient_code']) ?></small>
                                    </td>
                                    <td>
                                        <span class="badge badge-nexus badge-priority-<?= e($q['priority_level']) ?>">
                                            <?= strtoupper(e($q['priority_level'])) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge badge-nexus badge-status-<?= e($q['status']) ?>">
                                            <?= ucwords(str_replace('_', ' ', e($q['status']))) ?>
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <?php if ($q['status'] === 'waiting'): ?>
                                            <form method="POST" action="../queueflow/index.php" class="d-inline">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="call_patient">
                                                <input type="hidden" name="queue_entry_id" value="<?= $q['id'] ?>">
                                                <input type="hidden" name="doctor_id" value="<?= $doctorId ?>">
                                                <button type="submit" class="btn btn-outline-warning btn-sm">Call</button>
                                            </form>
                                        <?php elseif ($q['status'] === 'called'): ?>
                                            <button type="button" class="btn btn-success btn-sm" 
                                                    onclick="openConsultWithPatient(<?= $q['patient_id'] ?>, <?= $q['id'] ?>, '<?= e($q['patient_first_name'] . ' ' . $q['patient_last_name']) ?>')">
                                                <i class="bi bi-play-circle me-1"></i>Consult
                                            </button>
                                        <?php elseif ($q['status'] === 'in_consultation'): ?>
                                            <button type="button" class="btn btn-primary btn-sm" 
                                                    onclick="openConsultWithPatient(<?= $q['patient_id'] ?>, <?= $q['id'] ?>, '<?= e($q['patient_first_name'] . ' ' . $q['patient_last_name']) ?>')">
                                                <i class="bi bi-pencil-square me-1"></i>Doc Notes
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Today's Schedule -->
    <div class="col-lg-5">
        <div class="nexus-card h-100">
            <div class="nexus-card-header">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-calendar-event text-primary"></i>
                    <h6 class="fw-bold mb-0">Today's Appointment Schedule</h6>
                </div>
                <span class="badge bg-light text-secondary border"><?= count($todayAppointments) ?> Booked</span>
            </div>
            <div class="nexus-card-body p-0">
                <?php if (empty($todayAppointments)): ?>
                    <div class="text-center py-4 text-muted small">No scheduled appointments for today.</div>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($todayAppointments as $ta): ?>
                            <div class="list-group-item p-3 d-flex justify-content-between align-items-center">
                                <div>
                                    <strong class="text-dark small d-block"><?= e($ta['first_name'] . ' ' . $ta['last_name']) ?></strong>
                                    <small class="text-muted"><?= format_datetime($ta['appointment_time'], 'h:i A') ?> &bull; <?= e($ta['reason'] ?: 'Routine') ?></small>
                                </div>
                                <div>
                                    <span class="badge badge-nexus badge-status-<?= e($ta['status']) ?>">
                                        <?= ucfirst(e($ta['status'])) ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Record Clinical Consultation -->
<div class="modal fade" id="newConsultationModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title fw-bold fs-6">
                    <i class="bi bi-clipboard2-pulse-fill text-info me-2"></i>Record Clinical Consultation Notes
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="dashboard.php">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="save_consultation">
                <input type="hidden" name="queue_entry_id" id="modalQueueId" value="">

                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Patient *</label>
                        <select name="patient_id" id="modalPatientSelect" class="form-select" required>
                            <option value="">Select Patient...</option>
                            <?php foreach ($patientsList as $p): ?>
                                <option value="<?= $p['id'] ?>"><?= e($p['first_name'] . ' ' . $p['last_name']) ?> (<?= e($p['patient_id']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <h6 class="text-uppercase text-secondary fw-bold mb-2" style="font-size: 0.75rem;">1. Vital Signs Triage</h6>
                    <div class="row g-2 mb-3">
                        <div class="col-md-3">
                            <label class="form-label small">Blood Pressure (BP)</label>
                            <input type="text" name="vitals_bp" class="form-control form-control-sm" placeholder="120/80">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small">Pulse (bpm)</label>
                            <input type="number" name="vitals_pulse" class="form-control form-control-sm" placeholder="72">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small">Temp (°F)</label>
                            <input type="number" step="0.1" name="vitals_temp" class="form-control form-control-sm" placeholder="98.6">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small">Weight (kg)</label>
                            <input type="number" step="0.1" name="vitals_weight" class="form-control form-control-sm" placeholder="68.0">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small">SpO2 (%)</label>
                            <input type="number" name="vitals_spo2" class="form-control form-control-sm" placeholder="98">
                        </div>
                    </div>

                    <h6 class="text-uppercase text-secondary fw-bold mb-2" style="font-size: 0.75rem;">2. Clinical Notes & ICD-10 Diagnosis</h6>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Subjective & Objective Clinical Examination *</label>
                        <textarea name="clinical_notes" class="form-control" rows="3" required placeholder="Document presenting symptoms, history of present illness, and physical exam findings..."></textarea>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">ICD-10 Code</label>
                            <input type="text" name="diagnosis_code" class="form-control form-control-sm" value="I10" placeholder="e.g. I10, J06.9">
                        </div>
                        <div class="col-md-8">
                            <label class="form-label small fw-semibold">Primary Diagnosis Title *</label>
                            <input type="text" name="diagnosis_title" class="form-control form-control-sm" required placeholder="e.g. Essential (primary) hypertension">
                        </div>
                    </div>

                    <h6 class="text-uppercase text-secondary fw-bold mb-2" style="font-size: 0.75rem;">3. Treatment Plan & Prescription</h6>
                    <div class="mb-3">
                        <label class="form-label small">Treatment Plan / Instructions</label>
                        <textarea name="treatment_plan" class="form-control" rows="2" placeholder="Lifestyle modifications, dietary guidance, follow-up timeline..."></textarea>
                    </div>

                    <div class="row g-2">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Prescribe Medication</label>
                            <select name="medicine_id" class="form-select form-select-sm">
                                <option value="">No medication prescribed</option>
                                <?php foreach ($medicines as $med): ?>
                                    <option value="<?= $med['id'] ?>"><?= e($med['name']) ?> (<?= e($med['generic_name']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small">Dosage</label>
                            <input type="text" name="dosage" class="form-control form-control-sm" value="500mg">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small">Frequency</label>
                            <input type="text" name="frequency" class="form-control form-control-sm" value="1-0-1">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small">Days</label>
                            <input type="number" name="duration_days" class="form-control form-control-sm" value="5">
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-nexus-primary btn-sm px-4">Complete & Sign Consultation</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function openConsultWithPatient(patientId, queueId, patientName) {
        document.getElementById('modalPatientSelect').value = patientId;
        document.getElementById('modalQueueId').value = queueId;
        const modal = new bootstrap.Modal(document.getElementById('newConsultationModal'));
        modal.show();
    }
</script>

<?php require_once __DIR__ . '/../../app/views/layouts/footer.php'; ?>
