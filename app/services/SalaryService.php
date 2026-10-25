<?php
/**
 * KVM Hospital Management System — NexusCare
 * Salary & Payroll Management Service
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/functions.php';
require_once __DIR__ . '/AuditService.php';

class SalaryService {
    private PDO $db;
    private AuditService $auditService;

    // Doctor Salary Range
    public const DOCTOR_MIN_SALARY = 50000.00;
    public const DOCTOR_MAX_SALARY = 90000.00;

    // Staff Salary Range
    public const STAFF_MIN_SALARY = 30000.00;
    public const STAFF_MAX_SALARY = 60000.00;

    public function __construct(?PDO $db = null) {
        $this->db = $db ?? get_db();
        $this->auditService = new AuditService($this->db);
        $this->ensureTablesExist();
    }

    /**
     * Ensure employee_salaries and salary_history tables exist across MySQL and SQLite
     */
    public function ensureTablesExist(): void {
        $driver = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
        $isSqlite = ($driver === 'sqlite');

        if ($isSqlite) {
            $this->db->exec("
                CREATE TABLE IF NOT EXISTS `employee_salaries` (
                    `id` INTEGER PRIMARY KEY AUTOINCREMENT,
                    `employee_id` INT NOT NULL,
                    `employee_type` TEXT NOT NULL,
                    `department_id` INT NULL,
                    `monthly_salary` DECIMAL(10,2) NOT NULL,
                    `effective_from` DATE NOT NULL,
                    `effective_to` DATE NULL,
                    `status` TEXT DEFAULT 'active',
                    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE (`employee_id`, `employee_type`)
                );
            ");

            $this->db->exec("
                CREATE TABLE IF NOT EXISTS `salary_history` (
                    `id` INTEGER PRIMARY KEY AUTOINCREMENT,
                    `salary_id` INT NOT NULL,
                    `employee_id` INT NOT NULL,
                    `employee_type` TEXT NOT NULL,
                    `previous_salary` DECIMAL(10,2) NOT NULL,
                    `new_salary` DECIMAL(10,2) NOT NULL,
                    `change_reason` TEXT NULL,
                    `changed_by` INT NULL,
                    `effective_date` DATE NOT NULL,
                    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
                );
            ");
        } else {
            $this->db->exec("
                CREATE TABLE IF NOT EXISTS `employee_salaries` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `employee_id` INT NOT NULL,
                    `employee_type` ENUM('doctor', 'staff') NOT NULL,
                    `department_id` INT NULL,
                    `monthly_salary` DECIMAL(10,2) NOT NULL,
                    `effective_from` DATE NOT NULL,
                    `effective_to` DATE NULL,
                    `status` ENUM('active', 'superseded') DEFAULT 'active',
                    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY `uk_emp_sal` (`employee_id`, `employee_type`),
                    FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`) ON DELETE SET NULL,
                    INDEX `idx_salary_dept` (`department_id`),
                    INDEX `idx_salary_type` (`employee_type`),
                    INDEX `idx_salary_amount` (`monthly_salary`)
                ) ENGINE=InnoDB;
            ");

            $this->db->exec("
                CREATE TABLE IF NOT EXISTS `salary_history` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `salary_id` INT NOT NULL,
                    `employee_id` INT NOT NULL,
                    `employee_type` ENUM('doctor', 'staff') NOT NULL,
                    `previous_salary` DECIMAL(10,2) NOT NULL,
                    `new_salary` DECIMAL(10,2) NOT NULL,
                    `change_reason` VARCHAR(255) NULL,
                    `changed_by` INT NULL,
                    `effective_date` DATE NOT NULL,
                    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (`salary_id`) REFERENCES `employee_salaries`(`id`) ON DELETE CASCADE,
                    FOREIGN KEY (`changed_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
                    INDEX `idx_sal_hist_emp` (`employee_id`, `employee_type`)
                ) ENGINE=InnoDB;
            ");
        }
    }

    /**
     * Validate whether a salary falls strictly within the prescribed range
     */
    public function validateSalaryRange(string $employeeType, float $salary): array {
        if ($employeeType === 'doctor') {
            if ($salary < self::DOCTOR_MIN_SALARY || $salary > self::DOCTOR_MAX_SALARY) {
                return [
                    'valid' => false,
                    'error' => sprintf(
                        "Invalid Doctor Salary: %s. Doctor monthly salaries must be between %s and %s.",
                        format_currency($salary),
                        format_currency(self::DOCTOR_MIN_SALARY),
                        format_currency(self::DOCTOR_MAX_SALARY)
                    )
                ];
            }
        } elseif ($employeeType === 'staff') {
            if ($salary < self::STAFF_MIN_SALARY || $salary > self::STAFF_MAX_SALARY) {
                return [
                    'valid' => false,
                    'error' => sprintf(
                        "Invalid Staff Salary: %s. Staff monthly salaries must be between %s and %s.",
                        format_currency($salary),
                        format_currency(self::STAFF_MIN_SALARY),
                        format_currency(self::STAFF_MAX_SALARY)
                    )
                ];
            }
        } else {
            return ['valid' => false, 'error' => "Unknown employee type: {$employeeType}."];
        }

        return ['valid' => true, 'error' => ''];
    }

    /**
     * Check if a salary is already assigned to another employee of the same type
     */
    public function isSalaryUnique(string $employeeType, float $salary, ?int $excludeEmployeeId = null): bool {
        $sql = "SELECT COUNT(*) FROM `employee_salaries` WHERE `employee_type` = :etype AND `monthly_salary` = :sal";
        $params = [
            'etype' => $employeeType,
            'sal'   => number_format($salary, 2, '.', '')
        ];

        if ($excludeEmployeeId !== null) {
            $sql .= " AND `employee_id` != :exclude_id";
            $params['exclude_id'] = $excludeEmployeeId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return ((int)$stmt->fetchColumn()) === 0;
    }

    /**
     * Initialize / Seed unique salaries for all doctors and staff across all 89 departments.
     * Idempotent: Does NOT overwrite existing records.
     */
    public function seedInitialSalaries(): array {
        $this->ensureTablesExist();

        $doctorsStmt = $this->db->query("SELECT id, department_id FROM `doctor_profiles` ORDER BY id ASC");
        $doctors = $doctorsStmt->fetchAll(PDO::FETCH_ASSOC);

        $staffStmt = $this->db->query("SELECT id, department_id FROM `staff_profiles` ORDER BY id ASC");
        $staffMembers = $staffStmt->fetchAll(PDO::FETCH_ASSOC);

        $existingStmt = $this->db->query("SELECT employee_id, employee_type, monthly_salary FROM `employee_salaries`");
        $existing = [];
        $assignedSalaries = ['doctor' => [], 'staff' => []];

        while ($row = $existingStmt->fetch(PDO::FETCH_ASSOC)) {
            $key = $row['employee_type'] . '_' . $row['employee_id'];
            $existing[$key] = (float)$row['monthly_salary'];
            $assignedSalaries[$row['employee_type']][] = (float)$row['monthly_salary'];
        }

        $docsInserted = 0;
        $staffInserted = 0;

        $insertStmt = $this->db->prepare("
            INSERT INTO `employee_salaries` (
                `employee_id`, `employee_type`, `department_id`, `monthly_salary`,
                `effective_from`, `status`
            ) VALUES (
                :emp_id, :emp_type, :dept_id, :salary, CURDATE(), 'active'
            )
        ");

        // 1. Assign unique salaries for doctors (Range: ₹50,000 - ₹90,000)
        // Formula guarantees strictly unique, monotonically increasing salaries for each doctor index
        $docIndex = 1;
        foreach ($doctors as $doc) {
            $key = 'doctor_' . $doc['id'];
            if (isset($existing[$key])) {
                $docIndex++;
                continue;
            }

            // Generate deterministic unique salary in range [50305, 89250]
            $uniqueSalary = 50000.00 + ($docIndex * 220.00) + (($docIndex * 17) % 19) * 5.00;

            // Ensure no collision with any existing manually added salary
            while (in_array($uniqueSalary, $assignedSalaries['doctor'], true) || $uniqueSalary > self::DOCTOR_MAX_SALARY) {
                if ($uniqueSalary > self::DOCTOR_MAX_SALARY) {
                    $uniqueSalary = self::DOCTOR_MIN_SALARY + (($docIndex * 137) % 39000);
                } else {
                    $uniqueSalary += 1.00;
                }
            }

            $assignedSalaries['doctor'][] = $uniqueSalary;
            $insertStmt->execute([
                'emp_id'   => (int)$doc['id'],
                'emp_type' => 'doctor',
                'dept_id'  => $doc['department_id'] ? (int)$doc['department_id'] : null,
                'salary'   => number_format($uniqueSalary, 2, '.', '')
            ]);
            $docsInserted++;
            $docIndex++;
        }

        // 2. Assign unique salaries for staff (Range: ₹30,000 - ₹60,000)
        // Formula guarantees strictly unique, monotonically increasing salaries for each staff index
        $staffIndex = 1;
        foreach ($staffMembers as $staff) {
            $key = 'staff_' . $staff['id'];
            if (isset($existing[$key])) {
                $staffIndex++;
                continue;
            }

            // Generate deterministic unique salary in range [30119, 58528]
            $uniqueSalary = 30000.00 + ($staffIndex * 80.00) + (($staffIndex * 13) % 17) * 3.00;

            // Ensure no collision with any existing manually added salary
            while (in_array($uniqueSalary, $assignedSalaries['staff'], true) || $uniqueSalary > self::STAFF_MAX_SALARY) {
                if ($uniqueSalary > self::STAFF_MAX_SALARY) {
                    $uniqueSalary = self::STAFF_MIN_SALARY + (($staffIndex * 79) % 29000);
                } else {
                    $uniqueSalary += 1.00;
                }
            }

            $assignedSalaries['staff'][] = $uniqueSalary;
            $insertStmt->execute([
                'emp_id'   => (int)$staff['id'],
                'emp_type' => 'staff',
                'dept_id'  => $staff['department_id'] ? (int)$staff['department_id'] : null,
                'salary'   => number_format($uniqueSalary, 2, '.', '')
            ]);
            $staffInserted++;
            $staffIndex++;
        }

        return [
            'doctors_inserted' => $docsInserted,
            'staff_inserted'   => $staffInserted,
            'total_doctors'    => count($doctors),
            'total_staff'      => count($staffMembers)
        ];
    }

    /**
     * Compute dynamic payroll expenditure and averages across doctors and staff
     */
    public function getPayrollStats(): array {
        $stmt = $this->db->query("
            SELECT 
                employee_type,
                COUNT(*) AS count,
                COALESCE(SUM(monthly_salary), 0) AS total_expenditure,
                COALESCE(AVG(monthly_salary), 0) AS avg_salary
            FROM `employee_salaries`
            WHERE `status` = 'active'
            GROUP BY `employee_type`
        ");

        $stats = [
            'total_doctors'           => 0,
            'total_staff'             => 0,
            'doctor_payroll'          => 0.0,
            'staff_payroll'           => 0.0,
            'combined_monthly_payroll'=> 0.0,
            'avg_doctor_salary'       => 0.0,
            'avg_staff_salary'        => 0.0
        ];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if ($row['employee_type'] === 'doctor') {
                $stats['total_doctors']  = (int)$row['count'];
                $stats['doctor_payroll'] = (float)$row['total_expenditure'];
                $stats['avg_doctor_salary'] = (float)$row['avg_salary'];
            } elseif ($row['employee_type'] === 'staff') {
                $stats['total_staff']    = (int)$row['count'];
                $stats['staff_payroll']  = (float)$row['total_expenditure'];
                $stats['avg_staff_salary'] = (float)$row['avg_salary'];
            }
        }

        $stats['combined_monthly_payroll'] = $stats['doctor_payroll'] + $stats['staff_payroll'];

        return $stats;
    }

    /**
     * Retrieve paginated, searchable, filterable, and sortable employee salary records
     */
    public function getEmployeeSalaries(array $filters = [], int $page = 1, int $perPage = 25): array {
        $search = trim($filters['search'] ?? '');
        $type = trim($filters['type'] ?? 'all');
        $departmentId = !empty($filters['department_id']) ? (int)$filters['department_id'] : null;
        $sort = trim($filters['sort'] ?? 'name_asc');

        $whereClauses = [];
        $params = [];

        if ($type === 'doctor') {
            $whereClauses[] = "u.employee_type = 'doctor'";
        } elseif ($type === 'staff') {
            $whereClauses[] = "u.employee_type = 'staff'";
        }

        if ($departmentId !== null) {
            $whereClauses[] = "u.department_id = :dept_id";
            $params['dept_id'] = $departmentId;
        }

        if ($search !== '') {
            $whereClauses[] = "(u.employee_code LIKE :s1 OR u.first_name LIKE :s2 OR u.last_name LIKE :s3 OR u.full_name LIKE :s4)";
            $params['s1'] = "%{$search}%";
            $params['s2'] = "%{$search}%";
            $params['s3'] = "%{$search}%";
            $params['s4'] = "%{$search}%";
        }

        $whereSql = !empty($whereClauses) ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

        // Unified Employee Subquery
        $unionSql = "
            SELECT 
                d.id AS employee_id,
                'doctor' AS employee_type,
                d.doctor_code AS employee_code,
                d.first_name,
                d.last_name,
                ('Dr. ' || d.first_name || ' ' || d.last_name) AS full_name,
                d.department_id,
                dep.name AS department_name,
                dep.dept_code,
                d.specialization AS role_title,
                es.id AS salary_id,
                es.monthly_salary,
                es.effective_from,
                es.updated_at
            FROM `doctor_profiles` d
            LEFT JOIN `departments` dep ON d.department_id = dep.id
            LEFT JOIN `employee_salaries` es ON d.id = es.employee_id AND es.employee_type = 'doctor'

            UNION ALL

            SELECT 
                s.id AS employee_id,
                'staff' AS employee_type,
                s.employee_id AS employee_code,
                s.first_name,
                s.last_name,
                (s.first_name || ' ' || s.last_name) AS full_name,
                s.department_id,
                dep.name AS department_name,
                dep.dept_code,
                s.designation AS role_title,
                es.id AS salary_id,
                es.monthly_salary,
                es.effective_from,
                es.updated_at
            FROM `staff_profiles` s
            LEFT JOIN `departments` dep ON s.department_id = dep.id
            LEFT JOIN `employee_salaries` es ON s.id = es.employee_id AND es.employee_type = 'staff'
        ";

        // Count total matching
        $countQuery = "SELECT COUNT(*) FROM ({$unionSql}) u {$whereSql}";
        $countStmt = $this->db->prepare($countQuery);
        $countStmt->execute($params);
        $totalRecords = (int)$countStmt->fetchColumn();

        // Sorting
        $orderSql = match ($sort) {
            'salary_asc'  => 'ORDER BY u.monthly_salary ASC, u.full_name ASC',
            'salary_desc' => 'ORDER BY u.monthly_salary DESC, u.full_name ASC',
            'name_desc'   => 'ORDER BY u.full_name DESC',
            'id_asc'      => 'ORDER BY u.employee_code ASC',
            'id_desc'     => 'ORDER BY u.employee_code DESC',
            default       => 'ORDER BY u.full_name ASC'
        };

        // Pagination
        $offset = max(0, ($page - 1) * $perPage);
        $dataQuery = "SELECT * FROM ({$unionSql}) u {$whereSql} {$orderSql} LIMIT {$perPage} OFFSET {$offset}";
        $dataStmt = $this->db->prepare($dataQuery);
        $dataStmt->execute($params);
        $records = $dataStmt->fetchAll(PDO::FETCH_ASSOC);

        $totalPages = $perPage > 0 ? (int)ceil($totalRecords / $perPage) : 1;

        return [
            'records'       => $records,
            'total_records' => $totalRecords,
            'current_page'  => $page,
            'per_page'      => $perPage,
            'total_pages'   => $totalPages
        ];
    }

    /**
     * Update an employee's salary and record the change in salary_history and audit_logs
     */
    public function updateSalary(
        int $employeeId,
        string $employeeType,
        float $newSalary,
        string $reason,
        int $changedByUserId
    ): array {
        $validation = $this->validateSalaryRange($employeeType, $newSalary);
        if (!$validation['valid']) {
            return ['success' => false, 'message' => $validation['error']];
        }

        // Verify employee exists and get department
        $deptId = null;
        $empName = '';
        if ($employeeType === 'doctor') {
            $stmt = $this->db->prepare("SELECT id, department_id, first_name, last_name FROM `doctor_profiles` WHERE id = :id");
            $stmt->execute(['id' => $employeeId]);
            $doc = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$doc) {
                return ['success' => false, 'message' => "Doctor with ID #{$employeeId} not found."];
            }
            $deptId = $doc['department_id'] ? (int)$doc['department_id'] : null;
            $empName = "Dr. " . $doc['first_name'] . " " . $doc['last_name'];
        } else {
            $stmt = $this->db->prepare("SELECT id, department_id, first_name, last_name FROM `staff_profiles` WHERE id = :id");
            $stmt->execute(['id' => $employeeId]);
            $stf = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$stf) {
                return ['success' => false, 'message' => "Staff member with ID #{$employeeId} not found."];
            }
            $deptId = $stf['department_id'] ? (int)$stf['department_id'] : null;
            $empName = $stf['first_name'] . " " . $stf['last_name'];
        }

        // Check if another employee already has this exact salary
        if (!$this->isSalaryUnique($employeeType, $newSalary, $employeeId)) {
            return [
                'success' => false,
                'message' => sprintf(
                    "Salary Uniqueness Conflict: The amount %s is already assigned to another %s. All %ss must have distinct salary amounts.",
                    format_currency($newSalary),
                    $employeeType,
                    $employeeType
                )
            ];
        }

        $this->db->beginTransaction();
        try {
            // Check existing salary record
            $curStmt = $this->db->prepare("
                SELECT id, monthly_salary FROM `employee_salaries` 
                WHERE `employee_id` = :emp_id AND `employee_type` = :emp_type
            ");
            $curStmt->execute(['emp_id' => $employeeId, 'emp_type' => $employeeType]);
            $existing = $curStmt->fetch(PDO::FETCH_ASSOC);

            $previousSalary = $existing ? (float)$existing['monthly_salary'] : 0.00;
            $salaryId = 0;

            if ($existing) {
                $salaryId = (int)$existing['id'];
                $updateStmt = $this->db->prepare("
                    UPDATE `employee_salaries` 
                    SET `monthly_salary` = :sal, `department_id` = :dept_id, `updated_at` = CURRENT_TIMESTAMP
                    WHERE `id` = :id
                ");
                $updateStmt->execute([
                    'sal'     => number_format($newSalary, 2, '.', ''),
                    'dept_id' => $deptId,
                    'id'      => $salaryId
                ]);
            } else {
                $insertStmt = $this->db->prepare("
                    INSERT INTO `employee_salaries` (
                        `employee_id`, `employee_type`, `department_id`, `monthly_salary`,
                        `effective_from`, `status`
                    ) VALUES (
                        :emp_id, :emp_type, :dept_id, :sal, CURDATE(), 'active'
                    )
                ");
                $insertStmt->execute([
                    'emp_id'   => $employeeId,
                    'emp_type' => $employeeType,
                    'dept_id'  => $deptId,
                    'sal'      => number_format($newSalary, 2, '.', '')
                ]);
                $salaryId = (int)$this->db->lastInsertId();
            }

            // Record into salary_history
            $histStmt = $this->db->prepare("
                INSERT INTO `salary_history` (
                    `salary_id`, `employee_id`, `employee_type`, `previous_salary`,
                    `new_salary`, `change_reason`, `changed_by`, `effective_date`
                ) VALUES (
                    :sal_id, :emp_id, :emp_type, :prev_sal, :new_sal, :reason, :changed_by, CURDATE()
                )
            ");
            $histStmt->execute([
                'sal_id'     => $salaryId,
                'emp_id'     => $employeeId,
                'emp_type'   => $employeeType,
                'prev_sal'   => number_format($previousSalary, 2, '.', ''),
                'new_sal'    => number_format($newSalary, 2, '.', ''),
                'reason'     => $reason,
                'changed_by' => $changedByUserId
            ]);

            // Record TrustLedger Audit Log
            $this->auditService->log(
                actionType: 'UPDATE_SALARY',
                entityType: 'EmployeeSalary',
                entityId: $salaryId,
                description: sprintf(
                    "Salary updated for %s (%s #%d) from %s to %s. Reason: %s",
                    $empName,
                    ucfirst($employeeType),
                    $employeeId,
                    format_currency($previousSalary),
                    format_currency($newSalary),
                    $reason
                ),
                oldValues: ['monthly_salary' => $previousSalary],
                newValues: ['monthly_salary' => $newSalary, 'change_reason' => $reason],
                userId: $changedByUserId,
                roleName: 'hospital_admin'
            );

            $this->db->commit();

            return [
                'success' => true,
                'message' => sprintf(
                    "Successfully updated salary for %s to %s.",
                    $empName,
                    format_currency($newSalary)
                )
            ];
        } catch (Throwable $e) {
            $this->db->rollBack();
            return ['success' => false, 'message' => "Database Error: " . $e->getMessage()];
        }
    }

    /**
     * Get historical salary adjustments for a given employee
     */
    public function getSalaryHistory(int $employeeId, string $employeeType): array {
        $stmt = $this->db->prepare("
            SELECT sh.*, u.username AS changed_by_username
            FROM `salary_history` sh
            LEFT JOIN `users` u ON sh.changed_by = u.id
            WHERE sh.employee_id = :emp_id AND sh.employee_type = :emp_type
            ORDER BY sh.id DESC
        ");
        $stmt->execute(['emp_id' => $employeeId, 'emp_type' => $employeeType]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
