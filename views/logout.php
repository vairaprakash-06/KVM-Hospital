<?php
/**
 * KVM Hospital Management System — NexusCare
 * Logout Controller: Session destruction and audit logging
 */

declare(strict_types=1);

require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/helpers/functions.php';
require_once __DIR__ . '/../app/services/AuditService.php';

if (is_logged_in()) {
    $user = current_user();
    $audit = new AuditService();
    $audit->log(
        actionType: 'LOGOUT',
        entityType: 'UserSession',
        entityId: $user['id'],
        description: "User {$user['username']} logged out cleanly.",
        userId: $user['id'],
        roleName: $user['primary_role']
    );
}

// Clear session variables
$_SESSION = [];

// Destroy session cookie if set
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy session
session_destroy();

// Redirect to login page
session_start();
set_flash('info', 'You have been securely signed out of NexusCare.');
redirect('login.php');
