<?php
/**
 * KVM Hospital Management System — NexusCare
 * Doctor & Staff Salary Management Dashboard
 */

declare(strict_types=1);

require_once __DIR__ . '/../../app/config/app.php';
require_once __DIR__ . '/../../app/config/database.php';
require_once __DIR__ . '/../../app/helpers/functions.php';
require_once __DIR__ . '/../../app/middleware/permissions.php';
require_once __DIR__ . '/../../app/middleware/csrf.php';
require_once __DIR__ . '/../../app/services/SalaryService.php';

// Strict Role Guard: Only Hospital Admin & Super Admin
require_role('hospital_admin', 'super_admin');

$db = get_db();
$salaryService = new SalaryService($db);

// Handle AJAX Request for Salary History
if (isset($_GET['action']) && $_GET['action'] === 'get_history') {
    $empId = (int)($_GET['employee_id'] ?? 0);
    $empType = trim($_GET['employee_type'] ?? '');
    
    if ($empId > 0 && in_array($empType, ['doctor', 'staff'], true)) {
        $history = $salaryService->getSalaryHistory($empId, $empType);
        json_response([
            'success' => true,
            'history' => array_map(function ($h) {
                return [
                    'id'                  => (int)$h['id'],
                    'previous_salary'     => format_currency($h['previous_salary']),
                    'new_salary'          => format_currency($h['new_salary']),
                    'change_reason'       => e($h['change_reason'] ?: 'Routine adjustment'),
                    'changed_by'          => e($h['changed_by_username'] ?: 'System Admin'),
                    'effective_date'      => format_datetime($h['effective_date'], 'd M Y'),
                    'created_at'          => format_datetime($h['created_at'], 'd M Y, h:i A')
                ];
            }, $history)
        ]);
    } else {
        json_response(['success' => false, 'message' => 'Invalid parameters'], 400);
    }
}

// Handle POST: Update Salary
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'update_salary') {
        $empId   = (int)($_POST['employee_id'] ?? 0);
        $empType = trim($_POST['employee_type'] ?? '');
        $salary  = (float)($_POST['monthly_salary'] ?? 0);
        $reason  = sanitize_input($_POST['change_reason'] ?? 'Annual salary review');
        $adminId = (int)($_SESSION['user']['id'] ?? 1);

        if ($empId <= 0 || !in_array($empType, ['doctor', 'staff'], true)) {
            set_flash('danger', 'Invalid employee selection.');
        } else {
            $result = $salaryService->updateSalary($empId, $empType, $salary, $reason, $adminId);
            if ($result['success']) {
                set_flash('success', $result['message']);
            } else {
                set_flash('danger', $result['message']);
            }
        }
        redirect(BASE_URL . '/modules/admin/salaries.php?' . http_build_query([
            'type'          => $_GET['type'] ?? 'all',
            'department_id' => $_GET['department_id'] ?? '',
            'search'        => $_GET['search'] ?? '',
            'sort'          => $_GET['sort'] ?? 'name_asc',
            'page'          => $_GET['page'] ?? '1'
        ]));
    }
}

// Fetch KPI statistics dynamically
$stats = $salaryService->getPayrollStats();

