<?php
/**
 * KVM Hospital Management System — NexusCare
 * QueueFlow — Department-Wise Live Patient Flow Intelligence
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

// Supported 9 Core Clinical Departments
$departmentTabs = [
    ['id' => 4,  'name' => 'General Medicine',        'short' => 'Gen Medicine'],
    ['id' => 9,  'name' => 'Cardiology',              'short' => 'Cardiology'],
    ['id' => 8,  'name' => 'Orthopedics',             'short' => 'Orthopedics'],
    ['id' => 6,  'name' => 'Pediatrics',              'short' => 'Pediatrics'],
    ['id' => 10, 'name' => 'Neurology',               'short' => 'Neurology'],
    ['id' => 1,  'name' => 'Emergency',               'short' => 'Emergency'],
    ['id' => 11, 'name' => 'Dermatology',             'short' => 'Dermatology'],
    ['id' => 23, 'name' => 'ENT',                     'short' => 'ENT'],
    ['id' => 7,  'name' => 'Obstetrics & Gynecology', 'short' => 'OB/GYN']
];

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    $postDeptId = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null;

    // Helper redirect preserving selected department tab
    $redirectWithDept = function(?int $deptId = null) {
        $url = BASE_URL . '/modules/queueflow/index.php';
        if ($deptId) {
            $url .= '?department_id=' . $deptId;
        }
        redirect($url);
    };

    // Action 1: Check-in patient & generate token
    if ($action === 'checkin') {
        $patientId    = (int)($_POST['patient_id'] ?? 0);
        $doctorId     = (int)($_POST['doctor_id'] ?? 0);
        $departmentId = (int)($_POST['department_id'] ?? 0);
        $appointmentId = !empty($_POST['appointment_id']) ? (int)$_POST['appointment_id'] : null;
        $priority     = $_POST['priority_level'] ?? 'normal';
        $reason       = !empty($_POST['priority_reason']) ? sanitize_input($_POST['priority_reason']) : null;

        if ($doctorId && !$departmentId) {
            $dStmt = $db->prepare("SELECT department_id FROM `doctor_profiles` WHERE id = :id");
            $dStmt->execute(['id' => $doctorId]);
            $departmentId = (int)$dStmt->fetchColumn();
        }

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
        $redirectWithDept($departmentId);
    }

    // Action 2: Call patient
    if ($action === 'call_patient') {
        $queueId  = (int)($_POST['queue_entry_id'] ?? 0);
        $doctorId = !empty($_POST['doctor_id']) ? (int)$_POST['doctor_id'] : null;
        if ($queueService->callPatient($queueId, $doctorId)) {
            set_flash('info', "Patient called into consultation room.");
        } else {
            set_flash('warning', "Unable to call patient. Please refresh and try again.");
        }
        $redirectWithDept($postDeptId);
    }

    // Action 3: Start Consultation
    if ($action === 'start_consultation') {
        $queueId  = (int)($_POST['queue_entry_id'] ?? 0);
        $doctorId = !empty($_POST['doctor_id']) ? (int)$_POST['doctor_id'] : null;
        if ($queueService->startConsultation($queueId, $doctorId)) {
            set_flash('success', "Consultation session started. Patient is now in room.");
        } else {
            set_flash('warning', "Unable to start consultation session.");
        }
        $redirectWithDept($postDeptId);
    }

    // Action 4: Complete Consultation
    if ($action === 'complete_consultation') {
        $queueId  = (int)($_POST['queue_entry_id'] ?? 0);
        $doctorId = !empty($_POST['doctor_id']) ? (int)$_POST['doctor_id'] : null;
        if ($queueService->completeConsultation($queueId, $doctorId)) {
            set_flash('success', "Consultation completed successfully.");
        } else {
            set_flash('warning', "Unable to complete consultation.");
        }
        $redirectWithDept($postDeptId);
    }

    // Action 5: Cancel patient token
    if ($action === 'cancel_patient') {
        $queueId  = (int)($_POST['queue_entry_id'] ?? 0);
        $doctorId = !empty($_POST['doctor_id']) ? (int)$_POST['doctor_id'] : null;
        $reason   = sanitize_input($_POST['cancel_reason'] ?? 'Cancelled by staff');
        if ($queueService->cancelPatient($queueId, $doctorId, (int)($currentUser['id'] ?? 0), $reason)) {
            set_flash('info', "Patient queue token cancelled.");
        } else {
            set_flash('danger', "Failed to cancel queue token.");
        }
        $redirectWithDept($postDeptId);
    }

    // Action 6: Priority Override
    if ($action === 'update_priority') {
        $queueId  = (int)($_POST['queue_entry_id'] ?? 0);
        $priority = $_POST['priority_level'] ?? 'urgent';
        $reason   = sanitize_input($_POST['override_reason'] ?? '');

        if ($queueId && !empty($reason)) {
            if ($queueService->updatePriority($queueId, $priority, $reason, (int)($currentUser['id'] ?? 0))) {
                set_flash('warning', "Queue priority updated to " . strtoupper($priority) . ". Reason recorded in TrustLedger.");
            } else {
                set_flash('danger', "Failed to update queue priority.");
            }
        } else {
            set_flash('danger', "Documented justification reason is mandatory for priority escalation.");
        }
        $redirectWithDept($postDeptId);
    }
}

// Department selection logic (Default to General Medicine: ID 4)
$rawDept = $_GET['department_id'] ?? null;
if ($rawDept === 'all') {
    $filterDept = null;
    $activeTab = 'all';
} elseif ($rawDept !== null && is_numeric($rawDept)) {
    $filterDept = (int)$rawDept;
    $activeTab = (string)$filterDept;
} else {
    $filterDept = 4; // General Medicine
    $activeTab = '4';
}

// Fetch filter parameters
$filterDoctor   = !empty($_GET['doctor_id']) ? (int)$_GET['doctor_id'] : null;
$filterPriority = !empty($_GET['priority']) ? sanitize_input($_GET['priority']) : null;
$filterStatus   = !empty($_GET['status']) ? sanitize_input($_GET['status']) : null;
$filterSearch   = !empty($_GET['search']) ? sanitize_input($_GET['search']) : null;

// If user is a Doctor, prefilter to themselves by default unless explicitly searching all
if (has_role('doctor') && !empty($currentUser['doctor_id']) && !$filterDoctor) {
    $filterDoctor = (int)$currentUser['doctor_id'];
}

// Fetch counts per department for tab badges
$deptCountsStmt = $db->query("
    SELECT department_id, COUNT(*) AS total_count
    FROM `queue_entries`
    WHERE `queue_date` = CURDATE()
    GROUP BY department_id
");
$deptCounts = [];
while ($row = $deptCountsStmt->fetch()) {
    $deptCounts[(int)$row['department_id']] = (int)$row['total_count'];
}
$allDeptsTotal = array_sum($deptCounts);

// Fetch live operational queue entries
$entries = $queueService->getLiveQueue(
    doctorId: $filterDoctor,
    departmentId: $filterDept,
    date: null,
    priority: $filterPriority,
    status: $filterStatus,
    search: $filterSearch
);

// Summary statistics
$waitingCount = 0;
$inConsultCount = 0;
$calledCount = 0;
$completedCount = 0;
$cancelledCount = 0;
$totalWait = 0;
foreach ($entries as $e) {
    if ($e['status'] === 'waiting') {
        $waitingCount++;
        $totalWait += (int)$e['estimated_wait_time_minutes'];
    } elseif ($e['status'] === 'in_consultation') {
        $inConsultCount++;
    } elseif ($e['status'] === 'called') {
        $calledCount++;
    } elseif ($e['status'] === 'completed') {
        $completedCount++;
    } elseif ($e['status'] === 'cancelled') {
        $cancelledCount++;
    }
}
$avgWait = $waitingCount > 0 ? round($totalWait / $waitingCount) : 0;

// Fetch doctors list for dropdowns (filter by department if single dept active)
$docSql = "
    SELECT d.id, d.first_name, d.last_name, d.doctor_code, d.room_no, dep.name AS dept_name, dep.id AS dept_id 
    FROM `doctor_profiles` d 
    JOIN `departments` dep ON d.department_id = dep.id 
    WHERE d.availability_status != 'off_duty'
";
if ($filterDept) {
    $docSql .= " AND d.department_id = " . (int)$filterDept;
}
$docSql .= " ORDER BY d.first_name ASC";
$doctorsList = $db->query($docSql)->fetchAll();

// All doctors list for check-in modal
$allDoctors = $db->query("
    SELECT d.id, d.first_name, d.last_name, d.doctor_code, dep.name AS dept_name, dep.id AS dept_id 
    FROM `doctor_profiles` d 
    JOIN `departments` dep ON d.department_id = dep.id 
    ORDER BY dep.name ASC, d.first_name ASC
")->fetchAll();

$patientsList = $db->query("SELECT id, patient_id, first_name, last_name, phone FROM `patient_profiles` ORDER BY first_name ASC LIMIT 60")->fetchAll();

// Current active department name
$currentDeptName = 'All Clinical Departments';
if ($filterDept) {
    foreach ($departmentTabs as $dt) {
        if ($dt['id'] === $filterDept) {
            $currentDeptName = $dt['name'];
            break;
        }
    }
}

$pageTitle = 'QueueFlow — Live Operational Queue';
require_once __DIR__ . '/../../app/views/layouts/header.php';
?>

<style>
/* ====================================================================
   Department-Wise Patient Queue Styling
   ==================================================================== */
