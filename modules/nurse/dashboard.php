<?php
/**
 * KVM Hospital Management System — NexusCare
 * Nursing Station & Inpatient Ward Monitoring
 */

declare(strict_types=1);

require_once __DIR__ . '/../../app/config/app.php';
require_once __DIR__ . '/../../app/config/database.php';
require_once __DIR__ . '/../../app/helpers/functions.php';
require_once __DIR__ . '/../../app/middleware/permissions.php';
require_once __DIR__ . '/../../app/services/BedAllocationService.php';

require_role('nurse', 'hospital_admin', 'super_admin');

$db = get_db();
$bedService = new BedAllocationService($db);
$bedStats = $bedService->getBedStats();

// Active Inpatient Bed Assignments
$activeInpatients = $db->query("
    SELECT 
        ba.*,
        b.bed_number,
        b.bed_type,
        b.care_level,
        r.room_number,
        w.name AS ward_name,
        p.first_name,
        p.last_name,
        p.patient_id AS pcode,
        p.gender,
        TIMESTAMPDIFF(YEAR, p.dob, CURDATE()) AS age
    FROM `bed_assignments` ba
    JOIN `beds` b ON ba.bed_id = b.id
    JOIN `rooms` r ON b.room_id = r.id
    JOIN `wards` w ON b.ward_id = w.id
    JOIN `patient_profiles` p ON ba.patient_id = p.id
    WHERE ba.status = 'active'
    ORDER BY w.id ASC, b.bed_number ASC
")->fetchAll();

// Beds currently in cleaning status
$cleaningBeds = $db->query("
    SELECT b.*, w.name AS ward_name, r.room_number 
    FROM `beds` b 
    JOIN `wards` w ON b.ward_id = w.id 
    JOIN `rooms` r ON b.room_id = r.id 
    WHERE b.status = 'cleaning'
")->fetchAll();

$pageTitle = 'Nursing Station & Inpatient Ward Monitoring';
require_once __DIR__ . '/../../app/views/layouts/header.php';
?>

<!-- Title & Action Bar -->
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div>
        <h4 class="fw-bold mb-1">Nursing Station & Inpatient Ward Monitoring</h4>
        <p class="text-secondary small mb-0">BedMatrix inpatient tracking, patient vitals surveillance, and terminal sanitation management</p>
    </div>
    
    <div class="d-flex align-items-center gap-2">
        <a href="../bedmatrix/index.php" class="btn btn-nexus-primary btn-sm">
            <i class="bi bi-grid-3x3-gap-fill me-1"></i>BedMatrix Interactive Grid
        </a>
    </div>
</div>

<!-- KPI Summary Cards -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-lg-3">
        <div class="stat-widget">
            <div class="stat-icon danger">
                <i class="bi bi-person-fill"></i>
            </div>
            <div>
                <div class="stat-value text-danger"><?= count($activeInpatients) ?></div>
                <div class="stat-label">Active Inpatients Under Care</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="stat-widget">
            <div class="stat-icon emerald">
                <i class="bi bi-check2-circle"></i>
            </div>
            <div>
                <div class="stat-value text-success"><?= (int)($bedStats['available_beds'] ?? 0) ?></div>
                <div class="stat-label">Ready Available Beds</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="stat-widget">
            <div class="stat-icon amber">
                <i class="bi bi-bucket-fill"></i>
            </div>
            <div>
                <div class="stat-value text-warning"><?= count($cleaningBeds) ?></div>
                <div class="stat-label">Beds in Sanitization Queue</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="stat-widget">
            <div class="stat-icon blue">
                <i class="bi bi-heart-pulse"></i>
            </div>
            <div>
                <div class="stat-value"><?= $bedStats['occupancy_rate'] ?? 0 ?>%</div>
                <div class="stat-label">Ward Saturation Rate</div>
            </div>
        </div>
    </div>
</div>

<!-- Active Inpatients Table -->
<div class="nexus-card mb-4">
    <div class="nexus-card-header">
        <div class="d-flex align-items-center gap-2">
            <i class="bi bi-hospital text-primary"></i>
            <h6 class="fw-bold mb-0">Active Inpatient Ward Roster (<?= count($activeInpatients) ?> Patients)</h6>
        </div>
        <span class="badge bg-light text-secondary border">24/7 Telemetry & Nursing</span>
    </div>

    <div class="table-responsive">
        <table class="table-nexus">
            <thead>
                <tr>
                    <th>Bed #</th>
                    <th>Ward & Room</th>
                    <th>Patient Details</th>
                    <th>Care Level</th>
                    <th>Admission Date</th>
                    <th>Clinical Reason / Instructions</th>
                    <th class="text-end">Care Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($activeInpatients)): ?>
                    <tr><td colspan="7" class="text-center py-4 text-muted">No inpatients currently admitted.</td></tr>
                <?php else: ?>
                    <?php foreach ($activeInpatients as $ip): ?>
                        <tr>
                            <td>
                                <strong class="fs-6 text-danger"><?= e($ip['bed_number']) ?></strong>
                                <small class="text-muted d-block"><?= e($ip['bed_type']) ?></small>
                            </td>
                            <td>
                                <strong><?= e($ip['ward_name']) ?></strong>
                                <small class="text-muted d-block">Room <?= e($ip['room_number']) ?></small>
                            </td>
                            <td>
                                <strong><?= e($ip['first_name'] . ' ' . $ip['last_name']) ?></strong>
                                <small class="text-muted d-block"><?= e($ip['pcode']) ?> &bull; <?= e($ip['age']) ?>y / <?= ucfirst(e($ip['gender'])) ?></small>
                            </td>
                            <td>
                                <span class="badge bg-<?= $ip['care_level'] === 'critical' ? 'danger' : 'info text-dark' ?>">
                                    <?= strtoupper(e($ip['care_level'])) ?>
                                </span>
                            </td>
                            <td>
                                <small class="text-muted"><?= format_datetime($ip['admission_date'], 'd M Y, h:i A') ?></small>
                            </td>
                            <td>
                                <span class="text-secondary small"><?= e($ip['clinical_reason'] ?: 'Routine admission') ?></span>
                            </td>
                            <td class="text-end">
                                <a href="../caretrail/index.php?patient_id=<?= $ip['patient_id'] ?>" class="btn btn-outline-primary btn-sm">
                                    <i class="bi bi-signpost-split"></i> CareTrail
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Sanitization Queue -->
<?php if (!empty($cleaningBeds)): ?>
    <div class="nexus-card">
        <div class="nexus-card-header bg-warning bg-opacity-10">
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-bucket-fill text-warning"></i>
                <h6 class="fw-bold mb-0 text-dark">Sanitization & Terminal Cleaning Queue</h6>
            </div>
            <span class="badge bg-warning text-dark"><?= count($cleaningBeds) ?> Beds Awaiting Certification</span>
        </div>
        <div class="table-responsive">
            <table class="table-nexus">
                <thead>
                    <tr>
                        <th>Bed #</th>
                        <th>Ward</th>
                        <th>Room</th>
                        <th>Sanitization Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($cleaningBeds as $cb): ?>
                        <tr>
                            <td><strong><?= e($cb['bed_number']) ?></strong></td>
                            <td><?= e($cb['ward_name']) ?></td>
                            <td>Room <?= e($cb['room_number']) ?></td>
                            <td>
                                <form method="POST" action="../bedmatrix/index.php" class="d-inline">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="mark_cleaned">
                                    <input type="hidden" name="bed_id" value="<?= $cb['id'] ?>">
                                    <button type="submit" class="btn btn-success btn-sm">
                                        <i class="bi bi-check2-all me-1"></i>Certify Cleaned & Ready
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../../app/views/layouts/footer.php'; ?>