// Fetch departments for filter dropdown
$departments = $db->query("SELECT id, dept_code, name FROM `departments` ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

// Search, Filter, Sort and Pagination parameters
$filters = [
    'search'        => trim($_GET['search'] ?? ''),
    'type'          => trim($_GET['type'] ?? 'all'),
    'department_id' => !empty($_GET['department_id']) ? (int)$_GET['department_id'] : null,
    'sort'          => trim($_GET['sort'] ?? 'name_asc')
];

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = max(10, min(100, (int)($_GET['per_page'] ?? 25)));

$salaryData = $salaryService->getEmployeeSalaries($filters, $page, $perPage);
$records = $salaryData['records'];
$totalPages = $salaryData['total_pages'];
$totalRecords = $salaryData['total_records'];

$pageTitle = 'Salary & Payroll Management';
require_once __DIR__ . '/../../app/views/layouts/header.php';
?>

<!-- Title & Overview Header -->
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div>
        <h4 class="fw-bold mb-1">
            <i class="bi bi-wallet2 text-primary me-2"></i>Doctor &amp; Staff Salary Management
        </h4>
        <p class="text-secondary small mb-0">
            Enterprise compensation governance, individual salary assignments, and departmental payroll expenditure across all 89 divisions.
        </p>
    </div>

    <div class="d-flex align-items-center gap-2">
        <span class="badge bg-light text-dark border px-3 py-2">
            <i class="bi bi-shield-check text-success me-1"></i>Admin Exclusive Access
        </span>
    </div>
</div>

<!-- 1. Dynamic Payroll KPI Expenditure Widgets -->
<div class="row g-3 mb-4">
    <!-- Total Monthly Payroll Expenditure -->
    <div class="col-sm-6 col-lg-3">
        <div class="stat-widget">
            <div class="stat-icon emerald">
                <i class="bi bi-cash-stack"></i>
            </div>
            <div>
                <div class="stat-value text-success"><?= format_currency($stats['combined_monthly_payroll']) ?></div>
                <div class="stat-label">Total Monthly Payroll</div>
            </div>
        </div>
    </div>

    <!-- Doctor Payroll & Total Count -->
    <div class="col-sm-6 col-lg-3">
        <div class="stat-widget">
            <div class="stat-icon blue">
                <i class="bi bi-heart-pulse-fill"></i>
            </div>
            <div>
                <div class="stat-value text-primary"><?= format_currency($stats['doctor_payroll']) ?></div>
                <div class="stat-label">Doctor Payroll (<?= $stats['total_doctors'] ?> Doctors)</div>
            </div>
        </div>
    </div>

    <!-- Staff Payroll & Total Count -->
    <div class="col-sm-6 col-lg-3">
        <div class="stat-widget">
            <div class="stat-icon amber">
                <i class="bi bi-people-fill"></i>
            </div>
            <div>
                <div class="stat-value text-warning"><?= format_currency($stats['staff_payroll']) ?></div>
                <div class="stat-label">Staff Payroll (<?= $stats['total_staff'] ?> Staff)</div>
            </div>
        </div>
    </div>

    <!-- Average Salaries Breakdown -->
    <div class="col-sm-6 col-lg-3">
        <div class="stat-widget">
            <div class="stat-icon teal">
                <i class="bi bi-graph-up-arrow"></i>
            </div>
            <div>
                <div class="stat-value fs-6 text-dark">
                    Dr: <?= format_currency($stats['avg_doctor_salary']) ?>
                </div>
                <div class="stat-label text-muted">
                    Staff: <?= format_currency($stats['avg_staff_salary']) ?> avg
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Salary Rules & Range Banner -->
<div class="alert border-0 py-2 px-3 small d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4" 
     style="background: rgba(14, 165, 233, 0.08); border-left: 4px solid #0284c7 !important; color: #0369a1; border-radius: 8px;">
    <div class="d-flex align-items-center gap-2">
        <i class="bi bi-info-circle-fill text-primary fs-5"></i>
        <div>
            <strong>Salary Range Policy:</strong>
            Doctors: <span class="badge bg-primary px-2 py-1">₹50,000 – ₹90,000</span> &bull; 
            Staff: <span class="badge bg-warning text-dark px-2 py-1">₹30,000 – ₹60,000</span>.
            Every employee is assigned a strictly unique salary amount.
        </div>
    </div>
    <span class="badge bg-light text-muted border">
        89 Departments Covered
    </span>
</div>

<!-- 2. Search, Filter & Sort Controls -->
<div class="nexus-card p-3 mb-4">
    <form method="GET" action="salaries.php" class="row g-2 align-items-end">
        <!-- Search Input -->
        <div class="col-md-3">
            <label class="form-label small fw-semibold text-secondary mb-1">Search Employee</label>
            <div class="input-group input-group-sm">
                <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                <input type="text" class="form-control border-start-0" name="search" 
                       placeholder="Name or ID (e.g. DOC-0001)" value="<?= e($filters['search']) ?>">
            </div>
        </div>

        <!-- Employee Type Filter -->
        <div class="col-sm-6 col-md-2">
            <label class="form-label small fw-semibold text-secondary mb-1">Employee Type</label>
            <select name="type" class="form-select form-select-sm">
                <option value="all" <?= $filters['type'] === 'all' ? 'selected' : '' ?>>All Roles (Doctors &amp; Staff)</option>
                <option value="doctor" <?= $filters['type'] === 'doctor' ? 'selected' : '' ?>>Doctors Only</option>
                <option value="staff" <?= $filters['type'] === 'staff' ? 'selected' : '' ?>>Staff Only</option>
            </select>
        </div>

        <!-- Department Filter -->
        <div class="col-sm-6 col-md-3">
            <label class="form-label small fw-semibold text-secondary mb-1">Department</label>
            <select name="department_id" class="form-select form-select-sm">
                <option value="">All 89 Departments</option>
                <?php foreach ($departments as $dept): ?>
                    <option value="<?= $dept['id'] ?>" <?= $filters['department_id'] === (int)$dept['id'] ? 'selected' : '' ?>>
                        [<?= e($dept['dept_code']) ?>] <?= e($dept['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Sorting -->
        <div class="col-sm-6 col-md-2">
            <label class="form-label small fw-semibold text-secondary mb-1">Sort By</label>
            <select name="sort" class="form-select form-select-sm">
                <option value="name_asc" <?= $filters['sort'] === 'name_asc' ? 'selected' : '' ?>>Name (A &rarr; Z)</option>
                <option value="name_desc" <?= $filters['sort'] === 'name_desc' ? 'selected' : '' ?>>Name (Z &rarr; A)</option>
                <option value="salary_asc" <?= $filters['sort'] === 'salary_asc' ? 'selected' : '' ?>>Salary: Low &rarr; High</option>
                <option value="salary_desc" <?= $filters['sort'] === 'salary_desc' ? 'selected' : '' ?>>Salary: High &rarr; Low</option>
                <option value="id_asc" <?= $filters['sort'] === 'id_asc' ? 'selected' : '' ?>>Employee ID (Asc)</option>
                <option value="id_desc" <?= $filters['sort'] === 'id_desc' ? 'selected' : '' ?>>Employee ID (Desc)</option>
            </select>
        </div>

        <!-- Action Buttons -->
        <div class="col-sm-6 col-md-2 d-flex gap-1">
            <button type="submit" class="btn btn-sm btn-nexus-primary flex-grow-1">
                <i class="bi bi-funnel me-1"></i>Filter
            </button>
            <a href="salaries.php" class="btn btn-sm btn-outline-secondary" title="Reset Filters">
                <i class="bi bi-arrow-counterclockwise"></i>
            </a>
        </div>
    </form>
</div>

<!-- 3. Employee Salary Table -->
<div class="nexus-card">
    <div class="nexus-card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div class="d-flex align-items-center gap-2">
            <h6 class="fw-bold mb-0">Employee Salary Directory</h6>
            <span class="badge bg-secondary-subtle text-secondary small">
                Showing <?= count($records) ?> of <?= $totalRecords ?> Employees
            </span>
        </div>

        <div class="text-muted small">
            Page <?= $page ?> of <?= max(1, $totalPages) ?>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table-nexus align-middle mb-0">
            <thead>
                <tr>
                    <th style="width: 130px;">Employee ID</th>
                    <th>Employee Name</th>
                    <th style="width: 140px;">Employee Type</th>
                    <th>Department</th>
                    <th class="text-end" style="width: 160px;">Monthly Salary</th>
                    <th class="text-center" style="width: 180px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($records)): ?>
                    <tr>
                        <td colspan="6" class="text-center py-5">
                            <div class="text-muted">
                                <i class="bi bi-search fs-1 d-block mb-2 text-secondary opacity-50"></i>
                                <h6 class="fw-bold mb-1">No employees found</h6>
                                <p class="small mb-3">No doctor or staff records matched your search filters.</p>
                                <a href="salaries.php" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-arrow-repeat me-1"></i>Reset All Filters
                                </a>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($records as $r): ?>
                        <tr>
                            <!-- Employee ID -->
                            <td>
                                <code class="fw-semibold px-2 py-1 rounded bg-light border text-dark" style="font-size: 0.82rem;">
                                    <?= e($r['employee_code']) ?>
                                </code>
                            </td>

                            <!-- Employee Name -->
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="rounded-circle d-flex align-items-center justify-content-center text-white fw-bold"
                                         style="width: 34px; height: 34px; font-size: 0.8rem; background: <?= $r['employee_type'] === 'doctor' ? '#0284c7' : '#f59e0b' ?>;">
                                        <?= strtoupper(substr($r['first_name'], 0, 1) . substr($r['last_name'], 0, 1)) ?>
                                    </div>
                                    <div>
                                        <div class="fw-bold text-dark mb-0"><?= e($r['full_name']) ?></div>
                                        <small class="text-muted"><?= e($r['role_title']) ?></small>
                                    </div>
                                </div>
                            </td>

                            <!-- Employee Type -->
                            <td>
                                <?php if ($r['employee_type'] === 'doctor'): ?>
                                    <span class="badge bg-info text-dark px-2 py-1">
                                        <i class="bi bi-heart-pulse me-1"></i>Doctor
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-warning text-dark px-2 py-1">
                                        <i class="bi bi-person-badge me-1"></i>Staff
                                    </span>
                                <?php endif; ?>
                            </td>

                            <!-- Department -->
                            <td>
                                <?php if (!empty($r['department_name'])): ?>
                                    <span class="badge bg-light text-dark border me-1 small">
                                        <?= e($r['dept_code']) ?>
                                    </span>
                                    <span class="small"><?= e($r['department_name']) ?></span>
                                <?php else: ?>
                                    <span class="text-muted small">General Hospital</span>
                                <?php endif; ?>
                            </td>

                            <!-- Monthly Salary -->
                            <td class="text-end">
                                <?php if ($r['monthly_salary'] !== null): ?>
                                    <span class="fw-bold text-success" style="font-family: 'JetBrains Mono', monospace; font-size: 0.95rem;">
                                        <?= format_currency($r['monthly_salary']) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-danger">Unassigned</span>
                                <?php endif; ?>
                            </td>

                            <!-- Actions -->
                            <td class="text-center">
                                <div class="btn-group btn-group-sm">
                                    <button type="button" 
                                            class="btn btn-outline-primary"
                                            onclick='openEditSalaryModal(<?= json_encode($r) ?>)'
                                            title="Edit Salary">
                                        <i class="bi bi-pencil-square me-1"></i>Edit
                                    </button>
                                    <button type="button" 
                                            class="btn btn-outline-secondary"
                                            onclick="openHistoryModal(<?= (int)$r['employee_id'] ?>, '<?= e($r['employee_type']) ?>', '<?= e(addslashes($r['full_name'])) ?>')"
                                            title="View Salary History">
                                        <i class="bi bi-clock-history"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
        <div class="nexus-card-footer d-flex flex-wrap justify-content-between align-items-center gap-2 p-3">
            <span class="text-muted small">
                Showing Page <?= $page ?> of <?= $totalPages ?> (<?= $totalRecords ?> total records)
            </span>

            <nav>
                <ul class="pagination pagination-sm mb-0">
                    <!-- Prev -->
                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">
                            &laquo; Previous
                        </a>
                    </li>

                    <?php
                    $startPage = max(1, $page - 2);
                    $endPage = min($totalPages, $page + 2);
                    if ($startPage > 1): ?>
                        <li class="page-item"><a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => 1])) ?>">1</a></li>
                        <?php if ($startPage > 2): ?><li class="page-item disabled"><span class="page-link">&hellip;</span></li><?php endif; ?>
                    <?php endif; ?>

                    <?php for ($p = $startPage; $p <= $endPage; $p++): ?>
                        <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $p])) ?>"><?= $p ?></a>
                        </li>
                    <?php endfor; ?>

                    <?php if ($endPage < $totalPages): ?>
                        <?php if ($endPage < $totalPages - 1): ?><li class="page-item disabled"><span class="page-link">&hellip;</span></li><?php endif; ?>
                        <li class="page-item"><a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $totalPages])) ?>"><?= $totalPages ?></a></li>
                    <?php endif; ?>

                    <!-- Next -->
                    <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">
                            Next &raquo;
                        </a>
                    </li>
                </ul>
            </nav>
        </div>
    <?php endif; ?>