.dept-tabs-wrapper {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    margin-bottom: 1.25rem;
    padding: 0.25rem 0;
}

.dept-pill-tab {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.6rem 1.15rem;
    border-radius: 9999px;
    font-size: 0.85rem;
    font-weight: 600;
    color: #475569;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    text-decoration: none;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 1px 2px rgba(0,0,0,0.03);
    cursor: pointer;
}

.dept-pill-tab:hover {
    background: #f8fafc;
    color: #0284c7;
    border-color: #cbd5e1;
    transform: translateY(-1px);
}

.dept-pill-tab.active {
    background: #0284c7 !important;
    color: #ffffff !important;
    border-color: #0284c7 !important;
    box-shadow: 0 4px 12px rgba(2, 132, 199, 0.35);
}

.dept-pill-tab .badge {
    font-size: 0.75rem;
    font-weight: 700;
    padding: 0.25rem 0.55rem;
}

.dept-pill-tab.active .badge {
    background: rgba(255, 255, 255, 0.25) !important;
    color: #ffffff !important;
}

.badge-in-room {
    background: #ecfdf5 !important;
    color: #047857 !important;
    border: 1px solid #a7f3d0;
}

.badge-status-in_consultation {
    background: #ecfdf5 !important;
    color: #047857 !important;
    border: 1px solid #a7f3d0;
}

