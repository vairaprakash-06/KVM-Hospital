<?php
/**
 * KVM Hospital Management System — NexusCare
 * Billing & Accounts Receivable Operations Dashboard
 */

declare(strict_types=1);

require_once __DIR__ . '/../../app/config/app.php';
require_once __DIR__ . '/../../app/config/database.php';
require_once __DIR__ . '/../../app/helpers/functions.php';
require_once __DIR__ . '/../../app/middleware/permissions.php';

require_role('billing_staff', 'hospital_admin', 'super_admin');

$db = get_db();

// Financial metrics
$fin = $db->query("
    SELECT 
        SUM(total_amount) AS total_billed,
        SUM(paid_amount) AS total_paid,
        SUM(balance_amount) AS total_balance,
        COUNT(CASE WHEN status != 'paid' THEN 1 END) AS unpaid_invoices_count
    FROM `invoices`
")->fetch();

// Invoices with outstanding balances
$pendingInvoices = $db->query("
    SELECT i.*, p.first_name, p.last_name, p.patient_id AS pcode, p.phone
    FROM `invoices` i
    JOIN `patient_profiles` p ON i.patient_id = p.id
    WHERE i.balance_amount > 0
    ORDER BY i.balance_amount DESC
    LIMIT 10
")->fetchAll();

$pageTitle = 'Billing Operations & Revenue Management';
require_once __DIR__ . '/../../app/views/layouts/header.php';
?>

<!-- Title & Action Bar -->
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div>
        <h4 class="fw-bold mb-1">Billing Operations & Revenue Management</h4>
        <p class="text-secondary small mb-0">Invoice generation, accounts receivable tracking, and partial payment reconciliation</p>
    </div>
    
    <div class="d-flex align-items-center gap-2">
        <a href="invoices.php" class="btn btn-nexus-primary btn-sm">
            <i class="bi bi-file-earmark-plus-fill me-1"></i>All Invoices & Payments
        </a>
    </div>
</div>

<!-- KPI Cards -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-lg-3">
        <div class="stat-widget">
            <div class="stat-icon emerald">
                <i class="bi bi-cash-stack"></i>
            </div>
            <div>
                <div class="stat-value text-success"><?= format_currency($fin['total_paid'] ?? 0) ?></div>
                <div class="stat-label">Collected Hospital Revenue</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="stat-widget">
            <div class="stat-icon danger">
                <i class="bi bi-exclamation-circle-fill"></i>
            </div>
            <div>
                <div class="stat-value text-danger"><?= format_currency($fin['total_balance'] ?? 0) ?></div>
                <div class="stat-label">Outstanding Balance Due</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="stat-widget">
            <div class="stat-icon blue">
                <i class="bi bi-receipt"></i>
            </div>
            <div>
                <div class="stat-value text-primary"><?= format_currency($fin['total_billed'] ?? 0) ?></div>
                <div class="stat-label">Gross Billed Value</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="stat-widget">
            <div class="stat-icon amber">
                <i class="bi bi-hourglass-split"></i>
            </div>
            <div>
                <div class="stat-value text-warning"><?= (int)($fin['unpaid_invoices_count'] ?? 0) ?></div>
                <div class="stat-label">Unpaid Bills Awaiting Settlement</div>
            </div>
        </div>
    </div>
</div>

<!-- Invoices with Outstanding Balances -->
<div class="nexus-card">
    <div class="nexus-card-header">
        <h6 class="fw-bold mb-0">High-Priority Outstanding Patient Invoices (<?= count($pendingInvoices) ?>)</h6>
        <span class="badge bg-light text-secondary border">Accounts Receivable</span>
    </div>
    <div class="table-responsive">
        <table class="table-nexus">
            <thead>
                <tr>
                    <th>Invoice #</th>
                    <th>Patient Name</th>
                    <th>Contact Phone</th>
                    <th>Total Charges</th>
                    <th>Paid Amount</th>
                    <th>Outstanding Balance</th>
                    <th class="text-end">Payment Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($pendingInvoices)): ?>
                    <tr><td colspan="7" class="text-center py-4 text-muted">All finalized invoices are fully paid.</td></tr>
                <?php else: ?>
                    <?php foreach ($pendingInvoices as $pi): ?>
                        <tr>
                            <td><code><?= e($pi['invoice_number']) ?></code></td>
                            <td>
                                <strong><?= e($pi['first_name'] . ' ' . $pi['last_name']) ?></strong>
                                <small class="text-muted d-block"><?= e($pi['pcode']) ?></small>
                            </td>
                            <td><?= e($pi['phone']) ?></td>
                            <td><?= format_currency($pi['total_amount']) ?></td>
                            <td><span class="text-success"><?= format_currency($pi['paid_amount']) ?></span></td>
                            <td><span class="fs-6 fw-bold text-danger"><?= format_currency($pi['balance_amount']) ?></span></td>
                            <td class="text-end">
                                <a href="invoices.php" class="btn btn-nexus-primary btn-sm">
                                    <i class="bi bi-cash me-1"></i>Record Payment
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