</div>

<!-- ========================================================================= -->
<!-- MODAL 1: Edit Salary Modal                                                -->
<!-- ========================================================================= -->
<div class="modal fade" id="editSalaryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form method="POST" action="salaries.php" onsubmit="return validateEditForm()">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="update_salary">
                <input type="hidden" name="employee_id" id="edit_emp_id">
                <input type="hidden" name="employee_type" id="edit_emp_type">

                <div class="modal-header bg-light">
                    <h5 class="modal-title fw-bold" id="editModalTitle">
                        <i class="bi bi-pencil-square text-primary me-2"></i>Update Employee Salary
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body p-4">
                    <!-- Employee Summary Card -->
                    <div class="p-3 bg-light rounded border mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="badge bg-secondary" id="modal_emp_code">DOC-0001</span>
                            <span class="badge bg-primary" id="modal_emp_type_badge">Doctor</span>
                        </div>
                        <h6 class="fw-bold mb-1" id="modal_emp_name">Dr. Rajesh Sharma</h6>
                        <small class="text-muted d-block" id="modal_emp_dept">Cardiology Department</small>
                        <div class="mt-2 pt-2 border-top small d-flex justify-content-between">
                            <span class="text-secondary">Current Monthly Salary:</span>
                            <strong class="text-dark" id="modal_current_salary">₹75,000.00</strong>
                        </div>
                    </div>

                    <!-- Range Guidance Banner -->
                    <div class="alert alert-info py-2 px-3 small mb-3" id="modal_range_hint">
                        <i class="bi bi-info-circle me-1"></i>
                        <span id="range_text">Permitted Range: ₹50,000 – ₹90,000</span>
                    </div>

                    <!-- New Salary Input -->
                    <div class="mb-3">
                        <label for="new_monthly_salary" class="form-label small fw-semibold text-secondary">
                            New Monthly Salary (INR ₹) <span class="text-danger">*</span>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text bg-light fw-bold text-secondary">₹</span>
                            <input type="number" step="0.01" class="form-control" id="new_monthly_salary" 
                                   name="monthly_salary" required placeholder="Enter amount">
                        </div>
                        <div class="form-text small text-muted">
                            Must be within prescribed range and unique across employees in this category.
                        </div>
                    </div>

                    <!-- Reason for Adjustment -->
                    <div class="mb-3">
                        <label for="change_reason" class="form-label small fw-semibold text-secondary">
                            Reason for Salary Adjustment <span class="text-danger">*</span>
                        </label>
                        <input type="text" class="form-control" id="change_reason" name="change_reason" 
                               placeholder="e.g. Annual performance appraisal, Specialization adjustment" required>
                    </div>

                    <!-- Authorization Confirmation Checkbox -->
                    <div class="form-check p-3 bg-light rounded border">
                        <input class="form-check-input" type="checkbox" id="confirm_salary_change" required>
                        <label class="form-check-label small text-secondary" for="confirm_salary_change">
                            I confirm that I am an authorized Administrator approving this salary modification and that the amount is verified against hospital compensation scales.
                        </label>
                    </div>
                </div>

                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-nexus-primary">
                        <i class="bi bi-check2-circle me-1"></i>Save Salary Adjustment
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- MODAL 2: Salary History Drawer / Modal                                    -->
<!-- ========================================================================= -->
<div class="modal fade" id="salaryHistoryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-light">
                <h5 class="modal-title fw-bold">
                    <i class="bi bi-clock-history text-secondary me-2"></i>Salary Audit &amp; Revision History
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body p-4">
                <div class="mb-3">
                    <h6 class="fw-bold mb-1" id="history_emp_name">—</h6>
                    <small class="text-muted">Historical compensation modifications recorded in TrustLedger</small>
                </div>

                <div id="history_loading" class="text-center py-4">
                    <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                    <span class="ms-2 small text-muted">Retrieving audit trail...</span>
                </div>

                <div id="history_content" style="display: none;">
                    <div class="table-responsive">
                        <table class="table-nexus small mb-0">
                            <thead>
                                <tr>
                                    <th>Effective Date</th>
                                    <th>Previous Salary</th>
                                    <th>New Salary</th>
                                    <th>Reason / Notes</th>
                                    <th>Authorized By</th>
                                    <th>Timestamp</th>
                                </tr>
                            </thead>
                            <tbody id="history_table_body">
                                <!-- Populated dynamically via AJAX -->
                            </tbody>
                        </table>
                    </div>
                </div>

                <div id="history_empty" class="text-center py-4 text-muted small" style="display: none;">
                    <i class="bi bi-journal-x fs-2 d-block mb-1 text-secondary opacity-50"></i>
                    No previous salary revisions logged for this employee.
                </div>
            </div>

            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript Engine for Salary Management -->
