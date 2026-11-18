<?php
/**
 * KVM Hospital Management System — NexusCare
 * Helper Functions & Common Utilities
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';

/**
 * Escape HTML output to prevent XSS
 */
function e(mixed $string): string {
    return htmlspecialchars((string)($string ?? ''), ENT_QUOTES, 'UTF-8');
}

/**
 * Sanitize text input
 */
function sanitize_input(?string $input): string {
    return trim(strip_tags((string)($input ?? '')));
}

/**
 * Application security key for signing stateless cookies
 */
function get_app_secret(): string {
    return getenv('APP_KEY') ?: 'kvm_hospital_nexuscare_app_secret_salt_2026';
}

/**
 * Synchronize user session state to a secure signed cookie (serverless persistence across Vercel Lambdas)
 */
function sync_session_to_cookie(): void {
    if (!headers_sent() && !empty($_SESSION['user'])) {
        $secret = get_app_secret();
        $payload = base64_encode(json_encode($_SESSION['user']));
        $sig = hash_hmac('sha256', $payload, $secret);
        $cookieVal = $payload . '.' . $sig;
        $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
                    (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
        setcookie('nexus_sess', $cookieVal, [
            'expires'  => time() + 86400,
            'path'     => '/',
            'secure'   => $isSecure,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
    }
}

/**
 * Restore user session from secure signed cookie if missing from Lambda container memory
 */
function restore_session_from_cookie(): void {
    if (empty($_SESSION['user']) && !empty($_COOKIE['nexus_sess'])) {
        $secret = get_app_secret();
        $parts = explode('.', (string)$_COOKIE['nexus_sess'], 2);
        if (count($parts) === 2) {
            [$payload, $sig] = $parts;
            if (hash_equals(hash_hmac('sha256', $payload, $secret), $sig)) {
                $userData = json_decode(base64_decode($payload), true);
                if (is_array($userData) && !empty($userData['id'])) {
                    $_SESSION['user'] = $userData;
                }
            }
        }
    }
}

/**
 * Return currently authenticated user data or null
 */
function current_user(): ?array {
    if (empty($_SESSION['user']['id'])) {
        restore_session_from_cookie();
    }
    return $_SESSION['user'] ?? null;
}

/**
 * Check if a user is currently logged in
 */
function is_logged_in(): bool {
    if (empty($_SESSION['user']['id'])) {
        restore_session_from_cookie();
    }
    return !empty($_SESSION['user']['id']);
}

/**
 * Check if the active user possesses a given role
 */
function has_role(string ...$roles): bool {
    if (!is_logged_in()) {
        return false;
    }

    $userRoles = $_SESSION['user']['roles'] ?? [];
    foreach ($roles as $role) {
        if (in_array($role, $userRoles, true)) {
            return true;
        }
    }
    return false;
}

/**
 * Check if the active user possesses a specific permission key
 */
function has_permission(string $permissionKey): bool {
    if (!is_logged_in()) {
        return false;
    }

    // Super Admin & Hospital Admin have all permissions automatically
    if (has_role('super_admin', 'hospital_admin')) {
        return true;
    }

    $permissions = $_SESSION['user']['permissions'] ?? [];
    return in_array($permissionKey, $permissions, true);
}

/**
 * Safe redirect to a URL
 */
function redirect(string $path): void {
    if (!headers_sent()) {
        header("Location: " . $path);
        exit;
    }
    echo "<script>window.location.href=" . json_encode($path) . ";</script>";
    exit;
}

/**
 * Flash messages
 */
function set_flash(string $type, string $message): void {
    $_SESSION['flash'] = [
        'type' => $type, // success, danger, warning, info
        'message' => $message
    ];
}

function get_flash(): ?array {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/**
 * Return JSON response and exit
 */
function json_response(array $data, int $statusCode = 200): void {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

/**
 * Format currency in Indian Rupees
 */
function format_currency(float|int|string|null $amount): string {
    $num = (float)($amount ?? 0);
    return '₹' . number_format($num, 2);
}

/**
 * Format datetime nicely
 */
function format_datetime(?string $datetime, string $format = 'd M Y, h:i A'): string {
    if (empty($datetime)) return '—';
    try {
        $dt = new DateTime($datetime);
        return $dt->format($format);
    } catch (Exception $e) {
        return $datetime;
    }
}

/**
 * Generate unique human-readable patient identifier: PAT-YYYY-XXXX
 */
function generate_patient_id(PDO $db): string {
    $year = date('Y');
    $stmt = $db->prepare("SELECT COUNT(*) AS total FROM `patient_profiles` WHERE `patient_id` LIKE :prefix");
    $stmt->execute(['prefix' => "PAT-{$year}-%"]);
    $count = (int)$stmt->fetchColumn() + 1;
    return sprintf("PAT-%s-%04d", $year, $count);
}

/**
 * Generate appointment number: APT-YYYY-XXXX
 */
function generate_appointment_number(PDO $db): string {
    $year = date('Y');
    $stmt = $db->prepare("SELECT COUNT(*) AS total FROM `appointments` WHERE `appointment_number` LIKE :prefix");
    $stmt->execute(['prefix' => "APT-{$year}-%"]);
    $count = (int)$stmt->fetchColumn() + 1;
    return sprintf("APT-%s-%04d", $year, $count);
}

/**
 * Generate encounter number: ENC-YYYY-XXXX
 */
function generate_encounter_number(PDO $db): string {
    $year = date('Y');
    $stmt = $db->prepare("SELECT COUNT(*) AS total FROM `patient_encounters` WHERE `encounter_number` LIKE :prefix");
    $stmt->execute(['prefix' => "ENC-{$year}-%"]);
    $count = (int)$stmt->fetchColumn() + 1;
    return sprintf("ENC-%s-%04d", $year, $count);
}

/**
 * Generate invoice number: INV-YYYY-XXXX
 */
function generate_invoice_number(PDO $db): string {
    $year = date('Y');
    $stmt = $db->prepare("SELECT COUNT(*) AS total FROM `invoices` WHERE `invoice_number` LIKE :prefix");
    $stmt->execute(['prefix' => "INV-{$year}-%"]);
    $count = (int)$stmt->fetchColumn() + 1;
    return sprintf("INV-%s-%04d", $year, $count);
}

/**
 * Generate payment receipt number: PAY-YYYY-XXXX
 */
function generate_payment_number(PDO $db): string {
    $year = date('Y');
    $stmt = $db->prepare("SELECT COUNT(*) AS total FROM `payments` WHERE `payment_number` LIKE :prefix");
    $stmt->execute(['prefix' => "PAY-{$year}-%"]);
    $count = (int)$stmt->fetchColumn() + 1;
    return sprintf("PAY-%s-%04d", $year, $count);
}

/**
 * Get Client IP Address safely
 */
function get_client_ip(): string {
    return $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
}

function get_dashboard_route_for_role(string $role): string {
    return match ($role) {
        'super_admin' => BASE_URL . '/modules/admin/super_admin_dashboard.php',
        'hospital_admin' => BASE_URL . '/modules/admin/dashboard.php',
        'doctor' => BASE_URL . '/modules/doctor/dashboard.php',
        'receptionist' => BASE_URL . '/modules/reception/dashboard.php',
        'nurse' => BASE_URL . '/modules/nurse/dashboard.php',
        'lab_technician' => BASE_URL . '/modules/laboratory/dashboard.php',
        'pharmacist' => BASE_URL . '/modules/pharmacy/dashboard.php',
        'billing_staff' => BASE_URL . '/modules/billing/dashboard.php',
        'patient' => BASE_URL . '/modules/patient/dashboard.php',
        default => BASE_URL . '/modules/admin/dashboard.php'
    };
}
