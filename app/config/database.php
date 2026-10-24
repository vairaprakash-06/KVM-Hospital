<?php
/**
 * KVM Hospital Management System — NexusCare
 * Dual-Engine Centralized Database Connection
 * Supports Centralized Cloud MySQL with Seamless Embedded SQLite Serverless/Demo Fallback
 */

declare(strict_types=1);

/**
 * Custom PDO wrapper for SQLite to seamlessly support MySQL dialects used across modules:
 * - Strips `FOR UPDATE` pessimistic row lock (SQLite write locks entire database)
 * - Quotes unquoted unit in `TIMESTAMPDIFF(YEAR, ...)` -> `TIMESTAMPDIFF('YEAR', ...)`
 * - Translates `NOW() - INTERVAL X UNIT` and `CURDATE() + INTERVAL X UNIT` to standard SQLite datetime()
 */
class NexusSqlitePDO extends PDO {
    public function rewriteSql(string $sql): string {
        // 1. Remove FOR UPDATE (pessimistic locking not supported in SQLite)
        $sql = preg_replace('/\s+FOR\s+UPDATE\b/i', '', $sql);
        
        // 2. Quote TIMESTAMPDIFF unit parameter: TIMESTAMPDIFF(YEAR, ...) -> TIMESTAMPDIFF('YEAR', ...)
        $sql = preg_replace('/TIMESTAMPDIFF\s*\(\s*([A-Za-z]+)\s*,/i', 'TIMESTAMPDIFF(\'$1\',', $sql);
        
        // 3. Double intervals first: NOW() - INTERVAL 2 DAY + INTERVAL 30 MINUTE
        $sql = preg_replace_callback('/NOW\(\)\s*-\s*INTERVAL\s+([0-9]+)\s+([A-Za-z]+)\s*([\+\-])\s*INTERVAL\s+([0-9]+)\s+([A-Za-z]+)/i', function($m) {
            $arg1 = '-' . $m[1] . ' ' . strtolower($m[2]) . 's';
            $sign = $m[3] === '-' ? '-' : '+';
            $arg2 = $sign . $m[4] . ' ' . strtolower($m[5]) . 's';
            return "datetime('now', '{$arg1}', '{$arg2}')";
        }, $sql);

        // 4. Single NOW() intervals
        $sql = preg_replace('/NOW\(\)\s*-\s*INTERVAL\s+([0-9]+)\s+([A-Za-z]+)/i', "datetime('now', '-$1 $2s')", $sql);
        $sql = preg_replace('/NOW\(\)\s*\+\s*INTERVAL\s+([0-9]+)\s+([A-Za-z]+)/i', "datetime('now', '+$1 $2s')", $sql);

        // 5. Single CURDATE() intervals
        $sql = preg_replace('/CURDATE\(\)\s*-\s*INTERVAL\s+([0-9]+)\s+([A-Za-z]+)/i', "date('now', '-$1 $2s')", $sql);
        $sql = preg_replace('/CURDATE\(\)\s*\+\s*INTERVAL\s+([0-9]+)\s+([A-Za-z]+)/i', "date('now', '+$1 $2s')", $sql);

        // 6. Dynamic parameterized interval: NOW() - INTERVAL :mins MINUTE
        $sql = preg_replace('/NOW\(\)\s*-\s*INTERVAL\s+:mins\s+MINUTE/i', "datetime('now', '-' || :mins || ' minutes')", $sql);

        return $sql;
    }

    public function prepare(string $query, array $options = []): PDOStatement|false {
        return parent::prepare($this->rewriteSql($query), $options);
    }

    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false {
        $rewritten = $this->rewriteSql($query);
        if ($fetchMode === null) {
            return parent::query($rewritten);
        }
        return parent::query($rewritten, $fetchMode, ...$fetchModeArgs);
    }

    public function exec(string $statement): int|false {
        return parent::exec($this->rewriteSql($statement));
    }

    /**
     * Compatible custom SQLite function registrar supporting PHP 8.0 through PHP 8.5+
     */
    public function registerCustomFunction(string $name, callable $callback, int $numArgs = -1): void {
        if (method_exists($this, 'createFunction')) {
            @$this->createFunction($name, $callback, $numArgs);
        } else {
            @$this->sqliteCreateFunction($name, $callback, $numArgs);
        }
    }
}

