<?php
/**
 * KVM Hospital Management System — NexusCare
 * TrustLedger — Smart DBMS Audit & Data Integrity Service
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/functions.php';

class AuditService {
    private PDO $db;

    public function __construct(?PDO $db = null) {
        $this->db = $db ?? get_db();
    }

    /**
     * Record an audit event into audit_logs table
     */
    public function log(
        string $actionType,
        string $entityType,
        ?int $entityId,
        string $description,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?int $userId = null,
        ?string $roleName = null
    ): int {
        try {
            $currentUser = current_user();
            $effectiveUserId = $userId ?? ($currentUser['id'] ?? null);
            $effectiveRole = $roleName ?? ($currentUser['primary_role'] ?? 'system');

            $stmt = $this->db->prepare("
                INSERT INTO `audit_logs` (
                    `user_id`, `role_name`, `action_type`, `entity_type`, `entity_id`,
                    `ip_address`, `user_agent`, `description`, `old_values`, `new_values`
                ) VALUES (
                    :user_id, :role_name, :action_type, :entity_type, :entity_id,
                    :ip_address, :user_agent, :description, :old_values, :new_values
                )
            ");

            $stmt->execute([
                'user_id'     => $effectiveUserId,
                'role_name'   => $effectiveRole,
                'action_type' => $actionType,
                'entity_type' => $entityType,
                'entity_id'   => $entityId,
                'ip_address'  => get_client_ip(),
                'user_agent'  => substr($_SERVER['HTTP_USER_AGENT'] ?? 'CLI/Unknown', 0, 250),
                'description' => $description,
                'old_values'  => $oldValues ? json_encode($oldValues) : null,
                'new_values'  => $newValues ? json_encode($newValues) : null
            ]);

            return (int)$this->db->lastInsertId();
        } catch (Exception $e) {
            error_log("TrustLedger audit log failure: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Record login attempt
     */
    public function recordLoginAttempt(string $usernameOrEmail, string $status): void {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO `login_attempts` (`username_or_email`, `ip_address`, `user_agent`, `status`)
                VALUES (:user, :ip, :ua, :status)
            ");
            $stmt->execute([
                'user'   => $usernameOrEmail,
                'ip'     => get_client_ip(),
                'ua'     => substr($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown', 0, 250),
                'status' => $status
            ]);
        } catch (Exception $e) {
            error_log("Failed to log login attempt: " . $e->getMessage());
        }
    }

    /**
     * Record role assignment changes
     */
    public function logRoleChange(int $targetUserId, int $changedBy, array $oldRoles, array $newRoles, string $reason): void {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO `role_change_logs` (`target_user_id`, `changed_by`, `old_roles`, `new_roles`, `reason`)
                VALUES (:target, :by, :old_r, :new_r, :reason)
            ");
            $stmt->execute([
                'target' => $targetUserId,
                'by'     => $changedBy,
                'old_r'  => implode(', ', $oldRoles),
                'new_r'  => implode(', ', $newRoles),
                'reason' => $reason
            ]);
        } catch (Exception $e) {
            error_log("Failed to log role change: " . $e->getMessage());
        }
    }

    /**
     * Retrieve recent audit logs with user profile names
     */
    public function getRecentLogs(int $limit = 50, ?string $actionType = null, ?string $entityType = null): array {
        $sql = "
            SELECT 
                al.*,
                u.username,
                u.email
            FROM `audit_logs` al
            LEFT JOIN `users` u ON al.user_id = u.id
            WHERE 1=1
        ";
        $params = [];

        if ($actionType) {
            $sql .= " AND al.action_type = :action_type";
            $params['action_type'] = $actionType;
        }
        if ($entityType) {
            $sql .= " AND al.entity_type = :entity_type";
            $params['entity_type'] = $entityType;
        }

        $sql .= " ORDER BY al.created_at DESC LIMIT " . (int)$limit;

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Retrieve login attempts for security review
     */
    public function getLoginAttempts(int $limit = 25): array {
        $stmt = $this->db->prepare("
            SELECT * FROM `login_attempts`
            ORDER BY `attempt_time` DESC
            LIMIT :lim
        ");
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Count failed login attempts from an IP within the last N minutes
     */
    public function getRecentFailedAttemptsCount(string $ip, int $minutes = 15): int {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM `login_attempts`
            WHERE `ip_address` = :ip 
              AND `status` = 'failed'
              AND `attempt_time` >= NOW() - INTERVAL :mins MINUTE
        ");
        $stmt->execute(['ip' => $ip, 'mins' => $minutes]);
        return (int)$stmt->fetchColumn();
    }
}
