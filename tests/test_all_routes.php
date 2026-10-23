<?php
/**
 * Test: Verify all modules and assets return HTTP 200
 */
declare(strict_types=1);

$baseUrl = 'http://127.0.0.1:8000';

// 1. Authenticate as Super Admin
$html = file_get_contents($baseUrl . '/login.php');
preg_match('/name="csrf_token" value="([^"]+)"/', $html, $m);
$csrf = $m[1] ?? '';

$cookieMap = [];
foreach ($http_response_header as $hdr) {
    if (preg_match('/^Set-Cookie:\s*([^=]+)=([^;]+)/i', $hdr, $m)) {
        $cookieMap[$m[1]] = $m[2];
    }
}
$cookieStr = "PHPSESSID=" . ($cookieMap['PHPSESSID'] ?? '');

$postData = http_build_query([
    'csrf_token' => $csrf,
    'identifier' => 'superadmin',
    'password'   => 'Password123!'
]);

$opts = [
    'http' => [
        'method' => 'POST',
        'header' => "Content-Type: application/x-www-form-urlencoded\r\nCookie: {$cookieStr}\r\n",
        'content' => $postData,
        'follow_location' => 0
    ]
];
file_get_contents($baseUrl . '/login.php', false, stream_context_create($opts));

foreach ($http_response_header as $hdr) {
    if (preg_match('/^Set-Cookie:\s*([^=]+)=([^;]+)/i', $hdr, $m)) {
        $cookieMap[$m[1]] = $m[2];
    }
}
$authCookie = "PHPSESSID=" . ($cookieMap['PHPSESSID'] ?? '');

// Routes to test
$routes = [
    '/index.php' => 'Landing Page',
    '/login.php' => 'Login Portal',
    '/assets/css/style.css' => 'CSS Stylesheet',
    '/assets/js/main.js' => 'JavaScript Engine',
    '/api/queue.php' => 'QueueFlow API Endpoint',
    '/modules/admin/super_admin_dashboard.php' => 'Super Admin Dashboard',
    '/modules/admin/dashboard.php' => 'Hospital Admin Dashboard',
    '/modules/admin/users.php' => 'Staff & Roles Administration',
    '/modules/admin/departments.php' => 'Departments Administration',
    '/modules/queueflow/index.php' => 'QueueFlow Live Monitor',
    '/modules/bedmatrix/index.php' => 'BedMatrix Dynamic Grid',
    '/modules/caretrail/index.php' => 'CareTrail Patient Journey',
    '/modules/flowlens/index.php' => 'FlowLens Bottleneck Surveillance',
    '/modules/trustledger/index.php' => 'TrustLedger Audit Vault',
    '/modules/patients/index.php' => 'Patients Registry',
    '/modules/appointments/index.php' => 'Appointments Management',
    '/modules/laboratory/orders.php' => 'Laboratory Diagnostic Orders',
    '/modules/pharmacy/inventory.php' => 'Pharmacy Medication Inventory',
    '/modules/billing/invoices.php' => 'Billing & Financial Invoices',
    '/modules/reports/index.php' => 'Operations Analytics & Reports'
];

$allPassed = true;
echo "========================================================\n";
echo " NEXUSCARE ROUTE & MODULE INTEGRITY TEST (20 ROUTES)\n";
echo "========================================================\n";

foreach ($routes as $route => $name) {
    $headers = [];
    if (!in_array($route, ['/index.php', '/login.php'], true)) {
        $headers[] = "Cookie: {$authCookie}";
    }
    $opts = [
        'http' => [
            'method' => 'GET',
            'header' => implode("\r\n", $headers) . "\r\n",
            'follow_location' => 0,
            'ignore_errors' => true
        ]
    ];
    $ctx = stream_context_create($opts);
    $content = file_get_contents($baseUrl . $route, false, $ctx);
    $statusLine = $http_response_header[0] ?? '';
    preg_match('/HTTP\/\S+\s+(\d+)/', $statusLine, $sm);
    $code = (int)($sm[1] ?? 500);

    if ($code === 200) {
        echo "[PASS] (200 OK) {$name} -> {$route}\n";
    } else {
        echo "[FAIL] ({$code}) {$name} -> {$route}\n";
        $allPassed = false;
    }
}

echo "========================================================\n";
if ($allPassed) {
    echo "RESULT: ALL 20 ROUTES & MODULES RETURNED HTTP 200 OK!\n";
} else {
    echo "RESULT: SOME ROUTES FAILED. CHECK LOGS.\n";
}
echo "========================================================\n";
