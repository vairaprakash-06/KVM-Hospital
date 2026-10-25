<?php
/**
 * KVM Hospital Management System — NexusCare
 * Vercel Serverless Master Router & Dispatcher
 */
declare(strict_types=1);

// Parse request URI
$rawUri = $_SERVER['REQUEST_URI'] ?? '/';
$uriPath = parse_url($rawUri, PHP_URL_PATH) ?: '/';
$uriPath = '/' . ltrim($uriPath, '/');

// 1. Static Asset Serving (Fallback if not intercepted by Vercel routes)
if (str_starts_with($uriPath, '/assets/')) {
    $assetFile = __DIR__ . '/../public' . $uriPath;
    if (file_exists($assetFile) && !is_dir($assetFile)) {
        $ext = strtolower(pathinfo($assetFile, PATHINFO_EXTENSION));
        $mimes = [
            'css'   => 'text/css; charset=utf-8',
            'js'    => 'application/javascript; charset=utf-8',
            'svg'   => 'image/svg+xml',
            'png'   => 'image/png',
            'jpg'   => 'image/jpeg',
            'jpeg'  => 'image/jpeg',
            'gif'   => 'image/gif',
            'ico'   => 'image/x-icon',
            'woff'  => 'font/woff',
            'woff2' => 'font/woff2',
            'ttf'   => 'font/ttf'
        ];
        header('Content-Type: ' . ($mimes[$ext] ?? 'application/octet-stream'));
        header('Cache-Control: public, max-age=31536000, immutable');
        readfile($assetFile);
        exit;
    }
}

// 2. Public Root Entrypoints (Clean URLs and .php)
if ($uriPath === '/' || $uriPath === '/index' || $uriPath === '/index.php' || $uriPath === '/api' || $uriPath === '/api/') {
    $_SERVER['SCRIPT_NAME'] = '/index.php';
    require __DIR__ . '/../views/index.php';
    exit;
}

if ($uriPath === '/login' || $uriPath === '/login.php') {
    $_SERVER['SCRIPT_NAME'] = '/login.php';
    require __DIR__ . '/../views/login.php';
    exit;
}

if ($uriPath === '/logout' || $uriPath === '/logout.php') {
    $_SERVER['SCRIPT_NAME'] = '/logout.php';
    require __DIR__ . '/../views/logout.php';
    exit;
}

if ($uriPath === '/register' || $uriPath === '/register.php') {
    $_SERVER['SCRIPT_NAME'] = '/register.php';
    require __DIR__ . '/../views/register.php';
    exit;
}

if ($uriPath === '/unauthorized' || $uriPath === '/unauthorized.php') {
    $_SERVER['SCRIPT_NAME'] = '/unauthorized.php';
    require __DIR__ . '/../views/unauthorized.php';
    exit;
}

// 3. API Endpoints
if (str_starts_with($uriPath, '/api/queue')) {
    $_SERVER['SCRIPT_NAME'] = '/api/queue.php';
    require __DIR__ . '/queue.php';
    exit;
}

// 3b. Dedicated Hospital Pharmacy Intelligence SPA (React & FastAPI)
if (str_starts_with($uriPath, '/hospital-pharmacy')) {
    $subPath = substr($uriPath, strlen('/hospital-pharmacy'));
    if ($subPath === '' || $subPath === '/') {
        $subPath = '/index.html';
    }
    $spaFile = __DIR__ . '/../hospital-pharmacy/frontend/dist' . $subPath;
    if (file_exists($spaFile) && !is_dir($spaFile)) {
        $ext = strtolower(pathinfo($spaFile, PATHINFO_EXTENSION));
        $mimes = [
            'html' => 'text/html; charset=utf-8',
            'css'  => 'text/css; charset=utf-8',
            'js'   => 'application/javascript; charset=utf-8',
            'svg'  => 'image/svg+xml',
            'png'  => 'image/png',
            'jpg'  => 'image/jpeg',
            'ico'  => 'image/x-icon',
            'json' => 'application/json'
        ];
        header('Content-Type: ' . ($mimes[$ext] ?? 'application/octet-stream'));
        readfile($spaFile);
        exit;
    }
    $fallbackIndex = __DIR__ . '/../hospital-pharmacy/frontend/dist/index.html';
    if (file_exists($fallbackIndex)) {
        header('Content-Type: text/html; charset=utf-8');
        readfile($fallbackIndex);
        exit;
    }
}


// 4. Hospital Modules
if (str_starts_with($uriPath, '/modules/')) {
    $targetFile = __DIR__ . '/..' . $uriPath;
    if (is_dir($targetFile)) {
        $targetFile = rtrim($targetFile, '/') . '/index.php';
    } elseif (!file_exists($targetFile) && file_exists($targetFile . '.php')) {
        $targetFile = $targetFile . '.php';
    }
    if (file_exists($targetFile)) {
        $_SERVER['SCRIPT_NAME'] = str_ends_with($uriPath, '.php') ? $uriPath : $uriPath . '.php';
        require $targetFile;
        exit;
    }
}

// 5. General File Dispatcher from public/
$publicTarget = __DIR__ . '/../public' . $uriPath;
if (file_exists($publicTarget) && !is_dir($publicTarget)) {
    $_SERVER['SCRIPT_NAME'] = $uriPath;
    require $publicTarget;
    exit;
}

// 6. Security guard: block direct HTTP access to internal app and database files
if (str_starts_with($uriPath, '/database/') || 
    str_starts_with($uriPath, '/app/') || 
    preg_match('/\.(sqlite|sql|env|json|md|log)$/i', $uriPath)) {
    http_response_code(403);
    echo '<!DOCTYPE html><html><head><title>403 Forbidden</title></head><body style="font-family:sans-serif;padding:40px;text-align:center;"><h2>403 Forbidden</h2><p>Direct access to internal system files is prohibited.</p></body></html>';
    exit;
}

// 7. General File Dispatcher from project root
$rootTarget = __DIR__ . '/..' . $uriPath;
if (!file_exists($rootTarget) && file_exists($rootTarget . '.php')) {
    $rootTarget = $rootTarget . '.php';
}
if (file_exists($rootTarget) && !is_dir($rootTarget)) {
    $_SERVER['SCRIPT_NAME'] = str_ends_with($uriPath, '.php') ? $uriPath : $uriPath . '.php';
    require $rootTarget;
    exit;
}

// 404 Fallback
http_response_code(404);
echo '<!DOCTYPE html><html><head><title>404 Not Found</title><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css"></head><body class="bg-light d-flex align-items-center justify-content-center min-vh-100"><div class="card p-4 shadow-sm text-center" style="max-width: 480px;"><h2 class="text-danger mb-3">404 — Page Not Found</h2><p class="text-muted">The requested path <code>' . htmlspecialchars($uriPath, ENT_QUOTES, 'UTF-8') . '</code> could not be found.</p><a href="/" class="btn btn-primary mt-3">Return to Home</a></div></body></html>';
