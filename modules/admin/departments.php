<?php
/**
 * KVM Hospital Management System — NexusCare
 * Core Module: Department Management & Master Registry
 */

declare(strict_types=1);

require_once __DIR__ . '/../../app/config/app.php';
require_once __DIR__ . '/../../app/config/database.php';
require_once __DIR__ . '/../../app/helpers/functions.php';
require_once __DIR__ . '/../../app/middleware/permissions.php';
require_once __DIR__ . '/../../app/middleware/csrf.php';
require_once __DIR__ . '/../../app/services/AuditService.php';

require_role('super_admin', 'hospital_admin');

$db = get_db();
$audit = new AuditService($db);
$currentUser = current_user();

// Handle Direct File Downloads
if (isset($_GET['download'])) {
    $downloadType = $_GET['download'];
    if ($downloadType === 'sql') {
        $filePath = __DIR__ . '/../../public/assets/exports/kvm_hospital_department_seed.sql';
        if (file_exists($filePath)) {
            header('Content-Description: File Transfer');
            header('Content-Type: application/sql');
            header('Content-Disposition: attachment; filename="kvm_hospital_department_seed.sql"');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . filesize($filePath));
            readfile($filePath);
            exit;
        }
    } elseif ($downloadType === 'csv') {
        $filePath = __DIR__ . '/../../public/assets/exports/kvm_hospital_departments.csv';
        if (file_exists($filePath)) {
            header('Content-Description: File Transfer');
            header('Content-Type: text/csv; charset=UTF-8');
            header('Content-Disposition: attachment; filename="kvm_hospital_departments.csv"');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . filesize($filePath));
            readfile($filePath);
            exit;
        }
    } elseif ($downloadType === 'excel') {
        $filePath = __DIR__ . '/../../public/assets/exports/kvm_hospital_departments.xls';
        if (file_exists($filePath)) {
            header('Content-Description: File Transfer');
            header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
            header('Content-Disposition: attachment; filename="kvm_hospital_departments.xls"');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . filesize($filePath));
            readfile($filePath);
            exit;
        }
    }
}

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    // Action 1: Create Department
    if ($action === 'create_department') {
        $code     = strtoupper(trim(sanitize_input($_POST['dept_code'] ?? '')));
        $name     = trim(sanitize_input($_POST['name'] ?? ''));
        $desc     = trim(sanitize_input($_POST['description'] ?? ''));
        $location = trim(sanitize_input($_POST['location'] ?? ''));
        $ext      = trim(sanitize_input($_POST['phone_extension'] ?? ''));

        if (empty($code) || empty($name)) {
            set_flash('danger', "Department code and name are required.");
            redirect('departments.php');
        }

        // Duplicate checks
        $checkStmt = $db->prepare("SELECT id FROM `departments` WHERE `dept_code` = :c OR LOWER(`name`) = LOWER(:n) LIMIT 1");
        $checkStmt->execute(['c' => $code, 'n' => $name]);
        if ($checkStmt->fetch()) {
            set_flash('warning', "A department with code '{$code}' or name '{$name}' already exists.");
            redirect('departments.php');
        }

        try {
            $ins = $db->prepare("
                INSERT INTO `departments` (`dept_code`, `name`, `description`, `location`, `phone_extension`, `status`)
                VALUES (:c, :n, :d, :l, :e, 'active')
            ");
            $ins->execute([
                'c' => $code,
                'n' => $name,
                'd' => $desc,
                'l' => $location,
                'e' => $ext
            ]);
            $deptId = (int)$db->lastInsertId();
            $audit->log(
                'CREATE', 
                'Department', 
                $deptId, 
                "Created new department '{$name}' [Code: {$code}] at '{$location}'", 
                newValues: ['dept_code' => $code, 'name' => $name, 'location' => $location, 'phone_extension' => $ext, 'status' => 'active'],
                userId: $currentUser['id']
            );
            set_flash('success', "Department '{$name}' ({$code}) created successfully.");
        } catch (Exception $e) {
            set_flash('danger', "Failed to create department: " . $e->getMessage());
        }
        redirect('departments.php');
    }

    // Action 2: Edit Department
    if ($action === 'edit_department') {
        $deptId   = (int)($_POST['department_id'] ?? 0);
        $code     = strtoupper(trim(sanitize_input($_POST['dept_code'] ?? '')));
        $name     = trim(sanitize_input($_POST['name'] ?? ''));
        $desc     = trim(sanitize_input($_POST['description'] ?? ''));
        $location = trim(sanitize_input($_POST['location'] ?? ''));
        $ext      = trim(sanitize_input($_POST['phone_extension'] ?? ''));
        $status   = in_array($_POST['status'] ?? '', ['active', 'inactive']) ? $_POST['status'] : 'active';

        if ($deptId <= 0 || empty($code) || empty($name)) {
            set_flash('danger', "Invalid parameters for department update.");
            redirect('departments.php');
        }

        // Fetch old values
        $curStmt = $db->prepare("SELECT * FROM `departments` WHERE `id` = :id LIMIT 1");
        $curStmt->execute(['id' => $deptId]);
        $oldDept = $curStmt->fetch();

        if (!$oldDept) {
            set_flash('danger', "Department #{$deptId} not found.");
            redirect('departments.php');
        }

        // Check duplicate code on another department
        $dupStmt = $db->prepare("SELECT id FROM `departments` WHERE (`dept_code` = :c OR LOWER(`name`) = LOWER(:n)) AND id != :id LIMIT 1");
        $dupStmt->execute(['c' => $code, 'n' => $name, 'id' => $deptId]);
        if ($dupStmt->fetch()) {
            set_flash('warning', "Department code '{$code}' or name '{$name}' is already used by another department.");
            redirect('departments.php');
        }

        try {
            $upd = $db->prepare("
                UPDATE `departments` 
                SET `dept_code` = :c,
                    `name` = :n,
                    `description` = :d,
                    `location` = :l,
                    `phone_extension` = :e,
                    `status` = :s
                WHERE `id` = :id
            ");
            $upd->execute([
                'c'  => $code,
                'n'  => $name,
                'd'  => $desc,
                'l'  => $location,
                'e'  => $ext,
                's'  => $status,
                'id' => $deptId
            ]);

            $audit->log(
                'UPDATE', 
                'Department', 
                $deptId, 
                "Updated department '{$name}' [Code: {$code}] details and status ({$status})",
                oldValues: $oldDept,
                newValues: ['dept_code' => $code, 'name' => $name, 'description' => $desc, 'location' => $location, 'phone_extension' => $ext, 'status' => $status],
                userId: $currentUser['id']
            );
            set_flash('success', "Department '{$name}' updated successfully.");
        } catch (Exception $e) {
            set_flash('danger', "Failed to update department: " . $e->getMessage());
        }
        redirect('departments.php');
    }

    // Action 3: Toggle Active / Inactive Status
    if ($action === 'toggle_status') {
        $deptId = (int)($_POST['department_id'] ?? 0);
        $curStmt = $db->prepare("SELECT * FROM `departments` WHERE `id` = :id LIMIT 1");
        $curStmt->execute(['id' => $deptId]);
        $dept = $curStmt->fetch();

        if ($dept) {
            $newStatus = ($dept['status'] === 'active') ? 'inactive' : 'active';

            // If deactivating, check active doctor/staff dependencies to record helpful context
            $docCount = (int)$db->prepare("SELECT COUNT(*) FROM `doctor_profiles` WHERE `department_id` = :id")->execute(['id' => $deptId]) ? $db->query("SELECT COUNT(*) FROM `doctor_profiles` WHERE `department_id` = {$deptId}")->fetchColumn() : 0;
            $staffCount = (int)$db->query("SELECT COUNT(*) FROM `staff_profiles` WHERE `department_id` = {$deptId}")->fetchColumn();

            $upd = $db->prepare("UPDATE `departments` SET `status` = :st WHERE `id` = :id");
            $upd->execute(['st' => $newStatus, 'id' => $deptId]);

            $auditMsg = "Changed status of department '{$dept['name']}' ({$dept['dept_code']}) to " . strtoupper($newStatus);
            if ($newStatus === 'inactive' && ($docCount > 0 || $staffCount > 0)) {
                $auditMsg .= " (Preserved {$docCount} doctors and {$staffCount} staff links in inactive state)";
            }

            $audit->log(
                'STATUS_CHANGE', 
                'Department', 
                $deptId, 
                $auditMsg, 
                oldValues: ['status' => $dept['status']],
                newValues: ['status' => $newStatus],
                userId: $currentUser['id']
            );

            set_flash('success', "Department '{$dept['name']}' is now " . strtoupper($newStatus) . ".");
        }
        redirect('departments.php');
    }

    // Action 4: Safe Delete Department (Guarded by Foreign Key Integrity)
    if ($action === 'delete_department') {
        $deptId = (int)($_POST['department_id'] ?? 0);
        $curStmt = $db->prepare("SELECT * FROM `departments` WHERE `id` = :id LIMIT 1");
        $curStmt->execute(['id' => $deptId]);
        $dept = $curStmt->fetch();

        if (!$dept) {
            set_flash('danger', "Department not found.");
            redirect('departments.php');
        }

        // Check foreign key references across all referencing tables
        $refCounts = [];
        $tables = [
            'doctor_profiles'    => 'Doctor Profiles',
            'staff_profiles'     => 'Staff Assignments',
            'appointments'       => 'Appointments',
            'patient_encounters' => 'Patient Encounters',
            'lab_tests'          => 'Lab Tests',
            'queue_entries'      => 'Queue Entries'
        ];

        $totalRefs = 0;
        foreach ($tables as $tbl => $label) {
            try {
                $chk = $db->prepare("SELECT COUNT(*) FROM `{$tbl}` WHERE `department_id` = :id");
                $chk->execute(['id' => $deptId]);
                $c = (int)$chk->fetchColumn();
                if ($c > 0) {
                    $refCounts[] = "{$c} {$label}";
                    $totalRefs += $c;
                }
            } catch (Exception $e) {
                // Ignore missing table gracefully
            }
        }

        if ($totalRefs > 0) {
            // Requirement 8: Do not delete departments that are already referenced by existing records; deactivate them instead.
            $refSummary = implode(', ', $refCounts);
            set_flash('danger', "<strong>Deletion Blocked by Data Integrity:</strong> Department '{$dept['name']}' is actively referenced by {$totalRefs} record(s) ({$refSummary}). Deleting it would break hospital clinical records. Please <strong>Deactivate</strong> this department instead.");
            redirect('departments.php');
        }

        // Zero references: safe to delete
        try {
            $del = $db->prepare("DELETE FROM `departments` WHERE `id` = :id");
            $del->execute(['id' => $deptId]);
            $audit->log(
                'DELETE', 
                'Department', 
                $deptId, 
                "Permanently removed unreferenced department '{$dept['name']}' ({$dept['dept_code']})", 
                oldValues: $dept,
                userId: $currentUser['id']
            );
            set_flash('success', "Department '{$dept['name']}' has been safely deleted.");
        } catch (Exception $e) {
            set_flash('danger', "Failed to delete department: " . $e->getMessage());
        }
        redirect('departments.php');
    }
}

// Fetch all departments with doctor and staff counts
$departments = $db->query("
    SELECT 
        d.*,
        (SELECT COUNT(*) FROM `doctor_profiles` dp WHERE dp.department_id = d.id) AS doctor_count,
        (SELECT COUNT(*) FROM `staff_profiles` sp WHERE sp.department_id = d.id) AS staff_count,
        (SELECT COUNT(*) FROM `appointments` a WHERE a.department_id = d.id) AS appointment_count
    FROM `departments` d
    ORDER BY d.id ASC
")->fetchAll();

$totalDepts    = count($departments);
$activeDepts   = 0;
$inactiveDepts = 0;
$totalDoctors  = 0;
$totalStaff    = 0;

foreach ($departments as $d) {
    if ($d['status'] === 'active') {
        $activeDepts++;
    } else {
        $inactiveDepts++;
    }
    $totalDoctors += (int)$d['doctor_count'];
    $totalStaff   += (int)$d['staff_count'];
}

$pageTitle = 'Hospital Department Management';
require_once __DIR__ . '/../../app/views/layouts/header.php';
?>

<!-- Title & Action Bar -->
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div>
        <h4 class="fw-bold mb-1">Hospital Department Management</h4>
        <p class="text-secondary small mb-0">
            Enterprise master registry of all <?= $totalDepts ?> clinical divisions, surgical specialties, diagnostic units, and telephone directories
        </p>
    </div>
    
    <div class="d-flex flex-wrap align-items-center gap-2">
        <!-- Download Master Dropdown -->
        <div class="dropdown">
            <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="bi bi-download me-1"></i>Download Master List
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                <li>
                    <h6 class="dropdown-header text-uppercase small fw-bold">Hospital Master Exports</h6>
                </li>
                <li>
                    <a class="dropdown-item d-flex align-items-center gap-2" href="departments.php?download=sql">
                        <i class="bi bi-filetype-sql text-primary fs-5"></i>
                        <div>
                            <div class="fw-semibold">Download SQL Seed File</div>
                            <small class="text-muted">Idempotent MySQL seed (89 departments)</small>
                        </div>
                    </a>
                </li>
                <li>
                    <a class="dropdown-item d-flex align-items-center gap-2" href="departments.php?download=excel">
                        <i class="bi bi-file-earmark-excel text-success fs-5"></i>
                        <div>
                            <div class="fw-semibold">Download Excel Spreadsheet</div>
                            <small class="text-muted">Formatted workbook with 89 divisions</small>
                        </div>
                    </a>
                </li>
                <li>
                    <a class="dropdown-item d-flex align-items-center gap-2" href="departments.php?download=csv">
                        <i class="bi bi-filetype-csv text-info fs-5"></i>
                        <div>
                            <div class="fw-semibold">Download CSV File</div>
                            <small class="text-muted">Standard delimited data table</small>
                        </div>
                    </a>
                </li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <span class="dropdown-item-text small text-muted">
                        <i class="bi bi-shield-check text-success me-1"></i>Centralized DB: <code>kvm_hospital</code>
                    </span>
                </li>
            </ul>
        </div>

        <!-- Add Department Button -->
        <button type="button" class="btn btn-nexus-primary" data-bs-toggle="modal" data-bs-target="#addDeptModal">
            <i class="bi bi-plus-circle me-1"></i>New Department
        </button>
    </div>
</div>

<!-- Department KPI Metrics -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="nexus-card p-3 d-flex align-items-center gap-3">
            <div class="rounded-circle p-3 d-flex align-items-center justify-content-center" style="background: rgba(11, 118, 119, 0.12); color: #0b7677; width: 48px; height: 48px;">
                <i class="bi bi-hospital fs-5"></i>
            </div>
            <div>
                <div class="text-muted small">Total Master Units</div>
                <div class="fs-4 fw-bold text-dark" id="statTotalDepts"><?= $totalDepts ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="nexus-card p-3 d-flex align-items-center gap-3">
            <div class="rounded-circle p-3 d-flex align-items-center justify-content-center" style="background: rgba(22, 163, 74, 0.12); color: #16a34a; width: 48px; height: 48px;">
                <i class="bi bi-check2-circle fs-5"></i>
            </div>
            <div>
                <div class="text-muted small">Active Clinical Units</div>
                <div class="fs-4 fw-bold text-success" id="statActiveDepts"><?= $activeDepts ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="nexus-card p-3 d-flex align-items-center gap-3">
            <div class="rounded-circle p-3 d-flex align-items-center justify-content-center" style="background: rgba(239, 68, 68, 0.12); color: #ef4444; width: 48px; height: 48px;">
                <i class="bi bi-heart-pulse fs-5"></i>
            </div>
            <div>
                <div class="text-muted small">Assigned Doctors</div>
                <div class="fs-4 fw-bold text-dark"><?= $totalDoctors ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="nexus-card p-3 d-flex align-items-center gap-3">
            <div class="rounded-circle p-3 d-flex align-items-center justify-content-center" style="background: rgba(59, 130, 246, 0.12); color: #3b82f6; width: 48px; height: 48px;">
                <i class="bi bi-people fs-5"></i>
            </div>
            <div>
                <div class="text-muted small">Staff Deployed</div>
                <div class="fs-4 fw-bold text-dark"><?= $totalStaff ?></div>
            </div>
        </div>
    </div>
</div>

<!-- Search and Filter Toolbar -->
<div class="nexus-card p-3 mb-4">
    <div class="row g-3 align-items-center">
        <div class="col-md-5">
            <div class="input-group">
                <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                <input type="text" id="deptSearchInput" class="form-control border-start-0" placeholder="Search by department name, code, location, or description..." autocomplete="off">
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="btn-group w-100" role="group" aria-label="Status Filter">
                <button type="button" class="btn btn-outline-secondary btn-sm active status-filter-btn" data-status="all">
                    All (<?= $totalDepts ?>)
                </button>
                <button type="button" class="btn btn-outline-success btn-sm status-filter-btn" data-status="active">
                    Active (<?= $activeDepts ?>)
                </button>
                <button type="button" class="btn btn-outline-warning btn-sm status-filter-btn" data-status="inactive">
                    Inactive (<?= $inactiveDepts ?>)
                </button>
            </div>
        </div>

        <div class="col-md-3 text-md-end text-muted small">
            Showing <strong id="visibleCount" class="text-dark"><?= $totalDepts ?></strong> of <?= $totalDepts ?> departments
        </div>
    </div>
</div>

<!-- Departments Grid -->
<div class="row g-4" id="departmentsGrid">
    <?php foreach ($departments as $d): ?>
        <?php 
            $isActive = ($d['status'] === 'active');
            $statusBadgeClass = $isActive 
                ? 'bg-success bg-opacity-10 text-success border-success border-opacity-25' 
                : 'bg-secondary bg-opacity-10 text-secondary border-secondary border-opacity-25';
        ?>
        <div class="col-md-6 col-lg-4 department-card-wrapper" 
             data-status="<?= e($d['status']) ?>"
             data-code="<?= strtolower(e($d['dept_code'])) ?>"
             data-name="<?= strtolower(e($d['name'])) ?>"
             data-desc="<?= strtolower(e($d['description'] ?? '')) ?>"
             data-location="<?= strtolower(e($d['location'] ?? '')) ?>">
            <div class="nexus-card h-100 d-flex flex-column border <?= $isActive ? '' : 'opacity-75' ?>">
                <div class="nexus-card-header bg-light d-flex align-items-center justify-content-between p-3 border-bottom">
                    <div>
                        <span class="badge bg-dark px-2 py-1 mb-1 font-monospace"><?= e($d['dept_code']) ?></span>
                        <h6 class="fw-bold mb-0 text-dark"><?= e($d['name']) ?></h6>
                    </div>
                    <span class="badge border <?= $statusBadgeClass ?> px-2 py-1">
                        <?= strtoupper(e($d['status'])) ?>
                    </span>
                </div>
                
                <div class="nexus-card-body p-3 flex-grow-1">
                    <p class="text-secondary small mb-3" style="min-height: 40px;">
                        <?= e($d['description'] ?: 'Specialized hospital clinical unit providing patient care.') ?>
                    </p>
                    
                    <div class="bg-light p-2 rounded small mb-3 border">
                        <div class="mb-1 text-truncate">
                            <i class="bi bi-geo-alt text-primary me-1"></i>
                            <strong>Location:</strong> <?= e($d['location'] ?: 'Main Hospital Wing') ?>
                        </div>
                        <div>
                            <i class="bi bi-telephone text-primary me-1"></i>
                            <strong>Extension:</strong> Ext. <?= e($d['phone_extension'] ?: '—') ?>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center small text-muted border-top pt-2">
                        <span><i class="bi bi-heart-pulse me-1 text-danger"></i><strong><?= (int)$d['doctor_count'] ?></strong> Doctors</span>
                        <span><i class="bi bi-people me-1 text-primary"></i><strong><?= (int)$d['staff_count'] ?></strong> Staff</span>
                        <span><i class="bi bi-calendar2-check me-1 text-info"></i><strong><?= (int)$d['appointment_count'] ?></strong> Appts</span>
                    </div>
                </div>

                <!-- Card Action Footer -->
                <div class="nexus-card-footer bg-white border-top p-2 px-3 d-flex justify-content-between align-items-center">
                    <button type="button" 
                            class="btn btn-outline-primary btn-sm px-2 py-1 edit-dept-btn"
                            data-bs-toggle="modal" 
                            data-bs-target="#editDeptModal"
                            data-id="<?= (int)$d['id'] ?>"
                            data-code="<?= e($d['dept_code']) ?>"
                            data-name="<?= e($d['name']) ?>"
                            data-desc="<?= e($d['description'] ?? '') ?>"
                            data-location="<?= e($d['location'] ?? '') ?>"
                            data-ext="<?= e($d['phone_extension'] ?? '') ?>"
                            data-status="<?= e($d['status']) ?>">
                        <i class="bi bi-pencil-square me-1"></i>Edit
                    </button>

                    <div class="d-flex align-items-center gap-1">
                        <!-- Toggle Status Form -->
                        <form method="POST" action="departments.php" class="d-inline" onsubmit="return confirm('Change status of <?= addslashes(e($d['name'])) ?> to <?= $isActive ? 'INACTIVE' : 'ACTIVE' ?>?');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="toggle_status">
                            <input type="hidden" name="department_id" value="<?= (int)$d['id'] ?>">
                            <button type="submit" class="btn <?= $isActive ? 'btn-outline-warning' : 'btn-outline-success' ?> btn-sm px-2 py-1" title="<?= $isActive ? 'Deactivate Department' : 'Activate Department' ?>">
                                <i class="bi <?= $isActive ? 'bi-pause-circle' : 'bi-play-circle' ?> me-1"></i><?= $isActive ? 'Deactivate' : 'Activate' ?>
                            </button>
                        </form>

                        <!-- Safe Delete Form with FK Protection -->
                        <button type="button" 
                                class="btn btn-outline-danger btn-sm px-2 py-1 delete-dept-btn"
                                data-bs-toggle="modal" 
                                data-bs-target="#deleteDeptModal"
                                data-id="<?= (int)$d['id'] ?>"
                                data-name="<?= e($d['name']) ?>"
                                data-code="<?= e($d['dept_code']) ?>"
                                data-doctors="<?= (int)$d['doctor_count'] ?>"
                                data-staff="<?= (int)$d['staff_count'] ?>"
                                data-appts="<?= (int)$d['appointment_count'] ?>">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div id="noResultsAlert" class="alert alert-light border text-center my-4 py-5 d-none">
    <i class="bi bi-search fs-1 text-muted d-block mb-2"></i>
    <h6 class="fw-bold text-dark">No departments found matching your criteria</h6>
    <p class="text-secondary small mb-3">Try adjusting your search terms or filter status.</p>
    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="resetFilters()">Reset Filters</button>
</div>

<!-- Modal: Add Department -->
<div class="modal fade" id="addDeptModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title fw-bold fs-6">
                    <i class="bi bi-plus-circle me-1"></i>Add New Hospital Department
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="departments.php">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="create_department">

                <div class="modal-body p-4">
                    <div class="row g-2 mb-3">
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Code *</label>
                            <input type="text" name="dept_code" class="form-control text-uppercase" required placeholder="e.g. GERIATRIC" maxlength="20">
                        </div>
                        <div class="col-md-8">
                            <label class="form-label small fw-semibold">Department Name *</label>
                            <input type="text" name="name" class="form-control" required placeholder="e.g. Geriatric Medicine" maxlength="100">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Clinical Scope & Operational Description</label>
                        <textarea name="description" class="form-control" rows="3" placeholder="Summary of clinical operations, specialized equipment, and consultations"></textarea>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-md-8">
                            <label class="form-label small fw-semibold">Hospital Location (Floor / Wing)</label>
                            <input type="text" name="location" class="form-control" placeholder="e.g. First Floor, Suite 138" maxlength="100">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Phone Ext</label>
                            <input type="text" name="phone_extension" class="form-control" placeholder="e.g. 138" maxlength="20">
                        </div>
                    </div>

                    <div class="alert alert-info py-2 small mb-0">
                        <i class="bi bi-shield-check me-1"></i> Department will be created as <strong>ACTIVE</strong> and recorded in <strong>TrustLedger</strong>.
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-nexus-primary btn-sm px-4">Create Department</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Edit Department -->
<div class="modal fade" id="editDeptModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title fw-bold fs-6">
                    <i class="bi bi-pencil-square me-1"></i>Edit Department Details
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="departments.php">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="edit_department">
                <input type="hidden" name="department_id" id="editDeptId" value="">

                <div class="modal-body p-4">
                    <div class="row g-2 mb-3">
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Code *</label>
                            <input type="text" name="dept_code" id="editDeptCode" class="form-control text-uppercase" required maxlength="20">
                        </div>
                        <div class="col-md-8">
                            <label class="form-label small fw-semibold">Department Name *</label>
                            <input type="text" name="name" id="editDeptName" class="form-control" required maxlength="100">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Clinical Scope Description</label>
                        <textarea name="description" id="editDeptDesc" class="form-control" rows="3"></textarea>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-md-8">
                            <label class="form-label small fw-semibold">Location</label>
                            <input type="text" name="location" id="editDeptLocation" class="form-control" maxlength="100">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Phone Ext</label>
                            <input type="text" name="phone_extension" id="editDeptExt" class="form-control" maxlength="20">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Operational Status</label>
                        <select name="status" id="editDeptStatus" class="form-select">
                            <option value="active">Active (Available for appointments & assignments)</option>
                            <option value="inactive">Inactive (Suspended from bookings)</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-nexus-primary btn-sm px-4">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Safe Delete Department (with FK Reference Warning) -->
<div class="modal fade" id="deleteDeptModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title fw-bold fs-6">
                    <i class="bi bi-exclamation-triangle-fill me-1"></i>Delete Department
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="departments.php">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete_department">
                <input type="hidden" name="department_id" id="deleteDeptId" value="">

                <div class="modal-body p-4">
                    <p class="mb-2">Are you sure you want to delete <strong id="deleteDeptName"></strong> (<span id="deleteDeptCode"></span>)?</p>
                    
                    <div id="deleteWarningBox" class="alert alert-warning py-2 small mb-3">
                        <i class="bi bi-info-circle me-1"></i>
                        <span id="deleteWarningText">Checking database references...</span>
                    </div>

                    <p class="text-secondary small mb-0">
                        <strong>Integrity Rule:</strong> If this department has linked doctors, staff profiles, encounters, or appointments, the system will block permanent deletion. Deactivate the department instead to preserve historical medical records.
                    </p>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger btn-sm px-3">Confirm Deletion</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const searchInput = document.getElementById('deptSearchInput');
    const filterButtons = document.querySelectorAll('.status-filter-btn');
    const cards = document.querySelectorAll('.department-card-wrapper');
    const visibleCount = document.getElementById('visibleCount');
    const noResults = document.getElementById('noResultsAlert');

    let currentStatus = 'all';
    let searchQuery = '';

    function filterGrid() {
        let shown = 0;
        cards.forEach(card => {
            const status = card.getAttribute('data-status');
            const code = card.getAttribute('data-code') || '';
            const name = card.getAttribute('data-name') || '';
            const desc = card.getAttribute('data-desc') || '';
            const loc = card.getAttribute('data-location') || '';

            const matchesStatus = (currentStatus === 'all' || status === currentStatus);
            const matchesQuery = (!searchQuery || 
                code.includes(searchQuery) || 
                name.includes(searchQuery) || 
                desc.includes(searchQuery) || 
                loc.includes(searchQuery));

            if (matchesStatus && matchesQuery) {
                card.style.display = '';
                shown++;
            } else {
                card.style.display = 'none';
            }
        });

        visibleCount.textContent = shown;
        if (shown === 0) {
            noResults.classList.remove('d-none');
        } else {
            noResults.classList.add('d-none');
        }
    }

    if (searchInput) {
        searchInput.addEventListener('input', function () {
            searchQuery = this.value.trim().toLowerCase();
            filterGrid();
        });
    }

    filterButtons.forEach(btn => {
        btn.addEventListener('click', function () {
            filterButtons.forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            currentStatus = this.getAttribute('data-status');
            filterGrid();
        });
    });

    window.resetFilters = function() {
        if (searchInput) searchInput.value = '';
        searchQuery = '';
        currentStatus = 'all';
        filterButtons.forEach(b => b.classList.remove('active'));
        if (filterButtons[0]) filterButtons[0].classList.add('active');
        filterGrid();
    };

    // Edit Modal Data Population
    const editModal = document.getElementById('editDeptModal');
    if (editModal) {
        editModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            document.getElementById('editDeptId').value = button.getAttribute('data-id') || '';
            document.getElementById('editDeptCode').value = button.getAttribute('data-code') || '';
            document.getElementById('editDeptName').value = button.getAttribute('data-name') || '';
            document.getElementById('editDeptDesc').value = button.getAttribute('data-desc') || '';
            document.getElementById('editDeptLocation').value = button.getAttribute('data-location') || '';
            document.getElementById('editDeptExt').value = button.getAttribute('data-ext') || '';
            document.getElementById('editDeptStatus').value = button.getAttribute('data-status') || 'active';
        });
    }

    // Delete Modal Data Population & Warning Text
    const deleteModal = document.getElementById('deleteDeptModal');
    if (deleteModal) {
        deleteModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const deptId = button.getAttribute('data-id');
            const name = button.getAttribute('data-name');
            const code = button.getAttribute('data-code');
            const docs = parseInt(button.getAttribute('data-doctors') || '0', 10);
            const staff = parseInt(button.getAttribute('data-staff') || '0', 10);
            const appts = parseInt(button.getAttribute('data-appts') || '0', 10);

            document.getElementById('deleteDeptId').value = deptId;
            document.getElementById('deleteDeptName').textContent = name;
            document.getElementById('deleteDeptCode').textContent = code;

            const warningText = document.getElementById('deleteWarningText');
            const warningBox = document.getElementById('deleteWarningBox');

            const totalRefs = docs + staff + appts;
            if (totalRefs > 0) {
                warningBox.className = 'alert alert-danger py-2 small mb-3';
                warningText.innerHTML = `<strong>Attention:</strong> This department has <strong>${docs} doctors</strong>, <strong>${staff} staff members</strong>, and <strong>${appts} appointments</strong> linked. Deletion will be rejected by foreign-key enforcement. Deactivate instead.`;
            } else {
                warningBox.className = 'alert alert-warning py-2 small mb-3';
                warningText.innerHTML = `This department has 0 active references. It can be safely deleted.`;
            }
        });
    }
});
</script>

<?php require_once __DIR__ . '/../../app/views/layouts/footer.php'; ?>
