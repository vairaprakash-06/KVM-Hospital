<?php
/**
 * KVM Hospital Management System — NexusCare
 * CSRF Protection Middleware
 * Supports Cryptographic Stateless HMAC Tokens, Double-Submit Cookies, and Session Storage
 * Built for standard servers (XAMPP/Apache) and serverless cloud runtimes (Vercel Lambda)
 */

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    $tmpDir = sys_get_temp_dir();
    if (is_dir($tmpDir) && is_writable($tmpDir)) {
        @session_save_path($tmpDir);
    }
    @session_start();
}

/**
 * Get internal CSRF application secret
 */
function get_csrf_secret(): string {
    return getenv('APP_KEY') ?: 'kvm_hospital_nexuscare_csrf_secret_salt_2026';
}

/**
 * Generate CSRF Token
 * Encodes timestamp, random nonce, and HMAC-SHA256 signature for serverless resilience
 */
function csrf_token(): string {
    $secret = get_csrf_secret();
    $time = time();
    $nonce = bin2hex(random_bytes(16));
    $sig = hash_hmac('sha256', "{$time}:{$nonce}", $secret);
    $token = "{$time}.{$nonce}.{$sig}";

    if (session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION['csrf_token'] = $token;
    }

    if (!headers_sent()) {
        $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
                    (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
        setcookie('XSRF-TOKEN', $token, [
            'expires'  => time() + 86400,
            'path'     => '/',
            'secure'   => $isSecure,
            'httponly' => false,
            'samesite' => 'Lax'
        ]);
    }

    return $token;
}

/**
 * Output hidden CSRF input field
 */
function csrf_field(): string {
    $token = csrf_token();
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * Verify submitted CSRF token
 * Validates via:
 * 1. Active Session match (standard stateful)
 * 2. Double-Submit Cookie match (stateless cookie)
 * 3. Cryptographic HMAC Token verification (stateless serverless Lambda multi-container)
 */
function verify_csrf(): bool {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['_csrf'] ?? '';

        if (empty($token) || !is_string($token)) {
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
            die(json_encode([
                'error' => true,
                'message' => 'CSRF verification failed. Security token is required.'
            ]));
        }

        // 1. Session verification check (if session state persisted)
        if (!empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token)) {
            return true;
        }

        // 2. Double-Submit Cookie verification
        if (!empty($_COOKIE['XSRF-TOKEN']) && hash_equals($_COOKIE['XSRF-TOKEN'], $token)) {
            return true;
        }

        // 3. Cryptographic HMAC Token verification (serverless multi-container / cold start resilient)
        $secret = get_csrf_secret();
        $parts = explode('.', $token);
        if (count($parts) === 3) {
            [$timeStr, $nonce, $sig] = $parts;
            $tokenTime = (int)$timeStr;
            $now = time();
            // Valid within 24 hours (86,400s) and small clock-skew leeway (+600s)
            if ($tokenTime > ($now - 86400) && $tokenTime < ($now + 600)) {
                $expectedSig = hash_hmac('sha256', "{$timeStr}:{$nonce}", $secret);
                if (hash_equals($expectedSig, $sig)) {
                    if (session_status() === PHP_SESSION_ACTIVE) {
                        $_SESSION['csrf_token'] = $token;
                    }
                    return true;
                }
            }
        }

        // All checks failed: reject request
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        die(json_encode([
            'error' => true,
            'message' => 'CSRF verification failed. Form submission rejected for security reasons.'
        ]));
    }
    return true;
}
