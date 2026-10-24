<?php
/**
 * KVM Hospital Management System — NexusCare
 * Centralized Database Connection using PDO
 */

declare(strict_types=1);

class Database {
    private static ?PDO $instance = null;

    public static function getConnection(): PDO {
        if (self::$instance === null) {
            $host = getenv('DB_HOST') ?: '127.0.0.1';
            $port = getenv('DB_PORT') ?: '3306';
            $dbname = getenv('DB_DATABASE') ?: 'kvm_hospital';
            $username = getenv('DB_USERNAME') ?: 'root';
            $password = getenv('DB_PASSWORD') !== false ? getenv('DB_PASSWORD') : '';

            $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";
            
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
            ];

            try {
                self::$instance = new PDO($dsn, $username, $password, $options);
            } catch (PDOException $e) {
                error_log("Database connection failure: " . $e->getMessage());
                
                $isApi = str_contains($_SERVER['REQUEST_URI'] ?? '', '/api/') || 
                         (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'));
                
                if ($isApi) {
                    header('Content-Type: application/json; charset=utf-8');
                    http_response_code(500);
                    echo json_encode([
                        'error' => true,
                        'message' => 'Database connection failed: ' . $e->getMessage()
                    ]);
                    exit;
                }

                // Render helpful diagnostics for web UI
                http_response_code(500);
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
        <p class="text-secondary small mb-3">Add these Environment Variables in your <strong>Vercel Project Settings &gt; Environment Variables</strong> pointing to your managed MySQL database (e.g. Aiven, PlanetScale, Railway, or AWS RDS):</p>
        
        <ul class="list-unstyled text-secondary small mb-4">
            <li class="mb-2"><span class="env-badge">DB_HOST</span> &bull; Cloud database hostname</li>
            <li class="mb-2"><span class="env-badge">DB_PORT</span> &bull; Port (default 3306)</li>
            <li class="mb-2"><span class="env-badge">DB_DATABASE</span> &bull; <code>kvm_hospital</code></li>
            <li class="mb-2"><span class="env-badge">DB_USERNAME</span> &bull; Database username</li>
            <li class="mb-2"><span class="env-badge">DB_PASSWORD</span> &bull; Database user password</li>
        </ul>

        <h6 class="text-white fw-semibold mb-2">For Local XAMPP:</h6>
        <p class="text-secondary small mb-4">Open the <strong>XAMPP Control Panel</strong> and click <strong>Start</strong> next to MySQL, then ensure <code>database/schema.sql</code> and <code>database/seed.sql</code> are imported into <code>kvm_hospital</code>.</p>

        <a href="javascript:location.reload()" class="btn btn-primary w-100 py-2">
            <i class="bi bi-arrow-clockwise me-2"></i>Retry Connection
        </a>
    </div>
</body>
</html>';
                exit;
            }
        }

        return self::$instance;
    }

    public static function getSafeConnection(): ?PDO {
        if (self::$instance !== null) {
            return self::$instance;
        }
        $host = getenv('DB_HOST') ?: '127.0.0.1';
        $port = getenv('DB_PORT') ?: '3306';
        $dbname = getenv('DB_DATABASE') ?: 'kvm_hospital';
        $username = getenv('DB_USERNAME') ?: 'root';
        $password = getenv('DB_PASSWORD') !== false ? getenv('DB_PASSWORD') : '';

        $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";
        
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
        ];

        try {
            self::$instance = new PDO($dsn, $username, $password, $options);
            return self::$instance;
        } catch (PDOException $e) {
            error_log("Database connection failure (safe check): " . $e->getMessage());
            return null;
        }
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
