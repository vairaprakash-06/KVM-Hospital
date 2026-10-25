<?php
/**
 * KVM Hospital Management System — NexusCare
 * QueueFlow — Live Patient Flow Intelligence Service
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/functions.php';
require_once __DIR__ . '/AuditService.php';
require_once __DIR__ . '/CareTrailService.php';

class QueueService {
    private PDO $db;
    private AuditService $auditService;
    private CareTrailService $careTrailService;

    public function __construct(?PDO $db = null) {
        $this->db = $db ?? get_db();
        $this->auditService = new AuditService($this->db);
        $this->careTrailService = new CareTrailService($this->db);
    }

    /**
     * Check in a patient and generate next sequential token for the doctor on current date
     */
    public function checkInPatient(
        int $patientId,
        int $doctorId,
        int $departmentId,
        ?int $appointmentId = null,
        string $priority = 'normal',
        ?string $priorityReason = null,
        ?int $createdBy = null
    ): array {
        $this->db->beginTransaction();

        try {
            $today = date('Y-m-d');

            // 1. Prevent duplicate active queue entry for the same appointment or patient today
            if ($appointmentId) {
                $chk = $this->db->prepare("
                    SELECT id, token_number, status FROM `queue_entries`
                    WHERE `appointment_id` = :apt_id 
                      AND `queue_date` = :qdate
                      AND `status` IN ('waiting', 'called', 'in_consultation')
                    LIMIT 1 FOR UPDATE
                ");
                $chk->execute(['apt_id' => $appointmentId, 'qdate' => $today]);
                $existing = $chk->fetch();
                if ($existing) {
                    $this->db->rollBack();
                    return [
                        'success' => false,
                        'message' => "Patient already has an active queue entry with Token #{$existing['token_number']} (Status: {$existing['status']}).",
                        'queue_entry_id' => (int)$existing['id'],
                        'token_number' => (int)$existing['token_number']
                    ];
                }
            }

            // 2. Calculate next token number atomically using FOR UPDATE
            $tokenStmt = $this->db->prepare("
                SELECT COALESCE(MAX(token_number), 100) AS max_token 
                FROM `queue_entries` 
                WHERE `doctor_id` = :doc_id AND `queue_date` = :qdate
                FOR UPDATE
            ");
            $tokenStmt->execute(['doc_id' => $doctorId, 'qdate' => $today]);
            $nextToken = (int)$tokenStmt->fetchColumn() + 1;

            // 3. Compute initial estimated wait time
            $estimatedWait = $this->calculateWaitTime($doctorId, $nextToken);

            // 4. Insert queue entry
            $insert = $this->db->prepare("
                INSERT INTO `queue_entries` (
                    `token_number`, `patient_id`, `appointment_id`, `doctor_id`,
                    `department_id`, `queue_date`, `check_in_time`, `status`,
                    `priority_level`, `priority_reason`, `estimated_wait_time_minutes`, `created_by`
                ) VALUES (
                    :token_number, :patient_id, :appointment_id, :doctor_id,
                    :department_id, :queue_date, NOW(), 'waiting',
                    :priority_level, :priority_reason, :estimated_wait, :created_by
                )
            ");
            $insert->execute([
                'token_number'    => $nextToken,
                'patient_id'      => $patientId,
                'appointment_id'  => $appointmentId,
                'doctor_id'       => $doctorId,
                'department_id'   => $departmentId,
                'queue_date'      => $today,
                'priority_level'  => $priority,
                'priority_reason' => $priorityReason,
                'estimated_wait'  => $estimatedWait,
                'created_by'      => $createdBy ?? (current_user()['id'] ?? null)
            ]);
            $queueEntryId = (int)$this->db->lastInsertId();

            // 5. Update appointment status if applicable
            if ($appointmentId) {
                $aptStmt = $this->db->prepare("
                    UPDATE `appointments` SET `status` = 'checked_in', `updated_at` = NOW()
                    WHERE `id` = :id
                ");
                $aptStmt->execute(['id' => $appointmentId]);

                $histStmt = $this->db->prepare("
                    INSERT INTO `appointment_status_history` (`appointment_id`, `old_status`, `new_status`, `changed_by`, `change_reason`)
                    VALUES (:id, 'confirmed', 'checked_in', :by, 'Patient arrived and checked in at reception desk')
                ");
                $histStmt->execute(['id' => $appointmentId, 'by' => $createdBy ?? (current_user()['id'] ?? null)]);
            }

            // 6. CareTrail Journey Event
            $this->careTrailService->recordEvent(
                patientId: $patientId,
                eventType: 'check_in',
                eventTitle: "QueueFlow Check-In: Token #{$nextToken}",
                eventDescription: "Patient checked in for consultation. Priority: " . strtoupper($priority) . ". Estimated wait: ~{$estimatedWait} mins.",
                departmentId: $departmentId,
                sourceTable: 'queue_entries',
                sourceId: $queueEntryId,
                severity: $priority === 'emergency' ? 'critical' : ($priority === 'urgent' ? 'warning' : 'info'),
                createdBy: $createdBy
            );

            // 7. TrustLedger Audit Log
            $this->auditService->log(
                actionType: 'CREATE',
                entityType: 'QueueEntry',
                entityId: $queueEntryId,
                description: "Checked in patient #{$patientId}. Issued Token #{$nextToken} for Doctor #{$doctorId}.",
                newValues: ['token' => $nextToken, 'priority' => $priority, 'wait_time' => $estimatedWait]
            );

            $this->db->commit();

            return [
                'success' => true,
                'queue_entry_id' => $queueEntryId,
                'token_number' => $nextToken,
                'estimated_wait_minutes' => $estimatedWait,
                'message' => "Patient checked in successfully. Token #{$nextToken} assigned."
            ];
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Queue check-in failure: " . $e->getMessage());
            return [
                'success' => false,
                'message' => "Queue check-in failed: " . $e->getMessage()
            ];
        }
    }

    /**
     * Calculate dynamic estimated wait time based on actual doctor consultation pace and ahead patients
     */
    public function calculateWaitTime(int $doctorId, int $patientToken): int {
        // Fetch doctor's average consultation duration
        $docStmt = $this->db->prepare("
            SELECT `avg_consultation_time_mins`, `availability_status` 
            FROM `doctor_profiles` WHERE `id` = :id
        ");
        $docStmt->execute(['id' => $doctorId]);
        $doctor = $docStmt->fetch();
        $avgDuration = (int)($doctor['avg_consultation_time_mins'] ?? 15);
        if ($avgDuration <= 0) $avgDuration = 15;

        // If doctor is off duty or on break, add buffer
        $statusBuffer = 0;
        if (($doctor['availability_status'] ?? '') === 'on_break') {
            $statusBuffer = 20;
        }

        // Count patients ahead with higher priority or earlier token
        $today = date('Y-m-d');
        $aheadStmt = $this->db->prepare("
            SELECT COUNT(*) AS patients_ahead FROM `queue_entries`
            WHERE `doctor_id` = :doc_id 
              AND `queue_date` = :qdate
              AND `status` IN ('waiting', 'called')
              AND `token_number` < :curr_token
        ");
        $aheadStmt->execute([
            'doc_id' => $doctorId,
            'qdate'  => $today,
            'curr_token' => $patientToken
        ]);
        $aheadCount = (int)$aheadStmt->fetchColumn();

        return ($aheadCount * $avgDuration) + $statusBuffer;
    }

    /**
     * Doctor calls next patient into consultation room
     */
    public function callPatient(int $queueEntryId, ?int $doctorId = null): bool {
        $sql = "UPDATE `queue_entries` SET `status` = 'called', `updated_at` = NOW() WHERE `id` = :id AND `status` = 'waiting'";
        $params = ['id' => $queueEntryId];
        if (!empty($doctorId)) {
            $sql .= " AND `doctor_id` = :doc_id";
            $params['doc_id'] = $doctorId;
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        if ($stmt->rowCount() > 0) {
            $this->auditService->log('STATUS_CHANGE', 'QueueEntry', $queueEntryId, "Staff/Doctor called token for entry #{$queueEntryId}");
            return true;
        }
        return false;
    }

    /**
     * Start consultation
     */
    public function startConsultation(int $queueEntryId, ?int $doctorId = null): bool {
        $this->db->beginTransaction();
        try {
            // Update queue entry
            $sql = "UPDATE `queue_entries`
                    SET `status` = 'in_consultation',
                        `consultation_start_time` = NOW(),
                        `estimated_wait_time_minutes` = 0,
                        `updated_at` = NOW()
                    WHERE `id` = :id";
            $params = ['id' => $queueEntryId];
            if (!empty($doctorId)) {
                $sql .= " AND `doctor_id` = :doc_id";
                $params['doc_id'] = $doctorId;
            }
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);

            // Retrieve details for CareTrail
            $fetch = $this->db->prepare("
                SELECT patient_id, appointment_id, department_id, token_number 
                FROM `queue_entries` WHERE `id` = :id
            ");
            $fetch->execute(['id' => $queueEntryId]);
            $entry = $fetch->fetch();

            if ($entry) {
                // If appointment exists, update it to in_consultation
                if (!empty($entry['appointment_id'])) {
                    $this->db->prepare("
                        UPDATE `appointments` SET `status` = 'in_consultation' WHERE `id` = :id
                    ")->execute(['id' => $entry['appointment_id']]);
                }

                $this->careTrailService->recordEvent(
                    patientId: (int)$entry['patient_id'],
                    eventType: 'consultation_started',
                    eventTitle: "Consultation Began: Token #{$entry['token_number']}",
                    eventDescription: "Patient seated with doctor in consultation room.",
                    departmentId: (int)$entry['department_id'],
                    sourceTable: 'queue_entries',
                    sourceId: $queueEntryId
                );
            }

            $this->auditService->log('STATUS_CHANGE', 'QueueEntry', $queueEntryId, "Started consultation for Token #" . ($entry['token_number'] ?? $queueEntryId));
            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Failed to start consultation: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Complete consultation and update metrics
     */
    public function completeConsultation(int $queueEntryId, ?int $doctorId = null): bool {
        $this->db->beginTransaction();
        try {
            $sql = "UPDATE `queue_entries`
                    SET `status` = 'completed',
                        `completion_time` = NOW(),
                        `updated_at` = NOW()
                    WHERE `id` = :id";
            $params = ['id' => $queueEntryId];
            if (!empty($doctorId)) {
                $sql .= " AND `doctor_id` = :doc_id";
                $params['doc_id'] = $doctorId;
            }
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);

            $fetch = $this->db->prepare("
                SELECT patient_id, appointment_id, department_id, token_number, consultation_start_time 
                FROM `queue_entries` WHERE `id` = :id
            ");
            $fetch->execute(['id' => $queueEntryId]);
            $entry = $fetch->fetch();

            if ($entry) {
                if (!empty($entry['appointment_id'])) {
                    $this->db->prepare("
                        UPDATE `appointments` SET `status` = 'completed' WHERE `id` = :id
                    ")->execute(['id' => $entry['appointment_id']]);
                }

                $this->careTrailService->recordEvent(
                    patientId: (int)$entry['patient_id'],
                    eventType: 'consultation_completed',
                    eventTitle: "Consultation Completed: Token #{$entry['token_number']}",
                    eventDescription: "Consultation finished. Prescriptions, orders, or follow-up documented.",
                    departmentId: (int)$entry['department_id'],
                    sourceTable: 'queue_entries',
                    sourceId: $queueEntryId,
                    severity: 'success'
                );
            }

            $this->auditService->log('STATUS_CHANGE', 'QueueEntry', $queueEntryId, "Completed consultation for Token #" . ($entry['token_number'] ?? $queueEntryId));
            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Failed to complete consultation: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Cancel patient from queue
     */
    public function cancelPatient(int $queueEntryId, ?int $doctorId = null, ?int $cancelledBy = null, ?string $reason = null): bool {
        $this->db->beginTransaction();
        try {
            $sql = "UPDATE `queue_entries`
                    SET `status` = 'cancelled',
                        `updated_at` = NOW()
                    WHERE `id` = :id";
            $params = ['id' => $queueEntryId];
            if (!empty($doctorId)) {
                $sql .= " AND `doctor_id` = :doc_id";
                $params['doc_id'] = $doctorId;
            }
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);

            $fetch = $this->db->prepare("
                SELECT patient_id, appointment_id, department_id, token_number 
                FROM `queue_entries` WHERE `id` = :id
            ");
            $fetch->execute(['id' => $queueEntryId]);
            $entry = $fetch->fetch();

            if ($entry) {
                if (!empty($entry['appointment_id'])) {
                    $this->db->prepare("
                        UPDATE `appointments` SET `status` = 'cancelled' WHERE `id` = :id
                    ")->execute(['id' => $entry['appointment_id']]);
                }

                $this->careTrailService->recordEvent(
                    patientId: (int)$entry['patient_id'],
                    eventType: 'consultation_cancelled',
                    eventTitle: "Queue Token Cancelled: Token #{$entry['token_number']}",
                    eventDescription: "Patient consultation cancelled. Reason: " . ($reason ?: 'Cancelled by hospital staff'),
                    departmentId: (int)$entry['department_id'],
                    sourceTable: 'queue_entries',
                    sourceId: $queueEntryId,
                    severity: 'warning'
                );
            }

            $this->auditService->log(
                'STATUS_CHANGE',
                'QueueEntry',
                $queueEntryId,
                "Cancelled Token #" . ($entry['token_number'] ?? $queueEntryId) . ". Reason: " . ($reason ?: 'None stated'),
                userId: $cancelledBy
            );
            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Failed to cancel queue token: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update Queue Priority with documented justification (TrustLedger auditable override)
     */
    public function updatePriority(int $queueEntryId, string $newPriority, string $reason, int $changedBy): bool {
        $this->db->beginTransaction();
        try {
            $sel = $this->db->prepare("SELECT priority_level, patient_id FROM `queue_entries` WHERE id = :id FOR UPDATE");
            $sel->execute(['id' => $queueEntryId]);
            $row = $sel->fetch();
            if (!$row) {
                $this->db->rollBack();
                return false;
            }

            $oldPriority = $row['priority_level'];

            // Update queue entry
            $upd = $this->db->prepare("
                UPDATE `queue_entries` 
                SET `priority_level` = :priority,
                    `priority_reason` = :reason,
                    `updated_at` = NOW()
                WHERE `id` = :id
            ");
            $upd->execute([
                'priority' => $newPriority,
                'reason'   => $reason,
                'id'       => $queueEntryId
            ]);

            // Record priority change event
            $pe = $this->db->prepare("
                INSERT INTO `queue_priority_events` (`queue_entry_id`, `old_priority`, `new_priority`, `changed_by`, `reason`)
                VALUES (:qid, :old_p, :new_p, :by, :reason)
            ");
            $pe->execute([
                'qid'    => $queueEntryId,
                'old_p'  => $oldPriority,
                'new_p'  => $newPriority,
                'by'     => $changedBy,
                'reason' => $reason
            ]);

            // TrustLedger Audit
            $this->auditService->log(
                actionType: 'OVERRIDE',
                entityType: 'QueuePriority',
                entityId: $queueEntryId,
                description: "Overrode queue priority from {$oldPriority} to {$newPriority}. Reason: {$reason}",
                oldValues: ['priority' => $oldPriority],
                newValues: ['priority' => $newPriority, 'reason' => $reason],
                userId: $changedBy
            );

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Failed to update queue priority: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Retrieve live queue with department, doctor, priority, status, and text search filters.
     * Ordering rule:
     * 1. in_consultation (top, actively with doctor in room)
     * 2. called (second, on way to room)
     * 3. waiting (third, prioritized by triage urgency: emergency -> urgent -> normal; sub-sorted by check_in_time ASC, then token_number ASC)
     * 4. completed (fourth)
     * 5. cancelled (last)
     */
    public function getLiveQueue(
        ?int $doctorId = null,
        ?int $departmentId = null,
        ?string $date = null,
        ?string $priority = null,
        ?string $status = null,
        ?string $search = null
    ): array {
        $qdate = $date ?? date('Y-m-d');
        $sql = "
            SELECT 
                qe.*,
                p.patient_id AS patient_code,
                p.first_name AS patient_first_name,
                p.last_name AS patient_last_name,
                p.gender,
                p.dob,
                TIMESTAMPDIFF(YEAR, p.dob, CURDATE()) AS patient_age,
                d.doctor_code,
                d.first_name AS doctor_first_name,
                d.last_name AS doctor_last_name,
                d.room_no AS doctor_room,
                dep.name AS department_name
            FROM `queue_entries` qe
            JOIN `patient_profiles` p ON qe.patient_id = p.id
            JOIN `doctor_profiles` d ON qe.doctor_id = d.id
            JOIN `departments` dep ON qe.department_id = dep.id
            WHERE qe.queue_date = :qdate
        ";
        $params = ['qdate' => $qdate];

        if (!empty($doctorId)) {
            $sql .= " AND qe.doctor_id = :doc_id";
            $params['doc_id'] = $doctorId;
        }
        if (!empty($departmentId)) {
            $sql .= " AND qe.department_id = :dept_id";
            $params['dept_id'] = $departmentId;
        }
        if (!empty($priority)) {
            $sql .= " AND qe.priority_level = :priority";
            $params['priority'] = strtolower(trim($priority));
        }
        if (!empty($status)) {
            $sql .= " AND qe.status = :status";
            $params['status'] = strtolower(trim($status));
        }
        if (!empty($search)) {
            $searchTrim = trim($search);
            $searchLike = '%' . $searchTrim . '%';
            $sql .= " AND (
                p.first_name LIKE :s1 
                OR p.last_name LIKE :s2 
                OR p.patient_id LIKE :s3 
                OR CONCAT(p.first_name, ' ', p.last_name) LIKE :s4 
                OR qe.token_number LIKE :s5
            )";
            $params['s1'] = $searchLike;
            $params['s2'] = $searchLike;
            $params['s3'] = $searchLike;
            $params['s4'] = $searchLike;
            $params['s5'] = $searchLike;
        }

        $sql .= "
            ORDER BY 
              CASE qe.status
                WHEN 'in_consultation' THEN 1
                WHEN 'called' THEN 2
                WHEN 'waiting' THEN 3
                WHEN 'completed' THEN 4
                WHEN 'cancelled' THEN 5
                ELSE 6
              END ASC,
              CASE 
                WHEN qe.status = 'waiting' THEN
                  CASE qe.priority_level
                    WHEN 'emergency' THEN 1
                    WHEN 'urgent' THEN 2
                    ELSE 3
                  END
                ELSE 0
              END ASC,
              CASE 
                WHEN qe.status = 'waiting' THEN qe.check_in_time
                ELSE '9999-12-31'
              END ASC,
              qe.token_number ASC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}
