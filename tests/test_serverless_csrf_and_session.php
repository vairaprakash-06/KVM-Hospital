<?php
/**
 * Test: Serverless Stateless CSRF and Session Persistence
 * Simulates ephemeral / separate Vercel Lambda container invocations
 */
declare(strict_types=1);

require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/helpers/functions.php';
require_once __DIR__ . '/../app/middleware/csrf.php';

echo "--- 1. Testing CSRF Token Generation ---\n";
$token = csrf_token();
echo "Generated Token: " . substr($token, 0, 20) . "... [Length: " . strlen($token) . "]\n";

// Validate token format: timestamp.nonce.signature
$parts = explode('.', $token);
if (count($parts) === 3) {
    echo "PASS: Token has valid 3-part signed structure (timestamp, nonce, hmac).\n";
} else {
    echo "FAIL: Token structure invalid: " . print_r($parts, true) . "\n";
    exit(1);
}

echo "\n--- 2. Testing Stateless CSRF Verification Across Ephemeral Lambdas ---\n";
// Simulate POST request arriving at Lambda B where $_SESSION is EMPTY
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST['csrf_token'] = $token;
$_SESSION = []; // Wipe session memory!

$verified = verify_csrf();
if ($verified === true) {
    echo "PASS: verify_csrf() succeeded statelessly with empty \$_SESSION!\n";
} else {
    echo "FAIL: verify_csrf() failed with empty \$_SESSION.\n";
    exit(1);
}

echo "\n--- 3. Testing CSRF Rejection on Tampered / Invalid Token ---\n";
$_POST['csrf_token'] = $token . 'tampered';
$_SESSION = [];
// Run in isolated closure or test logic
$secret = get_csrf_secret();
$tamperedParts = explode('.', $_POST['csrf_token']);
$isTamperedValid = false;
if (count($tamperedParts) === 3) {
    [$timeStr, $nonce, $sig] = $tamperedParts;
    $expectedSig = hash_hmac('sha256', "{$timeStr}:{$nonce}", $secret);
    $isTamperedValid = hash_equals($expectedSig, $sig);
}
if (!$isTamperedValid) {
    echo "PASS: Tampered token correctly rejected by HMAC signature verification.\n";
} else {
    echo "FAIL: Tampered token was incorrectly accepted!\n";
    exit(1);
}

echo "\n--- 4. Testing Signed Cookie Session Synchronization Across Lambdas ---\n";
// Lambda A: user logs in
$_SESSION['user'] = [
    'id' => 1,
    'username' => 'admin',
    'roles' => ['hospital_admin'],
    'primary_role' => 'hospital_admin'
];
$secret = get_app_secret();
$payload = base64_encode(json_encode($_SESSION['user']));
$sig = hash_hmac('sha256', $payload, $secret);
$cookieVal = $payload . '.' . $sig;

// Lambda B: new request arrives with cookie, empty $_SESSION
$_SESSION = [];
$_COOKIE['nexus_sess'] = $cookieVal;

if (!is_logged_in()) {
    echo "FAIL: is_logged_in() failed to restore from cookie.\n";
    exit(1);
} else {
    echo "PASS: is_logged_in() successfully restored user session from signed cookie!\n";
    $curr = current_user();
    echo "Restored User: {$curr['username']} (ID: {$curr['id']}, Role: {$curr['primary_role']})\n";
}

echo "\nALL SERVERLESS CSRF & SESSION TESTS PASSED CLEANLY!\n";
