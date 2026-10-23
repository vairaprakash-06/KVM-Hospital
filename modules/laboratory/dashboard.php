<?php
/**
 * KVM Hospital Management System — NexusCare
 * Laboratory Diagnostic Station Dashboard
 */

declare(strict_types=1);

require_once __DIR__ . '/../../app/config/app.php';
require_once __DIR__ . '/../../app/config/database.php';
require_once __DIR__ . '/../../app/helpers/functions.php';
require_once __DIR__ . '/../../app/middleware/permissions.php';

require_role('lab_technician', 'hospital_admin', 'super_admin');

$db = get_db();

$pendingCount = (int)$db->query("SELECT COUNT(*) FROM `lab_orders` WHERE status IN ('ordered', 'processing')")->fetchColumn();
$completedToday = (int)$db->query("SELECT COUNT(*) FROM `lab_orders` WHERE status = 'completed' AND DATE(updated_at) = CURDATE()")->fetchColumn();
$statCount = (int)$db->query("SELECT COUNT(*) FROM `lab_orders` WHERE priority = 'stat' AND status != 'completed'")->fetchColumn();

// Pending items needing result entry
$pendingItems = $db->query("
    SELECT loi.*, lt.test_name, lt.test_code, lt.normal_range, lt.units, lo.order_number, lo.priority, p.first_name, p.last_name, p.patient_id AS pcode
    FROM `lab_order_items` loi
    JOIN `lab_tests` lt ON loi.test_id = lt.id
    JOIN `lab_orders` lo ON loi.lab_order_id = lo.id
    JOIN `patient_profiles` p ON lo.patient_id = p.id
    WHERE loi.status != 'completed'
    ORDER BY CASE lo.priority WHEN 'stat' THEN 1 WHEN 'urgent' THEN 2 ELSE 3 END, loi.id ASC
    LIMIT 15
")->fetchAll();

$pageTitle = 'Diagnostic Laboratory Command Station';
require_once __DIR__ . '/../../app/views/layouts/header.php';
?>

<!-- Title & Action Bar -->
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div>
        <h4 class="fw-bold mb-1">Diagnostic Laboratory Command Station</h4>
        <p class="text-secondary small mb-0">Specimen autoanalyzer queue, STAT emergency test triage, and diagnostic certification</p>
    </div>
    
    <div class="d-flex align-items-center gap-2">
        <a href="orders.php" class="btn btn-nexus-primary btn-sm">
            <i class="bi bi-eyedropper me-1"></i>All Lab Orders
        </a>
    </div>
</div>

<!-- KPI Cards -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-lg-4">
        <div class="stat-widget">
            <div class="stat-icon amber">
                <i class="bi bi-hourglass-top"></i>
            </div>
            <div>
                <div class="stat-value text-warning"><?= $pendingCount ?></div>
                <div class="stat-label">Pending Lab Orders</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-4">
        <div class="stat-widget">
            <div class="stat-icon emerald">
                <i class="bi bi-check2-circle"></i>
            </div>
            <div>
                <div class="stat-value text-success"><?= $completedToday ?></div>
                <div class="stat-label">Certified Tests Completed Today</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-4">
        <div class="stat-widget">
            <div class="stat-icon danger">
                <i class="bi bi-lightning-charge-fill"></i>
            </div>
            <div>
                <div class="stat-value text-danger"><?= $statCount ?></div>
                <div class="stat-label">STAT / Critical Priority Tests</div>
            </div>
        </div>
    </div>
</div>

<!-- Pending Test Specimen Queue -->
<div class="nexus-card">
    <div class="nexus-card-header">
        <h6 class="fw-bold mb-0">Specimen Analyzer Queue Awaiting Technologist Entry (<?= count($pendingItems) ?>)</h6>
        <span class="badge bg-light text-secondary border">Priority Order</span>
    </div>
    <div class="table-responsive">
        <table class="table-nexus">
            <thead>
                <tr>
                    <th>Order #</th>
                    <th>Patient Details</th>
                    <th>Investigation Test</th>
                    <th>Priority</th>
                    <th>Normal Reference Range</th>
                    <th class="text-end">Result Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($pendingItems)): ?>
                    <tr><td colspan="6" class="text-center py-4 text-muted">All laboratory tests are currently processed and up-to-date.</td></tr>
                <?php else: ?>
                    <?php foreach ($pendingItems as $pi): ?>
                        <tr>
                            <td><code><?= e($pi['order_number']) ?></code></td>
                            <td>
                                <strong><?= e($pi['first_name'] . ' ' . $pi['last_name']) ?></strong>
                                <small class="text-muted d-block"><?= e($pi['pcode']) ?></small>
                            </td>
                            <td>
                                <strong><?= e($pi['test_name']) ?></strong>
                                <small class="text-muted d-block"><?= e($pi['test_code']) ?></small>
                            </td>
                            <td>
                                <span class="badge badge-nexus badge-priority-<?= e($pi['priority']) ?>">
                                    <?= strtoupper(e($pi['priority'])) ?>
                                </span>
                            </td>
                            <td>
                                <?= e($pi['normal_range']) ?> <small class="text-muted"><?= e($pi['units']) ?></small>
                            </td>
                            <td class="text-end">
                                <a href="orders.php" class="btn btn-nexus-primary btn-sm">
                                    <i class="bi bi-pencil-square me-1"></i>Enter & Certify
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
