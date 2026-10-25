<?php
/**
 * KVM Hospital Management System — NexusCare
 * Super Admin Security & System Governance Dashboard
 */

declare(strict_types=1);

require_once __DIR__ . '/../../app/config/app.php';
require_once __DIR__ . '/../../app/config/database.php';
require_once __DIR__ . '/../../app/helpers/functions.php';
require_once __DIR__ . '/../../app/middleware/permissions.php';

require_role('super_admin');

$db = get_db();

// System stats
$totalUsers = (int)$db->query("SELECT COUNT(*) FROM `users`")->fetchColumn();
$activeUsers = (int)$db->query("SELECT COUNT(*) FROM `users` WHERE account_status = 'active'")->fetchColumn();
$failedLogins = (int)$db->query("SELECT COUNT(*) FROM `login_attempts` WHERE status = 'failed'")->fetchColumn();
$totalAuditEntries = (int)$db->query("SELECT COUNT(*) FROM `audit_logs`")->fetchColumn();

// Recent security login attempts
$securityLog = $db->query("SELECT * FROM `login_attempts` ORDER BY attempt_time DESC LIMIT 8")->fetchAll();

// Recent role changes
$roleHistory = $db->query("
    SELECT rcl.*, u1.username AS target, u2.username AS author
    FROM `role_change_logs` rcl
    JOIN `users` u1 ON rcl.target_user_id = u1.id
    JOIN `users` u2 ON rcl.changed_by = u2.id
    ORDER BY rcl.changed_at DESC
    LIMIT 6
")->fetchAll();

$pageTitle = 'Super Administrator Security & Governance';
require_once __DIR__ . '/../../app/views/layouts/header.php';
?>

<!-- Title & Action Bar -->
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div>
        <h4 class="fw-bold mb-1">Super Administrator Security & Governance</h4>
        <p class="text-secondary small mb-0">System configuration, role-permission authority, and security access logs</p>
    </div>
    
    <div class="d-flex align-items-center gap-2">
        <a href="users.php" class="btn btn-nexus-primary btn-sm">
            <i class="bi bi-people-fill me-1"></i>Manage Staff & Roles
        </a>
        <a href="../trustledger/index.php" class="btn btn-nexus-dark btn-sm">
            <i class="bi bi-journal-check me-1"></i>TrustLedger Vault
        </a>
    </div>
</div>

<!-- KPI Cards -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-lg-3">
        <div class="stat-widget">
            <div class="stat-icon blue">
                <i class="bi bi-person-lines-fill"></i>
            </div>
            <div>
                <div class="stat-value text-primary"><?= $totalUsers ?></div>
                <div class="stat-label">Total System Users</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="stat-widget">
            <div class="stat-icon emerald">
                <i class="bi bi-person-check-fill"></i>
            </div>
            <div>
                <div class="stat-value text-success"><?= $activeUsers ?></div>
                <div class="stat-label">Active Authorized Accounts</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="stat-widget">
            <div class="stat-icon danger">
                <i class="bi bi-shield-x"></i>
            </div>
            <div>
                <div class="stat-value text-danger"><?= $failedLogins ?></div>
                <div class="stat-label">Security Failed Attempts</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="stat-widget">
            <div class="stat-icon teal">
                <i class="bi bi-shield-shaded"></i>
            </div>
            <div>
                <div class="stat-value"><?= $totalAuditEntries ?></div>
                <div class="stat-label">Audit Log Count</div>
            </div>
        </div>
    </div>
</div>

<!-- Operational Stations & Clinical Desks Master Hub -->
<div class="row g-3 mb-4">
    <div class="col-12">
        <div class="nexus-card p-3 bg-light border">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                <span class="fw-bold text-dark">
                    <i class="bi bi-hospital-fill text-primary me-1"></i>Operational Stations &amp; Clinical Desks:
                </span>
                <div class="d-flex flex-wrap gap-2">
                    <a href="../reception/dashboard.php" class="btn btn-sm btn-outline-info text-dark">
                        <i class="bi bi-person-workspace me-1"></i>Reception Desk
                    </a>
                    <a href="../nurse/dashboard.php" class="btn btn-sm btn-outline-warning text-dark">
                        <i class="bi bi-bandaid me-1"></i>Nursing Station
                    </a>
                    <a href="../laboratory/dashboard.php" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-eyedropper me-1"></i>Diagnostic Lab
                    </a>
                    <a href="../pharmacy/dashboard.php" class="btn btn-sm btn-outline-danger">
                        <i class="bi bi-capsule me-1"></i>Pharmacy Unit
                    </a>
                    <a href="../billing/dashboard.php" class="btn btn-sm btn-outline-success">
                        <i class="bi bi-receipt me-1"></i>Billing Center
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Security Surveillance and Role Changes -->
<div class="row g-4">
    <div class="col-lg-6">
        <div class="nexus-card h-100">
            <div class="nexus-card-header">
                <h6 class="fw-bold mb-0">Recent Login Attempts Surveillance</h6>
                <span class="badge bg-light text-secondary border">Security Feed</span>
            </div>
            <div class="table-responsive">
                <table class="table-nexus">
                    <thead>
                        <tr>
                            <th>User / Email</th>
                            <th>IP Address</th>
                            <th>Status</th>
                            <th>Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($securityLog as $sl): ?>
                            <tr>
                                <td><strong><?= e($sl['username_or_email']) ?></strong></td>
                                <td><code><?= e($sl['ip_address']) ?></code></td>
                                <td>
                                    <span class="badge bg-<?= $sl['status'] === 'success' ? 'success' : 'danger' ?>">
                                        <?= strtoupper(e($sl['status'])) ?>
                                    </span>
                                </td>
                                <td><small class="text-muted"><?= format_datetime($sl['attempt_time'], 'd M, h:i A') ?></small></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="nexus-card h-100">
            <div class="nexus-card-header">
                <h6 class="fw-bold mb-0">Role & Privilege Escalation History</h6>
                <span class="badge bg-light text-secondary border">TrustLedger Logs</span>
            </div>
            <div class="nexus-card-body p-0">
                <?php if (empty($roleHistory)): ?>
                    <div class="text-center py-4 text-muted small">No role change events logged.</div>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($roleHistory as $rh): ?>
                            <div class="list-group-item p-3">
                                <div class="d-flex justify-content-between">
                                    <strong class="text-dark">User: <?= e($rh['target']) ?></strong>
                                    <small class="text-muted"><?= format_datetime($rh['changed_at'], 'd M, h:i A') ?></small>
                                </div>
                                <div class="small text-secondary mt-1">
                                    <span class="badge bg-light text-muted border"><?= e($rh['old_roles']) ?></span>
                                    <i class="bi bi-arrow-right mx-1"></i>
                                    <span class="badge bg-primary"><?= e($rh['new_roles']) ?></span>
                                </div>
                                <small class="text-muted d-block mt-1">
                                    Reason: <?= e($rh['reason']) ?> &bull; Authorized by: <strong><?= e($rh['author']) ?></strong>
                                </small>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../app/views/layouts/footer.php'; ?>
