<?php
/**
 * KVM Hospital Management System — NexusCare
 * Application Configuration & Environment Settings
 */

declare(strict_types=1);

// Error reporting settings
$isVercel = !empty($_ENV['VERCEL']) || !empty(getenv('VERCEL')) || !empty($_ENV['NOW_REGION']) || !empty(getenv('NOW_REGION'));
$appEnv = getenv('APP_ENV') ?: ($isVercel ? 'production' : 'development');
if ($appEnv === 'development') {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);
} else {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    error_reporting(0);
}

// Session configuration
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_samesite', 'Lax');
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        ini_set('session.cookie_secure', '1');
    }
    session_start();
}

// Global Application Constants
define('APP_NAME', 'KVM Hospital — NexusCare');
define('APP_TAGLINE', 'Centralized Clinical & Operations Intelligence');
define('APP_VERSION', '2.5.0');
if (!function_exists('detect_base_url')) {
    function detect_base_url(): string {
        $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
        $base = preg_replace('#/(modules|public|api)(/.*)?$#i', '', $script);
        $base = preg_replace('#/[^/]+\.php$#i', '', $base ?? '');
        return rtrim($base ?: '', '/');
    }
}
define('BASE_URL', detect_base_url());

if (!function_exists('url')) {
    function url(string $path = ''): string {
        $cleanPath = '/' . ltrim($path, '/');
        return rtrim(BASE_URL . $cleanPath, '/');
    }
}

// Fallback operational thresholds for FlowLens
define('FLOWLENS_MAX_WAIT_TIME_MINS', 25.0);
define('FLOWLENS_CRITICAL_BED_OCCUPANCY', 80.0);
define('FLOWLENS_MAX_LAB_TURNAROUND_MINS', 60.0);
