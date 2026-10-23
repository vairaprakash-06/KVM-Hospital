<?php
/**
 * KVM Hospital Management System — NexusCare
 * Authentication Middleware
 */

declare(strict_types=1);

require_once __DIR__ . '/../helpers/functions.php';

function require_auth(): void {
    if (!is_logged_in()) {
        $_SESSION['intended_url'] = $_SERVER['REQUEST_URI'] ?? '';
        set_flash('warning', 'Please sign in to access the requested department module.');
        
        // Find relative path to login.php
        $loginUrl = BASE_URL . '/login.php';
        redirect($loginUrl);
    }

    // Check if account status is active
    $user = current_user();
    if (($user['account_status'] ?? 'active') !== 'active') {
        set_flash('danger', 'Your account has been deactivated or locked by the system administrator.');
        redirect(BASE_URL . '/unauthorized.php?reason=account_disabled');
    }
}
