<?php
/**
 * KVM Hospital Management System — NexusCare
 * Core Module: Staff & User Account Management
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

    // Action 1: Create Staff User
    if ($action === 'create_user') {
        $username = sanitize_input($_POST['username'] ?? '');
        $email    = sanitize_input($_POST['email'] ?? '');
        $password = (string)($_POST['password'] ?? '');
        $roleId   = (int)($_POST['role_id'] ?? 0);
        $empCode  = sanitize_input($_POST['employee_id'] ?? '');
        $firstName= sanitize_input($_POST['first_name'] ?? '');
        $lastName = sanitize_input($_POST['last_name'] ?? '');
        $gender   = $_POST['gender'] ?? 'male';
        $phone    = sanitize_input($_POST['phone'] ?? '');
        $deptId   = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null;
        $designation = sanitize_input($_POST['designation'] ?? 'Hospital Staff');

        // Prevent hospital_admin from creating super_admin
        if ($roleId === 1 && !has_role('super_admin')) {
            set_flash('danger', 'Only an active Super Admin can create Super Admin accounts.');
            redirect('users.php');
        }

        if ($username && $email && $password && $roleId && $firstName && $lastName) {
            $db->beginTransaction();
            try {
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $insUser = $db->prepare("
                    INSERT INTO `users` (`username`, `email`, `password_hash`, `account_status`)
                    VALUES (:u, :e, :p, 'active')
                ");
                $insUser->execute(['u' => $username, 'e' => $email, 'p' => $hash]);
                $newUserId = (int)$db->lastInsertId();

                // Role assignment
                $db->prepare("INSERT INTO `user_role_assignments` (`user_id`, `role_id`, `assigned_by`) VALUES (:uid, :rid, :by)")
                   ->execute(['uid' => $newUserId, 'rid' => $roleId, 'by' => $currentUser['id']]);

                // Staff profile
                if (empty($empCode)) {
                    $empCode = "EMP-" . strtoupper(substr($username, 0, 3)) . "-" . rand(100, 999);
                }
                $insProf = $db->prepare("
                    INSERT INTO `staff_profiles` (`user_id`, `employee_id`, `first_name`, `last_name`, `gender`, `phone`, `department_id`, `designation`, `join_date`)
                    VALUES (:uid, :emp, :fn, :ln, :g, :ph, :dept, :des, CURDATE())
                ");
                $insProf->execute([
                    'uid'  => $newUserId,
                    'emp'  => $empCode,
                    'fn'   => $firstName,
                    'ln'   => $lastName,
                    'g'    => $gender,
                    'ph'   => $phone,
                    'dept' => $deptId,
                    'des'  => $designation
                ]);

                $audit->log('CREATE', 'User', $newUserId, "Created staff user account {$username} with role ID #{$roleId}", userId: $currentUser['id']);

                $db->commit();
                set_flash('success', "Staff account {$username} created successfully.");
            } catch (Exception $e) {
                $db->rollBack();
                set_flash('danger', "Failed to create user: " . $e->getMessage());
            }
        }
        redirect('users.php');
    }

    // Action 2: Toggle Account Status (active / inactive / suspended)
    if ($action === 'toggle_status') {
        $targetId  = (int)($_POST['target_user_id'] ?? 0);
        $newStatus = $_POST['new_status'] ?? 'active';

        // Cannot change own status or modify superadmin unless super_admin
        if ($targetId === $currentUser['id']) {
            set_flash('danger', 'You cannot change your own account status.');
            redirect('users.php');
        }

        $upd = $db->prepare("UPDATE `users` SET `account_status` = :st WHERE id = :id");
        $upd->execute(['st' => $newStatus, 'id' => $targetId]);

        $audit->log('STATUS_CHANGE', 'User', $targetId, "Changed account status to {$newStatus}", userId: $currentUser['id']);
        set_flash('info', "User account status updated to {$newStatus}.");
        redirect('users.php');
    }

    // Action 3: Reassign Role
    if ($action === 'change_role') {
        $targetId = (int)($_POST['target_user_id'] ?? 0);
        $newRoleId= (int)($_POST['new_role_id'] ?? 0);
        $reason   = sanitize_input($_POST['role_change_reason'] ?? 'Administrative role update');

        if ($targetId && $newRoleId && !empty($reason)) {
            $db->beginTransaction();
            try {
                // Get old role name
                $oldRoleStmt = $db->prepare("
                    SELECT r.role_name FROM `user_role_assignments` ura 
                    JOIN `roles` r ON ura.role_id = r.id WHERE ura.user_id = :uid
                ");
                $oldRoleStmt->execute(['uid' => $targetId]);
                $oldRoles = $oldRoleStmt->fetchAll(PDO::FETCH_COLUMN);

                // Get new role name
                $newRoleStmt = $db->prepare("SELECT role_name FROM `roles` WHERE id = :id");
                $newRoleStmt->execute(['id' => $newRoleId]);
                $newRoleName = $newRoleStmt->fetchColumn();

                // Update assignment
                $db->prepare("DELETE FROM `user_role_assignments` WHERE user_id = :uid")->execute(['uid' => $targetId]);
                $db->prepare("INSERT INTO `user_role_assignments` (`user_id`, `role_id`, `assigned_by`) VALUES (:uid, :rid, :by)")
                   ->execute(['uid' => $targetId, 'rid' => $newRoleId, 'by' => $currentUser['id']]);

                // Record in role_change_logs table
                $audit->logRoleChange($targetId, $currentUser['id'], $oldRoles, [$newRoleName], $reason);
                $audit->log('PERMISSION_CHANGE', 'UserRole', $targetId, "Reassigned user #{$targetId} from [" . implode(', ', $oldRoles) . "] to [{$newRoleName}]. Reason: {$reason}", userId: $currentUser['id']);

                $db->commit();
                set_flash('success', "Role reassigned to {$newRoleName} with logged justification.");
            } catch (Exception $e) {
                $db->rollBack();
                set_flash('danger', "Failed to reassign role: " . $e->getMessage());
            }
        }
        redirect('users.php');
    }
}

// Fetch users with their profiles and assigned roles
$users = $db->query("
    SELECT 
        u.*,
        GROUP_CONCAT(r.role_name SEPARATOR ', ') AS assigned_roles,
        GROUP_CONCAT(r.display_name SEPARATOR ', ') AS display_roles,
        COALESCE(
            CONCAT(sp.first_name, ' ', sp.last_name),
            CONCAT('Dr. ', dp.first_name, ' ', dp.last_name),
            CONCAT(pp.first_name, ' ', pp.last_name),
            u.username
        ) AS profile_name,
        COALESCE(sp.designation, dp.specialization, 'Patient') AS designation,
        COALESCE(sp.phone, dp.license_number, pp.phone, '—') AS contact_info
    FROM `users` u
    LEFT JOIN `user_role_assignments` ura ON u.id = ura.user_id
    LEFT JOIN `roles` r ON ura.role_id = r.id
    LEFT JOIN `staff_profiles` sp ON u.id = sp.user_id
    LEFT JOIN `doctor_profiles` dp ON u.id = dp.user_id
    LEFT JOIN `patient_profiles` pp ON u.id = pp.user_id
    GROUP BY u.id
    ORDER BY u.id ASC
")->fetchAll();

$roles = $db->query("SELECT * FROM `roles` ORDER BY id ASC")->fetchAll();
$departments = $db->query("SELECT id, name FROM `departments` WHERE `status` = 'active' ORDER BY name ASC")->fetchAll();

$pageTitle = 'Staff Accounts & Role-Based Access Control';
require_once __DIR__ . '/../../app/views/layouts/header.php';
?>

<!-- Title and Action Bar -->
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div>
        <h4 class="fw-bold mb-1">Staff Accounts & Role-Based Access Control</h4>
        <p class="text-secondary small mb-0">Manage hospital staff credentials, server-side role assignments, and account locks</p>
    </div>
    
    <div class="d-flex align-items-center gap-2">
        <button type="button" class="btn btn-nexus-primary" data-bs-toggle="modal" data-bs-target="#createUserModal">
            <i class="bi bi-person-plus-fill me-1"></i>Create Staff User Account
        </button>
    </div>
</div>

<!-- Users Table Card -->
<div class="nexus-card">
    <div class="nexus-card-header">
        <h6 class="fw-bold mb-0">System Users Ledger (<?= count($users) ?> Accounts)</h6>
        <span class="badge bg-light text-secondary border">One Common Login Directory</span>
    </div>

    <div class="table-responsive">
        <table class="table-nexus">
            <thead>
                <tr>
                    <th>User ID</th>
                    <th>User Credentials</th>
                    <th>Full Name / Designation</th>
                    <th>Assigned Roles</th>
                    <th>Account Status</th>
                    <th>Last Active</th>
                    <th class="text-end" style="width: 190px;">Privilege Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                    <tr>
                        <td><code>#<?= e((string)$u['id']) ?></code></td>
                        <td>
                            <strong><?= e($u['username']) ?></strong>
                            <small class="text-muted d-block"><?= e($u['email']) ?></small>
                        </td>
                        <td>
                            <strong class="text-dark"><?= e($u['profile_name']) ?></strong>
                            <small class="text-muted d-block"><?= e($u['designation']) ?></small>
                        </td>
                        <td>
                            <span class="badge bg-primary"><?= e($u['display_roles'] ?: 'Patient') ?></span>
                        </td>
                        <td>
                            <span class="badge bg-<?= $u['account_status'] === 'active' ? 'success' : 'danger' ?>">
                                <?= strtoupper(e($u['account_status'])) ?>
                            </span>
                        </td>
                        <td>
                            <small class="text-muted"><?= format_datetime($u['last_login_at'], 'd M, h:i A') ?></small>
                        </td>
                        <td class="text-end">
                            <div class="btn-group btn-group-sm">
                                <!-- Reassign Role Trigger -->
                                <button type="button" class="btn btn-outline-secondary btn-sm" 
                                        onclick="openRoleModal(<?= $u['id'] ?>, '<?= e($u['username']) ?>', '<?= e($u['assigned_roles']) ?>')">
                                    <i class="bi bi-shield-lock"></i> Role
                                </button>

                                <!-- Toggle Status Form -->
                                <?php if ($u['account_status'] === 'active'): ?>
                                    <form method="POST" action="users.php" class="d-inline" onsubmit="return confirm('Deactivate this account? User will be blocked from sign in.')">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="toggle_status">
                                        <input type="hidden" name="target_user_id" value="<?= $u['id'] ?>">
                                        <input type="hidden" name="new_status" value="inactive">
                                        <button type="submit" class="btn btn-outline-danger btn-sm" title="Deactivate Account">
                                            <i class="bi bi-lock"></i> Lock
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <form method="POST" action="users.php" class="d-inline">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="toggle_status">
                                        <input type="hidden" name="target_user_id" value="<?= $u['id'] ?>">
                                        <input type="hidden" name="new_status" value="active">
                                        <button type="submit" class="btn btn-outline-success btn-sm" title="Reactivate Account">
                                            <i class="bi bi-unlock"></i> Unlock
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal 1: Create Staff User -->
<div class="modal fade" id="createUserModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title fw-bold fs-6">Create Authorized Staff User Account</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="users.php">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="create_user">

                <div class="modal-body p-4">
                    <h6 class="text-uppercase text-secondary fw-bold mb-3" style="font-size: 0.75rem;">1. Account Credentials</h6>
                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Login Username *</label>
                            <input type="text" name="username" class="form-control" required placeholder="e.g. dr.mehta">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Official Email Address *</label>
                            <input type="email" name="email" class="form-control" required placeholder="e.g. mehta@nexuscare.org">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Initial Password *</label>
                            <input type="password" name="password" class="form-control" required minlength="6" placeholder="Min 6 chars">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Assigned Hospital Role *</label>
                            <select name="role_id" class="form-select" required>
                                <option value="">Choose Role...</option>
                                <?php foreach ($roles as $r): ?>
                                    <option value="<?= $r['id'] ?>"><?= e($r['display_name']) ?> (<?= e($r['role_name']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Department Assignment</label>
                            <select name="department_id" class="form-select">
                                <option value="">No Specific Department</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?= $dept['id'] ?>"><?= e($dept['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <h6 class="text-uppercase text-secondary fw-bold mb-3" style="font-size: 0.75rem;">2. Staff Demographic Profile</h6>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">First Name *</label>
                            <input type="text" name="first_name" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Last Name *</label>
                            <input type="text" name="last_name" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Gender</label>
                            <select name="gender" class="form-select">
                                <option value="male">Male</option>
                                <option value="female">Female</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Contact Phone Number</label>
                            <input type="text" name="phone" class="form-control" placeholder="+91 98000 00000">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Designation Title</label>
                            <input type="text" name="designation" class="form-control" placeholder="e.g. Attending Physician or Charge Nurse">
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-nexus-primary btn-sm px-4">Create Staff User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal 2: Reassign Role with Mandatory Reason -->
<div class="modal fade" id="changeRoleModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold fs-6">Update Role Privileges: <span id="roleModalUsername"></span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="users.php">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="change_role">
                <input type="hidden" name="target_user_id" id="roleModalUserId" value="">

                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Select New Authorized Role *</label>
                        <select name="new_role_id" class="form-select" required>
                            <?php foreach ($roles as $r): ?>
                                <option value="<?= $r['id'] ?>"><?= e($r['display_name']) ?> (<?= e($r['role_name']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Mandatory Administrative Justification *</label>
                        <textarea name="role_change_reason" class="form-control" rows="3" required placeholder="State operational reason for privilege change (audited in TrustLedger)"></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm px-4">Confirm Role Reassignment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function openRoleModal(uid, username, currentRole) {
        document.getElementById('roleModalUserId').value = uid;
        document.getElementById('roleModalUsername').textContent = username;
        const modal = new bootstrap.Modal(document.getElementById('changeRoleModal'));
        modal.show();
    }
</script>

<?php require_once __DIR__ . '/../../app/views/layouts/footer.php'; ?>
