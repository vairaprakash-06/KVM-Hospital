<?php
/**
 * KVM Hospital Management System — NexusCare
 * API: QueueFlow Live Status Polling Endpoint
 */

declare(strict_types=1);

require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/helpers/functions.php';
require_once __DIR__ . '/../app/services/QueueService.php';

$doctorId = !empty($_GET['doctor_id']) ? (int)$_GET['doctor_id'] : null;
$departmentId = !empty($_GET['department_id']) ? (int)$_GET['department_id'] : null;

$queueService = new QueueService();
$entries = $queueService->getLiveQueue($doctorId, $departmentId);

// Render HTML table snippet for live DOM replacement
ob_start();
?>
<?php if (empty($entries)): ?>
    <tr>
        <td colspan="8" class="text-center py-4 text-muted">
            <i class="bi bi-inbox fs-3 d-block mb-1"></i>
            No active queue tokens for today. Click "Check-In Patient" to generate a token.
        </td>
    </tr>
<?php else: ?>
    <?php foreach ($entries as $q): ?>
        <tr>
            <td>
                <span class="fs-5 fw-bold <?= $q['status'] === 'in_consultation' ? 'text-success' : ($q['status'] === 'called' ? 'text-warning' : 'text-primary') ?>">
                    #<?= e($q['token_number']) ?>
                </span>
            </td>
            <td>
                <strong><?= e($q['patient_first_name'] . ' ' . $q['patient_last_name']) ?></strong>
                <small class="text-muted d-block"><?= e($q['patient_code']) ?> &bull; <?= e($q['patient_age']) ?>y / <?= ucfirst(e($q['gender'])) ?></small>
            </td>
            <td>
                <span class="badge badge-nexus badge-priority-<?= e($q['priority_level']) ?>">
                    <?= strtoupper(e($q['priority_level'])) ?>
                </span>
                <?php if ($q['priority_reason']): ?>
                    <small class="text-danger d-block" style="font-size: 0.7rem;" title="<?= e($q['priority_reason']) ?>">
                        <i class="bi bi-info-circle me-1"></i><?= e(substr($q['priority_reason'], 0, 30)) ?>...
                    </small>
                <?php endif; ?>
            </td>
            <td>
                <strong>Dr. <?= e($q['doctor_first_name'] . ' ' . $q['doctor_last_name']) ?></strong>
                <small class="text-muted d-block"><?= e($q['doctor_room'] ?? 'OPD') ?> &bull; <?= e($q['department_name']) ?></small>
            </td>
            <td>
                <span class="badge badge-nexus badge-status-<?= e($q['status']) ?>">
                    <?= ucwords(str_replace('_', ' ', e($q['status']))) ?>
                </span>
            </td>
            <td>
                <?php if ($q['status'] === 'in_consultation'): ?>
                    <span class="text-success fw-bold"><i class="bi bi-check2-circle me-1"></i>In Room</span>
                <?php elseif ($q['status'] === 'completed'): ?>
                    <span class="text-muted">—</span>
                <?php else: ?>
                    <span class="fw-semibold text-secondary">~<?= (int)$q['estimated_wait_time_minutes'] ?> mins</span>
                <?php endif; ?>
            </td>
            <td>
                <small class="text-muted"><?= format_datetime($q['check_in_time'], 'h:i A') ?></small>
            </td>
            <td class="text-end">
                <?php if (is_logged_in() && has_role('doctor', 'receptionist', 'nurse', 'hospital_admin', 'super_admin')): ?>
                    <div class="btn-group btn-group-sm">
                        <?php if ($q['status'] === 'waiting'): ?>
                            <form method="POST" action="../../modules/queueflow/index.php" class="d-inline">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="call_patient">
                                <input type="hidden" name="queue_entry_id" value="<?= $q['id'] ?>">
                                <input type="hidden" name="doctor_id" value="<?= $q['doctor_id'] ?>">
                                <button type="submit" class="btn btn-outline-warning btn-sm" title="Call Patient">
                                    <i class="bi bi-megaphone"></i> Call
                                </button>
                            </form>
                        <?php elseif ($q['status'] === 'called'): ?>
                            <form method="POST" action="../../modules/queueflow/index.php" class="d-inline">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="start_consultation">
                                <input type="hidden" name="queue_entry_id" value="<?= $q['id'] ?>">
                                <input type="hidden" name="doctor_id" value="<?= $q['doctor_id'] ?>">
                                <button type="submit" class="btn btn-outline-success btn-sm" title="Start Consultation">
                                    <i class="bi bi-play-circle"></i> Start
                                </button>
                            </form>
                        <?php elseif ($q['status'] === 'in_consultation'): ?>
                            <form method="POST" action="../../modules/queueflow/index.php" class="d-inline">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="complete_consultation">
                                <input type="hidden" name="queue_entry_id" value="<?= $q['id'] ?>">
                                <input type="hidden" name="doctor_id" value="<?= $q['doctor_id'] ?>">
                                <button type="submit" class="btn btn-success btn-sm" title="Complete Consultation">
                                    <i class="bi bi-check-circle"></i> Finish
                                </button>
                            </form>
                        <?php endif; ?>

                        <button type="button" class="btn btn-outline-danger btn-sm" 
                                data-bs-toggle="modal" data-bs-target="#priorityModal" 
                                data-qid="<?= $q['id'] ?>" data-token="<?= $q['token_number'] ?>">
                            <i class="bi bi-arrow-up-circle"></i>
                        </button>
                    </div>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
<?php endif; ?>
<?php
$html = ob_get_clean();

json_response([
    'success' => true,
    'count'   => count($entries),
    'html'    => $html
]);
