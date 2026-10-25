<?php
/**
 * KVM Hospital Management System — NexusCare
 * Pharmacy Unit Operations Dashboard
 */

declare(strict_types=1);

require_once __DIR__ . '/../../app/config/app.php';
require_once __DIR__ . '/../../app/config/database.php';
require_once __DIR__ . '/../../app/helpers/functions.php';
require_once __DIR__ . '/../../app/middleware/permissions.php';

require_role('pharmacist', 'hospital_admin', 'super_admin');

$db = get_db();

$totalMedicines = (int)$db->query("SELECT COUNT(*) FROM `medicines`")->fetchColumn();
$lowStockCount  = (int)$db->query("SELECT COUNT(*) FROM `medicines` WHERE stock_quantity <= reorder_level")->fetchColumn();
$nearExpiryCount= (int)$db->query("SELECT COUNT(*) FROM `medicine_batches` WHERE expiry_date <= CURDATE() + INTERVAL 90 DAY")->fetchColumn();
$dispensedToday = (int)$db->query("SELECT COUNT(*) FROM `pharmacy_orders` WHERE status = 'dispensed' AND DATE(created_at) = CURDATE()")->fetchColumn();

// Pending prescriptions
$pendingPrescriptions = $db->query("
    SELECT pr.*, p.first_name, p.last_name, p.patient_id AS pcode, d.first_name AS dfn, d.last_name AS dln
    FROM `prescriptions` pr
    JOIN `patient_profiles` p ON pr.patient_id = p.id
    JOIN `doctor_profiles` d ON pr.doctor_id = d.id
    WHERE pr.status = 'pending'
    ORDER BY pr.id DESC
    LIMIT 10
")->fetchAll();

$pageTitle = 'Pharmacy Operations & Dispensary Unit';
require_once __DIR__ . '/../../app/views/layouts/header.php';
?>

<!-- Title & Action Bar -->
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div>
        <h4 class="fw-bold mb-1">Pharmacy Operations & Dispensary Unit</h4>
        <p class="text-secondary small mb-0">Medication inventory ledger, low-stock reorder triggers, and clinical prescription dispensing</p>
    </div>
    
    <div class="d-flex align-items-center gap-2">
        <a href="/hospital-pharmacy" class="btn btn-outline-primary btn-sm" target="_blank">
            <i class="bi bi-cpu me-1"></i>Launch Pharmacy Intelligence Platform
        </a>
        <a href="inventory.php" class="btn btn-nexus-primary btn-sm">
            <i class="bi bi-box-seam me-1"></i>Manage Full Inventory & Batches
        </a>
    </div>

</div>

<!-- KPI Cards -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-lg-3">
        <div class="stat-widget">
            <div class="stat-icon teal">
                <i class="bi bi-capsule"></i>
            </div>
            <div>
                <div class="stat-value"><?= $totalMedicines ?></div>
                <div class="stat-label">Catalog Medications</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="stat-widget">
            <div class="stat-icon danger">
                <i class="bi bi-exclamation-triangle-fill"></i>
            </div>
            <div>
                <div class="stat-value text-danger"><?= $lowStockCount ?></div>
                <div class="stat-label">Low Stock Alerts</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="stat-widget">
            <div class="stat-icon amber">
                <i class="bi bi-calendar-x-fill"></i>
            </div>
            <div>
                <div class="stat-value text-warning"><?= $nearExpiryCount ?></div>
                <div class="stat-label">Near Expiry Batches (&lt;90d)</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="stat-widget">
            <div class="stat-icon emerald">
                <i class="bi bi-cart-check-fill"></i>
            </div>
            <div>
                <div class="stat-value text-success"><?= $dispensedToday ?></div>
                <div class="stat-label">Orders Dispensed Today</div>
            </div>
        </div>
    </div>
</div>

<!-- Pending Prescriptions Table -->
<div class="nexus-card">
    <div class="nexus-card-header">
        <h6 class="fw-bold mb-0">Pending Prescriptions Awaiting Verification (<?= count($pendingPrescriptions) ?>)</h6>
        <span class="badge bg-light text-secondary border">Direct Dispensing</span>
    </div>
    <div class="table-responsive">
        <table class="table-nexus">
            <thead>
                <tr>
                    <th>Prescription #</th>
                    <th>Patient Name</th>
                    <th>Prescribing Doctor</th>
                    <th>Clinical Notes</th>
                    <th>Issued Date</th>
                    <th class="text-end">Dispense Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($pendingPrescriptions)): ?>
                    <tr><td colspan="6" class="text-center py-4 text-muted">No pending prescriptions in queue.</td></tr>
                <?php else: ?>
                    <?php foreach ($pendingPrescriptions as $pres): ?>
                        <tr>
                            <td><code>#PR-<?= e((string)$pres['id']) ?></code></td>
                            <td>
                                <strong><?= e($pres['first_name'] . ' ' . $pres['last_name']) ?></strong>
                                <small class="text-muted d-block"><?= e($pres['pcode']) ?></small>
                            </td>
                            <td>Dr. <?= e($pres['dfn'] . ' ' . $pres['dln']) ?></td>
                            <td><span class="text-secondary small"><?= e($pres['notes'] ?: 'Take as directed') ?></span></td>
                            <td><small class="text-muted"><?= format_datetime($pres['created_at'], 'd M, h:i A') ?></small></td>
                            <td class="text-end">
                                <a href="inventory.php" class="btn btn-nexus-primary btn-sm">
                                    <i class="bi bi-cart-check me-1"></i>Dispense Meds
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../../app/views/layouts/footer.php'; ?>
