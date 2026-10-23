<?php
/**
 * KVM Hospital Management System — NexusCare
 * Core Module: Patient Registry & Demographics
 */

declare(strict_types=1);

require_once __DIR__ . '/../../app/config/app.php';
require_once __DIR__ . '/../../app/config/database.php';
require_once __DIR__ . '/../../app/helpers/functions.php';
require_once __DIR__ . '/../../app/middleware/auth.php';
require_once __DIR__ . '/../../app/middleware/csrf.php';
require_once __DIR__ . '/../../app/services/AuditService.php';
require_once __DIR__ . '/../../app/services/CareTrailService.php';

require_auth();

$db = get_db();
$audit = new AuditService($db);
$careTrail = new CareTrailService($db);
$currentUser = current_user();

// Handle New Patient Creation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'create_patient') {
        $firstName = sanitize_input($_POST['first_name'] ?? '');
        $lastName  = sanitize_input($_POST['last_name'] ?? '');
        $dob       = trim($_POST['dob'] ?? '');
        $gender    = trim($_POST['gender'] ?? '');
        $blood     = trim($_POST['blood_group'] ?? 'Unknown');
        $phone     = sanitize_input($_POST['phone'] ?? '');
        $email     = sanitize_input($_POST['email'] ?? '');
        $address   = sanitize_input($_POST['address'] ?? '');
        $emName    = sanitize_input($_POST['emergency_contact_name'] ?? '');
        $emPhone   = sanitize_input($_POST['emergency_contact_phone'] ?? '');
        $allergies = sanitize_input($_POST['allergies'] ?? '');
        $history   = sanitize_input($_POST['medical_history'] ?? '');

        if ($firstName && $lastName && $dob && $gender && $phone && $emName && $emPhone) {
            $db->beginTransaction();
            try {
                $patientCode = generate_patient_id($db);

                $stmt = $db->prepare("
                    INSERT INTO `patient_profiles` (
                        `patient_id`, `first_name`, `last_name`, `dob`, `gender`,
                        `blood_group`, `phone`, `email`, `address`, `emergency_contact_name`,
                        `emergency_contact_phone`, `allergies`, `medical_history`, `created_at`
                    ) VALUES (
                        :pid, :fn, :ln, :dob, :gender,
                        :bg, :phone, :email, :addr, :em_name,
                        :em_phone, :allergies, :history, NOW()
                    )
                ");
                $stmt->execute([
                    'pid'       => $patientCode,
                    'fn'        => $firstName,
                    'ln'        => $lastName,
                    'dob'       => $dob,
                    'gender'    => $gender,
                    'bg'        => $blood,
                    'phone'     => $phone,
                    'email'     => $email,
                    'addr'      => $address,
                    'em_name'   => $emName,
                    'em_phone'  => $emPhone,
                    'allergies' => $allergies,
                    'history'   => $history
                ]);
                $newPatId = (int)$db->lastInsertId();

                // Initial CareTrail event
                $careTrail->recordEvent(
                    patientId: $newPatId,
                    eventType: 'registration',
                    eventTitle: "Hospital Patient Registered: {$patientCode}",
                    eventDescription: "Patient admitted to clinical registry. Demographic and contact data verified.",
                    sourceTable: 'patient_profiles',
                    sourceId: $newPatId,
                    createdBy: $currentUser['id']
                );

                // TrustLedger Audit
                $audit->log('CREATE', 'PatientProfile', $newPatId, "Registered patient {$firstName} {$lastName} ({$patientCode})", userId: $currentUser['id']);

                $db->commit();
                set_flash('success', "Patient successfully registered with ID: <strong>{$patientCode}</strong>");
            } catch (Exception $e) {
                $db->rollBack();
                set_flash('danger', "Failed to register patient: " . $e->getMessage());
            }
        } else {
            set_flash('danger', "Please complete all required fields marked with an asterisk (*).");
        }
        redirect('index.php');
    }
}

// Fetch patients list
$search = trim($_GET['search'] ?? '');
$sql = "
    SELECT 
        p.*,
        TIMESTAMPDIFF(YEAR, p.dob, CURDATE()) AS age,
        (SELECT COUNT(*) FROM appointments a WHERE a.patient_id = p.id) AS total_appointments,
        (SELECT COUNT(*) FROM bed_assignments ba WHERE ba.patient_id = p.id AND ba.status = 'active') AS is_inpatient
    FROM `patient_profiles` p
    WHERE 1=1
";
$params = [];
if ($search) {
    $sql .= " AND (p.patient_id LIKE :s OR p.first_name LIKE :s OR p.last_name LIKE :s OR p.phone LIKE :s)";
    $params['s'] = "%{$search}%";
}
$sql .= " ORDER BY p.id DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$patients = $stmt->fetchAll();

$pageTitle = 'Patient Management Registry';
require_once __DIR__ . '/../../app/views/layouts/header.php';
?>

<!-- Title & Action Bar -->
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div>
        <h4 class="fw-bold mb-1">Patient Management Registry</h4>
        <p class="text-secondary small mb-0">Demographics, clinical history, and CareTrail longitudinal patient dossiers</p>
    </div>
    
    <div class="d-flex align-items-center gap-2">
        <button type="button" class="btn btn-nexus-primary" data-bs-toggle="modal" data-bs-target="#newPatientModal">
            <i class="bi bi-person-plus-fill me-1"></i>Register New Patient
        </button>
    </div>
</div>

