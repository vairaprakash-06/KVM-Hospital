<?php
/**
 * KVM Hospital Management System — NexusCare
 * FlowLens — Hospital Bottleneck Detection Service
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/functions.php';
require_once __DIR__ . '/AuditService.php';

class FlowLensService {
    private PDO $db;
    private AuditService $auditService;

    public function __construct(?PDO $db = null) {
        $this->db = $db ?? get_db();
        $this->auditService = new AuditService($this->db);
    }

    /**
     * Run explainable rule-based bottleneck evaluations on real operational data
     */
    public function evaluateBottlenecks(): array {
        $alertsGenerated = [];

        // -------------------------------------------------------------
        // Rule 1: High Bed Occupancy in Wards (Threshold: >= 80%)
        // -------------------------------------------------------------
        $wardStmt = $this->db->query("
            SELECT 
                w.id, w.name, w.ward_code,
                COUNT(b.id) AS total_beds,
                SUM(CASE WHEN b.status = 'occupied' THEN 1 ELSE 0 END) AS occupied_beds,
                ROUND((SUM(CASE WHEN b.status = 'occupied' THEN 1 ELSE 0 END) / COUNT(b.id)) * 100, 2) AS occupancy_pct
            FROM `wards` w
            JOIN `beds` b ON w.id = b.ward_id
            GROUP BY w.id, w.name, w.ward_code
            HAVING occupancy_pct >= 80.0
        ");
        $criticalWards = $wardStmt->fetchAll();

        foreach ($criticalWards as $ward) {
            $code = "FL-BED-" . $ward['ward_code'] . "-" . date('Ymd');
            $this->createAlertIfNotExists(
                alertCode: $code,
                alertType: 'bed_occupancy_critical',
                deptId: null,
                severity: $ward['occupancy_pct'] >= 90 ? 'critical' : 'high',
                metric: 'Bed Occupancy Percentage',
                observed: (float)$ward['occupancy_pct'],
                threshold: 80.0,
                title: "{$ward['name']} Critical Capacity Saturation",
                description: "Ward occupancy is currently {$ward['occupancy_pct']}% with {$ward['occupied_beds']} of {$ward['total_beds']} beds occupied.",
                action: "Review medically stable patients for step-down transfer or discharge to free critical beds."
            );
            $alertsGenerated[] = $code;
        }

        // -------------------------------------------------------------
        // Rule 2: Queue Wait Time Bottleneck (Threshold: >= 25 mins)
        // -------------------------------------------------------------
        $today = date('Y-m-d');
        $queueStmt = $this->db->prepare("
            SELECT 
                d.id AS department_id,
                d.name AS department_name,
                COUNT(qe.id) AS waiting_count,
                ROUND(AVG(TIMESTAMPDIFF(MINUTE, qe.check_in_time, NOW())), 1) AS avg_current_wait_mins
            FROM `queue_entries` qe
            JOIN `departments` d ON qe.department_id = d.id
            WHERE qe.queue_date = :qdate AND qe.status = 'waiting'
            GROUP BY d.id, d.name
            HAVING avg_current_wait_mins >= 25.0
        ");
        $queueStmt->execute(['qdate' => $today]);
        $delayedDepts = $queueStmt->fetchAll();

        foreach ($delayedDepts as $dept) {
            $code = "FL-QWAIT-DEP{$dept['department_id']}-" . date('Ymd');
            $this->createAlertIfNotExists(
                alertCode: $code,
                alertType: 'queue_wait_time',
                deptId: (int)$dept['department_id'],
                severity: $dept['avg_current_wait_mins'] >= 40 ? 'critical' : 'high',
                metric: 'Average Patient Queue Waiting Time',
                observed: (float)$dept['avg_current_wait_mins'],
                threshold: 25.0,
                title: "{$dept['department_name']} Consultation Waiting Time Delay",
                description: "Average wait time has reached {$dept['avg_current_wait_mins']} minutes for {$dept['waiting_count']} waiting patients.",
                action: "Deploy secondary physician or open fast-track consultation cubicle."
            );
            $alertsGenerated[] = $code;
        }

        // -------------------------------------------------------------
        // Rule 3: Diagnostic Lab Order Delays (Threshold: >= 60 mins pending/processing)
        // -------------------------------------------------------------
        $labStmt = $this->db->query("
            SELECT 
                COUNT(*) AS delayed_orders,
                ROUND(AVG(TIMESTAMPDIFF(MINUTE, created_at, NOW())), 1) AS avg_delay_mins
            FROM `lab_orders`
            WHERE `status` IN ('ordered', 'processing')
              AND `created_at` <= NOW() - INTERVAL 60 MINUTE
        ");
        $labDelay = $labStmt->fetch();

        if ($labDelay && (int)$labDelay['delayed_orders'] > 0) {
            $code = "FL-LAB-DELAY-" . date('Ymd');
            $this->createAlertIfNotExists(
                alertCode: $code,
                alertType: 'lab_delay',
                deptId: 14, // Laboratory Department ID
                severity: (float)$labDelay['avg_delay_mins'] >= 90 ? 'high' : 'medium',
                metric: 'Lab Investigation Turnaround Time',
                observed: (float)$labDelay['avg_delay_mins'],
                threshold: 60.0,
                title: "Diagnostic Lab Specimen Processing Backlog",
                description: "There are {$labDelay['delayed_orders']} laboratory orders pending longer than 60 minutes (Average wait: {$labDelay['avg_delay_mins']} mins).",
                action: "Reassign analyzer workloads, expedite priority STAT specimens, and review reagent levels."
            );
            $alertsGenerated[] = $code;
        }

        return $alertsGenerated;
    }

    /**
     * Create FlowLens alert only if not already actively open today
     */
    private function createAlertIfNotExists(
        string $alertCode,
        string $alertType,
        ?int $deptId,
        string $severity,
        string $metric,
        float $observed,
        float $threshold,
        string $title,
        string $description,
        string $action
    ): void {
        $chk = $this->db->prepare("SELECT id, status FROM `flowlens_alerts` WHERE `alert_code` = :code");
        $chk->execute(['code' => $alertCode]);
        $existing = $chk->fetch();

        if (!$existing) {
            $ins = $this->db->prepare("
                INSERT INTO `flowlens_alerts` (
                    `alert_code`, `alert_type`, `department_id`, `severity`,
                    `triggering_metric`, `observed_value`, `threshold_value`,
                    `alert_title`, `alert_description`, `suggested_action`,
                    `status`, `created_at`
                ) VALUES (
                    :code, :type, :dept_id, :severity,
                    :metric, :observed, :threshold,
                    :title, :description, :action,
                    'active', NOW()
                )
            ");
            $ins->execute([
                'code'        => $alertCode,
                'type'        => $alertType,
                'dept_id'     => $deptId,
                'severity'    => $severity,
                'metric'      => $metric,
                'observed'    => $observed,
                'threshold'   => $threshold,
                'title'       => $title,
                'description' => $description,
                'action'      => $action
            ]);

            $newId = (int)$this->db->lastInsertId();
            $this->auditService->log('CREATE', 'FlowLensAlert', $newId, "FlowLens triggered operational alert: {$title}");
        } elseif ($existing['status'] === 'active') {
            // Update observed value dynamically
            $upd = $this->db->prepare("
                UPDATE `flowlens_alerts` 
                SET `observed_value` = :obs, `alert_description` = :desc 
                WHERE `id` = :id
            ");
            $upd->execute(['obs' => $observed, 'desc' => $description, 'id' => $existing['id']]);
        }
    }

    /**
     * Acknowledge an active alert
     */
    public function acknowledgeAlert(int $alertId, int $userId): bool {
        $stmt = $this->db->prepare("
            UPDATE `flowlens_alerts` 
            SET `status` = 'acknowledged' 
            WHERE `id` = :id AND `status` = 'active'
        ");
        $stmt->execute(['id' => $alertId]);

        if ($stmt->rowCount() > 0) {
            $this->db->prepare("
                INSERT INTO `flowlens_alert_history` (`alert_id`, `action_taken`, `action_by`, `notes`, `created_at`)
                VALUES (:id, 'acknowledged', :by, 'Operational alert acknowledged by administrator.', NOW())
            ")->execute(['id' => $alertId, 'by' => $userId]);

            $this->auditService->log('STATUS_CHANGE', 'FlowLensAlert', $alertId, "Acknowledged FlowLens alert #{$alertId}", userId: $userId);
            return true;
        }
        return false;
    }

    /**
     * Resolve a bottleneck alert with required resolution notes
     */
    public function resolveAlert(int $alertId, int $userId, string $resolutionNotes): bool {
        $stmt = $this->db->prepare("
            UPDATE `flowlens_alerts` 
            SET `status` = 'resolved',
                `resolved_by` = :by,
                `resolution_notes` = :notes,
                `resolved_at` = NOW()
            WHERE `id` = :id AND `status` IN ('active', 'acknowledged')
        ");
        $stmt->execute([
            'id'    => $alertId,
            'by'    => $userId,
            'notes' => $resolutionNotes
        ]);

        if ($stmt->rowCount() > 0) {
            $this->db->prepare("
                INSERT INTO `flowlens_alert_history` (`alert_id`, `action_taken`, `action_by`, `notes`, `created_at`)
                VALUES (:id, 'resolved', :by, :notes, NOW())
            ")->execute(['id' => $alertId, 'by' => $userId, 'notes' => $resolutionNotes]);

            $this->auditService->log('STATUS_CHANGE', 'FlowLensAlert', $alertId, "Resolved FlowLens alert #{$alertId}: {$resolutionNotes}", userId: $userId);
            return true;
        }
        return false;
    }

    /**
     * Get alerts list with department names
     */
    public function getAlerts(?string $status = null, ?string $severity = null): array {
        $sql = "
            SELECT 
                fa.*,
                d.name AS department_name,
                d.dept_code,
                u.username AS resolver_username
            FROM `flowlens_alerts` fa
            LEFT JOIN `departments` d ON fa.department_id = d.id
            LEFT JOIN `users` u ON fa.resolved_by = u.id
            WHERE 1=1
        ";
        $params = [];

        if ($status) {
            $sql .= " AND fa.status = :status";
            $params['status'] = $status;
        }
        if ($severity) {
            $sql .= " AND fa.severity = :severity";
            $params['severity'] = $severity;
        }

        $sql .= " ORDER BY 
            CASE fa.status 
                WHEN 'active' THEN 1 
                WHEN 'acknowledged' THEN 2 
                ELSE 3 
            END,
            CASE fa.severity 
                WHEN 'critical' THEN 1 
                WHEN 'high' THEN 2 
                WHEN 'medium' THEN 3 
                ELSE 4 
            END,
            fa.created_at DESC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Operational summary metrics for FlowLens Dashboard charts
     */
    public function getBottleneckAnalytics(): array {
        // Department wait times
        $waitTimes = $this->db->query("
            SELECT 
                d.name AS department,
                ROUND(AVG(TIMESTAMPDIFF(MINUTE, qe.check_in_time, COALESCE(qe.consultation_start_time, NOW()))), 1) AS avg_wait
            FROM `queue_entries` qe
            JOIN `departments` d ON qe.department_id = d.id
            WHERE qe.queue_date >= CURDATE() - INTERVAL 7 DAY
            GROUP BY d.name
            ORDER BY avg_wait DESC
            LIMIT 6
        ")->fetchAll();

        // Bed occupancy by ward type
        $wardOccupancy = $this->db->query("
            SELECT 
                w.ward_type,
                COUNT(b.id) AS total_beds,
                SUM(CASE WHEN b.status = 'occupied' THEN 1 ELSE 0 END) AS occupied,
                ROUND((SUM(CASE WHEN b.status = 'occupied' THEN 1 ELSE 0 END) / COUNT(b.id)) * 100, 1) AS rate
            FROM `wards` w
            JOIN `beds` b ON w.id = b.ward_id
            GROUP BY w.ward_type
        ")->fetchAll();

        return [
            'department_wait_times' => $waitTimes,
            'ward_occupancy' => $wardOccupancy
        ];
    }
}
