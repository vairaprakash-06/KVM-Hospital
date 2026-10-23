<?php
/**
 * KVM Hospital Management System — NexusCare
 * BedMatrix — Dynamic Bed and Resource Allocation Service
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/functions.php';
require_once __DIR__ . '/AuditService.php';
require_once __DIR__ . '/CareTrailService.php';

class BedAllocationService {
    private PDO $db;
    private AuditService $auditService;
    private CareTrailService $careTrailService;

    public function __construct(?PDO $db = null) {
        $this->db = $db ?? get_db();
        $this->auditService = new AuditService($this->db);
        $this->careTrailService = new CareTrailService($this->db);
    }

    /**
     * Retrieve complete ward-room-bed occupancy matrix
     */
    public function getOccupancyMatrix(?int $wardId = null): array {
        $sql = "
            SELECT 
                b.id AS bed_id,
                b.bed_number,
                b.bed_type,
                b.care_level,
                b.status AS bed_status,
                b.daily_rate,
                r.id AS room_id,
                r.room_number,
                r.room_type,
                r.standard_rate_per_day,
                w.id AS ward_id,
                w.name AS ward_name,
                w.ward_code,
                w.ward_type,
                w.gender_designation,
                ba.id AS active_assignment_id,
                ba.patient_id,
                ba.admission_date,
                ba.clinical_reason,
                p.patient_id AS patient_code,
                CONCAT(p.first_name, ' ', p.last_name) AS patient_name,
                p.gender AS patient_gender,
                (SELECT GROUP_CONCAT(resource_name SEPARATOR ', ') FROM `bed_resources` WHERE bed_id = b.id AND status = 'operational') AS attached_resources
            FROM `beds` b
            JOIN `rooms` r ON b.room_id = r.id
            JOIN `wards` w ON b.ward_id = w.id
            LEFT JOIN `bed_assignments` ba ON b.id = ba.bed_id AND ba.status = 'active'
            LEFT JOIN `patient_profiles` p ON ba.patient_id = p.id
            WHERE 1=1
        ";
        $params = [];

        if ($wardId) {
            $sql .= " AND w.id = :ward_id";
            $params['ward_id'] = $wardId;
        }

        $sql .= " ORDER BY w.id ASC, r.room_number ASC, b.bed_number ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Find eligible beds based on patient care level, equipment need, and ward gender
     */
    public function findEligibleBeds(string $requiredCareLevel, ?string $patientGender = null, bool $needsVentilator = false): array {
        $sql = "
            SELECT 
                b.*,
                r.room_number,
                r.room_type,
                w.name AS ward_name,
                w.ward_code,
                w.gender_designation,
                (SELECT GROUP_CONCAT(resource_name SEPARATOR ', ') FROM `bed_resources` WHERE bed_id = b.id AND status = 'operational') AS attached_resources
            FROM `beds` b
            JOIN `rooms` r ON b.room_id = r.id
            JOIN `wards` w ON b.ward_id = w.id
            WHERE b.status = 'available'
        ";
        $params = [];

        // Care level matching
        if ($requiredCareLevel === 'critical') {
            $sql .= " AND b.care_level = 'critical'";
        } elseif ($requiredCareLevel === 'intermediate') {
            $sql .= " AND b.care_level IN ('intermediate', 'critical')";
        }

        // Gender designation matching
        if ($patientGender === 'male') {
            $sql .= " AND w.gender_designation IN ('all', 'male_only')";
        } elseif ($patientGender === 'female') {
            $sql .= " AND w.gender_designation IN ('all', 'female_only')";
        }

        // Ventilator matching
        if ($needsVentilator) {
            $sql .= " AND (b.bed_type = 'ventilator_enabled' OR EXISTS(SELECT 1 FROM bed_resources br WHERE br.bed_id = b.id AND br.resource_type = 'Ventilator' AND br.status = 'operational'))";
        }

        $sql .= " ORDER BY b.daily_rate ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Allocate Bed inside a transaction with lock verification to prevent race conditions
     */
    public function assignBed(
        int $bedId,
        int $patientId,
        int $encounterId,
        int $assignedBy,
        ?string $clinicalReason = null
    ): array {
        $this->db->beginTransaction();

        try {
            // 1. Lock bed record and check current status
            $chkBed = $this->db->prepare("SELECT id, bed_number, status, ward_id FROM `beds` WHERE `id` = :id FOR UPDATE");
            $chkBed->execute(['id' => $bedId]);
            $bed = $chkBed->fetch();

            if (!$bed) {
                $this->db->rollBack();
                return ['success' => false, 'message' => "Requested bed ID #{$bedId} not found in database."];
            }

            if ($bed['status'] !== 'available') {
                $this->db->rollBack();
                return ['success' => false, 'message' => "Bed {$bed['bed_number']} is currently {$bed['status']}. Cannot assign an unavailable bed."];
            }

            // 2. Check if patient already has an active bed assignment
            $chkPat = $this->db->prepare("
                SELECT ba.id, b.bed_number 
                FROM `bed_assignments` ba
                JOIN `beds` b ON ba.bed_id = b.id
                WHERE ba.patient_id = :pid AND ba.status = 'active'
                FOR UPDATE
            ");
            $chkPat->execute(['pid' => $patientId]);
            $existingActive = $chkPat->fetch();

            if ($existingActive) {
                $this->db->rollBack();
                return [
                    'success' => false,
                    'message' => "Patient already holds an active bed assignment on Bed {$existingActive['bed_number']}. Please transfer or discharge the patient first."
                ];
            }

            // 3. Insert assignment
            $insAssign = $this->db->prepare("
                INSERT INTO `bed_assignments` (
                    `bed_id`, `patient_id`, `encounter_id`, `admission_date`,
                    `status`, `assigned_by`, `clinical_reason`
                ) VALUES (
                    :bed_id, :patient_id, :encounter_id, NOW(),
                    'active', :assigned_by, :clinical_reason
                )
            ");
            $insAssign->execute([
                'bed_id'          => $bedId,
                'patient_id'      => $patientId,
                'encounter_id'    => $encounterId,
                'assigned_by'     => $assignedBy,
                'clinical_reason' => $clinicalReason
            ]);
            $assignmentId = (int)$this->db->lastInsertId();

            // 4. Update bed status to 'occupied'
            $this->db->prepare("UPDATE `beds` SET `status` = 'occupied' WHERE `id` = :id")->execute(['id' => $bedId]);

            // 5. Record Bed Status History
            $this->db->prepare("
                INSERT INTO `bed_status_history` (`bed_id`, `old_status`, `new_status`, `changed_by`, `notes`)
                VALUES (:bed_id, 'available', 'occupied', :by, :notes)
            ")->execute([
                'bed_id' => $bedId,
                'by'     => $assignedBy,
                'notes'  => "Assigned to patient #{$patientId} (Encounter #{$encounterId})"
            ]);

            // 6. CareTrail Timeline
            $this->careTrailService->recordEvent(
                patientId: $patientId,
                eventType: 'bed_assigned',
                eventTitle: "BedMatrix Allocation: {$bed['bed_number']}",
                eventDescription: "Patient assigned to {$bed['bed_number']}. Clinical reason: " . ($clinicalReason ?: 'Routine admission'),
                encounterId: $encounterId,
                departmentId: (int)$bed['ward_id'],
                sourceTable: 'bed_assignments',
                sourceId: $assignmentId,
                severity: 'info',
                createdBy: $assignedBy
            );

            // 7. TrustLedger Audit
            $this->auditService->log(
                actionType: 'CREATE',
                entityType: 'BedAssignment',
                entityId: $assignmentId,
                description: "BedMatrix: Assigned Bed {$bed['bed_number']} to Patient #{$patientId}",
                newValues: ['bed' => $bed['bed_number'], 'patient' => $patientId, 'encounter' => $encounterId],
                userId: $assignedBy
            );

            $this->db->commit();

            return [
                'success' => true,
                'assignment_id' => $assignmentId,
                'bed_number' => $bed['bed_number'],
                'message' => "Bed {$bed['bed_number']} allocated successfully."
            ];
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Bed allocation error: " . $e->getMessage());
            return ['success' => false, 'message' => "Bed allocation failed: " . $e->getMessage()];
        }
    }

    /**
     * Discharge patient from bed and transition bed status to 'cleaning'
     */
    public function dischargeBed(int $assignmentId, int $userId, ?string $notes = null): array {
        $this->db->beginTransaction();

        try {
            $sel = $this->db->prepare("
                SELECT ba.*, b.bed_number, b.id AS bed_id 
                FROM `bed_assignments` ba
                JOIN `beds` b ON ba.bed_id = b.id
                WHERE ba.id = :id AND ba.status = 'active'
                FOR UPDATE
            ");
            $sel->execute(['id' => $assignmentId]);
            $assign = $sel->fetch();

            if (!$assign) {
                $this->db->rollBack();
                return ['success' => false, 'message' => "Active bed assignment #{$assignmentId} not found."];
            }

            $bedId = (int)$assign['bed_id'];

            // 1. Mark assignment discharged
            $updAssign = $this->db->prepare("
                UPDATE `bed_assignments` 
                SET `discharge_date` = NOW(), `status` = 'discharged' 
                WHERE `id` = :id
            ");
            $updAssign->execute(['id' => $assignmentId]);

            // 2. Move bed status to 'cleaning'
            $this->db->prepare("UPDATE `beds` SET `status` = 'cleaning' WHERE `id` = :id")->execute(['id' => $bedId]);

            // 3. Bed status history
            $this->db->prepare("
                INSERT INTO `bed_status_history` (`bed_id`, `old_status`, `new_status`, `changed_by`, `notes`)
                VALUES (:bed_id, 'occupied', 'cleaning', :by, :notes)
            ")->execute([
                'bed_id' => $bedId,
                'by'     => $userId,
                'notes'  => "Patient discharged. Bed transitioned to sanitization & cleaning queue."
            ]);

            // 4. CareTrail Event
            $this->careTrailService->recordEvent(
                patientId: (int)$assign['patient_id'],
                eventType: 'discharged',
                eventTitle: "Discharged from Bed {$assign['bed_number']}",
                eventDescription: "Patient cleared and discharged from inpatient bed. Discharge notes: " . ($notes ?: 'Discharge criteria met'),
                encounterId: (int)$assign['encounter_id'],
                sourceTable: 'bed_assignments',
                sourceId: $assignmentId,
                severity: 'success',
                createdBy: $userId
            );

            // 5. TrustLedger Audit
            $this->auditService->log(
                actionType: 'STATUS_CHANGE',
                entityType: 'BedAssignment',
                entityId: $assignmentId,
                description: "Discharged patient from Bed {$assign['bed_number']}. Bed set to cleaning.",
                userId: $userId
            );

            $this->db->commit();
            return ['success' => true, 'message' => "Patient discharged from Bed {$assign['bed_number']}. Bed is now in Cleaning queue."];
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Discharge bed failed: " . $e->getMessage());
            return ['success' => false, 'message' => "Discharge failed: " . $e->getMessage()];
        }
    }

    /**
     * Mark a cleaned bed as 'available'
     */
    public function markBedCleaned(int $bedId, int $userId): array {
        $this->db->beginTransaction();
        try {
            $sel = $this->db->prepare("SELECT bed_number, status FROM `beds` WHERE `id` = :id FOR UPDATE");
            $sel->execute(['id' => $bedId]);
            $bed = $sel->fetch();

            if (!$bed || $bed['status'] !== 'cleaning') {
                $this->db->rollBack();
                return ['success' => false, 'message' => "Bed is not in 'cleaning' status."];
            }

            $this->db->prepare("UPDATE `beds` SET `status` = 'available' WHERE `id` = :id")->execute(['id' => $bedId]);

            $this->db->prepare("
                INSERT INTO `bed_status_history` (`bed_id`, `old_status`, `new_status`, `changed_by`, `notes`)
                VALUES (:bed_id, 'cleaning', 'available', :by, 'Sanitization and terminal cleaning certified complete.')
            ")->execute(['bed_id' => $bedId, 'by' => $userId]);

            $this->auditService->log('STATUS_CHANGE', 'Bed', $bedId, "Marked Bed {$bed['bed_number']} as Cleaned & Available", userId: $userId);

            $this->db->commit();
            return ['success' => true, 'message' => "Bed {$bed['bed_number']} is sanitized and now AVAILABLE."];
        } catch (Exception $e) {
            $this->db->rollBack();
            return ['success' => false, 'message' => "Failed to update bed status: " . $e->getMessage()];
        }
    }

    /**
     * Transfer patient to another bed
     */
    public function transferBed(int $currentAssignmentId, int $newBedId, int $userId, string $reason): array {
        $this->db->beginTransaction();
        try {
            // Lock current assignment
            $curStmt = $this->db->prepare("
                SELECT ba.*, b.bed_number AS old_bed_number 
                FROM `bed_assignments` ba
                JOIN `beds` b ON ba.bed_id = b.id
                WHERE ba.id = :id AND ba.status = 'active'
                FOR UPDATE
            ");
            $curStmt->execute(['id' => $currentAssignmentId]);
            $current = $curStmt->fetch();
            if (!$current) {
                $this->db->rollBack();
                return ['success' => false, 'message' => "Active assignment not found."];
            }

            // Lock target new bed
            $newStmt = $this->db->prepare("SELECT * FROM `beds` WHERE id = :id FOR UPDATE");
            $newStmt->execute(['id' => $newBedId]);
            $newBed = $newStmt->fetch();
            if (!$newBed || $newBed['status'] !== 'available') {
                $this->db->rollBack();
                return ['success' => false, 'message' => "Target bed is not available."];
            }

            $oldBedId = (int)$current['bed_id'];

            // 1. Mark old assignment transferred
            $this->db->prepare("
                UPDATE `bed_assignments` SET `status` = 'transferred', `discharge_date` = NOW() WHERE `id` = :id
            ")->execute(['id' => $currentAssignmentId]);

            // 2. Set old bed to cleaning
            $this->db->prepare("UPDATE `beds` SET `status` = 'cleaning' WHERE id = :id")->execute(['id' => $oldBedId]);

            // 3. Create new assignment
            $insNew = $this->db->prepare("
                INSERT INTO `bed_assignments` (`bed_id`, `patient_id`, `encounter_id`, `admission_date`, `status`, `assigned_by`, `clinical_reason`)
                VALUES (:bed_id, :pid, :enc, NOW(), 'active', :by, :reason)
            ");
            $insNew->execute([
                'bed_id'  => $newBedId,
                'pid'     => $current['patient_id'],
                'enc'     => $current['encounter_id'],
                'by'      => $userId,
                'reason'  => "Transferred from {$current['old_bed_number']}. Reason: {$reason}"
            ]);
            $newAssignId = (int)$this->db->lastInsertId();

            // 4. Set new bed to occupied
            $this->db->prepare("UPDATE `beds` SET `status` = 'occupied' WHERE id = :id")->execute(['id' => $newBedId]);

            // 5. CareTrail
            $this->careTrailService->recordEvent(
                patientId: (int)$current['patient_id'],
                eventType: 'bed_transferred',
                eventTitle: "Bed Transfer: {$current['old_bed_number']} ➔ {$newBed['bed_number']}",
                eventDescription: "Patient transferred between beds. Clinical reason: {$reason}",
                encounterId: (int)$current['encounter_id'],
                sourceTable: 'bed_assignments',
                sourceId: $newAssignId,
                severity: 'info',
                createdBy: $userId
            );

            // 6. TrustLedger
            $this->auditService->log(
                actionType: 'STATUS_CHANGE',
                entityType: 'BedAssignment',
                entityId: $newAssignId,
                description: "Transferred Patient from Bed {$current['old_bed_number']} to {$newBed['bed_number']}. Reason: {$reason}",
                userId: $userId
            );

            $this->db->commit();
            return ['success' => true, 'message' => "Patient transferred successfully to Bed {$newBed['bed_number']}."];
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Bed transfer failed: " . $e->getMessage());
            return ['success' => false, 'message' => "Transfer failed: " . $e->getMessage()];
        }
    }

    /**
     * Bed Statistics Summary
     */
    public function getBedStats(): array {
        $stmt = $this->db->query("
            SELECT 
                COUNT(*) AS total_beds,
                SUM(CASE WHEN `status` = 'available' THEN 1 ELSE 0 END) AS available_beds,
                SUM(CASE WHEN `status` = 'occupied' THEN 1 ELSE 0 END) AS occupied_beds,
                SUM(CASE WHEN `status` = 'cleaning' THEN 1 ELSE 0 END) AS cleaning_beds,
                SUM(CASE WHEN `status` = 'reserved' THEN 1 ELSE 0 END) AS reserved_beds,
                SUM(CASE WHEN `status` = 'out_of_service' THEN 1 ELSE 0 END) AS out_of_service_beds,
                ROUND((SUM(CASE WHEN `status` = 'occupied' THEN 1 ELSE 0 END) / COUNT(*)) * 100, 1) AS occupancy_rate
            FROM `beds`
        ");
        return $stmt->fetch() ?: [];
    }
}
