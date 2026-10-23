<?php
/**
 * KVM Hospital Management System — NexusCare
 * Core Module: Department Management
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

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'create_department') {
        $code     = sanitize_input($_POST['dept_code'] ?? '');
        $name     = sanitize_input($_POST['name'] ?? '');
        $desc     = sanitize_input($_POST['description'] ?? '');
        $location = sanitize_input($_POST['location'] ?? '');
        $ext      = sanitize_input($_POST['phone_extension'] ?? '');

        if ($code && $name) {
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
                $audit->log('CREATE', 'Department', $deptId, "Created department {$name} ({$code})", userId: $currentUser['id']);
                set_flash('success', "Department {$name} added successfully.");
            } catch (Exception $e) {
                set_flash('danger', "Failed to create department: " . $e->getMessage());
            }
        }
        redirect('departments.php');
    }
}

$departments = $db->query("
    SELECT 
        d.*,
        (SELECT COUNT(*) FROM `doctor_profiles` dp WHERE dp.department_id = d.id) AS doctor_count,
        (SELECT COUNT(*) FROM `staff_profiles` sp WHERE sp.department_id = d.id) AS staff_count
    FROM `departments` d
    ORDER BY d.id ASC
")->fetchAll();

$pageTitle = 'Hospital Department Management';
require_once __DIR__ . '/../../app/views/layouts/header.php';
?>

<!-- Title & Action Bar -->
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div>
        <h4 class="fw-bold mb-1">Hospital Department Management</h4>
        <p class="text-secondary small mb-0">Overview of all 15 clinical divisions, staffing ratios, and telephone directories</p>
    </div>
    
    <div class="d-flex align-items-center gap-2">
        <button type="button" class="btn btn-nexus-primary" data-bs-toggle="modal" data-bs-target="#addDeptModal">
            <i class="bi bi-plus-circle me-1"></i>New Department
        </button>
    </div>
</div>

<!-- Departments Grid -->
<div class="row g-4">
    <?php foreach ($departments as $d): ?>
        <div class="col-md-6 col-lg-4">
            <div class="nexus-card h-100">
                <div class="nexus-card-header bg-light">
                    <div>
                        <span class="badge bg-dark mb-1"><?= e($d['dept_code']) ?></span>
                        <h6 class="fw-bold mb-0 text-dark"><?= e($d['name']) ?></h6>
                    </div>
                    <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25">
                        <?= strtoupper(e($d['status'])) ?>
                    </span>
                </div>
                <div class="nexus-card-body">
                    <p class="text-secondary small mb-3"><?= e($d['description'] ?: 'Specialized hospital clinical unit.') ?></p>
                    
                    <div class="bg-light p-2 rounded small mb-3 border">
                        <div class="mb-1"><i class="bi bi-geo-alt text-primary me-1"></i><strong>Location:</strong> <?= e($d['location'] ?: 'Main Wing') ?></div>
                        <div><i class="bi bi-telephone text-primary me-1"></i><strong>Extension:</strong> Ext. <?= e($d['phone_extension'] ?: '—') ?></div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center small text-muted border-top pt-2">
                        <span><i class="bi bi-heart-pulse me-1 text-danger"></i><strong><?= (int)$d['doctor_count'] ?></strong> Doctors</span>
                        <span><i class="bi bi-people me-1 text-primary"></i><strong><?= (int)$d['staff_count'] ?></strong> Staff Members</span>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- Modal: Add Department -->
<div class="modal fade" id="addDeptModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title fw-bold fs-6">Add New Department</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="departments.php">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="create_department">

                <div class="modal-body p-4">
                    <div class="row g-2 mb-3">
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Code *</label>
                            <input type="text" name="dept_code" class="form-control" required placeholder="e.g. ONCOL">
                        </div>
                        <div class="col-md-8">
                            <label class="form-label small fw-semibold">Department Name *</label>
                            <input type="text" name="name" class="form-control" required placeholder="e.g. Medical Oncology">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Clinical Scope Description</label>
                        <textarea name="description" class="form-control" rows="2" placeholder="Brief summary of clinical operations"></textarea>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-md-8">
                            <label class="form-label small fw-semibold">Floor / Wing Location</label>
                            <input type="text" name="location" class="form-control" placeholder="e.g. Floor 2, East Wing">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Phone Ext</label>
                            <input type="text" name="phone_extension" class="form-control" placeholder="116">
                        </div>
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

<?php require_once __DIR__ . '/../../app/views/layouts/footer.php'; ?>
