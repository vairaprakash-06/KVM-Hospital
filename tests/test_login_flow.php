<?php
/**
 * Test: Full Authentication & Dashboard Redirection Flow
 */
declare(strict_types=1);

$baseUrl = 'http://127.0.0.1:8000';

// 1. Fetch Login Page
$opts = ['http' => ['method' => 'GET', 'follow_location' => 0]];
$context = stream_context_create($opts);
$html = file_get_contents($baseUrl . '/login.php', false, $context);
$cookies = [];
foreach ($http_response_header as $hdr) {
    if (preg_match('/^Set-Cookie:\s*([^;]+)/i', $hdr, $m)) {
        $cookies[] = $m[1];
    }
}
$cookieStr = implode('; ', $cookies);

// Extract CSRF token
preg_match('/name="csrf_token" value="([^"]+)"/', $html, $m);
$csrfToken = $m[1] ?? '';
echo "1. Login Page Loaded. CSRF: " . substr($csrfToken, 0, 10) . "...\n";

// 2. Post Credentials as Super Admin
$postData = http_build_query([
    'csrf_token' => $csrfToken,
    'identifier' => 'superadmin',
    'password'   => 'Password123!'
]);

$opts = [
    'http' => [
        'method' => 'POST',
        'header' => "Content-Type: application/x-www-form-urlencoded\r\n" .
                    "Cookie: {$cookieStr}\r\n",
        'content' => $postData,
        'follow_location' => 0
    ]
];
$context = stream_context_create($opts);
$res = file_get_contents($baseUrl . '/login.php', false, $context);

$cookieMap = [];
foreach ($http_response_header as $hdr) {
    if (preg_match('/^Location:\s*(.+)$/i', $hdr, $m)) {
        $redirectLocation = trim($m[1]);
    }
    if (preg_match('/^Set-Cookie:\s*([^=]+)=([^;]+)/i', $hdr, $m)) {
        $cookieMap[$m[1]] = $m[2];
    }
}
$cookiePairs = [];
foreach ($cookieMap as $k => $v) {
    $cookiePairs[] = "$k=$v";
}
$cookieStr = implode('; ', $cookiePairs);
echo "2. Login Submitted. Redirect Location: {$redirectLocation}\n";
echo "   Session Cookie: {$cookieStr}\n";

// 3. Follow Redirect to Super Admin Dashboard
$opts = [
    'http' => [
        'method' => 'GET',
        'header' => "Cookie: {$cookieStr}\r\n",
        'follow_location' => 0
    ]
];
$context = stream_context_create($opts);
$targetUrl = str_starts_with($redirectLocation, 'http') ? $redirectLocation : $baseUrl . $redirectLocation;
$dashHtml = file_get_contents($targetUrl, false, $context);

$statusLine = $http_response_header[0] ?? '';
echo "3. Dashboard Response: {$statusLine}\n";

if (str_contains($dashHtml, 'Super Administrator Portal')) {
    echo "SUCCESS: Super Administrator Portal loaded cleanly!\n";
} else {
    echo "FAILED: Portal title not found in response.\n";
}

if (str_contains($dashHtml, 'TrustLedger')) {
    echo "SUCCESS: TrustLedger widget verified on dashboard.\n";
}
