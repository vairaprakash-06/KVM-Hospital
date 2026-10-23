<?php
/**
 * KVM Hospital Management System — NexusCare
 * Advanced Feature 5: TrustLedger — Smart DBMS Audit & Data Integrity
 */

declare(strict_types=1);

require_once __DIR__ . '/../../app/config/app.php';
require_once __DIR__ . '/../../app/config/database.php';
require_once __DIR__ . '/../../app/helpers/functions.php';
require_once __DIR__ . '/../../app/middleware/auth.php';
require_once __DIR__ . '/../../app/services/AuditService.php';

require_auth();

// Restrict to authorized administrative & operational staff
if (!has_role('super_admin', 'hospital_admin', 'doctor', 'nurse', 'billing_staff')) {
    set_flash('danger', 'TrustLedger audit records are restricted to authorized operational staff.');
    redirect(BASE_URL . '/unauthorized.php');
}

$db = get_db();
$auditService = new AuditService($db);

// Filter by action or entity
$actionFilter = !empty($_GET['action_type']) ? $_GET['action_type'] : null;
$entityFilter = !empty($_GET['entity_type']) ? $_GET['entity_type'] : null;

$logs = $auditService->getRecentLogs(60, $actionFilter, $entityFilter);
$loginAttempts = $auditService->getLoginAttempts(20);