.badge-status-called {
    background: #fffbeb !important;
    color: #b45309 !important;
    border: 1px solid #fde68a;
}

.badge-status-waiting {
    background: #eff6ff !important;
    color: #1d4ed8 !important;
    border: 1px solid #bfdbfe;
}

.badge-status-completed {
    background: #f1f5f9 !important;
    color: #475569 !important;
    border: 1px solid #e2e8f0;
}

.badge-status-cancelled {
    background: #fef2f2 !important;
    color: #991b1b !important;
    border: 1px solid #fecaca;
}

.badge-priority-emergency {
    background: #fef2f2 !important;
    color: #b91c1c !important;
    border: 1px solid #fecaca;
    font-weight: 800;
    animation: pulse 2s infinite;
}

.badge-priority-urgent {
    background: #fffbeb !important;
    color: #b45309 !important;
    border: 1px solid #fde68a;
    font-weight: 700;
}

.badge-priority-normal {
    background: #f8fafc !important;
    color: #475569 !important;
    border: 1px solid #e2e8f0;
}

.table-nexus tbody tr {
    transition: background-color 0.15s ease;
}

.table-nexus tbody tr:hover {
    background-color: #f8fafc;
}

.demo-notice-banner {
    background: linear-gradient(90deg, #eff6ff 0%, #f0fdfa 100%);
    border-left: 4px solid #0284c7 !important;
    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
}
</style>

<!-- Title and Top Action Bar -->
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
    <div>
        <div class="d-flex align-items-center gap-2">
            <h4 class="fw-bold mb-0">QueueFlow — Live Operational Queue</h4>
            <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 px-2 py-1">
                <?= e($currentDeptName) ?>
            </span>
        </div>
        <p class="text-secondary small mb-0 mt-1">Independent department queues, dynamic wait-time estimation, triage priority sorting & status controls</p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 py-2 px-3">
            <i class="bi bi-broadcast me-1"></i>Live Polling (12s)
        </span>
        <?php if (has_role('receptionist', 'nurse', 'hospital_admin', 'super_admin')): ?>
            <button type="button" class="btn btn-nexus-primary" data-bs-toggle="modal" data-bs-target="#checkInModal">
                <i class="bi bi-person-check-fill me-1"></i>Check-In / Issue Token
            </button>
        <?php endif; ?>
    </div>
</div>

<!-- Demo Data Notice Banner -->
<div class="alert demo-notice-banner border-0 p-3 mb-4 rounded-3 d-flex flex-wrap align-items-center justify-content-between gap-3">
    <div class="d-flex align-items-center gap-3">
        <div class="rounded-circle p-2 d-flex align-items-center justify-content-center flex-shrink-0" style="background: rgba(2, 132, 199, 0.12); width: 40px; height: 40px;">
            <i class="bi bi-info-circle-fill text-primary fs-5"></i>
        </div>
        <div>
            <div class="fw-bold text-dark">Live Operational Queue &bull; Demo Environment Active</div>
            <div class="text-secondary small">
                Showing exactly 5 fictional demo patients per department (45 total across 9 clinical departments) with authentic Tamil Nadu identities and triage urgencies. All status transitions update database records in real time.
            </div>
        </div>
    </div>
    <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 py-1 px-3">
        <i class="bi bi-shield-check me-1"></i>Demo Data Notice
    </span>
</div>

<!-- 9 Department Pill Tabs -->
<div class="dept-tabs-wrapper">
    <?php foreach ($departmentTabs as $dept): ?>
        <?php $countForDept = $deptCounts[$dept['id']] ?? 0; ?>
        <a href="index.php?department_id=<?= $dept['id'] ?>" 
           class="dept-pill-tab <?= $filterDept === $dept['id'] ? 'active' : '' ?>"
           id="tab-dept-<?= $dept['id'] ?>">
            <span><?= e($dept['name']) ?></span>
            <span class="badge rounded-pill <?= $filterDept === $dept['id'] ? 'bg-white bg-opacity-25 text-white' : 'bg-light text-secondary border' ?>">
                <?= $countForDept ?>
            </span>
        </a>
    <?php endforeach; ?>
    <a href="index.php?department_id=all" 
       class="dept-pill-tab <?= $filterDept === null ? 'active' : '' ?>"
       id="tab-dept-all">
        <i class="bi bi-grid me-1"></i>All Departments
        <span class="badge rounded-pill <?= $filterDept === null ? 'bg-white bg-opacity-25 text-white' : 'bg-light text-secondary border' ?>">
            <?= $allDeptsTotal ?>
        </span>
    </a>
</div>

<!-- QueueFlow KPI Cards -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-lg-3">
        <div class="stat-widget">
            <div class="stat-icon emerald">
                <i class="bi bi-person-video2"></i>
            </div>
            <div>
                <div class="stat-value text-success" id="kpiInConsult"><?= $inConsultCount ?></div>
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
                <div class="stat-value text-warning" id="kpiCalled"><?= $calledCount ?></div>
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
                <div class="stat-value text-primary" id="kpiWaiting"><?= $waitingCount ?></div>
                <div class="stat-label">Waiting in Lounge</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="stat-widget">
            <div class="stat-icon teal">
                <i class="bi bi-clock-history"></i>
            </div>
            <div>
                <div class="stat-value" id="kpiAvgWait"><?= $avgWait ?> <span class="fs-6 text-muted">mins</span></div>
                <div class="stat-label">Est. Average Wait</div>
            </div>
        </div>
    </div>
</div>

<!-- Filter Bar -->
<div class="nexus-card mb-4">
    <div class="nexus-card-body p-3">
        <form method="GET" action="index.php" id="queueFilterForm" class="row g-2 align-items-center">
            <!-- Hidden Department ID to preserve active tab -->
            <?php if ($filterDept): ?>
                <input type="hidden" name="department_id" id="filterDeptIdInput" value="<?= $filterDept ?>">
            <?php else: ?>
                <input type="hidden" name="department_id" id="filterDeptIdInput" value="all">
            <?php endif; ?>

            <!-- Search -->
            <div class="col-md-4">
                <label class="form-label small text-secondary fw-semibold mb-1">Live Search</label>
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" name="search" id="queueSearchInput" class="form-control border-start-0" 
                           placeholder="Search name, token #, or ID..." 
                           value="<?= e($filterSearch ?? '') ?>" autocomplete="off">
                </div>
            </div>

            <!-- Doctor Filter -->
            <div class="col-md-3">
                <label class="form-label small text-secondary fw-semibold mb-1">Attending Doctor</label>
                <select name="doctor_id" id="filterDoctorSelect" class="form-select" onchange="submitFilterForm()">
                    <option value="">All Attending Doctors</option>
                    <?php foreach ($doctorsList as $doc): ?>
                        <option value="<?= $doc['id'] ?>" <?= $filterDoctor === (int)$doc['id'] ? 'selected' : '' ?>>
                            Dr. <?= e($doc['first_name'] . ' ' . $doc['last_name']) ?> (<?= e($doc['dept_name']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Priority Filter -->
            <div class="col-md-2">
                <label class="form-label small text-secondary fw-semibold mb-1">Priority</label>
                <select name="priority" id="filterPrioritySelect" class="form-select" onchange="submitFilterForm()">
                    <option value="">All Priorities</option>
                    <option value="emergency" <?= $filterPriority === 'emergency' ? 'selected' : '' ?>>Emergency (Red)</option>
                    <option value="urgent" <?= $filterPriority === 'urgent' ? 'selected' : '' ?>>Urgent (Amber)</option>
                    <option value="normal" <?= $filterPriority === 'normal' ? 'selected' : '' ?>>Normal</option>
                </select>
            </div>

            <!-- Status Filter -->
            <div class="col-md-2">
                <label class="form-label small text-secondary fw-semibold mb-1">Queue Status</label>
                <select name="status" id="filterStatusSelect" class="form-select" onchange="submitFilterForm()">
                    <option value="">All Statuses</option>
                    <option value="waiting" <?= $filterStatus === 'waiting' ? 'selected' : '' ?>>Waiting</option>
                    <option value="called" <?= $filterStatus === 'called' ? 'selected' : '' ?>>Called</option>
                    <option value="in_consultation" <?= $filterStatus === 'in_consultation' ? 'selected' : '' ?>>In Consultation</option>
                    <option value="completed" <?= $filterStatus === 'completed' ? 'selected' : '' ?>>Completed</option>
                    <option value="cancelled" <?= $filterStatus === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                </select>
            </div>

            <!-- Reset Filter Button -->
            <div class="col-md-1 d-flex align-items-end">
                <a href="index.php<?= $filterDept ? '?department_id=' . $filterDept : '' ?>" 
                   class="btn btn-outline-secondary w-100" 
                   style="margin-top: 1.5rem;" 
                   title="Reset all filters back to department default">
                    <i class="bi bi-arrow-clockwise"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Main Live Queue Table Card -->
<div class="nexus-card">
    <div class="nexus-card-header d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center gap-2">
            <i class="bi bi-table text-primary"></i>
            <h6 class="fw-bold mb-0">Live Operational Queue — <?= e($currentDeptName) ?> (<?= date('d M Y') ?>)</h6>
        </div>
        <span class="badge bg-light text-secondary border" id="totalTokensBadge">
            Total Tokens: <?= count($entries) ?>
        </span>
    </div>
    <div class="table-responsive">
        <table class="table-nexus" id="queueTable">
            <thead>
                <tr>
                    <th style="width: 95px;">Token #</th>
                    <th>Patient Details</th>
                    <th>Priority Level</th>
                    <th>Doctor & Room</th>
                    <th>Queue Status</th>
                    <th>Est. Waiting</th>
                    <th>Check-In Time</th>
                    <th class="text-end" style="width: 210px;">Flow Actions</th>
                </tr>
            </thead>
            <tbody id="queueFlowMonitor" 
                   data-api-url="<?= BASE_URL ?>/api/queue.php" 
                   data-doctor-id="<?= e((string)$filterDoctor) ?>" 
                   data-dept-id="<?= e((string)$filterDept) ?>"
                   data-priority="<?= e((string)$filterPriority) ?>"
                   data-status="<?= e((string)$filterStatus) ?>"
                   data-search="<?= e((string)$filterSearch) ?>">
                <?php if (empty($entries)): ?>
                    <tr>
                        <td colspan="8" class="text-center py-4 text-muted">
                            <i class="bi bi-inbox fs-3 d-block mb-1"></i>
                            No patient queue tokens found for the selected filter criteria.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($entries as $q): ?>
                        <tr data-patient-name="<?= strtolower(e($q['patient_first_name'] . ' ' . $q['patient_last_name'])) ?>"
                            data-token="<?= e((string)$q['token_number']) ?>"
                            data-code="<?= strtolower(e($q['patient_code'])) ?>">
                            <td>
                                <span class="fs-5 fw-bold <?= $q['status'] === 'in_consultation' ? 'text-success' : ($q['status'] === 'called' ? 'text-warning' : ($q['status'] === 'cancelled' ? 'text-danger' : 'text-primary')) ?>">
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
                                    <span class="badge badge-in-room fw-bold"><i class="bi bi-check2-circle me-1"></i>In Room</span>
                                <?php elseif ($q['status'] === 'completed' || $q['status'] === 'cancelled'): ?>
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
                                            <input type="hidden" name="department_id" value="<?= $q['department_id'] ?>">
                                            <button type="submit" class="btn btn-outline-warning btn-sm" title="Call Patient into Room">
                                                <i class="bi bi-megaphone"></i> Call
                                            </button>
                                        </form>
                                    <?php elseif ($q['status'] === 'called'): ?>
                                        <form method="POST" action="index.php" class="d-inline">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="start_consultation">
                                            <input type="hidden" name="queue_entry_id" value="<?= $q['id'] ?>">
                                            <input type="hidden" name="doctor_id" value="<?= $q['doctor_id'] ?>">
                                            <input type="hidden" name="department_id" value="<?= $q['department_id'] ?>">
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
                                            <input type="hidden" name="department_id" value="<?= $q['department_id'] ?>">
                                            <button type="submit" class="btn btn-success btn-sm" title="Finish Consultation">
                                                <i class="bi bi-check-circle"></i> Finish
                                            </button>
                                        </form>
                                    <?php endif; ?>

                                    <?php if (in_array($q['status'], ['waiting', 'called', 'in_consultation'])): ?>
                                        <button type="button" class="btn btn-outline-danger btn-sm" 
                                                onclick="openPriorityModal(<?= $q['id'] ?>, <?= $q['token_number'] ?>, '<?= e($q['priority_level']) ?>', <?= $q['department_id'] ?>)"
                                                title="Triage Priority Override">
                                            <i class="bi bi-arrow-up-circle"></i> Priority
                                        </button>
                                    <?php endif; ?>

                                    <?php if (in_array($q['status'], ['waiting', 'called'])): ?>
                                        <button type="button" class="btn btn-outline-secondary btn-sm" 
                                                onclick="openCancelModal(<?= $q['id'] ?>, <?= $q['token_number'] ?>, '<?= e(addslashes($q['patient_first_name'] . ' ' . $q['patient_last_name'])) ?>', <?= $q['department_id'] ?>)"
                                                title="Cancel Queue Token">
                                            <i class="bi bi-x-circle"></i> Cancel
                                        </button>
                                    <?php endif; ?>
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
                            <?php foreach ($allDoctors as $doc): ?>
                                <option value="<?= $doc['id'] ?>" data-dept="<?= $doc['dept_id'] ?>" <?= $filterDept === (int)$doc['dept_id'] ? 'selected' : '' ?>>
                                    Dr. <?= e($doc['first_name'] . ' ' . $doc['last_name']) ?> — <?= e($doc['dept_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <input type="hidden" name="department_id" id="modalDepartmentId" value="<?= $filterDept ?? 4 ?>">
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
                <input type="hidden" name="department_id" id="modalPriorityDeptId" value="">

                <div class="modal-body p-4">
                    <div class="alert alert-warning py-2 small mb-3">
                        <i class="bi bi-info-circle-fill me-1"></i>
                        Priority escalations bypass the sequential token order and are audited in TrustLedger.
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Escalation Target for Token #<span id="priorityTokenNum"></span></label>
                        <select name="priority_level" class="form-select" required>
                            <option value="urgent">Urgent (Elevated triage)</option>
                            <option value="emergency">Emergency (Stat resuscitation)</option>
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

<!-- Modal 3: Cancel Patient Queue Token -->
<div class="modal fade" id="cancelModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title fw-bold fs-6">
                    <i class="bi bi-x-circle me-2 text-danger"></i>Cancel Queue Token #<span id="cancelTokenNum"></span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="index.php">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="cancel_patient">
                <input type="hidden" name="queue_entry_id" id="modalCancelQid" value="">
                <input type="hidden" name="department_id" id="modalCancelDeptId" value="">

                <div class="modal-body p-4">
                    <p class="mb-3">Are you sure you want to cancel the queue token for <strong id="cancelPatientName"></strong>?</p>
                    <div class="mb-2">
                        <label class="form-label small fw-semibold">Reason for Cancellation</label>
                        <select name="cancel_reason" class="form-select">
                            <option value="Patient no-show after multiple calls">Patient no-show after multiple calls</option>
                            <option value="Patient requested reschedule">Patient requested reschedule</option>
                            <option value="Re-routed to Emergency / Critical Care">Re-routed to Emergency / Critical Care</option>
                            <option value="Duplicate token issued">Duplicate token issued</option>
                            <option value="Other administrative cancellation">Other administrative cancellation</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-danger btn-sm px-3">Confirm Cancellation</button>
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

    function openPriorityModal(qid, tokenNum, currentPriority, deptId) {
        document.getElementById('modalPriorityQid').value = qid;
        document.getElementById('priorityTokenNum').textContent = tokenNum;
        document.getElementById('modalPriorityDeptId').value = deptId || '';
        const modal = new bootstrap.Modal(document.getElementById('priorityModal'));
        modal.show();
    }

    function openCancelModal(qid, tokenNum, patientName, deptId) {
        document.getElementById('modalCancelQid').value = qid;
        document.getElementById('cancelTokenNum').textContent = tokenNum;
        document.getElementById('cancelPatientName').textContent = patientName;
        document.getElementById('modalCancelDeptId').value = deptId || '';
        const modal = new bootstrap.Modal(document.getElementById('cancelModal'));
        modal.show();
    }

    function submitFilterForm() {
        document.getElementById('queueFilterForm').submit();
    }

    // Client-side instant table filtering
    const searchInput = document.getElementById('queueSearchInput');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const query = this.value.toLowerCase().trim();
            const rows = document.querySelectorAll('#queueTable tbody tr');
            rows.forEach(function(row) {
                if (!row.hasAttribute('data-patient-name')) return;
                const name = row.getAttribute('data-patient-name') || '';
                const token = row.getAttribute('data-token') || '';
                const code = row.getAttribute('data-code') || '';
                if (!query || name.includes(query) || token.includes(query) || code.includes(query)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });

        // Submit form when pressing Enter to get server-filtered results
        searchInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                submitFilterForm();
            }
        });
    }

    // Live Polling every 12 seconds
    setInterval(function() {
        const monitor = document.getElementById('queueFlowMonitor');
        if (!monitor) return;

        const apiUrl = monitor.getAttribute('data-api-url');
        const doctorId = monitor.getAttribute('data-doctor-id') || '';
        const deptId = monitor.getAttribute('data-dept-id') || '';
        const priority = monitor.getAttribute('data-priority') || '';
        const status = monitor.getAttribute('data-status') || '';
        const search = monitor.getAttribute('data-search') || '';

        // If search input currently has focused user input, don't interrupt typing
        if (document.activeElement === searchInput && searchInput.value.length > 0) {
            return;
        }

        const params = new URLSearchParams();
        if (doctorId) params.append('doctor_id', doctorId);
        if (deptId) params.append('department_id', deptId);
        if (priority) params.append('priority', priority);
        if (status) params.append('status', status);
        if (search) params.append('search', search);

        fetch(apiUrl + '?' + params.toString())
            .then(res => res.json())
            .then(data => {
                if (data && data.success) {
                    monitor.innerHTML = data.html;
                    const totalBadge = document.getElementById('totalTokensBadge');
                    if (totalBadge) totalBadge.textContent = 'Total Tokens: ' + data.count;

                    const kpiWaiting = document.getElementById('kpiWaiting');
                    if (kpiWaiting && data.waiting !== undefined) kpiWaiting.textContent = data.waiting;

                    const kpiInConsult = document.getElementById('kpiInConsult');
                    if (kpiInConsult && data.in_consult !== undefined) kpiInConsult.textContent = data.in_consult;

                    const kpiCalled = document.getElementById('kpiCalled');
                    if (kpiCalled && data.called !== undefined) kpiCalled.textContent = data.called;

                    const kpiAvgWait = document.getElementById('kpiAvgWait');
                    if (kpiAvgWait && data.avg_wait !== undefined) kpiAvgWait.innerHTML = data.avg_wait + ' <span class="fs-6 text-muted">mins</span>';
                }
            })
            .catch(err => console.warn('Queue live poll notice:', err));
    }, 12000);
</script>

<?php require_once __DIR__ . '/../../app/views/layouts/footer.php'; ?>