<script>
let activeEditMin = 50000;
let activeEditMax = 90000;

function openEditSalaryModal(data) {
    document.getElementById('edit_emp_id').value = data.employee_id;
    document.getElementById('edit_emp_type').value = data.employee_type;
    document.getElementById('modal_emp_code').innerText = data.employee_code;
    document.getElementById('modal_emp_name').innerText = data.full_name;
    document.getElementById('modal_emp_dept').innerText = data.department_name ? data.department_name : 'General Hospital';
    
    const curSal = parseFloat(data.monthly_salary) || 0;
    document.getElementById('modal_current_salary').innerText = '₹' + curSal.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    document.getElementById('new_monthly_salary').value = curSal > 0 ? curSal.toFixed(2) : '';
    document.getElementById('change_reason').value = '';
    document.getElementById('confirm_salary_change').checked = false;

    if (data.employee_type === 'doctor') {
        activeEditMin = 50000;
        activeEditMax = 90000;
        document.getElementById('modal_emp_type_badge').innerText = 'Doctor';
        document.getElementById('modal_emp_type_badge').className = 'badge bg-info text-dark';
        document.getElementById('range_text').innerText = 'Permitted Doctor Range: ₹50,000.00 – ₹90,000.00';
    } else {
        activeEditMin = 30000;
        activeEditMax = 60000;
        document.getElementById('modal_emp_type_badge').innerText = 'Staff';
        document.getElementById('modal_emp_type_badge').className = 'badge bg-warning text-dark';
        document.getElementById('range_text').innerText = 'Permitted Staff Range: ₹30,000.00 – ₹60,000.00';
    }

    const modal = new bootstrap.Modal(document.getElementById('editSalaryModal'));
    modal.show();
}