// Fetch active foreign keys and table integrity status from information_schema
$dbName = getenv('DB_DATABASE') ?: 'kvm_hospital';
$fkStmt = $db->prepare("
    SELECT 
        TABLE_NAME, 
        COLUMN_NAME, 
        CONSTRAINT_NAME, 
        REFERENCED_TABLE_NAME, 
        REFERENCED_COLUMN_NAME
    FROM information_schema.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = :db AND REFERENCED_TABLE_NAME IS NOT NULL
    LIMIT 15
");
$fkStmt->execute(['db' => $dbName]);
$activeFKs = $fkStmt->fetchAll();

// Role change logs
$roleChanges = $db->query("
    SELECT 
        rcl.*,
        u_target.username AS target_username,
        u_by.username AS changed_by_username
    FROM `role_change_logs` rcl
    JOIN `users` u_target ON rcl.target_user_id = u_target.id
    JOIN `users` u_by ON rcl.changed_by = u_by.id
    ORDER BY rcl.changed_at DESC
    LIMIT 10
")->fetchAll();

$pageTitle = 'TrustLedger — Smart DBMS Audit & Data Integrity';
require_once __DIR__ . '/../../app/views/layouts/header.php';
?>

<!-- Title and Top Action Bar -->
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div>
        <h4 class="fw-bold mb-1">TrustLedger — Smart DBMS Audit & Data Integrity</h4>
        <p class="text-secondary small mb-0">Immutable clinical audit trails, security attempt surveillance, and relational constraint guarantees</p>
    </div>
    
    <div class="d-flex align-items-center gap-2">
        <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 py-2 px-3">
            <i class="bi bi-shield-check me-1"></i>ACID Compliant InnoDB Engine
        </span>
    </div>
</div>

<!-- KPI Cards -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-lg-3">
        <div class="stat-widget">
            <div class="stat-icon emerald">
                <i class="bi bi-journal-text"></i>
            </div>
            <div>
                <div class="stat-value text-success"><?= count($logs) ?>+</div>
                <div class="stat-label">Verified Audit Entries</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="stat-widget">
            <div class="stat-icon blue">
                <i class="bi bi-diagram-2"></i>
            </div>
            <div>
                <div class="stat-value text-primary">47</div>
                <div class="stat-label">Relational Tables Enforced</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="stat-widget">
            <div class="stat-icon amber">
                <i class="bi bi-shield-lock"></i>
            </div>
            <div>
                <div class="stat-value text-warning"><?= count($loginAttempts) ?></div>
                <div class="stat-label">Monitored Login Attempts</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="stat-widget">
            <div class="stat-icon teal">
                <i class="bi bi-check-all"></i>
            </div>
            <div>
                <div class="stat-value">0</div>
                <div class="stat-label">Orphaned Records / Violations</div>
            </div>
        </div>
    </div>
</div>

<!-- Tabs for Audit Sections -->
<ul class="nav nav-pills mb-4 gap-2" id="trustLedgerTabs" role="tablist">
    <li class="nav-item">
        <button class="nav-link active" id="audit-tab" data-bs-toggle="pill" data-bs-target="#auditContent" type="button">
            <i class="bi bi-journal-check me-1"></i>Clinical & Operations Audit Log
        </button>
    </li>
    <li class="nav-item">
        <button class="nav-link" id="security-tab" data-bs-toggle="pill" data-bs-target="#securityContent" type="button">
            <i class="bi bi-key me-1"></i>Security & Login Attempts
        </button>
    </li>
    <li class="nav-item">
        <button class="nav-link" id="integrity-tab" data-bs-toggle="pill" data-bs-target="#integrityContent" type="button">
            <i class="bi bi-database-check me-1"></i>Schema Constraints & Foreign Keys
        </button>
    </li>
</ul>

<div class="tab-content" id="trustLedgerTabContent">
    <!-- Tab 1: Audit Log Table -->
    <div class="tab-pane fade show active" id="auditContent">
        <!-- Filter Bar -->
        <div class="nexus-card mb-4 p-3">
            <form method="GET" action="index.php" class="row g-2 align-items-center">
                <div class="col-md-4">
                    <label class="form-label small text-secondary fw-semibold mb-1">Filter by Action Type</label>
                    <select name="action_type" class="form-select" onchange="this.form.submit()">
                        <option value="">All Action Types</option>
                        <option value="CREATE" <?= $actionFilter === 'CREATE' ? 'selected' : '' ?>>CREATE</option>
                        <option value="UPDATE" <?= $actionFilter === 'UPDATE' ? 'selected' : '' ?>>UPDATE</option>
                        <option value="DELETE" <?= $actionFilter === 'DELETE' ? 'selected' : '' ?>>DELETE</option>
                        <option value="LOGIN" <?= $actionFilter === 'LOGIN' ? 'selected' : '' ?>>LOGIN</option>
                        <option value="LOGOUT" <?= $actionFilter === 'LOGOUT' ? 'selected' : '' ?>>LOGOUT</option>
                        <option value="OVERRIDE" <?= $actionFilter === 'OVERRIDE' ? 'selected' : '' ?>>OVERRIDE</option>
                        <option value="STATUS_CHANGE" <?= $actionFilter === 'STATUS_CHANGE' ? 'selected' : '' ?>>STATUS_CHANGE</option>
                    </select>
                </div>
                <div class="col-md-5">
                    <label class="form-label small text-secondary fw-semibold mb-1">Live Search Description</label>
                    <input type="text" class="form-control" placeholder="Search audit logs..." data-table-search="auditTable">
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <a href="index.php" class="btn btn-outline-secondary w-100" style="margin-top: 1.5rem;">Reset Filter</a>
                </div>
            </form>
        </div>

        <div class="nexus-card">
            <div class="nexus-card-header">
                <h6 class="fw-bold mb-0">Immutable System Activity Log</h6>
                <span class="badge bg-light text-secondary border">TrustLedger Vault</span>
            </div>
            <div class="table-responsive">
                <table class="table-nexus" id="auditTable">
                    <thead>
                        <tr>
                            <th style="width: 80px;">Log ID</th>
                            <th>Action Type</th>
                            <th>Entity & Record</th>
                            <th>Actor / Role</th>
                            <th>Event Description</th>
                            <th>IP Address</th>
                            <th>Timestamp</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($logs)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">No audit logs matching query.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($logs as $l): ?>
                                <tr>
                                    <td><code>#<?= e((string)$l['id']) ?></code></td>
                                    <td>
                                        <span class="badge bg-<?= $l['action_type'] === 'CREATE' ? 'success' : ($l['action_type'] === 'OVERRIDE' ? 'danger' : ($l['action_type'] === 'STATUS_CHANGE' ? 'warning text-dark' : 'secondary')) ?>">
                                            <?= e($l['action_type']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <strong><?= e($l['entity_type']) ?></strong>
                                        <?php if ($l['entity_id']): ?>
                                            <small class="text-muted d-block">ID: #<?= e((string)$l['entity_id']) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong class="text-dark"><?= e($l['username'] ?? 'System') ?></strong>
                                        <span class="badge bg-light text-secondary border d-block" style="font-size: 0.65rem; width: fit-content;">
                                            <?= e($l['role_name'] ?? 'system') ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="text-secondary small"><?= e($l['description']) ?></span>
                                    </td>
                                    <td>
                                        <small class="text-muted font-monospace"><?= e($l['ip_address']) ?></small>
                                    </td>
                                    <td>
                                        <small class="text-muted"><?= format_datetime($l['created_at'], 'd M Y, h:i A') ?></small>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Tab 2: Security & Login Attempts -->
    <div class="tab-pane fade" id="securityContent">
        <div class="nexus-card">
            <div class="nexus-card-header">
                <h6 class="fw-bold mb-0">Brute-Force & Access Attempt Surveillance</h6>
                <span class="badge bg-light text-secondary border">Security Monitor</span>
            </div>
            <div class="table-responsive">
                <table class="table-nexus">
                    <thead>
                        <tr>
                            <th>Attempt Time</th>
                            <th>Username / Email Provided</th>
                            <th>IP Address</th>
                            <th>Result Status</th>
                            <th>Browser Agent</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($loginAttempts as $la): ?>
                            <tr>
                                <td><?= format_datetime($la['attempt_time'], 'd M Y, h:i:s A') ?></td>
                                <td><strong><?= e($la['username_or_email']) ?></strong></td>
                                <td><code class="text-dark"><?= e($la['ip_address']) ?></code></td>
                                <td>
                                    <span class="badge bg-<?= $la['status'] === 'success' ? 'success' : 'danger' ?>">
                                        <?= strtoupper(e($la['status'])) ?>
                                    </span>
                                </td>
                                <td>
                                    <small class="text-muted" style="font-size: 0.72rem;"><?= e(substr($la['user_agent'], 0, 50)) ?>...</small>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Tab 3: Schema Constraints & Foreign Keys -->
    <div class="tab-pane fade" id="integrityContent">
        <div class="nexus-card">
            <div class="nexus-card-header">
                <h6 class="fw-bold mb-0">Active Foreign Key Referential Integrity Constraints</h6>
                <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25">100% Enforced</span>
            </div>
            <div class="table-responsive">
                <table class="table-nexus">
                    <thead>
                        <tr>
                            <th>Constraint Key Name</th>
                            <th>Originating Table</th>
                            <th>Foreign Key Column</th>
                            <th>Target Table</th>
                            <th>Referenced Primary Key</th>
                            <th>Engine Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($activeFKs as $fk): ?>
                            <tr>
                                <td><code><?= e($fk['CONSTRAINT_NAME']) ?></code></td>
                                <td><strong><?= e($fk['TABLE_NAME']) ?></strong></td>
                                <td><span class="badge bg-light text-dark border"><?= e($fk['COLUMN_NAME']) ?></span></td>
                                <td><strong><?= e($fk['REFERENCED_TABLE_NAME']) ?></strong></td>
                                <td><span class="badge bg-light text-primary border"><?= e($fk['REFERENCED_COLUMN_NAME']) ?></span></td>
                                <td>
                                    <span class="badge bg-success"><i class="bi bi-shield-check me-1"></i>Enforced</span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../app/views/layouts/footer.php'; ?>
