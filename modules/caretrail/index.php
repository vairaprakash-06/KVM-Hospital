<?php
/**
 * KVM Hospital Management System — NexusCare
 * Advanced Feature 3: CareTrail — Unified Patient Journey Timeline
 */

declare(strict_types=1);

require_once __DIR__ . '/../../app/config/app.php';
require_once __DIR__ . '/../../app/config/database.php';
require_once __DIR__ . '/../../app/helpers/functions.php';
require_once __DIR__ . '/../../app/middleware/auth.php';
require_once __DIR__ . '/../../app/middleware/csrf.php';
require_once __DIR__ . '/../../app/services/CareTrailService.php';

require_auth();

$db = get_db();
$careTrail = new CareTrailService($db);
$currentUser = current_user();

// Handle Manual Milestone Post by authorized staff
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $patientId = (int)($_POST['patient_id'] ?? 0);
    $title     = sanitize_input($_POST['event_title'] ?? '');
    $desc      = sanitize_input($_POST['event_description'] ?? '');
    $type      = $_POST['event_type'] ?? 'consultation_completed';
    $severity  = $_POST['severity'] ?? 'info';
    $deptId    = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null;

    if ($patientId && !empty($title) && !empty($desc)) {
        $careTrail->recordEvent(
            patientId: $patientId,
            eventType: $type,
            eventTitle: $title,
            eventDescription: $desc,
            departmentId: $deptId,
            severity: $severity,
            createdBy: $currentUser['id']
        );
        set_flash('success', "CareTrail clinical milestone recorded successfully.");
    }
    redirect(BASE_URL . "/modules/caretrail/index.php?patient_id={$patientId}");
}

// Select active patient
$patientId = !empty($_GET['patient_id']) ? (int)$_GET['patient_id'] : null;

// If logged in as patient, force to their own profile
if (has_role('patient') && !empty($currentUser['patient_id'])) {
    $patientId = $currentUser['patient_id'];
}

// If no patient selected yet, default to the first seeded patient (Anita Desai or Arun Verma)
if (!$patientId) {
    $defStmt = $db->query("SELECT id FROM `patient_profiles` ORDER BY id ASC LIMIT 1");
    $patientId = (int)$defStmt->fetchColumn();
}