class Database {
    private static ?PDO $instance = null;
    private static string $driver = 'mysql'; // 'mysql' or 'sqlite'
    private static ?string $lastError = null;

    public static function getConnection(): PDO {
        if (self::$instance !== null) {
            return self::$instance;
        }

        $safe = self::getSafeConnection();
        if ($safe !== null) {
            return $safe;
        }

        // Render helpful diagnostics if both MySQL and SQLite failed
        $isApi = str_contains($_SERVER['REQUEST_URI'] ?? '', '/api/') || 
                 (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'));
        
        if ($isApi) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(500);
            echo json_encode([
                'error' => true,
                'message' => 'Database connection failed: ' . (self::$lastError ?? 'No database driver available.')
            ]);
            exit;
        }

        http_response_code(500);
        $host = getenv('DB_HOST') ?: '127.0.0.1';
        $port = getenv('DB_PORT') ?: '3306';
        echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Configuration Required — NexusCare</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.min.css">
    <style>
        body { background: #081120; color: #f8fafc; min-height: 100vh; display: flex; align-items: center; justify-content: center; font-family: system-ui, -apple-system, sans-serif; padding: 20px; }
        .setup-card { background: #0f1e36; border: 1px solid rgba(255,255,255,0.08); border-radius: 16px; padding: 36px; max-width: 680px; width: 100%; box-shadow: 0 20px 50px rgba(0,0,0,0.5); }
        .env-badge { background: #1e293b; padding: 3px 8px; border-radius: 6px; font-family: monospace; color: #38bdf8; }
    </style>
</head>
<body>
    <div class="setup-card">
        <div class="d-flex align-items-center gap-3 mb-4">
            <div class="bg-primary bg-opacity-25 text-primary p-3 rounded-3 fs-3">
                <i class="bi bi-database-exclamation"></i>
            </div>
            <div>
                <h4 class="mb-1 text-white fw-bold">Database Connection Setup</h4>
                <p class="text-secondary small mb-0">KVM Hospital Management System — NexusCare</p>
            </div>
        </div>

        <div class="alert alert-warning border-0 bg-warning bg-opacity-10 text-warning-emphasis mb-4">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            Could not connect to the centralized MySQL database on host <strong>' . htmlspecialchars($host, ENT_QUOTES, 'UTF-8') . ':' . htmlspecialchars((string)$port, ENT_QUOTES, 'UTF-8') . '</strong>.
        </div>

        <h6 class="text-white fw-semibold mb-2">For Vercel Cloud Deployment:</h6>
        <p class="text-secondary small mb-3">Add these Environment Variables in your <strong>Vercel Project Settings &gt; Environment Variables</strong> pointing to your managed MySQL database:</p>
        
        <ul class="list-unstyled text-secondary small mb-4">
            <li class="mb-2"><span class="env-badge">DB_HOST</span> &bull; Cloud database hostname</li>
            <li class="mb-2"><span class="env-badge">DB_PORT</span> &bull; Port (default 3306)</li>
            <li class="mb-2"><span class="env-badge">DB_DATABASE</span> &bull; <code>kvm_hospital</code></li>
            <li class="mb-2"><span class="env-badge">DB_USERNAME</span> &bull; Database username</li>
            <li class="mb-2"><span class="env-badge">DB_PASSWORD</span> &bull; Database user password</li>
        </ul>

        <a href="javascript:location.reload()" class="btn btn-primary w-100 py-2">
            <i class="bi bi-arrow-clockwise me-2"></i>Retry Connection
        </a>
    </div>
</body>
</html>';
        exit;
    }

    public static function getSafeConnection(): ?PDO {
        if (self::$instance !== null) {
            return self::$instance;
        }

        $requestedDriver = strtolower(getenv('DB_DRIVER') ?: '');
        $isVercel = !empty($_ENV['VERCEL']) || !empty(getenv('VERCEL'));
        $host = getenv('DB_HOST') ?: '';

        // 1. Attempt MySQL only if explicitly configured via DB_HOST or DB_DRIVER=mysql
        $shouldAttemptMysql = ($requestedDriver === 'mysql') || (!empty($host));

        if ($shouldAttemptMysql) {
            $targetHost = !empty($host) ? $host : '127.0.0.1';
            $port = getenv('DB_PORT') ?: '3306';
            $dbname = getenv('DB_DATABASE') ?: 'kvm_hospital';
            $username = getenv('DB_USERNAME') ?: 'root';
            $password = getenv('DB_PASSWORD') !== false ? getenv('DB_PASSWORD') : '';

            $dsn = "mysql:host={$targetHost};port={$port};dbname={$dbname};charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_TIMEOUT            => 2
            ];

            try {
                self::$instance = new PDO($dsn, $username, $password, $options);
                self::$driver = 'mysql';
                return self::$instance;
            } catch (PDOException $e) {
                self::$lastError = $e->getMessage();
                error_log("MySQL connection attempt failed: " . $e->getMessage());
            }
        }

        // 2. High-Performance Embedded SQLite Fallback for Vercel Serverless / Demo / Viva Evaluation
        try {
            $bundledDb = __DIR__ . '/../../database/kvm_hospital.sqlite';

            // Serverless environments (like Vercel Lambda) have a read-only code root; copy DB to writable /tmp
            if ($isVercel || !is_writable(dirname($bundledDb)) || (file_exists($bundledDb) && !is_writable($bundledDb))) {
                $tmpDir = sys_get_temp_dir();
                $tmpDb = rtrim($tmpDir, '/\\') . '/kvm_hospital.sqlite';
                if (!file_exists($tmpDb) || filesize($tmpDb) === 0) {
                    if (file_exists($bundledDb)) {
                        copy($bundledDb, $tmpDb);
                    }
                }
                $sqlitePath = file_exists($tmpDb) ? $tmpDb : $bundledDb;
            } else {
                $sqlitePath = $bundledDb;
            }

            if (file_exists($sqlitePath)) {
                $pdo = new NexusSqlitePDO('sqlite:' . $sqlitePath);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

                // Register MySQL compatibility functions inside SQLite (PHP 8.0 - 8.5+ compatible)
                $pdo->registerCustomFunction('NOW', fn() => date('Y-m-d H:i:s'));
                $pdo->registerCustomFunction('CURDATE', fn() => date('Y-m-d'));
                $pdo->registerCustomFunction('CURTIME', fn() => date('H:i:s'));
                $pdo->registerCustomFunction('VERSION', fn() => '8.0.35-NexusCare-SQLite');
                $pdo->registerCustomFunction('CONCAT', fn(...$p) => implode('', $p));
                $pdo->registerCustomFunction('IFNULL', fn($a, $b) => $a !== null ? $a : $b);
                $pdo->registerCustomFunction('TIMESTAMPDIFF', function($unit, $d1, $d2) {
                    if (!$d1 || !$d2) return 0;
                    $t1 = strtotime((string)$d1);
                    $t2 = strtotime((string)$d2);
                    $diff = $t2 - $t1;
                    $unit = strtoupper(trim((string)$unit));
                    if ($unit === 'YEAR') {
                        $y1 = (int)date('Y', $t1);
                        $y2 = (int)date('Y', $t2);
                        $age = $y2 - $y1;
                        if (date('md', $t2) < date('md', $t1)) $age--;
                        return $age;
                    }
                    if ($unit === 'MINUTE') return (int)round($diff / 60);
                    if ($unit === 'HOUR') return (int)round($diff / 3600);
                    if ($unit === 'DAY') return (int)round($diff / 86400);
                    return (int)$diff;
                });

                self::$instance = $pdo;
                self::$driver = 'sqlite';
                return self::$instance;
            }
        } catch (Throwable $e) {
            self::$lastError = $e->getMessage();
            error_log("SQLite fallback failed: " . $e->getMessage());
        }

        return null;
    }

    public static function getDriverName(): string {
        return self::$driver;
    }

    public static function isSqlite(): bool {
        return self::$driver === 'sqlite';
    }

    public static function isMysql(): bool {
        return self::$driver === 'mysql';
    }

    /**
     * Prevent cloning or instantiation
     */
    private function __construct() {}
    private function __clone() {}
}

function get_db(): PDO {
    return Database::getConnection();
}

function get_db_or_null(): ?PDO {
    return Database::getSafeConnection();
}
