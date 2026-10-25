<?php
/**
 * KVM Hospital Management System — NexusCare
 * Role-Based Access Control & Permissions Middleware
 */

declare(strict_types=1);

require_once __DIR__ . '/auth.php';

/**
 * Enforce that the user has at least one of the specified roles
 */
function require_role(string ...$roles): void {
    require_auth();

    // Universal Access: Super Admin and Hospital Admin have unrestricted access to all hospital sections
    if (has_role('super_admin', 'hospital_admin')) {
        return;
    }

    if (!has_role(...$roles)) {
        http_response_code(403);
        $userRole = $_SESSION['user']['primary_role'] ?? 'user';
        set_flash('danger', "Access Denied: Your assigned role ({$userRole}) does not have permission to view this section.");
        redirect(BASE_URL . '/unauthorized.php?required=' . urlencode(implode(',', $roles)));
    }
}

/**
 * Enforce that the user has a specific permission key
 */
function require_permission(string $permissionKey): void {
    require_auth();

    // Universal Access: Super Admin and Hospital Admin possess all permissions
    if (has_role('super_admin', 'hospital_admin')) {
        return;
    }

    if (!has_permission($permissionKey)) {
        http_response_code(403);
        set_flash('danger', "Access Denied: Missing required permission [{$permissionKey}].");
        redirect(BASE_URL . '/unauthorized.php?perm=' . urlencode($permissionKey));
    }
}
