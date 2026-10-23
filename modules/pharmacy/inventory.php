<?php
/**
 * KVM Hospital Management System — NexusCare
 * Core Module: Pharmacy Inventory & Medication Dispensing
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

    // Action 1: Add new medicine catalog entry
    if ($action === 'add_medicine') {
        $code     = sanitize_input($_POST['medicine_code'] ?? '');
        $name     = sanitize_input($_POST['name'] ?? '');
        $generic  = sanitize_input($_POST['generic_name'] ?? '');
        $category = sanitize_input($_POST['category'] ?? 'Tablet');
        $unit     = sanitize_input($_POST['unit'] ?? 'strip');
        $price    = (float)($_POST['unit_price'] ?? 0);
        $reorder  = (int)($_POST['reorder_level'] ?? 20);

        if ($code && $name && $generic && $price >= 0) {
            try {
                $ins = $db->prepare("
                    INSERT INTO `medicines` (`medicine_code`, `name`, `generic_name`, `category`, `unit`, `unit_price`, `stock_quantity`, `reorder_level`)
                    VALUES (:c, :n, :g, :cat, :u, :p, 0, :r)
                ");
                $ins->execute([
                    'c'   => $code,
                    'n'   => $name,
                    'g'   => $generic,
                    'cat' => $category,
                    'u'   => $unit,
                    'p'   => $price,
                    'r'   => $reorder
                ]);
                $medId = (int)$db->lastInsertId();
                $audit->log('CREATE', 'Medicine', $medId, "Added new medication {$name} ({$code}) to catalog", userId: $currentUser['id']);
                set_flash('success', "Medication {$name} added to pharmacy catalog.");
            } catch (Exception $e) {
                set_flash('danger', "Failed to add medication: " . $e->getMessage());
            }
        }
        redirect('inventory.php');
    }

    // Action 2: Stock-In Batch
    if ($action === 'add_batch') {
        $medId    = (int)($_POST['medicine_id'] ?? 0);
        $batchNo  = sanitize_input($_POST['batch_number'] ?? '');
        $expiry   = trim($_POST['expiry_date'] ?? '');
        $qty      = (int)($_POST['quantity'] ?? 0);
        $cost     = (float)($_POST['purchase_cost'] ?? 0);

        if ($medId && $batchNo && $expiry && $qty > 0) {
            $db->beginTransaction();
            try {
                // Insert batch
                $insB = $db->prepare("
                    INSERT INTO `medicine_batches` (`medicine_id`, `batch_number`, `expiry_date`, `quantity`, `purchase_cost`)
                    VALUES (:mid, :bno, :exp, :qty, :cost)
                ");
                $insB->execute([
                    'mid'  => $medId,
                    'bno'  => $batchNo,
                    'exp'  => $expiry,
                    'qty'  => $qty,
                    'cost' => $cost
                ]);
                $batchId = (int)$db->lastInsertId();

                // Increment stock on master table
                $updMed = $db->prepare("UPDATE `medicines` SET `stock_quantity` = `stock_quantity` + :qty WHERE `id` = :mid");
                $updMed->execute(['qty' => $qty, 'mid' => $medId]);

                // Balance after
                $balStmt = $db->prepare("SELECT stock_quantity FROM `medicines` WHERE id = :mid");
                $balStmt->execute(['mid' => $medId]);
                $balAfter = (int)$balStmt->fetchColumn();

                // Inventory transaction record
                $insTx = $db->prepare("
                    INSERT INTO `inventory_transactions` (`medicine_id`, `batch_id`, `transaction_type`, `quantity`, `balance_after`, `reference_id`, `created_by`)
                    VALUES (:mid, :bid, 'stock_in', :qty, :bal, :ref, :by)
                ");
                $insTx->execute([
                    'mid' => $medId,
                    'bid' => $batchId,
                    'qty' => $qty,
                    'bal' => $balAfter,
                    'ref' => "PO-BATCH-{$batchNo}",
                    'by'  => $currentUser['id']
                ]);

                $audit->log('CREATE', 'MedicineBatch', $batchId, "Received batch {$batchNo} (+{$qty} units) for Medicine #{$medId}", userId: $currentUser['id']);

                $db->commit();
                set_flash('success', "Batch {$batchNo} received. Added {$qty} units to stock.");
            } catch (Exception $e) {
                $db->rollBack();
                set_flash('danger', "Failed to record stock batch: " . $e->getMessage());
            }
        }
        redirect('inventory.php');
    }

    // Action 3: Direct Dispense with Non-Negative Stock Enforcement
    if ($action === 'dispense') {
        $patientId = (int)($_POST['patient_id'] ?? 0);
        $medId     = (int)($_POST['medicine_id'] ?? 0);
        $dispQty   = (int)($_POST['quantity'] ?? 0);
        $notes     = sanitize_input($_POST['dispensing_notes'] ?? '');

        if ($patientId && $medId && $dispQty > 0) {
            $db->beginTransaction();
            try {
                // 1. Lock medicine record and check available stock
                $sel = $db->prepare("SELECT name, stock_quantity, unit_price FROM `medicines` WHERE id = :id FOR UPDATE");
                $sel->execute(['id' => $medId]);
                $med = $sel->fetch();

                if (!$med) {
                    $db->rollBack();
                    set_flash('danger', "Selected medication does not exist.");
                    redirect('inventory.php');
                }

                if ($med['stock_quantity'] < $dispQty) {
                    $db->rollBack();
                    set_flash('danger', "Insufficient stock: Requested {$dispQty} units, but only {$med['stock_quantity']} units available in pharmacy for {$med['name']}. Transaction aborted to prevent negative inventory.");
                    redirect('inventory.php');
                }

                // 2. Deduct inventory safely
                $updStock = $db->prepare("UPDATE `medicines` SET `stock_quantity` = `stock_quantity` - :qty WHERE id = :id");
                $updStock->execute(['qty' => $dispQty, 'id' => $medId]);
                $balAfter = $med['stock_quantity'] - $dispQty;

                // 3. Create Pharmacy Order & Dispensing Record
                $ordNum = "PHO-" . date('Y') . "-" . sprintf("%04d", rand(1000, 9999));
                $totalPrice = (float)$med['unit_price'] * $dispQty;

                $insOrd = $db->prepare("
                    INSERT INTO `pharmacy_orders` (`order_number`, `patient_id`, `status`, `total_amount`, `created_at`)
                    VALUES (:num, :pid, 'dispensed', :tot, NOW())
                ");
                $insOrd->execute(['num' => $ordNum, 'pid' => $patientId, 'tot' => $totalPrice]);
                $orderId = (int)$db->lastInsertId();

                $insItem = $db->prepare("
                    INSERT INTO `pharmacy_order_items` (`pharmacy_order_id`, `medicine_id`, `quantity`, `unit_price`, `total_price`)
                    VALUES (:oid, :mid, :qty, :up, :tot)
                ");
                $insItem->execute(['oid' => $orderId, 'mid' => $medId, 'qty' => $dispQty, 'up' => $med['unit_price'], 'tot' => $totalPrice]);

                $insDisp = $db->prepare("
                    INSERT INTO `pharmacy_dispensing_records` (`pharmacy_order_id`, `dispensed_by`, `dispensing_notes`, `dispensed_at`)
                    VALUES (:oid, :by, :notes, NOW())
                ");
                $insDisp->execute(['oid' => $orderId, 'by' => $currentUser['id'], 'notes' => $notes]);

                // 4. Inventory Transaction Log
                $insTx = $db->prepare("
                    INSERT INTO `inventory_transactions` (`medicine_id`, `transaction_type`, `quantity`, `balance_after`, `reference_id`, `created_by`)
                    VALUES (:mid, 'dispensed', :qty, :bal, :ref, :by)
                ");
                $insTx->execute([
                    'mid' => $medId,
                    'qty' => -$dispQty,
                    'bal' => $balAfter,
                    'ref' => $ordNum,
                    'by'  => $currentUser['id']
                ]);

                // 5. CareTrail Milestone
                $careTrail->recordEvent(
                    patientId: $patientId,
                    eventType: 'medicine_dispensed',
                    eventTitle: "Pharmacy Dispensed: {$med['name']}",
                    eventDescription: "Dispensed {$dispQty} units of {$med['name']} (#{$ordNum}). Total: " . format_currency($totalPrice),
                    departmentId: 15,
                    sourceTable: 'pharmacy_orders',
                    sourceId: $orderId,
                    severity: 'success',
                    createdBy: $currentUser['id']
                );

                // 6. TrustLedger Audit
                $audit->log('STATUS_CHANGE', 'PharmacyOrder', $orderId, "Dispensed {$dispQty}x {$med['name']} to Patient #{$patientId} (Order #{$ordNum})", userId: $currentUser['id']);

                $db->commit();
                set_flash('success', "Dispensed {$dispQty} units of {$med['name']}. Balance remaining: {$balAfter} units.");
            } catch (Exception $e) {
                $db->rollBack();
                set_flash('danger', "Dispensing failed: " . $e->getMessage());
            }
        }
        redirect('inventory.php');
    }
}

// Fetch inventory list
$medicines = $db->query("
    SELECT 
        m.*,
        (SELECT COUNT(*) FROM `medicine_batches` mb WHERE mb.medicine_id = m.id AND mb.expiry_date <= CURDATE() + INTERVAL 90 DAY) AS near_expiry_batches
    FROM `medicines` m
    ORDER BY m.name ASC
")->fetchAll();

// Statistics
$totalStockValue = 0;
$lowStockCount = 0;
$nearExpiryCount = 0;
foreach ($medicines as $m) {
    $totalStockValue += ((float)$m['unit_price'] * (int)$m['stock_quantity']);
    if ((int)$m['stock_quantity'] <= (int)$m['reorder_level']) {
        $lowStockCount++;
    }
    if ((int)$m['near_expiry_batches'] > 0) {
        $nearExpiryCount++;
    }
}

$patientsList = $db->query("SELECT id, patient_id, first_name, last_name FROM `patient_profiles` ORDER BY first_name ASC")->fetchAll();

$pageTitle = 'Pharmacy Inventory & Dispensing Management';
require_once __DIR__ . '/../../app/views/layouts/header.php';
?>

<!-- Title & Action Bar -->
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div>
        <h4 class="fw-bold mb-1">Pharmacy Inventory & Dispensing Management</h4>
        <p class="text-secondary small mb-0">Stock batch tracking, non-negative inventory enforcement, and CareTrail integration</p>
    </div>
    
    <div class="d-flex align-items-center gap-2">
        <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#dispenseModal">
            <i class="bi bi-cart-check-fill me-1"></i>Dispense Prescription
        </button>
        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#addBatchModal">
            <i class="bi bi-box-arrow-in-down me-1"></i>Stock In Batch
        </button>
        <button type="button" class="btn btn-nexus-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addMedModal">
            <i class="bi bi-plus-circle me-1"></i>New Medicine
        </button>
    </div>
</div>

<!-- KPI Cards -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-lg-3">
        <div class="stat-widget">
            <div class="stat-icon teal">
                <i class="bi bi-capsule"></i>
            </div>
            <div>
                <div class="stat-value"><?= count($medicines) ?></div>
                <div class="stat-label">Catalog Items</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="stat-widget">
            <div class="stat-icon emerald">
                <i class="bi bi-cash-stack"></i>
            </div>
            <div>
                <div class="stat-value text-success"><?= format_currency($totalStockValue) ?></div>
                <div class="stat-label">Total Inventory Valuation</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="stat-widget">
            <div class="stat-icon danger">
                <i class="bi bi-exclamation-triangle"></i>
            </div>
            <div>
                <div class="stat-value text-danger"><?= $lowStockCount ?></div>
                <div class="stat-label">Low Stock Alerts</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="stat-widget">
            <div class="stat-icon amber">
                <i class="bi bi-calendar-x"></i>
            </div>
            <div>
                <div class="stat-value text-warning"><?= $nearExpiryCount ?></div>
                <div class="stat-label">Batches Near Expiry (&lt;90d)</div>
            </div>
        </div>
    </div>
</div>

<!-- Search Bar -->
<div class="nexus-card mb-4 p-3">
    <input type="text" class="form-control" placeholder="Search pharmacy catalog by brand name, generic name, or medicine code..." data-table-search="pharmacyTable">
</div>

<!-- Inventory Table Card -->
<div class="nexus-card">
    <div class="nexus-card-header">
        <h6 class="fw-bold mb-0">Pharmacy Inventory Ledger</h6>
        <span class="badge bg-light text-secondary border">ACID Stock Protected</span>
    </div>

    <div class="table-responsive">
        <table class="table-nexus" id="pharmacyTable">
            <thead>
                <tr>
                    <th>Med Code</th>
                    <th>Brand & Generic Name</th>
                    <th>Category</th>
                    <th>Unit Price</th>
                    <th>Available Stock</th>
                    <th>Reorder Level</th>
                    <th>Stock Health</th>
                    <th class="text-end">Quick Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($medicines as $m): ?>
                    <?php 
                        $isLow = (int)$m['stock_quantity'] <= (int)$m['reorder_level'];
                        $isNearExp = (int)$m['near_expiry_batches'] > 0;
                    ?>
                    <tr>
                        <td><code><?= e($m['medicine_code']) ?></code></td>
                        <td>
                            <strong><?= e($m['name']) ?></strong>
                            <small class="text-muted d-block"><?= e($m['generic_name']) ?></small>
                        </td>
                        <td>
                            <span class="badge bg-light text-dark border"><?= e($m['category']) ?> (<?= e($m['unit']) ?>)</span>
                        </td>
                        <td>
                            <strong><?= format_currency($m['unit_price']) ?></strong>
                        </td>
                        <td>
                            <span class="fs-6 fw-bold <?= $isLow ? 'text-danger' : 'text-success' ?>">
                                <?= (int)$m['stock_quantity'] ?> <?= e($m['unit']) ?>s
                            </span>
                        </td>
                        <td>
                            <small class="text-muted"><?= (int)$m['reorder_level'] ?></small>
                        </td>
                        <td>
                            <?php if ($isLow): ?>
                                <span class="badge bg-danger"><i class="bi bi-exclamation-circle me-1"></i>Low Stock</span>
                            <?php elseif ($isNearExp): ?>
                                <span class="badge bg-warning text-dark"><i class="bi bi-clock me-1"></i>Near Expiry</span>
                            <?php else: ?>
                                <span class="badge bg-success">Optimal</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <button type="button" class="btn btn-outline-primary btn-sm" 
                                    onclick="openQuickDispense(<?= $m['id'] ?>, '<?= e($m['name']) ?>', <?= (int)$m['stock_quantity'] ?>)">
                                <i class="bi bi-cart-check"></i> Dispense
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal 1: Add New Medicine -->
<div class="modal fade" id="addMedModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title fw-bold fs-6">Add New Medication to Catalog</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="inventory.php">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="add_medicine">

                <div class="modal-body p-4">
                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Medicine Code *</label>
                            <input type="text" name="medicine_code" class="form-control" required placeholder="e.g. MED-IBU-400">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Dosage Form</label>
                            <select name="category" class="form-select">
                                <option value="Tablet" selected>Tablet</option>
                                <option value="Syrup">Syrup</option>
                                <option value="Injection">Injection</option>
                                <option value="Capsule">Capsule</option>
                                <option value="Ointment">Ointment</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Brand / Commercial Name *</label>
                        <input type="text" name="name" class="form-control" required placeholder="e.g. Brufen 400mg">
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Generic Chemical Formula *</label>
                        <input type="text" name="generic_name" class="form-control" required placeholder="e.g. Ibuprofen">
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Unit Type</label>
                            <input type="text" name="unit" class="form-control" value="strip" placeholder="strip / bottle">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Unit Price (₹) *</label>
                            <input type="number" step="0.01" name="unit_price" class="form-control" required placeholder="50.00">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Reorder Alert Qty</label>
                            <input type="number" name="reorder_level" class="form-control" value="20">
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-nexus-primary btn-sm px-4">Save Medication</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal 2: Stock In Batch -->
<div class="modal fade" id="addBatchModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title fw-bold fs-6">Receive Medicine Stock Batch</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="inventory.php">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="add_batch">

                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Select Medication *</label>
                        <select name="medicine_id" class="form-select" required>
                            <option value="">Choose Medication...</option>
                            <?php foreach ($medicines as $m): ?>
                                <option value="<?= $m['id'] ?>"><?= e($m['name']) ?> (<?= e($m['medicine_code']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Batch Number *</label>
                            <input type="text" name="batch_number" class="form-control" required placeholder="e.g. BTH-2026-X8">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Expiry Date *</label>
                            <input type="date" name="expiry_date" class="form-control" required min="<?= date('Y-m-d') ?>">
                        </div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Quantity Received *</label>
                            <input type="number" name="quantity" class="form-control" required min="1" placeholder="100">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Purchase Cost Unit (₹)</label>
                            <input type="number" step="0.01" name="purchase_cost" class="form-control" placeholder="25.00">
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-nexus-primary btn-sm px-4">Receive Stock</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal 3: Dispense Prescription -->
<div class="modal fade" id="dispenseModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold fs-6">Dispense Prescription to Patient</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="inventory.php">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="dispense">

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

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Select Medication *</label>
                        <select name="medicine_id" id="dispenseMedSelect" class="form-select" required>
                            <option value="">Choose Medication...</option>
                            <?php foreach ($medicines as $m): ?>
                                <option value="<?= $m['id'] ?>">
                                    <?= e($m['name']) ?> (Stock: <?= (int)$m['stock_quantity'] ?> <?= e($m['unit']) ?>s) &bull; <?= format_currency($m['unit_price']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Units to Dispense *</label>
                        <input type="number" name="quantity" class="form-control" required min="1" value="1">
                        <small class="text-muted">Quantity will be immediately deducted via atomic inventory transaction.</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Pharmacist Counseling Notes</label>
                        <input type="text" name="dispensing_notes" class="form-control" placeholder="e.g. Counseled on taking with food, twice daily">
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-nexus-primary btn-sm px-4">Confirm & Dispense</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function openQuickDispense(medId, medName, stock) {
        document.getElementById('dispenseMedSelect').value = medId;
        const modal = new bootstrap.Modal(document.getElementById('dispenseModal'));
        modal.show();
    }
</script>

<?php require_once __DIR__ . '/../../app/views/layouts/footer.php'; ?>
