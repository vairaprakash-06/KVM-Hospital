<?php
/**
 * KVM Hospital Management System — NexusCare
 * Advanced Feature 2: BedMatrix — Dynamic Bed & Inpatient Allocation
 */

declare(strict_types=1);

require_once __DIR__ . '/../../app/config/app.php';
require_once __DIR__ . '/../../app/config/database.php';
require_once __DIR__ . '/../../app/helpers/functions.php';
require_once __DIR__ . '/../../app/middleware/auth.php';
require_once __DIR__ . '/../../app/middleware/csrf.php';
require_once __DIR__ . '/../../app/services/BedAllocationService.php';

require_auth();

$db = get_db();
$bedService = new BedAllocationService($db);
$currentUser = current_user();

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    // Action 1: Assign Bed
    if ($action === 'assign_bed') {
        $bedId       = (int)($_POST['bed_id'] ?? 0);
        $patientId   = (int)($_POST['patient_id'] ?? 0);
        $encounterId = (int)($_POST['encounter_id'] ?? 0);
        $reason      = sanitize_input($_POST['clinical_reason'] ?? '');

        if ($bedId && $patientId) {
            // If encounterId was 0, find or create active encounter
            if ($encounterId === 0) {
                $encStmt = $db->prepare("
                    SELECT id FROM `patient_encounters` 
                    WHERE `patient_id` = :pid AND `status` = 'active' 
                    ORDER BY id DESC LIMIT 1
                ");
                $encStmt->execute(['pid' => $patientId]);
                $encounterId = (int)$encStmt->fetchColumn();

                if ($encounterId === 0) {
                    $docId = (int)$db->query("SELECT id FROM `doctor_profiles` LIMIT 1")->fetchColumn() ?: 1;
                    $deptId = (int)$db->query("SELECT id FROM `departments` LIMIT 1")->fetchColumn() ?: 1;
                    $encCode = generate_encounter_number($db);
                    $insEnc = $db->prepare("
                        INSERT INTO `patient_encounters` (
                            `encounter_number`, `patient_id`, `doctor_id`, `department_id`,
                            `encounter_type`, `status`, `started_at`
                        ) VALUES (
                            :code, :pid, :doc_id, :dept_id, 'inpatient', 'active', NOW()
                        )
                    ");
                    $insEnc->execute(['code' => $encCode, 'pid' => $patientId, 'doc_id' => $docId, 'dept_id' => $deptId]);
                    $encounterId = (int)$db->lastInsertId();
                }
            }

            $res = $bedService->assignBed($bedId, $patientId, $encounterId, $currentUser['id'], $reason);
            if ($res['success']) {
                set_flash('success', $res['message']);
            } else {
                set_flash('danger', $res['message']);
            }
        } else {
            set_flash('danger', 'Please choose both an available bed and a valid patient.');
        }
        redirect(BASE_URL . '/modules/bedmatrix/index.php');
    }

    // Action 2: Discharge Bed
    if ($action === 'discharge_bed') {
        $assignId = (int)($_POST['assignment_id'] ?? 0);
        $notes    = sanitize_input($_POST['discharge_notes'] ?? '');

        $res = $bedService->dischargeBed($assignId, $currentUser['id'], $notes);
        if ($res['success']) {
            set_flash('success', $res['message']);
        } else {
            set_flash('danger', $res['message']);
        }
        redirect(BASE_URL . '/modules/bedmatrix/index.php');
    }

    // Action 3: Mark Bed Cleaned
    if ($action === 'mark_cleaned') {
        $bedId = (int)($_POST['bed_id'] ?? 0);
        $res = $bedService->markBedCleaned($bedId, $currentUser['id']);
        if ($res['success']) {
            set_flash('success', $res['message']);
        } else {
            set_flash('danger', $res['message']);
        }
        redirect(BASE_URL . '/modules/bedmatrix/index.php');
    }

    // Action 4: Transfer Bed
    if ($action === 'transfer_bed') {
        $assignId = (int)($_POST['assignment_id'] ?? 0);
        $newBedId = (int)($_POST['new_bed_id'] ?? 0);
        $reason   = sanitize_input($_POST['transfer_reason'] ?? '');

        $res = $bedService->transferBed($assignId, $newBedId, $currentUser['id'], $reason);
        if ($res['success']) {
            set_flash('success', $res['message']);
        } else {
            set_flash('danger', $res['message']);
        }
        redirect(BASE_URL . '/modules/bedmatrix/index.php');
    }
}

// Filter by ward
$filterWard = !empty($_GET['ward_id']) ? (int)$_GET['ward_id'] : null;

$wards = $db->query("
    SELECT w.*, COUNT(b.id) AS bed_count 
    FROM `wards` w 
    LEFT JOIN `beds` b ON w.id = b.ward_id 
    GROUP BY w.id 
    ORDER BY w.id ASC
")->fetchAll();
$beds  = $bedService->getOccupancyMatrix($filterWard);
$stats = $bedService->getBedStats();

// Available unassigned patients for modal
$patients = $db->query("
    SELECT p.id, p.patient_id, p.first_name, p.last_name, p.gender 
    FROM `patient_profiles` p
    WHERE NOT EXISTS (
        SELECT 1 FROM `bed_assignments` ba WHERE ba.patient_id = p.id AND ba.status = 'active'
    )
    ORDER BY p.first_name ASC
")->fetchAll();

// Available beds for transfer dropdown
$availableBeds = $db->query("
    SELECT b.id, b.bed_number, w.name AS ward_name, r.room_number 
    FROM `beds` b 
    JOIN `wards` w ON b.ward_id = w.id 
    JOIN `rooms` r ON b.room_id = r.id 
    WHERE b.status = 'available'
")->fetchAll();

$pageTitle = 'BedMatrix — Dynamic Bed & Inpatient Allocation';
require_once __DIR__ . '/../../app/views/layouts/header.php';
?>

<!-- Title and Action Bar -->
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div>
        <h4 class="fw-bold mb-1">BedMatrix — Dynamic Bed & Resource Allocation</h4>
        <p class="text-secondary small mb-0">Interactive ward grid, race-safe bed locking, and equipment compatibility matching</p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <?php if (has_role('nurse', 'hospital_admin', 'super_admin')): ?>
            <button type="button" class="btn btn-nexus-primary" data-bs-toggle="modal" data-bs-target="#assignBedModal">
                <i class="bi bi-plus-circle me-1"></i>New Inpatient Bed Allocation
            </button>
        <?php endif; ?>
    </div>
</div>

<!-- BedMatrix KPI Summary Cards -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-lg-3">
        <div class="stat-widget">
            <div class="stat-icon teal">
                <i class="bi bi-hospital"></i>
            </div>
            <div>
                <div class="stat-value"><?= (int)($stats['total_beds'] ?? 0) ?></div>
                <div class="stat-label">Total Registered Beds</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="stat-widget">
            <div class="stat-icon emerald">
                <i class="bi bi-check2-circle"></i>
            </div>
            <div>
                <div class="stat-value text-success"><?= (int)($stats['available_beds'] ?? 0) ?></div>
                <div class="stat-label">Available for Admission</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="stat-widget">
            <div class="stat-icon danger">
                <i class="bi bi-person-slash"></i>
            </div>
            <div>
                <div class="stat-value text-danger"><?= (int)($stats['occupied_beds'] ?? 0) ?></div>
                <div class="stat-label">Currently Occupied</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="stat-widget">
            <div class="stat-icon amber">
                <i class="bi bi-bucket"></i>
            </div>
            <div>
                <div class="stat-value text-warning"><?= (int)($stats['cleaning_beds'] ?? 0) ?></div>
                <div class="stat-label">In Sanitization / Cleaning</div>
            </div>
        </div>
    </div>
</div>

<!-- Occupancy Progress Bar -->
<div class="nexus-card mb-4 p-3">
    <div class="d-flex justify-content-between align-items-center mb-2">
        <span class="fw-semibold text-secondary small">Hospital Inpatient Bed Occupancy Saturation</span>
        <span class="badge bg-<?= ($stats['occupancy_rate'] ?? 0) > 80 ? 'danger' : 'primary' ?> fs-6">
            <?= $stats['occupancy_rate'] ?? 0 ?>% Occupied
        </span>
    </div>
    <div class="progress" style="height: 10px;">
        <div class="progress-bar <?= ($stats['occupancy_rate'] ?? 0) > 80 ? 'bg-danger' : 'bg-primary' ?>" 
             role="progressbar" style="width: <?= $stats['occupancy_rate'] ?? 0 ?>%;"></div>
    </div>
</div>

<!-- Ward Filter Tabs -->
<div class="d-flex flex-wrap gap-2 mb-4">
    <a href="index.php" class="btn btn-sm <?= !$filterWard ? 'btn-dark' : 'btn-outline-secondary' ?>">
        All Wards (<?= count($beds) ?> Beds)
    </a>
    <?php foreach ($wards as $w): ?>
        <a href="index.php?ward_id=<?= $w['id'] ?>" 
           class="btn btn-sm <?= $filterWard === (int)$w['id'] ? 'btn-dark' : 'btn-outline-secondary' ?>">
            <?= e($w['name']) ?> <span class="badge bg-secondary ms-1"><?= (int)$w['bed_count'] ?></span>
        </a>
    <?php endforeach; ?>
</div>

<!-- Interactive BedMatrix Grid -->
<div class="nexus-card mb-4">
    <div class="nexus-card-header flex-wrap gap-2">
        <div class="d-flex align-items-center gap-2">
            <i class="bi bi-grid-fill text-primary"></i>
            <h6 class="fw-bold mb-0">Dynamic Bed Grid Visualizer</h6>
            <span class="badge bg-light text-dark border ms-2" id="visibleBedCount"><?= count($beds) ?> Beds Shown</span>
        </div>
        <div class="d-flex align-items-center gap-2 flex-wrap ms-auto">
            <div class="input-group input-group-sm" style="max-width: 240px;">
                <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                <input type="text" id="bedSearchInput" class="form-control border-start-0" placeholder="Filter bed # or patient..." oninput="filterBedGrid()">
            </div>
            <div class="btn-group btn-group-sm" role="group">
                <button type="button" class="btn btn-outline-secondary active" id="filterBtnAll" onclick="filterByStatus('all', this)">All</button>
                <button type="button" class="btn btn-outline-success" onclick="filterByStatus('available', this)">Available</button>
                <button type="button" class="btn btn-outline-danger" onclick="filterByStatus('occupied', this)">Occupied</button>
                <button type="button" class="btn btn-outline-warning" onclick="filterByStatus('cleaning', this)">Cleaning</button>
                <button type="button" class="btn btn-outline-primary" onclick="filterByStatus('reserved', this)">Reserved</button>
            </div>
        </div>
    </div>
    <div class="nexus-card-body">
        <div class="bed-grid">
            <?php foreach ($beds as $b): ?>
                <div class="bed-card status-<?= e($b['bed_status']) ?>" 
                     onclick="showBedActions(<?= htmlspecialchars(json_encode($b), ENT_QUOTES, 'UTF-8') ?>)">
                    
                    <div class="bed-icon">
                        <?php if ($b['bed_status'] === 'occupied'): ?>
                            <i class="bi bi-person-fill text-danger"></i>
                        <?php elseif ($b['bed_status'] === 'cleaning'): ?>
                            <i class="bi bi-bucket-fill text-warning"></i>
                        <?php elseif ($b['bed_status'] === 'available'): ?>
                            <i class="bi bi-check-circle-fill text-success"></i>
                        <?php else: ?>
                            <i class="bi bi-slash-circle text-muted"></i>
                        <?php endif; ?>
                    </div>

                    <h6 class="fw-bold mb-1 text-dark"><?= e($b['bed_number']) ?></h6>
                    <small class="text-muted d-block" style="font-size: 0.75rem;">
                        <?= e($b['ward_code']) ?> &bull; Rm <?= e($b['room_number']) ?>
                    </small>

                    <div class="mt-2">
                        <span class="badge badge-nexus badge-bed-<?= e($b['bed_status']) ?>">
                            <?= strtoupper(e($b['bed_status'])) ?>
                        </span>
                    </div>

                    <?php if ($b['bed_status'] === 'occupied'): ?>
                        <div class="mt-2 pt-2 border-top text-start" style="font-size: 0.72rem;">
                            <strong class="text-danger d-block text-truncate"><?= e($b['patient_name']) ?></strong>
                            <span class="text-muted"><?= e($b['patient_code']) ?></span>
                        </div>
                    <?php endif; ?>

                    <?php if ($b['attached_resources']): ?>
                        <div class="mt-2 text-start" style="font-size: 0.68rem;">
                            <span class="badge bg-light text-dark border w-100 text-truncate" title="<?= e($b['attached_resources']) ?>">
                                <i class="bi bi-cpu me-1"></i><?= e($b['attached_resources']) ?>
                            </span>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Modal 1: Assign Bed -->
<div class="modal fade" id="assignBedModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title fw-bold fs-6">
                    <i class="bi bi-grid-3x3-gap-fill text-info me-2"></i>Inpatient Bed Allocation
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="index.php">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="assign_bed">

                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Select Target Available Bed *</label>
                        <select name="bed_id" id="assignModalBedSelect" class="form-select" required>
                            <option value="">Choose Available Bed...</option>
                            <?php foreach ($beds as $b): ?>
                                <?php if ($b['bed_status'] === 'available'): ?>
                                    <option value="<?= $b['bed_id'] ?>">
                                        <?= e($b['bed_number']) ?> — <?= e($b['ward_name']) ?> (Rm <?= e($b['room_number']) ?>) &bull; <?= format_currency($b['daily_rate']) ?>/day
                                    </option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Select Patient for Inpatient Care *</label>
                        <select name="patient_id" class="form-select" required>
                            <option value="">Choose Patient...</option>
                            <?php foreach ($patients as $p): ?>
                                <option value="<?= $p['id'] ?>">
                                    <?= e($p['first_name'] . ' ' . $p['last_name']) ?> (<?= e($p['patient_id']) ?>) &bull; <?= ucfirst(e($p['gender'])) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Clinical Admission Reason / Care Instructions</label>
                        <textarea name="clinical_reason" class="form-control" rows="2" placeholder="e.g. Post-operative recovery monitoring, continuous telemetry required"></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-nexus-primary btn-sm px-4">Confirm Allocation</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal 2: Bed Details & Actions Dialog (Occupied/Cleaning/Transfer) -->
<div class="modal fade" id="bedActionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title fw-bold fs-6">
                    <i class="bi bi-info-circle text-info me-2"></i>Bed Matrix Details: <span id="detailBedNumber"></span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4" id="bedDetailContent">
                <!-- Dynamically populated via JS -->
            </div>
        </div>
    </div>
</div>

<script>
    function showBedActions(bed) {
        document.getElementById('detailBedNumber').textContent = bed.bed_number;
        const container = document.getElementById('bedDetailContent');

        let html = `
            <div class="bg-light p-3 rounded border mb-3">
                <div class="row g-2 small">
                    <div class="col-6"><strong>Ward:</strong> ${bed.ward_name} (${bed.ward_code})</div>
                    <div class="col-6"><strong>Room:</strong> ${bed.room_number} (${bed.room_type})</div>
                    <div class="col-6"><strong>Care Level:</strong> ${bed.care_level}</div>
                    <div class="col-6"><strong>Daily Rate:</strong> ₹${parseFloat(bed.daily_rate).toFixed(2)}</div>
                    <div class="col-12"><strong>Attached Resources:</strong> ${bed.attached_resources || 'Standard Monitoring Port'}</div>
                </div>
            </div>
        `;

        if (bed.bed_status === 'available') {
            html += `
                <div class="alert alert-success py-2 small mb-3">
                    <i class="bi bi-check-circle me-1"></i> Bed is currently available for allocation.
                </div>
                <button type="button" class="btn btn-nexus-primary w-100" onclick="openAssignWithBed(${bed.bed_id})">
                    Allocate This Bed
                </button>
            `;
        } else if (bed.bed_status === 'occupied') {
            html += `
                <div class="alert alert-danger py-2 small mb-3">
                    <strong>Currently Occupied By:</strong> ${bed.patient_name} (${bed.patient_code})<br>
                    <small>Admitted on: ${bed.admission_date}</small>
                </div>

                <div class="d-flex gap-2">
                    <!-- Discharge Button -->
                    <form method="POST" action="index.php" class="flex-fill" onsubmit="return confirm('Confirm patient discharge? Bed will transition to Cleaning queue.')">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="discharge_bed">
                        <input type="hidden" name="assignment_id" value="${bed.active_assignment_id}">
                        <button type="submit" class="btn btn-danger btn-sm w-100">
                            <i class="bi bi-box-arrow-right me-1"></i>Discharge Patient
                        </button>
                    </form>

                    <!-- Transfer Trigger -->
                    <button type="button" class="btn btn-outline-primary btn-sm flex-fill" onclick="showTransferForm(${bed.active_assignment_id})">
                        <i class="bi bi-arrow-left-right me-1"></i>Transfer Bed
                    </button>
                </div>

                <div id="transferFormContainer" class="mt-3 d-none border-top pt-3">
                    <form method="POST" action="index.php">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="transfer_bed">
                        <input type="hidden" name="assignment_id" value="${bed.active_assignment_id}">
                        
                        <div class="mb-2">
                            <label class="form-label small fw-semibold">Target New Bed *</label>
                            <select name="new_bed_id" class="form-select form-select-sm" required>
                                <option value="">Choose Target Bed...</option>
                                <?php foreach ($availableBeds as $ab): ?>
                                    <option value="<?= $ab['id'] ?>"><?= e($ab['bed_number']) ?> (<?= e($ab['ward_name']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-2">
                            <label class="form-label small fw-semibold">Clinical Reason for Transfer *</label>
                            <input type="text" name="transfer_reason" class="form-control form-control-sm" required placeholder="e.g. ICU step-down to General Ward">
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm w-100">Execute Bed Transfer</button>
                    </form>
                </div>
            `;
        } else if (bed.bed_status === 'cleaning') {
            html += `
                <div class="alert alert-warning py-2 small mb-3">
                    <i class="bi bi-bucket-fill me-1"></i> Bed is currently undergoing terminal sanitation & cleaning.
                </div>
                <form method="POST" action="index.php">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="mark_cleaned">
                    <input type="hidden" name="bed_id" value="${bed.bed_id}">
                    <button type="submit" class="btn btn-success btn-sm w-100">
                        <i class="bi bi-check-all me-1"></i>Certification: Mark Bed Cleaned & Available
                    </button>
                </form>
            `;
        } else {
            html += `<p class="text-muted small">Bed is currently ${bed.bed_status}.</p>`;
        }

        container.innerHTML = html;
        const modal = new bootstrap.Modal(document.getElementById('bedActionModal'));
        modal.show();
    }

    function openAssignWithBed(bedId) {
        const modalEl = bootstrap.Modal.getInstance(document.getElementById('bedActionModal'));
        if (modalEl) modalEl.hide();

        const select = document.getElementById('assignModalBedSelect');
        select.value = bedId;

        const assignModal = new bootstrap.Modal(document.getElementById('assignBedModal'));
        assignModal.show();
    }

    function showTransferForm(assignmentId) {
        document.getElementById('transferFormContainer').classList.remove('d-none');
    }

    let currentStatusFilter = 'all';

    function filterByStatus(status, btn) {
        currentStatusFilter = status;
        document.querySelectorAll('.btn-group .btn').forEach(b => b.classList.remove('active'));
        if (btn) btn.classList.add('active');
        applyBedFilters();
    }

    function filterBedGrid() {
        applyBedFilters();
    }

    function applyBedFilters() {
        const term = (document.getElementById('bedSearchInput')?.value || '').toLowerCase().trim();
        const cards = document.querySelectorAll('.bed-card');
        let visibleCount = 0;
        
        cards.forEach(card => {
            const matchesStatus = (currentStatusFilter === 'all' || card.classList.contains('status-' + currentStatusFilter));
            const text = card.textContent.toLowerCase();
            const matchesSearch = (!term || text.includes(term));
            
            if (matchesStatus && matchesSearch) {
                card.style.display = '';
                visibleCount++;
            } else {
                card.style.display = 'none';
            }
        });

        const counterEl = document.getElementById('visibleBedCount');
        if (counterEl) {
            counterEl.textContent = `${visibleCount} Beds Shown`;
        }
    }
</script>

<?php require_once __DIR__ . '/../../app/views/layouts/footer.php'; ?>