// Fetch Patient Details
$patStmt = $db->prepare("
    SELECT 
        p.*,
        TIMESTAMPDIFF(YEAR, p.dob, CURDATE()) AS age
    FROM `patient_profiles` p 
    WHERE p.id = :pid
");
$patStmt->execute(['pid' => $patientId]);
$patient = $patStmt->fetch();

// Fetch All Patients for selector dropdown
$allPatients = $db->query("SELECT id, patient_id, first_name, last_name, phone FROM `patient_profiles` ORDER BY first_name ASC")->fetchAll();

// Timeline filters
$filters = [];
if (!empty($_GET['event_type'])) {
    $filters['event_type'] = $_GET['event_type'];
}

$timeline = $patient ? $careTrail->getPatientTimeline($patientId, $filters) : [];
$distinctTypes = $patient ? $careTrail->getDistinctEventTypes($patientId) : [];
$departments = $db->query("SELECT id, name FROM `departments` WHERE `status` = 'active' ORDER BY name ASC")->fetchAll();

$pageTitle = 'CareTrail — Unified Patient Journey Timeline';
require_once __DIR__ . '/../../app/views/layouts/header.php';
?>

<!-- Title & Selector Bar -->
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div>
        <h4 class="fw-bold mb-1">CareTrail — Unified Patient Journey Timeline</h4>
        <p class="text-secondary small mb-0">Cross-department longitudinal patient milestones from first check-in to post-discharge</p>
    </div>
    
    <div class="d-flex align-items-center gap-2">
        <?php if (!has_role('patient')): ?>
            <button type="button" class="btn btn-nexus-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addMilestoneModal">
                <i class="bi bi-plus-circle me-1"></i>Append Clinical Milestone
            </button>
        <?php endif; ?>
    </div>
</div>

<!-- Patient Selection Bar (Staff View) -->
<?php if (!has_role('patient')): ?>
    <div class="nexus-card mb-4 p-3">
        <form method="GET" action="index.php" class="row g-2 align-items-center">
            <div class="col-md-6">
                <label class="form-label small text-secondary fw-semibold mb-1">Select Patient to View CareTrail</label>
                <select name="patient_id" class="form-select" onchange="this.form.submit()">
                    <?php foreach ($allPatients as $ap): ?>
                        <option value="<?= $ap['id'] ?>" <?= $patientId === (int)$ap['id'] ? 'selected' : '' ?>>
                            <?= e($ap['first_name'] . ' ' . $ap['last_name']) ?> (<?= e($ap['patient_id']) ?>) &bull; <?= e($ap['phone']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label small text-secondary fw-semibold mb-1">Filter by Journey Milestone Type</label>
                <select name="event_type" class="form-select" onchange="this.form.submit()">
                    <option value="">All Care Events (<?= count($timeline) ?>)</option>
                    <?php foreach ($distinctTypes as $dt): ?>
                        <option value="<?= $dt ?>" <?= ($_GET['event_type'] ?? '') === $dt ? 'selected' : '' ?>>
                            <?= ucwords(str_replace('_', ' ', $dt)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <a href="index.php?patient_id=<?= $patientId ?>" class="btn btn-outline-secondary w-100" style="margin-top: 1.5rem;">Reset Filter</a>
            </div>
        </form>
    </div>
<?php endif; ?>

<?php if ($patient): ?>
    <!-- Patient Medical Dossier Card -->
    <div class="nexus-card mb-4">
        <div class="nexus-card-body p-4">
            <div class="row g-3 align-items-center">
                <div class="col-auto">
                    <div class="stat-icon blue" style="width: 60px; height: 60px; font-size: 1.8rem;">
                        <i class="bi bi-person-fill"></i>
                    </div>
                </div>
                <div class="col">
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <h4 class="fw-bold mb-0 text-dark"><?= e($patient['first_name'] . ' ' . $patient['last_name']) ?></h4>
                        <span class="badge bg-primary"><?= e($patient['patient_id']) ?></span>
                        <span class="badge bg-light text-dark border"><?= e($patient['age']) ?> Years / <?= ucfirst(e($patient['gender'])) ?></span>
                        <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25">Blood: <?= e($patient['blood_group']) ?></span>
                    </div>
                    <div class="d-flex flex-wrap gap-4 mt-2 text-secondary small">
                        <span><i class="bi bi-telephone me-1 text-primary"></i><?= e($patient['phone']) ?></span>
                        <span><i class="bi bi-envelope me-1 text-primary"></i><?= e($patient['email'] ?: 'No email on file') ?></span>
                        <span><i class="bi bi-geo-alt me-1 text-primary"></i><?= e($patient['address'] ?: 'Not recorded') ?></span>
                        <span><i class="bi bi-shield-exclamation me-1 text-danger"></i><strong>Allergies:</strong> <?= e($patient['allergies'] ?: 'None Reported') ?></span>
                    </div>
                </div>
                <div class="col-md-auto text-md-end border-start ps-md-4">
                    <small class="text-muted d-block">Emergency Contact</small>
                    <strong class="text-dark"><?= e($patient['emergency_contact_name']) ?></strong>
                    <span class="d-block small text-muted"><?= e($patient['emergency_contact_phone']) ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Chronological CareTrail Timeline -->
    <div class="nexus-card">
        <div class="nexus-card-header">
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-clock-history text-primary"></i>
                <h6 class="fw-bold mb-0">Longitudinal CareTrail Encounter Stream (<?= count($timeline) ?> Events Recorded)</h6>
            </div>
            <span class="badge bg-light text-secondary border">Chronological Order</span>
        </div>

        <div class="nexus-card-body p-4">
            <?php if (empty($timeline)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-signpost-split fs-1 d-block mb-2"></i>
                    No CareTrail events recorded for this patient under the selected filter.
                </div>
            <?php else: ?>
                <div class="caretrail-timeline">
                    <?php foreach ($timeline as $event): ?>
                        <div class="timeline-item">
                            <!-- Severity color indicator icon -->
                            <div class="timeline-marker <?= e($event['severity']) ?>">
                                <?php if ($event['event_type'] === 'registration'): ?>
                                    <i class="bi bi-person-plus-fill"></i>
                                <?php elseif ($event['event_type'] === 'check_in' || $event['event_type'] === 'queue_entered'): ?>
                                    <i class="bi bi-ticket-perforated-fill"></i>
                                <?php elseif (str_contains($event['event_type'], 'consultation')): ?>
                                    <i class="bi bi-stethoscope"></i>
                                <?php elseif (str_contains($event['event_type'], 'lab')): ?>
                                    <i class="bi bi-eyedropper"></i>
                                <?php elseif (str_contains($event['event_type'], 'medicine') || str_contains($event['event_type'], 'prescription')): ?>
                                    <i class="bi bi-capsule"></i>
                                <?php elseif (str_contains($event['event_type'], 'bed') || $event['event_type'] === 'admitted'): ?>
                                    <i class="bi bi-hospital-fill"></i>
                                <?php elseif (str_contains($event['event_type'], 'invoice') || str_contains($event['event_type'], 'payment')): ?>
                                    <i class="bi bi-receipt"></i>
                                <?php else: ?>
                                    <i class="bi bi-check-circle-fill"></i>
                                <?php endif; ?>
                            </div>

                            <div class="timeline-card">
                                <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-2">
                                    <div>
                                        <h6 class="fw-bold mb-1 text-dark"><?= e($event['event_title']) ?></h6>
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="badge bg-light text-dark border" style="font-size: 0.72rem;">
                                                <?= ucwords(str_replace('_', ' ', e($event['event_type']))) ?>
                                            </span>
                                            <?php if ($event['department_name']): ?>
                                                <span class="badge bg-secondary bg-opacity-10 text-secondary" style="font-size: 0.72rem;">
                                                    <i class="bi bi-building me-1"></i><?= e($event['department_name']) ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <div class="text-md-end text-muted small">
                                        <i class="bi bi-calendar3 me-1"></i><?= format_datetime($event['event_time'], 'd M Y, h:i A') ?>
                                        <?php if ($event['actor_username']): ?>
                                            <span class="d-block text-secondary" style="font-size: 0.72rem;">
                                                Logged by: <strong><?= e($event['actor_username']) ?></strong>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <p class="text-secondary mb-2" style="font-size: 0.88rem;">
                                    <?= nl2br(e($event['event_description'])) ?>
                                </p>

                                <?php if ($event['source_table']): ?>
                                    <div class="border-top pt-2 mt-2 text-muted" style="font-size: 0.72rem;">
                                        <i class="bi bi-link-45deg me-1"></i>Source Relational Record: 
                                        <code><?= e($event['source_table']) ?> #<?= e((string)$event['source_id']) ?></code>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<!-- Modal: Append Milestone -->
<div class="modal fade" id="addMilestoneModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title fw-bold fs-6">
                    <i class="bi bi-signpost-split-fill text-info me-2"></i>Append Clinical Journey Milestone
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="<?= BASE_URL ?>/modules/caretrail/index.php">
                <?= csrf_field() ?>

                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Target Patient *</label>
                        <select name="patient_id" class="form-select" required>
                            <?php foreach ($allPatients as $p): ?>
                                <option value="<?= $p['id'] ?>" <?= ((int)$p['id'] === (int)$patientId) ? 'selected' : '' ?>>
                                    <?= e($p['first_name'] . ' ' . $p['last_name']) ?> (<?= e($p['patient_id']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Event Milestone Title *</label>
                        <input type="text" name="event_title" class="form-control" required placeholder="e.g. Cardiology Echocardiogram Assessment">
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Milestone Category *</label>
                            <select name="event_type" class="form-select" required>
                                <option value="consultation_completed">Clinical Observation</option>
                                <option value="lab_completed">Diagnostic Investigation</option>
                                <option value="bed_assigned">Inpatient Movement</option>
                                <option value="follow_up_scheduled">Follow-Up Note</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Clinical Department</label>
                            <select name="department_id" class="form-select">
                                <option value="">Select Department...</option>
                                <?php foreach ($departments as $d): ?>
                                    <option value="<?= $d['id'] ?>"><?= e($d['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Severity / Priority</label>
                        <select name="severity" class="form-select">
                            <option value="info" selected>Info (Routine Care)</option>
                            <option value="success">Success (Resolution / Negative Test)</option>
                            <option value="warning">Warning (Elevated Vitals / Mild Adverse)</option>
                            <option value="critical">Critical (Acute Incident / STAT)</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Clinical Observation / Notes *</label>
                        <textarea name="event_description" class="form-control" rows="3" required placeholder="Document the clinical finding, physician assessment, or operational milestone..."></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-nexus-primary btn-sm px-4">Append to CareTrail</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../app/views/layouts/footer.php'; ?>