function validateEditForm() {
    const salVal = parseFloat(document.getElementById('new_monthly_salary').value);
    if (isNaN(salVal) || salVal < activeEditMin || salVal > activeEditMax) {
        alert('Invalid Salary Amount: Must be between ₹' + activeEditMin.toLocaleString('en-IN') + ' and ₹' + activeEditMax.toLocaleString('en-IN') + '.');
        return false;
    }
    if (!document.getElementById('confirm_salary_change').checked) {
        alert('Please check the confirmation box before saving.');
        return false;
    }
    return true;
}

function openHistoryModal(empId, empType, empName) {
    document.getElementById('history_emp_name').innerText = empName;
    document.getElementById('history_loading').style.display = 'block';
    document.getElementById('history_content').style.display = 'none';
    document.getElementById('history_empty').style.display = 'none';

    const modal = new bootstrap.Modal(document.getElementById('salaryHistoryModal'));
    modal.show();

    fetch(`salaries.php?action=get_history&employee_id=${empId}&employee_type=${empType}`)
        .then(res => res.json())
        .then(data => {
            document.getElementById('history_loading').style.display = 'none';
            if (data.success && data.history && data.history.length > 0) {
                const tbody = document.getElementById('history_table_body');
                tbody.innerHTML = '';
                data.history.forEach(item => {
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td><strong>${item.effective_date}</strong></td>
                        <td class="text-muted"><del>${item.previous_salary}</del></td>
                        <td class="text-success fw-bold">${item.new_salary}</td>
                        <td>${item.change_reason}</td>
                        <td><span class="badge bg-light text-dark border">${item.changed_by}</span></td>
                        <td><small class="text-muted">${item.created_at}</small></td>
                    `;
                    tbody.appendChild(tr);
                });
                document.getElementById('history_content').style.display = 'block';
            } else {
                document.getElementById('history_empty').style.display = 'block';
            }
        })
        .catch(err => {
            document.getElementById('history_loading').style.display = 'none';
            document.getElementById('history_empty').innerText = 'Failed to load salary history.';
            document.getElementById('history_empty').style.display = 'block';
        });
}
</script>

<?php require_once __DIR__ . '/../../app/views/layouts/footer.php'; ?>
