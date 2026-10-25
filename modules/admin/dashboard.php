<?php
/**
 * KVM Hospital Management System — NexusCare
 * Hospital Admin Executive Operations Dashboard
 */

declare(strict_types=1);

require_once __DIR__ . '/../../app/config/app.php';
require_once __DIR__ . '/../../app/config/database.php';
require_once __DIR__ . '/../../app/helpers/functions.php';
require_once __DIR__ . '/../../app/middleware/permissions.php';

require_role('hospital_admin', 'super_admin');

if (!isset($_GET['view']) || $_GET['view'] !== 'legacy') {
    require __DIR__ . '/../../dashboard.php';
    exit;
}

$db = get_db();

// 1. Live Operational KPI Counters
$totalPatients = (int)$db->query("SELECT COUNT(*) FROM `patient_profiles`")->fetchColumn();
$todayAppts    = (int)$db->query("SELECT COUNT(*) FROM `appointments` WHERE appointment_date = CURDATE()")->fetchColumn();
$waitingQueue  = (int)$db->query("SELECT COUNT(*) FROM `queue_entries` WHERE queue_date = CURDATE() AND status = 'waiting'")->fetchColumn();
$bedStats      = $db->query("
    SELECT 
        COUNT(*) AS total,
        SUM(CASE WHEN status = 'available' THEN 1 ELSE 0 END) AS available,
        SUM(CASE WHEN status = 'occupied' THEN 1 ELSE 0 END) AS occupied,
        SUM(CASE WHEN status = 'cleaning' THEN 1 ELSE 0 END) AS cleaning
    FROM `beds`
")->fetch();
$pendingLabs   = (int)$db->query("SELECT COUNT(*) FROM `lab_orders` WHERE status IN ('ordered', 'processing')")->fetchColumn();
$lowStockMeds  = (int)$db->query("SELECT COUNT(*) FROM `medicines` WHERE stock_quantity <= reorder_level")->fetchColumn();
$unpaidBalance = (float)$db->query("SELECT SUM(balance_amount) FROM `invoices` WHERE status != 'paid'")->fetchColumn();
$activeAlerts  = (int)$db->query("SELECT COUNT(*) FROM `flowlens_alerts` WHERE status = 'active'")->fetchColumn();

// 2. Recent Queue Tokens Today
$todayQueue = $db->query("
    SELECT qe.*, p.first_name, p.last_name, p.patient_id AS pcode, d.first_name AS dfn, d.last_name AS dln
    FROM `queue_entries` qe
    JOIN `patient_profiles` p ON qe.patient_id = p.id
    JOIN `doctor_profiles` d ON qe.doctor_id = d.id
    WHERE qe.queue_date = CURDATE()
    ORDER BY qe.id DESC
    LIMIT 6
")->fetchAll();

// 3. Active FlowLens Bottleneck Alerts
$bottlenecks = $db->query("
    SELECT * FROM `flowlens_alerts` 
    WHERE status IN ('active', 'acknowledged') 
    ORDER BY severity DESC, created_at DESC 
    LIMIT 4
")->fetchAll();

$pageTitle = 'Hospital Administrator Command Center';
require_once __DIR__ . '/../../app/views/layouts/header.php';
?>

<!-- Title & Overview -->
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div>
        <h4 class="fw-bold mb-1">Hospital Administrator Command Center</h4>
        <p class="text-secondary small mb-0">Realtime hospital operations, clinical flow surveillance, and departmental oversight</p>
    </div>
    
    <div class="d-flex align-items-center gap-2">
        <a href="<?= BASE_URL ?>/dashboard.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-speedometer2 me-1"></i>Executive Dashboard
        </a>
        <span class="badge bg-light text-dark border px-3 py-2">
            <i class="bi bi-clock me-1 text-primary"></i><?= date('l, d F Y') ?>
        </span>
    </div>
</div>

<!-- Live Operational KPI Cards (Query-Backed) -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-lg-3">
        <div class="stat-widget">
            <div class="stat-icon teal">
                <i class="bi bi-people-fill"></i>
            </div>
            <div>
                <div class="stat-value"><?= $totalPatients ?></div>
                <div class="stat-label">Registered Patients</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="stat-widget">
            <div class="stat-icon blue">
                <i class="bi bi-calendar2-check-fill"></i>
            </div>
            <div>
                <div class="stat-value text-primary"><?= $todayAppts ?></div>
                <div class="stat-label">Today's Appointments</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="stat-widget">
            <div class="stat-icon amber">
                <i class="bi bi-hourglass-split"></i>
            </div>
            <div>
                <div class="stat-value text-warning"><?= $waitingQueue ?></div>
                <div class="stat-label">Patients Waiting in Queue</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="stat-widget">
            <div class="stat-icon emerald">
                <i class="bi bi-hospital-fill"></i>
            </div>
            <div>
                <div class="stat-value text-success"><?= (int)($bedStats['available'] ?? 0) ?> / <?= (int)($bedStats['total'] ?? 0) ?></div>
                <div class="stat-label">Available Inpatient Beds</div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-sm-6 col-lg-3">
        <div class="stat-widget">
            <div class="stat-icon blue">
                <i class="bi bi-eyedropper"></i>
            </div>
            <div>
                <div class="stat-value text-info"><?= $pendingLabs ?></div>
                <div class="stat-label">Pending Lab Orders</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="stat-widget">
            <div class="stat-icon danger">
                <i class="bi bi-capsule"></i>
            </div>
            <div>
                <div class="stat-value text-danger"><?= $lowStockMeds ?></div>
                <div class="stat-label">Pharmacy Low Stock Items</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="stat-widget">
            <div class="stat-icon amber">
                <i class="bi bi-cash"></i>
            </div>
            <div>
                <div class="stat-value text-warning"><?= format_currency($unpaidBalance) ?></div>
                <div class="stat-label">Unpaid Bills Balance</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="stat-widget">
            <div class="stat-icon danger">
                <i class="bi bi-radar"></i>
            </div>
            <div>
                <div class="stat-value text-danger"><?= $activeAlerts ?></div>
                <div class="stat-label">Active FlowLens Bottlenecks</div>
            </div>
        </div>
    </div>
</div>

<!-- Operational Stations & Clinical Desks (Integrated Under Admin Login) -->
<div class="row g-3 mb-4">
    <div class="col-12">
        <div class="d-flex align-items-center justify-content-between mb-2">
            <div>
                <h6 class="fw-bold mb-0 text-dark">
                    <i class="bi bi-hospital-fill text-primary me-2"></i>Hospital Operational Stations &amp; Clinical Desks
                </h6>
                <small class="text-secondary">Administrator master access to hospital front-desk, inpatient wards, diagnostics, dispensary, and cashiers</small>
            </div>
            <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 px-2 py-1 small">
                <i class="bi bi-shield-lock-fill me-1"></i>All Access &bull; Admin Exclusive
            </span>
        </div>

        <div class="row g-3">
            <!-- 1. Reception Desk -->
            <div class="col-md-4 col-lg">
                <div class="nexus-card h-100 p-3 border hover-shadow transition-all d-flex flex-column justify-content-between" style="border-top: 3px solid #0284c7 !important;">
                    <div>
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <div class="p-2 rounded bg-info bg-opacity-10 text-info">
                                <i class="bi bi-person-workspace fs-5"></i>
                            </div>
                            <span class="badge bg-info text-dark small">Live OPD</span>
                        </div>
                        <h6 class="fw-bold mb-1">Reception Desk</h6>
                        <p class="text-muted small mb-3" style="font-size: 0.8rem; line-height: 1.35;">
                            OPD patient registration, appointment check-ins, and QueueFlow token dispensing.
                        </p>
                    </div>
                    <a href="../reception/dashboard.php" class="btn btn-sm btn-outline-primary w-100">
                        <i class="bi bi-box-arrow-in-right me-1"></i>Open Desk
                    </a>
                </div>
            </div>

            <!-- 2. Nursing Station -->
            <div class="col-md-4 col-lg">
                <div class="nexus-card h-100 p-3 border hover-shadow transition-all d-flex flex-column justify-content-between" style="border-top: 3px solid #f59e0b !important;">
                    <div>
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <div class="p-2 rounded bg-warning bg-opacity-10 text-warning">
                                <i class="bi bi-bandaid fs-5"></i>
                            </div>
                            <span class="badge bg-warning text-dark small">Ward Care</span>
                        </div>
                        <h6 class="fw-bold mb-1">Nursing Station</h6>
                        <p class="text-muted small mb-3" style="font-size: 0.8rem; line-height: 1.35;">
                            Inpatient bed oversight, BedMatrix allocations, vitals recording &amp; care rounds.
                        </p>
                    </div>
                    <a href="../nurse/dashboard.php" class="btn btn-sm btn-outline-warning text-dark w-100">
                        <i class="bi bi-box-arrow-in-right me-1"></i>Open Station
                    </a>
                </div>
            </div>

            <!-- 3. Diagnostic Lab -->
            <div class="col-md-4 col-lg">
                <div class="nexus-card h-100 p-3 border hover-shadow transition-all d-flex flex-column justify-content-between" style="border-top: 3px solid #8b5cf6 !important;">
                    <div>
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <div class="p-2 rounded bg-primary bg-opacity-10 text-primary">
                                <i class="bi bi-eyedropper fs-5"></i>
                            </div>
                            <span class="badge bg-primary small"><?= $pendingLabs ?> Pending</span>
                        </div>
                        <h6 class="fw-bold mb-1">Diagnostic Lab</h6>
                        <p class="text-muted small mb-3" style="font-size: 0.8rem; line-height: 1.35;">
                            Laboratory test orders, specimen sample intake, and diagnostic report verification.
                        </p>
                    </div>
                    <a href="../laboratory/dashboard.php" class="btn btn-sm btn-outline-primary w-100">
                        <i class="bi bi-box-arrow-in-right me-1"></i>Open Lab
                    </a>
                </div>
            </div>

            <!-- 4. Pharmacy Unit -->
            <div class="col-md-6 col-lg">
                <div class="nexus-card h-100 p-3 border hover-shadow transition-all d-flex flex-column justify-content-between" style="border-top: 3px solid #ef4444 !important;">
                    <div>
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <div class="p-2 rounded bg-danger bg-opacity-10 text-danger">
                                <i class="bi bi-capsule fs-5"></i>
                            </div>
                            <span class="badge bg-danger small"><?= $lowStockMeds ?> Low Stock</span>
                        </div>
                        <h6 class="fw-bold mb-1">Pharmacy Unit</h6>
                        <p class="text-muted small mb-3" style="font-size: 0.8rem; line-height: 1.35;">
                            Doctor prescription dispensing, medicine formulary, and drug inventory balance.
                        </p>
                    </div>
                    <a href="../pharmacy/dashboard.php" class="btn btn-sm btn-outline-danger w-100">
                        <i class="bi bi-box-arrow-in-right me-1"></i>Open Pharmacy
                    </a>
                </div>
            </div>

            <!-- 5. Billing Center -->
            <div class="col-md-6 col-lg">
                <div class="nexus-card h-100 p-3 border hover-shadow transition-all d-flex flex-column justify-content-between" style="border-top: 3px solid #10b981 !important;">
                    <div>
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <div class="p-2 rounded bg-success bg-opacity-10 text-success">
                                <i class="bi bi-receipt fs-5"></i>
                            </div>
                            <span class="badge bg-success small">Cashier Desk</span>
                        </div>
                        <h6 class="fw-bold mb-1">Billing Center</h6>
                        <p class="text-muted small mb-3" style="font-size: 0.8rem; line-height: 1.35;">
                            Patient invoices, receipt generation, insurance claims, and discharge clearances.
                        </p>
                    </div>
                    <a href="../billing/dashboard.php" class="btn btn-sm btn-outline-success w-100">
                        <i class="bi bi-box-arrow-in-right me-1"></i>Open Billing
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Differentiated Engines Quick Access -->
<div class="row g-3 mb-4">
    <div class="col-12">
        <div class="nexus-card p-3 bg-light">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                <span class="fw-bold text-dark"><i class="bi bi-stars text-warning me-1"></i>Differentiated Operational Engines:</span>
                <div class="d-flex flex-wrap gap-2">
                    <a href="../queueflow/index.php" class="btn btn-sm btn-outline-primary">QueueFlow Live Tokens</a>
                    <a href="../bedmatrix/index.php" class="btn btn-sm btn-outline-info text-dark">BedMatrix Ward Grid</a>
                    <a href="../caretrail/index.php" class="btn btn-sm btn-outline-success">CareTrail Longitudinal Dossier</a>
                    <a href="../flowlens/index.php" class="btn btn-sm btn-outline-danger">FlowLens Bottlenecks (<?= $activeAlerts ?>)</a>
                    <a href="../trustledger/index.php" class="btn btn-sm btn-outline-dark">TrustLedger Audit Vault</a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Two-Column Operational Tables -->
<div class="row g-4">
    <!-- Active Bottlenecks -->
    <div class="col-lg-6">
        <div class="nexus-card h-100">
            <div class="nexus-card-header">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-exclamation-triangle-fill text-danger"></i>
                    <h6 class="fw-bold mb-0">Active FlowLens Bottlenecks</h6>
                </div>
                <a href="../flowlens/index.php" class="small text-decoration-none">Manage Alerts</a>
            </div>
            <div class="nexus-card-body p-0">
                <?php if (empty($bottlenecks)): ?>
                    <div class="text-center py-4 text-muted small">
                        <i class="bi bi-check-circle text-success fs-3 d-block mb-1"></i>
                        No operational bottlenecks detected.
                    </div>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($bottlenecks as $bn): ?>
                            <div class="list-group-item p-3">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <strong class="text-dark small"><?= e($bn['alert_title']) ?></strong>
                                    <span class="badge bg-<?= $bn['severity'] === 'critical' ? 'danger' : 'warning text-dark' ?>">
                                        <?= strtoupper(e($bn['severity'])) ?>
                                    </span>
                                </div>
                                <p class="text-secondary small mb-1"><?= e($bn['alert_description']) ?></p>
                                <small class="text-primary"><i class="bi bi-lightbulb me-1"></i><?= e($bn['suggested_action']) ?></small>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Live Queue Tokens Today -->
    <div class="col-lg-6">
        <div class="nexus-card h-100">
            <div class="nexus-card-header">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-ticket-perforated-fill text-primary"></i>
                    <h6 class="fw-bold mb-0">QueueFlow Live Activity</h6>
                </div>
                <a href="../queueflow/index.php" class="small text-decoration-none">Open Queue Monitor</a>
            </div>
            <div class="table-responsive">
                <table class="table-nexus">
                    <thead>
                        <tr>
                            <th>Token</th>
                            <th>Patient</th>
                            <th>Attending Doctor</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($todayQueue)): ?>
                            <tr><td colspan="4" class="text-center py-3 text-muted">No active queue tokens yet today.</td></tr>
                        <?php else: ?>
                            <?php foreach ($todayQueue as $tq): ?>
                                <tr>
                                    <td><strong>#<?= e($tq['token_number']) ?></strong></td>
                                    <td>
                                        <?= e($tq['first_name'] . ' ' . $tq['last_name']) ?>
                                        <small class="text-muted d-block"><?= e($tq['pcode']) ?></small>
                                    </td>
                                    <td>Dr. <?= e($tq['dfn'] . ' ' . $tq['dln']) ?></td>
                                    <td>
                                        <span class="badge badge-nexus badge-status-<?= e($tq['status']) ?>">
                                            <?= ucwords(str_replace('_', ' ', e($tq['status']))) ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../app/views/layouts/footer.php'; ?>
