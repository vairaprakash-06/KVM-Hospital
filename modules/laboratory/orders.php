<?php
/**
 * KVM Hospital Management System — NexusCare
 * Core Module: Diagnostic Laboratory & Results Entry
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

    // Action 1: Create Lab Order
    if ($action === 'create_order') {
        $patientId = (int)($_POST['patient_id'] ?? 0);
        $doctorId  = (int)($_POST['doctor_id'] ?? 1);
        $testIds   = $_POST['test_ids'] ?? [];
        $priority  = $_POST['priority'] ?? 'routine';
        $notes     = sanitize_input($_POST['clinical_notes'] ?? '');

        if ($patientId && !empty($testIds)) {
            $db->beginTransaction();
            try {
                $orderNumber = "LBO-" . date('Y') . "-" . sprintf("%04d", rand(1000, 9999));
                $insOrder = $db->prepare("
                    INSERT INTO `lab_orders` (`order_number`, `patient_id`, `ordered_by_doctor_id`, `priority`, `status`, `clinical_notes`, `created_at`)
                    VALUES (:num, :pid, :doc_id, :pri, 'ordered', :notes, NOW())
                ");
                $insOrder->execute([
                    'num'    => $orderNumber,
                    'pid'    => $patientId,
                    'doc_id' => $doctorId,
                    'pri'    => $priority,
                    'notes'  => $notes
                ]);
                $orderId = (int)$db->lastInsertId();

                // Add Items
                $insItem = $db->prepare("
                    INSERT INTO `lab_order_items` (`lab_order_id`, `test_id`, `status`)
                    VALUES (:oid, :tid, 'pending')
                ");
                foreach ($testIds as $tid) {
                    $insItem->execute(['oid' => $orderId, 'tid' => (int)$tid]);
                }

                // CareTrail Event
                $careTrail->recordEvent(
                    patientId: $patientId,
                    eventType: 'lab_ordered',
                    eventTitle: "Lab Order Dispatched: {$orderNumber}",
                    eventDescription: "Diagnostic orders sent to Core Lab. Priority: " . strtoupper($priority),
                    departmentId: 14,
                    sourceTable: 'lab_orders',
                    sourceId: $orderId,
                    severity: $priority === 'stat' ? 'critical' : ($priority === 'urgent' ? 'warning' : 'info'),
                    createdBy: $currentUser['id']
                );

                // TrustLedger
                $audit->log('CREATE', 'LabOrder', $orderId, "Dispatched lab order {$orderNumber} for Patient #{$patientId}", userId: $currentUser['id']);

                $db->commit();
                set_flash('success', "Lab order {$orderNumber} created and dispatched to laboratory.");
            } catch (Exception $e) {
                $db->rollBack();
                set_flash('danger', "Failed to create lab order: " . $e->getMessage());
            }
        }
        redirect('orders.php');
    }

    // Action 2: Enter Result
    if ($action === 'enter_result') {
        $itemId  = (int)($_POST['item_id'] ?? 0);
        $orderId = (int)($_POST['order_id'] ?? 0);
        $resultVal = sanitize_input($_POST['result_value'] ?? '');
        $refRange  = sanitize_input($_POST['reference_range'] ?? '');
        $resNotes  = sanitize_input($_POST['result_notes'] ?? '');

        if ($itemId && !empty($resultVal)) {
            $db->beginTransaction();
            try {
                $upd = $db->prepare("
                    UPDATE `lab_order_items` 
                    SET `status` = 'completed',
                        `result_value` = :val,
                        `reference_range` = :ref,
                        `result_notes` = :notes,
                        `tested_by` = :uid,
                        `completed_at` = NOW()
                    WHERE `id` = :id
                ");
                $upd->execute([
                    'val'   => $resultVal,
                    'ref'   => $refRange,
                    'notes' => $resNotes,
                    'uid'   => $currentUser['id'],
                    'id'    => $itemId
                ]);

                // Update order status if all items completed
                $chkPending = $db->prepare("SELECT COUNT(*) FROM `lab_order_items` WHERE `lab_order_id` = :oid AND `status` != 'completed'");
                $chkPending->execute(['oid' => $orderId]);
                $pendingCount = (int)$chkPending->fetchColumn();

                if ($pendingCount === 0) {
                    $db->prepare("UPDATE `lab_orders` SET `status` = 'completed', `updated_at` = NOW() WHERE `id` = :oid")
                       ->execute(['oid' => $orderId]);

                    // Fetch patient for CareTrail
                    $pStmt = $db->prepare("SELECT patient_id, order_number FROM `lab_orders` WHERE id = :oid");
                    $pStmt->execute(['oid' => $orderId]);
                    $ord = $pStmt->fetch();

                    if ($ord) {
                        $careTrail->recordEvent(
                            patientId: (int)$ord['patient_id'],
                            eventType: 'lab_completed',
                            eventTitle: "Lab Results Ready: {$ord['order_number']}",
                            eventDescription: "All diagnostic tests analyzed and results certified by technologist.",
                            departmentId: 14,
                            sourceTable: 'lab_orders',
                            sourceId: $orderId,
                            severity: 'success',
                            createdBy: $currentUser['id']
                        );
                    }
                }

                $audit->log('UPDATE', 'LabResult', $itemId, "Entered test result for Lab Item #{$itemId}: {$resultVal}", userId: $currentUser['id']);

                $db->commit();
                set_flash('success', "Test result recorded successfully.");
            } catch (Exception $e) {
                $db->rollBack();
                set_flash('danger', "Failed to record result: " . $e->getMessage());
            }
        }
        redirect('orders.php');
    }
}

// Fetch orders list
$sql = "
    SELECT 
        lo.*,
        p.patient_id AS patient_code,
        p.first_name AS patient_first_name,
        p.last_name AS patient_last_name,
        d.first_name AS doc_first_name,
        d.last_name AS doc_last_name
    FROM `lab_orders` lo
    JOIN `patient_profiles` p ON lo.patient_id = p.id
    JOIN `doctor_profiles` d ON lo.ordered_by_doctor_id = d.id
    ORDER BY lo.created_at DESC
";
$orders = $db->query($sql)->fetchAll();

// Fetch items for all orders
$itemsStmt = $db->query("
    SELECT 
        loi.*,
        lt.test_name,
        lt.test_code,
        lt.normal_range AS default_normal,
        lt.units,
        u.username AS tester_name
    FROM `lab_order_items` loi
    JOIN `lab_tests` lt ON loi.test_id = lt.id
    LEFT JOIN `users` u ON loi.tested_by = u.id
    ORDER BY loi.id ASC
");
$rawItems = $itemsStmt->fetchAll();
$orderItems = [];
foreach ($rawItems as $ri) {
    $orderItems[$ri['lab_order_id']][] = $ri;
}

$labCatalog = $db->query("SELECT * FROM `lab_tests` ORDER BY test_name ASC")->fetchAll();
$patientsList = $db->query("SELECT id, patient_id, first_name, last_name FROM `patient_profiles` ORDER BY first_name ASC")->fetchAll();
$doctorsList = $db->query("SELECT id, first_name, last_name FROM `doctor_profiles`")->fetchAll();

$pageTitle = 'Diagnostic Laboratory Management';
require_once __DIR__ . '/../../app/views/layouts/header.php';
?>

<!-- Title & Action Bar -->
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div>
        <h4 class="fw-bold mb-1">Diagnostic Laboratory Management</h4>
        <p class="text-secondary small mb-0">Specimen workflow tracking, quantitative result entry, and automated CareTrail synchronization</p>
    </div>
    
    <div class="d-flex align-items-center gap-2">
        <button type="button" class="btn btn-nexus-primary" data-bs-toggle="modal" data-bs-target="#newLabOrderModal">
            <i class="bi bi-eyedropper me-1"></i>New Investigation Order
        </button>
    </div>
</div>

<!-- Orders List -->
<div class="row g-4">
    <?php if (empty($orders)): ?>
        <div class="col-12 text-center py-5 text-muted">
            <i class="bi bi-vial fs-1 d-block mb-2"></i>
            No laboratory orders on record.
        </div>
    <?php else: ?>
        <?php foreach ($orders as $ord): ?>
            <div class="col-12">
                <div class="nexus-card">
                    <div class="nexus-card-header bg-light">
                        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 w-100">
                            <div class="d-flex align-items-center gap-3">
                                <span class="badge bg-dark fs-6"><?= e($ord['order_number']) ?></span>
                                <div>
                                    <strong class="text-dark"><?= e($ord['patient_first_name'] . ' ' . $ord['patient_last_name']) ?></strong>
                                    <small class="text-muted ms-1">(<?= e($ord['patient_code']) ?>)</small>
                                </div>
                                <span class="badge badge-nexus badge-priority-<?= e($ord['priority']) ?>">
                                    <?= strtoupper(e($ord['priority'])) ?>
                                </span>
                            </div>

                            <div class="d-flex align-items-center gap-2">
                                <span class="badge bg-<?= $ord['status'] === 'completed' ? 'success' : 'warning text-dark' ?>">
                                    <?= strtoupper(e($ord['status'])) ?>
                                </span>
                                <small class="text-muted"><?= format_datetime($ord['created_at'], 'd M, h:i A') ?></small>
                            </div>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table-nexus mb-0">
                            <thead>
                                <tr>
                                    <th>Test Code & Name</th>
                                    <th>Result Value</th>
                                    <th>Normal Range / Units</th>
                                    <th>Status</th>
                                    <th>Verified By</th>
                                    <th class="text-end">Result Entry</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $items = $orderItems[$ord['id']] ?? []; ?>
                                <?php foreach ($items as $item): ?>
                                    <tr>
                                        <td>
                                            <strong><?= e($item['test_name']) ?></strong>
                                            <code class="d-block text-muted"><?= e($item['test_code']) ?></code>
                                        </td>
                                        <td>
                                            <?php if ($item['result_value']): ?>
                                                <span class="fs-6 fw-bold text-dark"><?= e($item['result_value']) ?></span>
                                                <?php if ($item['result_notes']): ?>
                                                    <small class="text-muted d-block"><?= e($item['result_notes']) ?></small>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="text-muted italic">Pending Analysis</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?= e($item['reference_range'] ?: $item['default_normal']) ?> 
                                            <small class="text-muted"><?= e($item['units']) ?></small>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?= $item['status'] === 'completed' ? 'success' : 'secondary' ?>">
                                                <?= ucfirst(e($item['status'])) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <small class="text-muted"><?= e($item['tester_name'] ?: '—') ?></small>
                                        </td>
                                        <td class="text-end">
                                            <?php if ($item['status'] !== 'completed'): ?>
                                                <button type="button" class="btn btn-nexus-primary btn-sm"
                                                        onclick="openResultModal(<?= $item['id'] ?>, <?= $ord['id'] ?>, '<?= e($item['test_name']) ?>', '<?= e($item['default_normal']) ?>')">
                                                    <i class="bi bi-pencil-square me-1"></i>Enter Result
                                                </button>
                                            <?php else: ?>
                                                <span class="text-success small fw-semibold"><i class="bi bi-check-circle-fill me-1"></i>Certified</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Modal: New Lab Order -->
<div class="modal fade" id="newLabOrderModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title fw-bold fs-6">Create Diagnostic Lab Order</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="orders.php">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="create_order">

                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Patient *</label>
                        <select name="patient_id" class="form-select" required>
                            <option value="">Select Patient...</option>
                            <?php foreach ($patientsList as $p): ?>
                                <option value="<?= $p['id'] ?>"><?= e($p['first_name'] . ' ' . $p['last_name']) ?> (<?= e($p['patient_id']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Ordering Doctor *</label>
                        <select name="doctor_id" class="form-select" required>
                            <?php foreach ($doctorsList as $d): ?>
                                <option value="<?= $d['id'] ?>">Dr. <?= e($d['first_name'] . ' ' . $d['last_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Priority Level</label>
                        <select name="priority" class="form-select">
                            <option value="routine" selected>Routine Investigation</option>
                            <option value="urgent">Urgent</option>
                            <option value="stat">STAT / Immediate Critical</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Select Tests to Order *</label>
                        <div class="border rounded p-2" style="max-height: 180px; overflow-y: auto;">
                            <?php foreach ($labCatalog as $test): ?>
                                <div class="form-check mb-1">
                                    <input class="form-check-input" type="checkbox" name="test_ids[]" value="<?= $test['id'] ?>" id="test_<?= $test['id'] ?>">
                                    <label class="form-check-label small" for="test_<?= $test['id'] ?>">
                                        <strong><?= e($test['test_name']) ?></strong> — <?= format_currency($test['standard_charge']) ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Clinical Indication / Notes</label>
                        <input type="text" name="clinical_notes" class="form-control" placeholder="e.g. Check for cardiac biomarkers, rule out infection">
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-nexus-primary btn-sm px-4">Dispatch Order</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Enter Test Result -->
<div class="modal fade" id="enterResultModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold fs-6">Enter Diagnostic Result: <span id="resTestName"></span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="orders.php">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="enter_result">
                <input type="hidden" name="item_id" id="resItemId" value="">
                <input type="hidden" name="order_id" id="resOrderId" value="">

                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Observed Quantitative / Qualitative Value *</label>
                        <input type="text" name="result_value" class="form-control" required placeholder="e.g. 14.2 or Negative or Total Chol: 210">
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Reference Range Benchmark</label>
                        <input type="text" name="reference_range" id="resRefRange" class="form-control">
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Laboratory Findings & Interpretation</label>
                        <textarea name="result_notes" class="form-control" rows="2" placeholder="e.g. Within acceptable biological limits; no acute pathology detected."></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-nexus-primary btn-sm px-4">Certify Result</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function openResultModal(itemId, orderId, testName, defaultNormal) {
        document.getElementById('resItemId').value = itemId;
        document.getElementById('resOrderId').value = orderId;
        document.getElementById('resTestName').textContent = testName;
        document.getElementById('resRefRange').value = defaultNormal;
        const modal = new bootstrap.Modal(document.getElementById('enterResultModal'));
        modal.show();
    }
</script>

<?php require_once __DIR__ . '/../../app/views/layouts/footer.php'; ?>
