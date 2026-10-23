<?php
/**
 * KVM Hospital Management System — NexusCare
 * CareTrail — Unified Patient Journey Timeline Service
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/functions.php';

class CareTrailService {
    private PDO $db;

    public function __construct(?PDO $db = null) {
        $this->db = $db ?? get_db();
    }

    /**
     * Record a new CareTrail journey event
     */
    public function recordEvent(
        int $patientId,
        string $eventType,
        string $eventTitle,
        string $eventDescription,
        ?int $encounterId = null,
        ?int $departmentId = null,
        ?string $sourceTable = null,
        ?int $sourceId = null,
        string $severity = 'info',
        ?int $createdBy = null
    ): int {
        try {
            $currentUser = current_user();
            $effectiveCreator = $createdBy ?? ($currentUser['id'] ?? null);

            $stmt = $this->db->prepare("
                INSERT INTO `patient_journey_events` (
                    `patient_id`, `encounter_id`, `event_type`, `event_title`,
                    `event_description`, `department_id`, `source_table`, `source_id`,
                    `severity`, `created_by`, `event_time`
                ) VALUES (
                    :patient_id, :encounter_id, :event_type, :event_title,
                    :event_description, :department_id, :source_table, :source_id,
                    :severity, :created_by, NOW()
                )
            ");

            $stmt->execute([
                'patient_id'        => $patientId,
                'encounter_id'      => $encounterId,
                'event_type'        => $eventType,
                'event_title'       => $eventTitle,
                'event_description' => $eventDescription,
                'department_id'     => $departmentId,
                'source_table'      => $sourceTable,
                'source_id'         => $sourceId,
                'severity'          => $severity,
                'created_by'        => $effectiveCreator
            ]);

            return (int)$this->db->lastInsertId();
        } catch (Exception $e) {
            error_log("CareTrail event recording failure: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Fetch the chronological journey timeline for a patient
     */
    public function getPatientTimeline(int $patientId, array $filters = []): array {
        $sql = "
            SELECT 
                pje.*,
                d.name AS department_name,
                d.dept_code,
                u.username AS actor_username
            FROM `patient_journey_events` pje
            LEFT JOIN `departments` d ON pje.department_id = d.id
            LEFT JOIN `users` u ON pje.created_by = u.id
            WHERE pje.patient_id = :patient_id
        ";
        $params = ['patient_id' => $patientId];

        if (!empty($filters['encounter_id'])) {
            $sql .= " AND pje.encounter_id = :encounter_id";
            $params['encounter_id'] = $filters['encounter_id'];
        }
        if (!empty($filters['event_type'])) {
            $sql .= " AND pje.event_type = :event_type";
            $params['event_type'] = $filters['event_type'];
        }
        if (!empty($filters['department_id'])) {
            $sql .= " AND pje.department_id = :department_id";
            $params['department_id'] = $filters['department_id'];
        }
        if (!empty($filters['start_date'])) {
            $sql .= " AND DATE(pje.event_time) >= :start_date";
            $params['start_date'] = $filters['start_date'];
        }
        if (!empty($filters['end_date'])) {
            $sql .= " AND DATE(pje.event_time) <= :end_date";
            $params['end_date'] = $filters['end_date'];
        }

        $sql .= " ORDER BY pje.event_time DESC, pje.id DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Get distinct event types present for a patient
     */
    public function getDistinctEventTypes(int $patientId): array {
        $stmt = $this->db->prepare("
            SELECT DISTINCT `event_type` 
            FROM `patient_journey_events` 
            WHERE `patient_id` = :patient_id
            ORDER BY `event_type` ASC
        ");
        $stmt->execute(['patient_id' => $patientId]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
}
