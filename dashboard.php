<?php
/**
 * KVM Hospital Management System — NexusCare
 * Executive Operations Command Center Dashboard
 */

declare(strict_types=1);

require_once __DIR__ . '/app/config/app.php';
require_once __DIR__ . '/app/config/database.php';
require_once __DIR__ . '/app/helpers/functions.php';

if (!is_logged_in()) {
    redirect(BASE_URL . '/login.php');
}

$currentUser = current_user();
$primaryRole = $currentUser['primary_role'] ?? 'hospital_admin';

// Query database for operational metrics with fallback to reference values
$totalPatients = 248;
$occupiedBeds  = 142;
$totalBeds     = 180;
$todayQueue    = 36;
$labOrders     = 18;
$stockPercent  = 87;

$db = get_db_or_null();
if ($db !== null) {
    try {
        $pCount = (int)$db->query("SELECT COUNT(*) FROM `patient_profiles`")->fetchColumn();
        if ($pCount > 0) $totalPatients = $pCount;

        $bStats = $db->query("
            SELECT 
                COUNT(*) AS total,
                SUM(CASE WHEN status = 'occupied' THEN 1 ELSE 0 END) AS occupied,
                SUM(CASE WHEN status = 'available' THEN 1 ELSE 0 END) AS available
            FROM `beds`
        ")->fetch();
        if ($bStats && (int)$bStats['total'] > 0) {
            $totalBeds = (int)$bStats['total'];
            $occupiedBeds = (int)$bStats['occupied'];
        }

        $qCount = (int)$db->query("SELECT COUNT(*) FROM `queue_entries` WHERE queue_date = CURDATE()")->fetchColumn();
        if ($qCount > 0) $todayQueue = $qCount;

        $lCount = (int)$db->query("SELECT COUNT(*) FROM `lab_orders` WHERE status IN ('ordered', 'processing')")->fetchColumn();
        if ($lCount > 0) $labOrders = $lCount;
    } catch (Throwable $e) {
        error_log("Dashboard query fallback: " . $e->getMessage());
    }
}

$bedOccPercent = $totalBeds > 0 ? round(($occupiedBeds / $totalBeds) * 100) : 79;
$availableBeds = max(0, $totalBeds - $occupiedBeds);

// Dynamic Greeting based on time of day
$hour = (int)date('G');
if ($hour >= 5 && $hour < 12) {
    $timeGreeting = 'Good Morning';
} elseif ($hour >= 12 && $hour < 17) {
    $timeGreeting = 'Good Afternoon';
} else {
    $timeGreeting = 'Good Evening';
}

$greetingName = $currentUser['full_name'] ?? $currentUser['username'] ?? 'Dr. Anita Desai';
if ($primaryRole === 'doctor' && !str_starts_with($greetingName, 'Dr.')) {
    $greetingName = 'Dr. ' . $greetingName;
}

// 9 Clinical Departments Matrix Data
$departmentsMatrix = [
    [
        'id' => 4,
        'name' => 'General Medicine',
        'icon' => 'bi-heart-pulse',
        'icon_color' => 'teal',
        'count' => 5,
        'status' => 'Normal',
        'badge' => 'badge-dark-normal'
    ],
    [
        'id' => 9,
        'name' => 'Cardiology',
        'icon' => 'bi-activity',
        'icon_color' => 'amber',
        'count' => 5,
        'status' => 'Urgent',
        'badge' => 'badge-dark-urgent'
    ],
    [
        'id' => 8,
        'name' => 'Orthopedics',
        'icon' => 'bi-person-walking',
        'icon_color' => 'teal',
        'count' => 5,
        'status' => 'Normal',
        'badge' => 'badge-dark-normal'
    ],
    [
        'id' => 6,
        'name' => 'Pediatrics',
        'icon' => 'bi-emoji-smile-fill',
        'icon_color' => 'blue',
        'count' => 5,
        'status' => 'Waiting',
        'badge' => 'badge-dark-waiting'
    ],
    [
        'id' => 10,
        'name' => 'Neurology',
        'icon' => 'bi-cpu-fill',
        'icon_color' => 'blue',
        'count' => 5,
        'status' => 'Waiting',
        'badge' => 'badge-dark-waiting'
    ],
    [
        'id' => 1,
        'name' => 'Emergency',
        'icon' => 'bi-bell-fill',
        'icon_color' => 'red',
        'count' => 5,
        'status' => 'Urgent',
        'badge' => 'badge-dark-urgent'
    ],
    [
        'id' => 11,
        'name' => 'Dermatology',
        'icon' => 'bi-droplet-half',
        'icon_color' => 'teal',
        'count' => 5,
        'status' => 'Normal',
        'badge' => 'badge-dark-normal'
    ],
    [
        'id' => 23,
        'name' => 'ENT',
        'icon' => 'bi-soundwave',
        'icon_color' => 'teal',
        'count' => 5,
        'status' => 'Normal',
        'badge' => 'badge-dark-normal'
    ],
    [
        'id' => 7,
        'name' => 'Obstetrics & Gynecology',
        'icon' => 'bi-flower1',
        'icon_color' => 'blue',
        'count' => 5,
        'status' => 'Waiting',
        'badge' => 'badge-dark-waiting'
    ]
];

// Dark theme flag triggers body.dark-dashboard in header.php
$darkTheme = true;
$pageTitle = 'NexusCare Executive Operations Command Center';
require_once __DIR__ . '/app/views/layouts/header.php';
?>

<!-- Hero Greeting Banner -->
<div class="dark-greeting-banner">
    <div>
        <h3 class="fw-bold text-white mb-1 d-flex align-items-center gap-2">
            <span>👋</span>
            <span><?= $timeGreeting ?>, <?= e($greetingName) ?></span>
        </h3>
        <p class="text-secondary small mb-0">Here's what's happening at NexusCare KVM Hospital today.</p>
    </div>
    <div class="d-none d-lg-flex align-items-center gap-4">
        <span class="text-secondary small fw-medium" style="letter-spacing: 0.05em; font-size: 0.82rem;">
            Better Care &nbsp;|&nbsp; Smarter Operations &nbsp;|&nbsp; Healthier Tomorrow
        </span>
        <div class="ecg-container" style="width: 140px; height: 36px; display: flex; align-items: center;">
            <svg class="ecg-waveform" viewBox="0 0 140 36" style="width: 100%; height: 100%;">
                <path d="M 0 18 L 30 18 L 38 6 L 46 30 L 54 10 L 60 24 L 66 18 L 140 18" stroke="#14b8a6" stroke-width="2.5" fill="none" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
        </div>
    </div>
</div>

<!-- Top 5 KPI Metric Cards -->
<div class="row g-3 mb-4">
    <!-- 1. Total Patients -->
    <div class="col-6 col-md-4 col-xl">
        <div class="dark-stat-card">
            <div class="dark-stat-icon cyan">
                <i class="bi bi-people-fill"></i>
            </div>
            <div>
                <div class="dark-stat-label">Total Patients</div>
                <div class="dark-stat-value"><?= number_format($totalPatients) ?></div>
                <div class="dark-stat-trend text-info">
                    <i class="bi bi-arrow-up-short"></i>12% <span class="text-secondary fw-normal">vs. yesterday</span>
                </div>
            </div>
        </div>
    </div>

    <!-- 2. Beds Occupied -->
    <div class="col-6 col-md-4 col-xl">
        <div class="dark-stat-card">
            <div class="dark-stat-icon cyan">
                <i class="bi bi-hospital-fill"></i>
            </div>
            <div>
                <div class="dark-stat-label">Beds Occupied</div>
                <div class="dark-stat-value"><?= $occupiedBeds ?> / <?= $totalBeds ?></div>
                <div class="dark-stat-trend text-secondary">
                    <?= $bedOccPercent ?>% occupancy
                </div>
            </div>
        </div>
    </div>

    <!-- 3. Today's Queue -->
    <div class="col-6 col-md-4 col-xl">
        <div class="dark-stat-card">
            <div class="dark-stat-icon cyan">
                <i class="bi bi-person-lines-fill"></i>
            </div>
            <div>
                <div class="dark-stat-label">Today's Queue</div>
                <div class="dark-stat-value"><?= $todayQueue ?></div>
                <div class="dark-stat-trend text-info">
                    <i class="bi bi-arrow-up-short"></i>8% <span class="text-secondary fw-normal">vs. yesterday</span>
                </div>
            </div>
        </div>
    </div>

    <!-- 4. Lab Orders -->
    <div class="col-6 col-md-6 col-xl">
        <div class="dark-stat-card">
            <div class="dark-stat-icon cyan">
                <i class="bi bi-eyedropper"></i>
            </div>
            <div>
                <div class="dark-stat-label">Lab Orders</div>
                <div class="dark-stat-value"><?= $labOrders ?></div>
                <div class="dark-stat-trend text-info">
                    <i class="bi bi-arrow-up-short"></i>15% <span class="text-secondary fw-normal">pending results</span>
                </div>
            </div>
        </div>
    </div>

    <!-- 5. Pharmacy Stock -->
    <div class="col-12 col-md-6 col-xl">
        <div class="dark-stat-card">
            <div class="dark-stat-icon cyan">
                <i class="bi bi-capsule"></i>
            </div>
            <div class="flex-grow-1">
                <div class="dark-stat-label">Pharmacy Stock</div>
                <div class="dark-stat-value"><?= $stockPercent ?>%</div>
                <div class="dark-stat-trend text-secondary mb-1">in stock</div>
                <div class="progress" style="height: 5px; background: rgba(255,255,255,0.08); border-radius: 9999px;">
                    <div class="progress-bar" style="width: <?= $stockPercent ?>%; background: #0ea5e9; box-shadow: 0 0 10px #0ea5e9;"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Middle Operations Row -->
<div class="row g-4 mb-4">
    <!-- Live Operational Queue (Department Matrix) -->
    <div class="col-lg-6 col-xl-6">
        <div class="nexus-dark-card h-100 d-flex flex-column">
            <div class="nexus-dark-card-header">
                <div>
                    <h6 class="fw-bold mb-0 text-white d-flex align-items-center gap-2">
                        <i class="bi bi-person-lines-fill text-info"></i>Live Operational Queue
                    </h6>
                    <small class="text-secondary" style="font-size: 0.75rem;">Real-time patient flow across departments</small>
                </div>
                <a href="<?= BASE_URL ?>/modules/queueflow/index.php" class="text-decoration-none small text-info fw-semibold hover-underline">
                    View All &rarr;
                </a>
            </div>
            <div class="nexus-dark-card-body flex-grow-1">
                <div class="dept-matrix-grid">
                    <?php foreach ($departmentsMatrix as $dept): ?>
                        <a href="<?= BASE_URL ?>/modules/queueflow/index.php?department_id=<?= $dept['id'] ?>" class="dept-matrix-card" title="Open <?= e($dept['name']) ?> Live Queue">
                            <div class="dept-matrix-icon <?= $dept['icon_color'] ?>">
                                <i class="bi <?= $dept['icon'] ?>"></i>
                            </div>
                            <div class="dept-matrix-name"><?= e($dept['name']) ?></div>
                            <div class="dept-matrix-count"><?= $dept['count'] ?></div>
                            <span class="<?= $dept['badge'] ?>"><?= $dept['status'] ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Bed Occupancy Donut & Sparkline -->
    <div class="col-lg-3 col-xl-3">
        <div class="nexus-dark-card h-100 d-flex flex-column">
            <div class="nexus-dark-card-header">
                <h6 class="fw-bold mb-0 text-white d-flex align-items-center gap-2">
                    <i class="bi bi-pie-chart text-info"></i>Bed Occupancy
                </h6>
            </div>
            <div class="nexus-dark-card-body d-flex flex-column justify-content-between flex-grow-1">
                <!-- Donut Chart Canvas Container -->
                <div class="position-relative d-flex align-items-center justify-content-center my-2" style="height: 160px;">
                    <canvas id="bedOccupancyChart"></canvas>
                    <div class="position-absolute text-center" style="pointer-events: none;">
                        <div class="fw-bold text-white fs-4" style="line-height: 1;"><?= $bedOccPercent ?>%</div>
                        <small class="text-secondary" style="font-size: 0.7rem;"><?= $occupiedBeds ?>/<?= $totalBeds ?></small>
                    </div>
                </div>

                <!-- Legend -->
                <div class="d-flex flex-column gap-2 mt-2 pt-2 border-top border-secondary border-opacity-10" style="font-size: 0.8rem;">
                    <div class="d-flex align-items-center justify-content-between">
                        <span class="d-flex align-items-center gap-2 text-secondary">
                            <span style="width: 8px; height: 8px; border-radius: 50%; background: #0ea5e9;"></span> Occupied
                        </span>
                        <strong class="text-white"><?= $occupiedBeds ?></strong>
                    </div>
                    <div class="d-flex align-items-center justify-content-between">
                        <span class="d-flex align-items-center gap-2 text-secondary">
                            <span style="width: 8px; height: 8px; border-radius: 50%; background: #14b8a6;"></span> Available
                        </span>
                        <strong class="text-white"><?= $availableBeds ?></strong>
                    </div>
                    <div class="d-flex align-items-center justify-content-between">
                        <span class="d-flex align-items-center gap-2 text-secondary">
                            <span style="width: 8px; height: 8px; border-radius: 50%; background: #334155;"></span> Maintenance
                        </span>
                        <strong class="text-white">0</strong>
                    </div>
                </div>

                <!-- Sparkline Trend -->
                <div class="mt-3 pt-2 border-top border-secondary border-opacity-10 d-flex align-items-center justify-content-between">
                    <div style="width: 80px; height: 26px;">
                        <canvas id="bedSparkline"></canvas>
                    </div>
                    <div class="text-success small fw-semibold">
                        + 8% <span class="text-secondary fw-normal">vs. last week</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions Card -->
    <div class="col-lg-3 col-xl-3">
        <div class="nexus-dark-card h-100 d-flex flex-column">
            <div class="nexus-dark-card-header">
                <h6 class="fw-bold mb-0 text-white d-flex align-items-center gap-2">
                    <i class="bi bi-lightning-charge-fill text-warning"></i>Quick Actions
                </h6>
            </div>
            <div class="nexus-dark-card-body d-flex flex-column justify-content-between flex-grow-1 gap-2">
                <a href="<?= BASE_URL ?>/modules/appointments/index.php" class="quick-action-row">
                    <span class="d-flex align-items-center gap-2">
                        <i class="bi bi-calendar2-plus text-info"></i>New Appointment
                    </span>
                    <i class="bi bi-chevron-right text-secondary small"></i>
                </a>

                <a href="<?= BASE_URL ?>/modules/patients/index.php?action=create" class="quick-action-row">
                    <span class="d-flex align-items-center gap-2">
                        <i class="bi bi-person-plus text-info"></i>Add Patient
                    </span>
                    <i class="bi bi-chevron-right text-secondary small"></i>
                </a>

                <a href="<?= BASE_URL ?>/modules/laboratory/orders.php" class="quick-action-row">
                    <span class="d-flex align-items-center gap-2">
                        <i class="bi bi-eyedropper text-info"></i>Create Lab Order
                    </span>
                    <i class="bi bi-chevron-right text-secondary small"></i>
                </a>

                <a href="<?= BASE_URL ?>/hospital-pharmacy" class="quick-action-row">
                    <span class="d-flex align-items-center gap-2">
                        <i class="bi bi-capsule text-info"></i>Add Prescription
                    </span>
                    <i class="bi bi-chevron-right text-secondary small"></i>
                </a>

                <a href="<?= BASE_URL ?>/modules/billing/invoices.php" class="quick-action-row">
                    <span class="d-flex align-items-center gap-2">
                        <i class="bi bi-receipt text-info"></i>Generate Bill
                    </span>
                    <i class="bi bi-chevron-right text-secondary small"></i>
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Lower Operations Row -->
<div class="row g-4 mb-4">
    <!-- Patient Flow & Admissions Area Chart -->
    <div class="col-lg-6">
        <div class="nexus-dark-card h-100">
            <div class="nexus-dark-card-header">
                <div>
                    <h6 class="fw-bold mb-0 text-white d-flex align-items-center gap-2">
                        <i class="bi bi-graph-up text-info"></i>Patient Flow &amp; Admissions
                    </h6>
                    <small class="text-secondary" style="font-size: 0.75rem;">Last 7 days</small>
                </div>
                <div class="d-flex align-items-center gap-3 small" style="font-size: 0.75rem;">
                    <span class="text-secondary"><span style="color: #0ea5e9;">&bull;</span> Admissions</span>
                    <span class="text-secondary"><span style="color: #14b8a6;">&bull;</span> Discharges</span>
                    <span class="text-secondary"><span style="color: #a855f7;">&bull;</span> Active Patients</span>
                </div>
            </div>
            <div class="nexus-dark-card-body">
                <div style="height: 250px; position: relative;">
                    <canvas id="patientFlowChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Activity -->
    <div class="col-lg-3">
        <div class="nexus-dark-card h-100 d-flex flex-column">
            <div class="nexus-dark-card-header">
                <h6 class="fw-bold mb-0 text-white d-flex align-items-center gap-2">
                    <i class="bi bi-clock-history text-info"></i>Recent Activity
                </h6>
                <a href="<?= BASE_URL ?>/modules/trustledger/index.php" class="text-decoration-none small text-info fw-semibold">
                    View All
                </a>
            </div>
            <div class="nexus-dark-card-body flex-grow-1">
                <div class="activity-item-dark">
                    <div class="d-flex align-items-center gap-2">
                        <div class="activity-icon-dark" style="background: rgba(14, 165, 233, 0.15); color: #38bdf8;">
                            <i class="bi bi-person"></i>
                        </div>
                        <div>
                            <div class="fw-bold text-white small">Priya Patel</div>
                            <div class="text-secondary" style="font-size: 0.7rem;">Checked in &ndash; OPD</div>
                        </div>
                    </div>
                    <span class="text-secondary" style="font-size: 0.7rem;">04:45 AM</span>
                </div>

                <div class="activity-item-dark">
                    <div class="d-flex align-items-center gap-2">
                        <div class="activity-icon-dark" style="background: rgba(59, 130, 246, 0.15); color: #60a5fa;">
                            <i class="bi bi-prescription2"></i>
                        </div>
                        <div>
                            <div class="fw-bold text-white small">Ramesh Kumar</div>
                            <div class="text-secondary" style="font-size: 0.7rem;">Prescription Issued</div>
                        </div>
                    </div>
                    <span class="text-secondary" style="font-size: 0.7rem;">04:32 AM</span>
                </div>

                <div class="activity-item-dark">
                    <div class="d-flex align-items-center gap-2">
                        <div class="activity-icon-dark" style="background: rgba(20, 184, 166, 0.15); color: #2dd4bf;">
                            <i class="bi bi-eyedropper"></i>
                        </div>
                        <div>
                            <div class="fw-bold text-white small">Lakshmi Devi</div>
                            <div class="text-secondary" style="font-size: 0.7rem;">Lab Order Completed</div>
                        </div>
                    </div>
                    <span class="text-secondary" style="font-size: 0.7rem;">04:20 AM</span>
                </div>

                <div class="activity-item-dark">
                    <div class="d-flex align-items-center gap-2">
                        <div class="activity-icon-dark" style="background: rgba(16, 185, 129, 0.15); color: #34d399;">
                            <i class="bi bi-hospital"></i>
                        </div>
                        <div>
                            <div class="fw-bold text-white small">Suresh Babu</div>
                            <div class="text-secondary" style="font-size: 0.7rem;">Admitted &ndash; Ward 3A</div>
                        </div>
                    </div>
                    <span class="text-secondary" style="font-size: 0.7rem;">04:12 AM</span>
                </div>

                <div class="activity-item-dark">
                    <div class="d-flex align-items-center gap-2">
                        <div class="activity-icon-dark" style="background: rgba(168, 85, 247, 0.15); color: #c084fc;">
                            <i class="bi bi-check2-circle"></i>
                        </div>
                        <div>
                            <div class="fw-bold text-white small">Anjali Sharma</div>
                            <div class="text-secondary" style="font-size: 0.7rem;">Discharged</div>
                        </div>
                    </div>
                    <span class="text-secondary" style="font-size: 0.7rem;">03:50 AM</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Alerts & Notifications -->
    <div class="col-lg-3">
        <div class="nexus-dark-card h-100 d-flex flex-column">
            <div class="nexus-dark-card-header">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-bell-fill text-danger"></i>
                    <h6 class="fw-bold mb-0 text-white">Alerts &amp; Notifications</h6>
                    <span class="badge rounded-pill bg-danger" style="font-size: 0.65rem;">3</span>
                </div>
                <a href="<?= BASE_URL ?>/modules/flowlens/index.php" class="text-decoration-none small text-info fw-semibold">
                    View All
                </a>
            </div>
            <div class="nexus-dark-card-body flex-grow-1 d-flex flex-column justify-content-between gap-2">
                <div class="alert-item-dark danger">
                    <div class="d-flex align-items-start gap-2">
                        <i class="bi bi-exclamation-triangle-fill text-danger mt-1"></i>
                        <div>
                            <div class="fw-bold text-white small">Low Stock Alert</div>
                            <div class="text-secondary" style="font-size: 0.75rem;">Amoxicillin 500mg (12 units left)</div>
                        </div>
                    </div>
                    <span class="text-secondary small text-nowrap" style="font-size: 0.7rem;">2h ago</span>
                </div>

                <div class="alert-item-dark warning">
                    <div class="d-flex align-items-start gap-2">
                        <i class="bi bi-clock-history text-warning mt-1"></i>
                        <div>
                            <div class="fw-bold text-white small">Expiring Soon</div>
                            <div class="text-secondary" style="font-size: 0.75rem;">Dolo 650mg (expires in 5 days)</div>
                        </div>
                    </div>
                    <span class="text-secondary small text-nowrap" style="font-size: 0.7rem;">3h ago</span>
                </div>

                <div class="alert-item-dark info">
                    <div class="d-flex align-items-start gap-2">
                        <i class="bi bi-info-circle-fill text-info mt-1"></i>
                        <div>
                            <div class="fw-bold text-white small">New Lab Result</div>
                            <div class="text-secondary" style="font-size: 0.75rem;">Patient ID: PAT-2026-0043</div>
                        </div>
                    </div>
                    <span class="text-secondary small text-nowrap" style="font-size: 0.7rem;">5h ago</span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Bottom Navigation Dock & Architectural Showcase Banner -->
<div class="row g-3">
    <!-- 5 Feature Quick Jump Cards -->
    <div class="col-xl-8">
        <div class="row g-2 h-100">
            <div class="col">
                <a href="<?= BASE_URL ?>/modules/queueflow/index.php" class="dock-card h-100">
                    <i class="bi bi-hourglass-split text-info fs-5"></i>
                    <div>
                        <div class="fw-bold text-white small" style="line-height: 1.2;">QueueFlow</div>
                        <div class="text-secondary" style="font-size: 0.65rem;">Live Patient Queue</div>
                    </div>
                </a>
            </div>
            <div class="col">
                <a href="<?= BASE_URL ?>/modules/bedmatrix/index.php" class="dock-card h-100">
                    <i class="bi bi-grid-3x3-gap text-info fs-5"></i>
                    <div>
                        <div class="fw-bold text-white small" style="line-height: 1.2;">BedMatrix</div>
                        <div class="text-secondary" style="font-size: 0.65rem;">Bed Allocation</div>
                    </div>
                </a>
            </div>
            <div class="col">
                <a href="<?= BASE_URL ?>/modules/caretrail/index.php" class="dock-card h-100">
                    <i class="bi bi-signpost-split text-info fs-5"></i>
                    <div>
                        <div class="fw-bold text-white small" style="line-height: 1.2;">CareTrail</div>
                        <div class="text-secondary" style="font-size: 0.65rem;">Patient Journey</div>
                    </div>
                </a>
            </div>
            <div class="col">
                <a href="<?= BASE_URL ?>/modules/flowlens/index.php" class="dock-card h-100">
                    <i class="bi bi-radar text-warning fs-5"></i>
                    <div>
                        <div class="fw-bold text-white small" style="line-height: 1.2;">FlowLens</div>
                        <div class="text-secondary" style="font-size: 0.65rem;">Process Analytics</div>
                    </div>
                </a>
            </div>
            <div class="col">
                <a href="<?= BASE_URL ?>/modules/trustledger/index.php" class="dock-card h-100">
                    <i class="bi bi-journal-check text-success fs-5"></i>
                    <div>
                        <div class="fw-bold text-white small" style="line-height: 1.2;">TrustLedger</div>
                        <div class="text-secondary" style="font-size: 0.65rem;">Audit &amp; Compliance</div>
                    </div>
                </a>
            </div>
        </div>
    </div>

    <!-- Showcase Architectural Hero Card -->
    <div class="col-xl-4">
        <div class="showcase-banner-card h-100">
            <div class="d-flex align-items-center gap-3">
                <img src="<?= BASE_URL ?>/assets/img/hospital_building.jpg" alt="NexusCare Hospital" style="width: 100px; height: 54px; object-fit: cover; border-radius: 8px; border: 1px solid rgba(14, 165, 233, 0.3); box-shadow: 0 0 10px rgba(14, 165, 233, 0.2);">
                <div>
                    <h6 class="fw-bold text-white mb-0" style="font-size: 0.95rem;">NexusCare</h6>
                    <div class="fw-bold text-info" style="font-size: 0.72rem; letter-spacing: 0.04em;">KVM Hospital OS</div>
                    <div class="text-secondary small" style="font-size: 0.68rem; white-space: nowrap;">Integrated Care. Seamless Operations.</div>
                </div>
            </div>
            <a href="<?= BASE_URL ?>/modules/queueflow/index.php" class="btn btn-sm btn-info rounded-circle d-flex align-items-center justify-content-center text-dark flex-shrink-0" style="width: 38px; height: 38px; box-shadow: 0 0 12px rgba(14, 165, 233, 0.5);" title="Open Operations Hub">
                <i class="bi bi-arrow-right fs-5 fw-bold"></i>
            </a>
        </div>
    </div>
</div>

<!-- Chart.js Dark Mode Initialization -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // 1. Bed Occupancy Donut Chart
    const ctxDonut = document.getElementById('bedOccupancyChart');
    if (ctxDonut) {
        new Chart(ctxDonut, {
            type: 'doughnut',
            data: {
                labels: ['Occupied', 'Available', 'Maintenance'],
                datasets: [{
                    data: [<?= $occupiedBeds ?>, <?= $availableBeds ?>, 0],
                    backgroundColor: ['#0ea5e9', '#14b8a6', '#334155'],
                    borderWidth: 0,
                    hoverOffset: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '76%',
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#0b1a30',
                        borderColor: '#0ea5e9',
                        borderWidth: 1,
                        titleColor: '#fff',
                        bodyColor: '#e2e8f0'
                    }
                }
            }
        });
    }

    // 2. Bed Occupancy Mini Sparkline
    const ctxSpark = document.getElementById('bedSparkline');
    if (ctxSpark) {
        new Chart(ctxSpark, {
            type: 'line',
            data: {
                labels: ['M', 'T', 'W', 'T', 'F', 'S', 'S'],
                datasets: [{
                    data: [68, 71, 70, 74, 72, 77, 79],
                    borderColor: '#10b981',
                    borderWidth: 2,
                    tension: 0.4,
                    pointRadius: 0,
                    fill: false
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: { display: false },
                    y: { display: false }
                },
                plugins: {
                    legend: { display: false },
                    tooltip: { enabled: false }
                }
            }
        });
    }

    // 3. Patient Flow & Admissions Area Chart (Last 7 Days)
    const ctxFlow = document.getElementById('patientFlowChart');
    if (ctxFlow) {
        const flowCtx = ctxFlow.getContext('2d');
        
        // Gradients
        const gradAdmissions = flowCtx.createLinearGradient(0, 0, 0, 240);
        gradAdmissions.addColorStop(0, 'rgba(14, 165, 233, 0.45)');
        gradAdmissions.addColorStop(1, 'rgba(14, 165, 233, 0.0)');

        const gradDischarges = flowCtx.createLinearGradient(0, 0, 0, 240);
        gradDischarges.addColorStop(0, 'rgba(20, 184, 166, 0.4)');
        gradDischarges.addColorStop(1, 'rgba(20, 184, 166, 0.0)');

        const gradActive = flowCtx.createLinearGradient(0, 0, 0, 240);
        gradActive.addColorStop(0, 'rgba(168, 85, 247, 0.35)');
        gradActive.addColorStop(1, 'rgba(168, 85, 247, 0.0)');

        new Chart(ctxFlow, {
            type: 'line',
            data: {
                labels: ['Oct 19', 'Oct 20', 'Oct 21', 'Oct 22', 'Oct 23', 'Oct 24', 'Oct 25'],
                datasets: [
                    {
                        label: 'Active Patients',
                        data: [120, 138, 142, 130, 162, 154, 178],
                        borderColor: '#a855f7',
                        backgroundColor: gradActive,
                        fill: true,
                        tension: 0.42,
                        pointBackgroundColor: '#a855f7',
                        pointBorderColor: '#0b1a30',
                        pointBorderWidth: 2,
                        pointRadius: 4,
                        borderWidth: 2.5
                    },
                    {
                        label: 'Admissions',
                        data: [42, 58, 64, 48, 72, 68, 82],
                        borderColor: '#0ea5e9',
                        backgroundColor: gradAdmissions,
                        fill: true,
                        tension: 0.42,
                        pointBackgroundColor: '#0ea5e9',
                        pointBorderColor: '#0b1a30',
                        pointBorderWidth: 2,
                        pointRadius: 4,
                        borderWidth: 2.5
                    },
                    {
                        label: 'Discharges',
                        data: [32, 40, 44, 52, 46, 62, 56],
                        borderColor: '#14b8a6',
                        backgroundColor: gradDischarges,
                        fill: true,
                        tension: 0.42,
                        pointBackgroundColor: '#14b8a6',
                        pointBorderColor: '#0b1a30',
                        pointBorderWidth: 2,
                        pointRadius: 4,
                        borderWidth: 2.5
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    intersect: false,
                    mode: 'index'
                },
                scales: {
                    x: {
                        grid: {
                            color: 'rgba(255, 255, 255, 0.05)',
                            drawBorder: false
                        },
                        ticks: {
                            color: '#94a3b8',
                            font: { size: 11 }
                        }
                    },
                    y: {
                        beginAtZero: true,
                        max: 200,
                        ticks: {
                            stepSize: 50,
                            color: '#94a3b8',
                            font: { size: 11 }
                        },
                        grid: {
                            color: 'rgba(255, 255, 255, 0.05)',
                            drawBorder: false
                        }
                    }
                },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#0b1a30',
                        borderColor: '#0ea5e9',
                        borderWidth: 1,
                        titleColor: '#fff',
                        bodyColor: '#e2e8f0',
                        padding: 10
                    }
                }
            }
        });
    }
});
</script>

<?php require_once __DIR__ . '/app/views/layouts/footer.php'; ?>