<!-- Search & Filter Card -->
<div class="nexus-card mb-4 p-3">
    <form method="GET" action="index.php" class="row g-2 align-items-center">
        <div class="col-md-9">
            <input type="text" name="search" class="form-control" placeholder="Search by Patient ID (e.g. PAT-2026-0001), name, or phone..." value="<?= e($search) ?>">
        </div>
        <div class="col-md-3 d-flex gap-2">
            <button type="submit" class="btn btn-nexus-dark flex-fill"><i class="bi bi-search me-1"></i>Search</button>
            <?php if ($search): ?>
                <a href="index.php" class="btn btn-outline-secondary">Clear</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Patients Table Card -->
<div class="nexus-card">
    <div class="nexus-card-header">
        <h6 class="fw-bold mb-0">Registered Patients (<?= count($patients) ?>)</h6>
        <span class="badge bg-light text-secondary border">Live Database Records</span>
    </div>

    <div class="table-responsive">
        <table class="table-nexus">
            <thead>
                <tr>
                    <th>Patient ID</th>
                    <th>Full Name</th>
                    <th>Age / Gender</th>
                    <th>Blood Group</th>
                    <th>Contact Phone</th>
                    <th>Care Status</th>
                    <th>Known Allergies</th>
                    <th class="text-end" style="width: 190px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($patients)): ?>
                    <tr>
                        <td colspan="8" class="text-center py-4 text-muted">No patients found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($patients as $p): ?>
                        <tr>
                            <td>
                                <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 fs-6">
                                    <?= e($p['patient_id']) ?>
                                </span>
                            </td>
                            <td>
                                <strong><?= e($p['first_name'] . ' ' . $p['last_name']) ?></strong>
                                <?php if ($p['email']): ?>
                                    <small class="text-muted d-block"><?= e($p['email']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?= e($p['age']) ?> Years &bull; <?= ucfirst(e($p['gender'])) ?>
                            </td>
                            <td>
                                <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25">
                                    <?= e($p['blood_group']) ?>
                                </span>
                            </td>
                            <td>
                                <i class="bi bi-telephone text-muted me-1"></i><?= e($p['phone']) ?>
                            </td>
                            <td>
                                <?php if ((int)$p['is_inpatient'] > 0): ?>
                                    <span class="badge badge-bed-occupied">Inpatient Admitted</span>
                                <?php else: ?>
                                    <span class="badge badge-nexus badge-priority-normal">Outpatient</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="text-secondary small"><?= e($p['allergies'] ?: 'None reported') ?></span>
                            </td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    <a href="../caretrail/index.php?patient_id=<?= $p['id'] ?>" class="btn btn-outline-primary btn-sm" title="View Complete CareTrail Timeline">
                                        <i class="bi bi-signpost-split"></i> CareTrail
                                    </a>
                                    <a href="../appointments/index.php?patient_id=<?= $p['id'] ?>" class="btn btn-outline-secondary btn-sm" title="Schedule Appointment">
                                        <i class="bi bi-calendar-plus"></i> Book
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: Register New Patient -->
<div class="modal fade" id="newPatientModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title fw-bold fs-6">
                    <i class="bi bi-person-plus-fill text-info me-2"></i>Register New Hospital Patient
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="index.php">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="create_patient">

                <div class="modal-body p-4">
                    <h6 class="text-uppercase text-secondary fw-bold mb-3" style="font-size: 0.75rem;">1. Personal Details</h6>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">First Name *</label>
                            <input type="text" name="first_name" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Last Name *</label>
                            <input type="text" name="last_name" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Date of Birth *</label>
                            <input type="date" name="dob" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Gender *</label>
                            <select name="gender" class="form-select" required>
                                <option value="">Select Gender</option>
                                <option value="male">Male</option>
                                <option value="female">Female</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Blood Group</label>
                            <select name="blood_group" class="form-select">
                                <option value="Unknown">Select Blood Group</option>
                                <option value="A+">A+</option>
                                <option value="A-">A-</option>
                                <option value="B+">B+</option>
                                <option value="B-">B-</option>
                                <option value="AB+">AB+</option>
                                <option value="AB-">AB-</option>
                                <option value="O+">O+</option>
                                <option value="O-">O-</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Phone Number *</label>
                            <input type="text" name="phone" class="form-control" required placeholder="+91 98000 00000">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Email Address</label>
                            <input type="email" name="email" class="form-control" placeholder="patient@example.com">
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-semibold">Residential Address</label>
                            <input type="text" name="address" class="form-control">
                        </div>
                    </div>

                    <h6 class="text-uppercase text-secondary fw-bold mb-3" style="font-size: 0.75rem;">2. Emergency Contact</h6>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Emergency Contact Person *</label>
                            <input type="text" name="emergency_contact_name" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Emergency Contact Phone *</label>
                            <input type="text" name="emergency_contact_phone" class="form-control" required>
                        </div>
                    </div>

                    <h6 class="text-uppercase text-secondary fw-bold mb-3" style="font-size: 0.75rem;">3. Clinical Alerts</h6>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Known Drug Allergies</label>
                            <input type="text" name="allergies" class="form-control" placeholder="e.g. Penicillin, NSAIDs, Sulfa">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Underlying Medical History</label>
                            <input type="text" name="medical_history" class="form-control" placeholder="e.g. Diabetes, Hypertension">
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-nexus-primary btn-sm px-4">Register Patient</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../app/views/layouts/footer.php'; ?>
