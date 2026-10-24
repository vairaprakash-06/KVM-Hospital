<?php
/**
 * KVM Hospital Management System — NexusCare
 * Core Module: Billing, Invoices & Payment Ledger
 */

declare(strict_types=1);

require_once __DIR__ . '/../../app/config/app.php';
require_once __DIR__ . '/../../app/config/database.php';
require_once __DIR__ . '/../../app/helpers/functions.php';
require_once __DIR__ . '/../../app/middleware/auth.php';
require_once __DIR__ . '/../../app/middleware/csrf.php';
require_once __DIR__ . '/../../app/services/AuditService.php';
require_once __DIR__ . '/../../app/services/CareTrailService.php';

require_auth();

$db = get_db();
$audit = new AuditService($db);
$careTrail = new CareTrailService($db);
$currentUser = current_user();

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    // Action 1: Create Invoice
    if ($action === 'create_invoice') {
        $patientId = (int)($_POST['patient_id'] ?? 0);
        $itemTypes = $_POST['item_type'] ?? [];
        $itemDescs = $_POST['item_desc'] ?? [];
        $itemQtys  = $_POST['item_qty'] ?? [];
        $itemPrices= $_POST['item_price'] ?? [];
        $discount  = (float)($_POST['discount_amount'] ?? 0);

        if ($patientId && !empty($itemDescs)) {
            $db->beginTransaction();
            try {
                $subtotal = 0.0;
                $itemsToInsert = [];
                for ($i = 0; $i < count($itemDescs); $i++) {
                    $desc = sanitize_input($itemDescs[$i] ?? '');
                    if (empty($desc)) continue;
                    $qty = max(1, (int)($itemQtys[$i] ?? 1));
                    $price = max(0.0, (float)($itemPrices[$i] ?? 0));
                    $total = $qty * $price;
                    $subtotal += $total;

                    $itemsToInsert[] = [
                        'type'  => $itemTypes[$i] ?? 'other',
                        'desc'  => $desc,
                        'qty'   => $qty,
                        'price' => $price,
                        'total' => $total
                    ];
                }

                $tax = round(($subtotal - $discount) * 0.05, 2); // 5% GST
                if ($tax < 0) $tax = 0.0;
                $totalAmount = ($subtotal - $discount) + $tax;
                $invNumber = generate_invoice_number($db);

                $insInv = $db->prepare("
                    INSERT INTO `invoices` (
                        `invoice_number`, `patient_id`, `subtotal`, `discount_amount`,
                        `tax_amount`, `total_amount`, `paid_amount`, `balance_amount`,
                        `status`, `created_by`, `finalized_at`, `created_at`
                    ) VALUES (
                        :num, :pid, :sub, :disc,
                        :tax, :tot, 0.00, :bal,
                        'finalized', :by, NOW(), NOW()
                    )
                ");
                $insInv->execute([
                    'num'  => $invNumber,
                    'pid'  => $patientId,
                    'sub'  => $subtotal,
                    'disc' => $discount,
                    'tax'  => $tax,
                    'tot'  => $totalAmount,
                    'bal'  => $totalAmount,
                    'by'   => $currentUser['id']
                ]);
                $invId = (int)$db->lastInsertId();

                // Insert Items
                $insItem = $db->prepare("
                    INSERT INTO `invoice_items` (`invoice_id`, `item_type`, `item_description`, `quantity`, `unit_price`, `total_price`)
                    VALUES (:iid, :type, :desc, :qty, :up, :tot)
                ");
                foreach ($itemsToInsert as $item) {
                    $insItem->execute([
                        'iid'   => $invId,
                        'type'  => $item['type'],
                        'desc'  => $item['desc'],
                        'qty'   => $item['qty'],
                        'up'    => $item['price'],
                        'tot'   => $item['total']
                    ]);
                }

                // CareTrail Event
                $careTrail->recordEvent(
                    patientId: $patientId,
                    eventType: 'invoice_generated',
                    eventTitle: "Invoice Finalized: {$invNumber}",
                    eventDescription: "Generated itemized patient bill for " . format_currency($totalAmount) . " (Tax: " . format_currency($tax) . ")",
                    departmentId: 2,
                    sourceTable: 'invoices',
                    sourceId: $invId,
                    severity: 'info',
                    createdBy: $currentUser['id']
                );

                $audit->log('CREATE', 'Invoice', $invId, "Generated invoice {$invNumber} for Patient #{$patientId} (Total: ₹{$totalAmount})", userId: $currentUser['id']);

                $db->commit();
                set_flash('success', "Invoice {$invNumber} generated successfully.");
            } catch (Exception $e) {
                $db->rollBack();
                set_flash('danger', "Invoice creation failed: " . $e->getMessage());
            }
        }
        redirect('invoices.php');
    }

    // Action 2: Record Payment
    if ($action === 'record_payment') {
        $invId   = (int)($_POST['invoice_id'] ?? 0);
        $amount  = (float)($_POST['amount'] ?? 0);
        $method  = $_POST['payment_method'] ?? 'cash';
        $ref     = sanitize_input($_POST['transaction_reference'] ?? '');
        $notes   = sanitize_input($_POST['notes'] ?? '');

        if ($invId && $amount > 0) {
            $db->beginTransaction();
            try {
                // Lock invoice
                $sel = $db->prepare("SELECT * FROM `invoices` WHERE id = :id FOR UPDATE");
                $sel->execute(['id' => $invId]);
                $inv = $sel->fetch();

                if (!$inv) {
                    $db->rollBack();
                    set_flash('danger', "Invoice not found.");
                    redirect('invoices.php');
                }

                if ($amount > (float)$inv['balance_amount']) {
                    $db->rollBack();
                    set_flash('danger', "Payment amount (₹{$amount}) cannot exceed current outstanding balance (₹{$inv['balance_amount']}).");
                    redirect('invoices.php');
                }

                $payNumber = generate_payment_number($db);
                $insPay = $db->prepare("
                    INSERT INTO `payments` (`payment_number`, `invoice_id`, `patient_id`, `amount`, `payment_method`, `transaction_reference`, `notes`, `received_by`, `payment_date`)
                    VALUES (:pnum, :iid, :pid, :amt, :meth, :ref, :notes, :by, NOW())
                ");
                $insPay->execute([
                    'pnum'  => $payNumber,
                    'iid'   => $invId,
                    'pid'   => $inv['patient_id'],
                    'amt'   => $amount,
                    'meth'  => $method,
                    'ref'   => $ref,
                    'notes' => $notes,
                    'by'    => $currentUser['id']
                ]);
                $payId = (int)$db->lastInsertId();

                // Recalculate invoice
                $newPaid = (float)$inv['paid_amount'] + $amount;
                $newBal  = (float)$inv['total_amount'] - $newPaid;
                $newStatus = $newBal <= 0.01 ? 'paid' : 'partially_paid';

                $db->prepare("
                    UPDATE `invoices` 
                    SET `paid_amount` = :pd, `balance_amount` = :bal, `status` = :st, `updated_at` = NOW()
                    WHERE `id` = :id
                ")->execute(['pd' => $newPaid, 'bal' => $newBal, 'st' => $newStatus, 'id' => $invId]);

                // CareTrail Event
                $careTrail->recordEvent(
                    patientId: (int)$inv['patient_id'],
                    eventType: 'payment_received',
                    eventTitle: "Payment Received: {$payNumber}",
                    eventDescription: "Received " . format_currency($amount) . " via " . strtoupper($method) . " for Invoice #{$inv['invoice_number']}. Remaining balance: " . format_currency($newBal),
                    departmentId: 2,
                    sourceTable: 'payments',
                    sourceId: $payId,
                    severity: 'success',
                    createdBy: $currentUser['id']
                );

                $audit->log('CREATE', 'Payment', $payId, "Recorded payment {$payNumber} of ₹{$amount} for Invoice #{$inv['invoice_number']}", userId: $currentUser['id']);

                $db->commit();
                set_flash('success', "Payment receipt {$payNumber} recorded. Outstanding balance: " . format_currency($newBal));
            } catch (Exception $e) {
                $db->rollBack();
                set_flash('danger', "Payment recording failed: " . $e->getMessage());
            }
        }
        redirect('invoices.php');
    }
}

// Fetch invoices
$patientFilter = !empty($_GET['patient_id']) ? (int)$_GET['patient_id'] : null;
if (has_role('patient') && !empty($currentUser['patient_id'])) {
    $patientFilter = $currentUser['patient_id'];
}

$sql = "
    SELECT 
        i.*,
        p.patient_id AS patient_code,
        p.first_name AS patient_first_name,
        p.last_name AS patient_last_name,
        p.phone AS patient_phone
    FROM `invoices` i
    JOIN `patient_profiles` p ON i.patient_id = p.id
    WHERE 1=1
";
$params = [];
if ($patientFilter) {
    $sql .= " AND i.patient_id = :pid";
    $params['pid'] = $patientFilter;
}
$sql .= " ORDER BY i.created_at DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$invoices = $stmt->fetchAll();

// Items per invoice
$rawItems = $db->query("SELECT * FROM `invoice_items` ORDER BY id ASC")->fetchAll();
$invItems = [];
foreach ($rawItems as $ri) {
    $invItems[$ri['invoice_id']][] = $ri;
}

$patientsList = $db->query("SELECT id, patient_id, first_name, last_name FROM `patient_profiles` ORDER BY first_name ASC")->fetchAll();

$pageTitle = 'Inpatient & Outpatient Invoicing Ledger';
require_once __DIR__ . '/../../app/views/layouts/header.php';
?>

<!-- Title & Action Bar -->
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div>
        <h4 class="fw-bold mb-1">Inpatient & Outpatient Invoicing Ledger</h4>
        <p class="text-secondary small mb-0">Itemized charge calculation, partial payment allocation, and receipts</p>
    </div>
    
    <div class="d-flex align-items-center gap-2">
        <?php if (!has_role('patient')): ?>
            <button type="button" class="btn btn-nexus-primary" data-bs-toggle="modal" data-bs-target="#createInvoiceModal">
                <i class="bi bi-file-earmark-plus-fill me-1"></i>Generate New Invoice
            </button>
        <?php endif; ?>
    </div>
</div>

<!-- Invoices Table -->
<div class="nexus-card">
    <div class="nexus-card-header">
        <h6 class="fw-bold mb-0">Hospital Invoices & Accounts Receivable (<?= count($invoices) ?>)</h6>
        <span class="badge bg-light text-secondary border">Exact Decimal Balance</span>
    </div>

    <div class="table-responsive">
        <table class="table-nexus">
            <thead>
                <tr>
                    <th>Invoice #</th>
                    <th>Patient Details</th>
                    <th>Total Charges</th>
                    <th>Paid Amount</th>
                    <th>Outstanding Balance</th>
                    <th>Payment Status</th>
                    <th>Invoice Date</th>
                    <th class="text-end" style="width: 180px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($invoices)): ?>
                    <tr>
                        <td colspan="8" class="text-center py-4 text-muted">No invoices found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($invoices as $inv): ?>
                        <tr>
                            <td>
                                <code><?= e($inv['invoice_number']) ?></code>
                            </td>
                            <td>
                                <strong><?= e($inv['patient_first_name'] . ' ' . $inv['patient_last_name']) ?></strong>
                                <small class="text-muted d-block"><?= e($inv['patient_code']) ?> &bull; <?= e($inv['patient_phone']) ?></small>
                            </td>
                            <td>
                                <strong><?= format_currency($inv['total_amount']) ?></strong>
                                <small class="text-muted d-block" style="font-size: 0.72rem;">Tax: <?= format_currency($inv['tax_amount']) ?></small>
                            </td>
                            <td>
                                <span class="text-success fw-semibold"><?= format_currency($inv['paid_amount']) ?></span>
                            </td>
                            <td>
                                <span class="fs-6 fw-bold <?= (float)$inv['balance_amount'] > 0 ? 'text-danger' : 'text-success' ?>">
                                    <?= format_currency($inv['balance_amount']) ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge bg-<?= $inv['status'] === 'paid' ? 'success' : ($inv['status'] === 'partially_paid' ? 'warning text-dark' : 'danger') ?>">
                                    <?= strtoupper(str_replace('_', ' ', e($inv['status']))) ?>
                                </span>
                            </td>
                            <td>
                                <small class="text-muted"><?= format_datetime($inv['created_at'], 'd M Y') ?></small>
                            </td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    <button type="button" class="btn btn-outline-secondary btn-sm" 
                                            onclick="viewInvoiceDetails(<?= htmlspecialchars(json_encode($inv), ENT_QUOTES, 'UTF-8') ?>, <?= htmlspecialchars(json_encode($invItems[$inv['id']] ?? []), ENT_QUOTES, 'UTF-8') ?>)">
                                        <i class="bi bi-eye"></i> View
                                    </button>

                                    <?php if ((float)$inv['balance_amount'] > 0 && !has_role('patient')): ?>
                                        <button type="button" class="btn btn-nexus-primary btn-sm" 
                                                onclick="openPaymentModal(<?= $inv['id'] ?>, '<?= e($inv['invoice_number']) ?>', <?= (float)$inv['balance_amount'] ?>)">
                                            <i class="bi bi-cash"></i> Pay
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

<!-- Modal 1: Create Invoice -->
<div class="modal fade" id="createInvoiceModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title fw-bold fs-6">Generate Itemized Hospital Invoice</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="invoices.php">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="create_invoice">

                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Select Patient *</label>
                        <select name="patient_id" class="form-select" required>
                            <option value="">Choose Patient...</option>
                            <?php foreach ($patientsList as $p): ?>
                                <option value="<?= $p['id'] ?>"><?= e($p['first_name'] . ' ' . $p['last_name']) ?> (<?= e($p['patient_id']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <h6 class="text-uppercase text-secondary fw-bold mb-2" style="font-size: 0.75rem;">Itemized Charges</h6>
                    <div id="invoiceItemsRows">
                        <div class="row g-2 mb-2 item-row">
                            <div class="col-md-3">
                                <select name="item_type[]" class="form-select form-select-sm">
                                    <option value="consultation">Consultation</option>
                                    <option value="lab_test">Lab Test</option>
                                    <option value="pharmacy">Pharmacy</option>
                                    <option value="bed_charge">Bed Charge</option>
                                    <option value="procedure">Procedure</option>
                                </select>
                            </div>
                            <div class="col-md-5">
                                <input type="text" name="item_desc[]" class="form-control form-control-sm" placeholder="Description (e.g. Echo + Consultation)" required>
                            </div>
                            <div class="col-md-2">
                                <input type="number" name="item_qty[]" class="form-control form-control-sm" value="1" min="1" placeholder="Qty">
                            </div>
                            <div class="col-md-2">
                                <input type="number" step="0.01" name="item_price[]" class="form-control form-control-sm" placeholder="Price (₹)" required>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="addInvoiceItemRow()">
                            <i class="bi bi-plus-circle me-1"></i>Add Another Line Item
                        </button>
                    </div>

                    <div class="row g-2 mb-2">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Discount Amount (₹)</label>
                            <input type="number" step="0.01" name="discount_amount" class="form-control" value="0.00">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Applicable GST Tax Rate</label>
                            <input type="text" class="form-control" value="5% (Healthcare Standard)" readonly>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-nexus-primary btn-sm px-4">Generate & Finalize Invoice</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal 2: Record Payment -->
<div class="modal fade" id="paymentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title fw-bold fs-6">Record Patient Payment</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="invoices.php">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="record_payment">
                <input type="hidden" name="invoice_id" id="payInvoiceId" value="">

                <div class="modal-body p-4">
                    <div class="alert alert-light border mb-3 small">
                        <strong>Invoice Number:</strong> <span id="payInvoiceNumber"></span><br>
                        <strong>Remaining Balance Due:</strong> <span class="text-danger fw-bold fs-6" id="payBalanceDue"></span>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Payment Amount to Collect (₹) *</label>
                        <input type="number" step="0.01" name="amount" id="payAmountInput" class="form-control" required min="1">
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Payment Method *</label>
                        <select name="payment_method" class="form-select" required>
                            <option value="cash" selected>Cash Payment</option>
                            <option value="card">Credit / Debit Card (POS)</option>
                            <option value="upi">UPI / QR Code Transfer</option>
                            <option value="insurance">Health Insurance Third-Party Claim</option>
                            <option value="bank_transfer">Direct Bank NEFT/RTGS</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Transaction / Insurance Claim Ref #</label>
                        <input type="text" name="transaction_reference" class="form-control" placeholder="e.g. UPI-9928172 or CLAIM-7729">
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Payment Notes</label>
                        <input type="text" name="notes" class="form-control" placeholder="Optional notes">
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success btn-sm px-4">Issue Payment Receipt</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal 3: View Invoice Printable Details -->
<div class="modal fade" id="viewInvoiceModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title fw-bold fs-6">Invoice Dossier: <span id="invViewNumber"></span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4" id="invViewContent">
                <!-- Populated via JS -->
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-primary btn-sm" onclick="printInvoiceDocument()">
                    <i class="bi bi-printer me-1"></i>Print Invoice Alone
                </button>
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
    function addInvoiceItemRow() {
        const container = document.getElementById('invoiceItemsRows');
        const row = document.createElement('div');
        row.className = 'row g-2 mb-2 item-row';
        row.innerHTML = `
            <div class="col-md-3">
                <select name="item_type[]" class="form-select form-select-sm">
                    <option value="consultation">Consultation</option>
                    <option value="lab_test">Lab Test</option>
                    <option value="pharmacy">Pharmacy</option>
                    <option value="bed_charge">Bed Charge</option>
                    <option value="procedure">Procedure</option>
                </select>
            </div>
            <div class="col-md-5">
                <input type="text" name="item_desc[]" class="form-control form-control-sm" placeholder="Description" required>
            </div>
            <div class="col-md-2">
                <input type="number" name="item_qty[]" class="form-control form-control-sm" value="1" min="1">
            </div>
            <div class="col-md-2">
                <input type="number" step="0.01" name="item_price[]" class="form-control form-control-sm" placeholder="Price (₹)" required>
            </div>
        `;
        container.appendChild(row);
    }

    function openPaymentModal(invId, invNum, balance) {
        document.getElementById('payInvoiceId').value = invId;
        document.getElementById('payInvoiceNumber').textContent = invNum;
        document.getElementById('payBalanceDue').textContent = '₹' + balance.toFixed(2);
        document.getElementById('payAmountInput').value = balance.toFixed(2);
        document.getElementById('payAmountInput').max = balance;
        const modal = new bootstrap.Modal(document.getElementById('paymentModal'));
        modal.show();
    }

    function viewInvoiceDetails(inv, items) {
        document.getElementById('invViewNumber').textContent = inv.invoice_number;
        let itemsHtml = '';
        items.forEach(it => {
            itemsHtml += `
                <tr>
                    <td>${it.item_description} <span class="badge bg-light text-muted border">${it.item_type}</span></td>
                    <td class="text-center">${it.quantity}</td>
                    <td class="text-end">₹${parseFloat(it.unit_price).toFixed(2)}</td>
                    <td class="text-end fw-bold">₹${parseFloat(it.total_price).toFixed(2)}</td>
                </tr>
            `;
        });

        const html = `
            <div class="border-bottom pb-3 mb-3 d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="fw-bold mb-0 text-dark">KVM Hospital — NexusCare</h5>
                    <small class="text-muted">Itemized Clinical Patient Bill</small>
                </div>
                <div class="text-end">
                    <span class="badge bg-dark fs-6">${inv.invoice_number}</span><br>
                    <small class="text-muted">Date: ${inv.created_at}</small>
                </div>
            </div>

            <div class="bg-light p-3 rounded mb-3 small">
                <strong>Patient:</strong> ${inv.patient_first_name} ${inv.patient_last_name} (${inv.patient_code})<br>
                <strong>Phone:</strong> ${inv.patient_phone}
            </div>

            <table class="table table-sm table-bordered">
                <thead class="table-light">
                    <tr>
                        <th>Item Description</th>
                        <th class="text-center" style="width: 70px;">Qty</th>
                        <th class="text-end" style="width: 100px;">Unit Price</th>
                        <th class="text-end" style="width: 110px;">Total</th>
                    </tr>
                </thead>
                <tbody>
                    ${itemsHtml || '<tr><td colspan="4" class="text-center text-muted">No itemized charges</td></tr>'}
                </tbody>
            </table>

            <div class="row justify-content-end text-end small">
                <div class="col-md-5">
                    <div class="d-flex justify-content-between py-1 border-bottom">
                        <span>Subtotal:</span><strong>₹${parseFloat(inv.subtotal).toFixed(2)}</strong>
                    </div>
                    <div class="d-flex justify-content-between py-1 border-bottom">
                        <span>Discount:</span><span class="text-danger">-₹${parseFloat(inv.discount_amount).toFixed(2)}</span>
                    </div>
                    <div class="d-flex justify-content-between py-1 border-bottom">
                        <span>GST Tax (5%):</span><span>+₹${parseFloat(inv.tax_amount).toFixed(2)}</span>
                    </div>
                    <div class="d-flex justify-content-between py-1 border-bottom fs-6 fw-bold">
                        <span>Grand Total:</span><span class="text-primary">₹${parseFloat(inv.total_amount).toFixed(2)}</span>
                    </div>
                    <div class="d-flex justify-content-between py-1 border-bottom">
                        <span>Paid:</span><span class="text-success">₹${parseFloat(inv.paid_amount).toFixed(2)}</span>
                    </div>
                    <div class="d-flex justify-content-between py-1 fs-6 fw-bold">
                        <span>Balance Due:</span><span class="text-danger">₹${parseFloat(inv.balance_amount).toFixed(2)}</span>
                    </div>
                </div>
            </div>
        `;

        document.getElementById('invViewContent').innerHTML = html;
        const modal = new bootstrap.Modal(document.getElementById('viewInvoiceModal'));
        modal.show();
    }

    function printInvoiceDocument() {
        const content = document.getElementById('invViewContent');
        if (!content) {
            window.print();
            return;
        }

        const invNum = document.getElementById('invViewNumber')?.textContent || 'Invoice';

        // Create an isolated hidden iframe for 100% clean, pure document printing
        let printFrame = document.getElementById('invoicePrintFrame');
        if (!printFrame) {
            printFrame = document.createElement('iframe');
            printFrame.id = 'invoicePrintFrame';
            printFrame.style.position = 'fixed';
            printFrame.style.right = '0';
            printFrame.style.bottom = '0';
            printFrame.style.width = '0';
            printFrame.style.height = '0';
            printFrame.style.border = '0';
            document.body.appendChild(printFrame);
        }

        const doc = printFrame.contentDocument || printFrame.contentWindow.document;
        doc.open();
        doc.write(`<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Clinical Bill — ${invNum}</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <style>
        @page {
            size: A4 portrait;
            margin: 15mm 12mm;
        }
        * {
            box-sizing: border-box;
        }
        body {
            background: #ffffff !important;
            color: #111827 !important;
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            font-size: 11pt;
            line-height: 1.4;
            padding: 0;
            margin: 0;
        }
        .invoice-print-container {
            max-width: 100%;
            margin: 0 auto;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
            margin-bottom: 1.5rem;
        }
        th, td {
            border: 1px solid #d1d5db !important;
            padding: 7px 10px !important;
        }
        .bg-light {
            background-color: #f8fafc !important;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
        .badge {
            border: 1px solid #94a3b8 !important;
            color: #334155 !important;
            background: transparent !important;
            font-weight: 500;
        }
        .hospital-signature-footer {
            border-top: 1px dashed #cbd5e1;
            margin-top: 3rem;
            padding-top: 1.25rem;
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            font-size: 9pt;
            color: #64748b;
        }
        .signature-box {
            text-align: right;
        }
        .signature-line {
            border-bottom: 1px solid #94a3b8;
            width: 200px;
            margin-left: auto;
            margin-bottom: 4px;
        }
    </style>
</head>
<body>
    <div class="invoice-print-container">
        ${content.innerHTML}
        <div class="hospital-signature-footer">
            <div>
                <strong>KVM Hospital Management System — NexusCare</strong><br>
                Computer-generated official clinical bill & receipt.<br>
                Health Reg: KVM-HOSP-2026-MED &bull; Centralized DBMS Invoicing
            </div>
            <div class="signature-box">
                <div class="signature-line"></div>
                <span>Authorized Billing Officer / Cashier</span>
            </div>
        </div>
    </div>
</body>
</html>`);
        doc.close();

        setTimeout(() => {
            try {
                printFrame.contentWindow.focus();
                printFrame.contentWindow.print();
            } catch (err) {
                window.print();
            }
        }, 300);
    }
</script>

<?php require_once __DIR__ . '/../../app/views/layouts/footer.php'; ?>
