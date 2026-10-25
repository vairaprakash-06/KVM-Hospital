<?php
/**
 * KVM Hospital Management System — NexusCare
 * Shared Sidebar Navigation
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../helpers/functions.php';

$currentUser = current_user();
$primaryRole = $currentUser['primary_role'] ?? 'guest';
$currentScript = $_SERVER['SCRIPT_NAME'] ?? '';
?>
<aside class="sidebar">
    <!-- Header / Brand -->
    <div class="sidebar-header">
        <div class="brand-icon">
            <i class="bi bi-hospital"></i>
        </div>
        <div class="brand-text">
            <div class="title">NexusCare</div>
            <div class="subtitle">KVM Hospital OS</div>
        </div>
    </div>

    <!-- Navigation List -->
    <nav class="sidebar-nav">
        <!-- 1. Role Dashboard -->
        <span class="nav-section-label">Command Center</span>
        
        <?php if ($primaryRole === 'super_admin'): ?>
            <a href="<?= BASE_URL ?>/modules/admin/super_admin_dashboard.php" class="nav-link <?= strpos($currentScript, 'super_admin') !== false ? 'active' : '' ?>">
                <i class="bi bi-shield-lock"></i>
                <span>Super Admin</span>
            </a>
        <?php endif; ?>

        <?php if (in_array($primaryRole, ['super_admin', 'hospital_admin'], true)): ?>
            <a href="<?= BASE_URL ?>/modules/admin/dashboard.php" class="nav-link <?= (strpos($currentScript, 'admin/dashboard') !== false) ? 'active' : '' ?>">
                <i class="bi bi-speedometer2"></i>
                <span>Hospital Admin</span>
            </a>
        <?php endif; ?>

        <?php if ($primaryRole === 'doctor'): ?>
            <a href="<?= BASE_URL ?>/modules/doctor/dashboard.php" class="nav-link <?= strpos($currentScript, 'doctor/dashboard') !== false ? 'active' : '' ?>">
                <i class="bi bi-heart-pulse"></i>
                <span>Doctor Workspace</span>
            </a>
        <?php endif; ?>

        <?php if ($primaryRole === 'patient'): ?>
            <a href="<?= BASE_URL ?>/modules/patient/dashboard.php" class="nav-link <?= strpos($currentScript, 'patient/dashboard') !== false ? 'active' : '' ?>">
                <i class="bi bi-person-badge"></i>
                <span>Patient Portal</span>
            </a>
        <?php endif; ?>

        <!-- Operational Stations & Desks (Admin Universal Hub) -->
        <?php if (in_array($primaryRole, ['super_admin', 'hospital_admin'], true)): ?>
            <span class="nav-section-label mt-2">Operational Stations</span>

            <a href="<?= BASE_URL ?>/modules/reception/dashboard.php" class="nav-link <?= strpos($currentScript, 'reception/dashboard') !== false ? 'active' : '' ?>">
                <i class="bi bi-person-workspace"></i>
                <span>Reception Desk</span>
                <span class="feature-pill">Live OPD</span>
            </a>

            <a href="<?= BASE_URL ?>/modules/nurse/dashboard.php" class="nav-link <?= strpos($currentScript, 'nurse/dashboard') !== false ? 'active' : '' ?>">
                <i class="bi bi-bandaid"></i>
                <span>Nursing Station</span>
                <span class="feature-pill">Ward Care</span>
            </a>

            <a href="<?= BASE_URL ?>/modules/laboratory/dashboard.php" class="nav-link <?= strpos($currentScript, 'laboratory/dashboard') !== false ? 'active' : '' ?>">
                <i class="bi bi-eyedropper"></i>
                <span>Diagnostic Lab</span>
                <span class="feature-pill">Reports</span>
            </a>

            <a href="<?= BASE_URL ?>/modules/pharmacy/dashboard.php" class="nav-link <?= strpos($currentScript, 'pharmacy/dashboard') !== false ? 'active' : '' ?>">
                <i class="bi bi-capsule"></i>
                <span>Pharmacy Unit</span>
                <span class="feature-pill">Dispensary</span>
            </a>

            <a href="<?= BASE_URL ?>/modules/billing/dashboard.php" class="nav-link <?= strpos($currentScript, 'billing/dashboard') !== false ? 'active' : '' ?>">
                <i class="bi bi-receipt"></i>
                <span>Billing Center</span>
                <span class="feature-pill">Cashier</span>
            </a>
        <?php endif; ?>

        <!-- 2. Five Differentiated Advanced Features -->
        <span class="nav-section-label mt-2">Differentiated Features</span>

        <a href="<?= BASE_URL ?>/modules/queueflow/index.php" class="nav-link <?= strpos($currentScript, 'queueflow') !== false ? 'active' : '' ?>">
            <i class="bi bi-hourglass-split"></i>
            <span>QueueFlow</span>
            <span class="feature-pill">Live</span>
        </a>

        <a href="<?= BASE_URL ?>/modules/bedmatrix/index.php" class="nav-link <?= strpos($currentScript, 'bedmatrix') !== false ? 'active' : '' ?>">
            <i class="bi bi-grid-3x3-gap"></i>
            <span>BedMatrix</span>
            <span class="feature-pill">Dynamic</span>
        </a>

        <a href="<?= BASE_URL ?>/modules/caretrail/index.php" class="nav-link <?= strpos($currentScript, 'caretrail') !== false ? 'active' : '' ?>">
            <i class="bi bi-signpost-split"></i>
            <span>CareTrail</span>
            <span class="feature-pill">Journey</span>
        </a>

        <a href="<?= BASE_URL ?>/modules/flowlens/index.php" class="nav-link <?= strpos($currentScript, 'flowlens') !== false ? 'active' : '' ?>">
            <i class="bi bi-radar"></i>
            <span>FlowLens</span>
            <span class="feature-pill">Bottleneck</span>
        </a>

        <a href="<?= BASE_URL ?>/modules/trustledger/index.php" class="nav-link <?= strpos($currentScript, 'trustledger') !== false ? 'active' : '' ?>">
            <i class="bi bi-journal-check"></i>
            <span>TrustLedger</span>
            <span class="feature-pill">Audit</span>
        </a>

        <!-- 3. Core Hospital Modules -->
        <span class="nav-section-label mt-2">Core Clinical Modules</span>

        <a href="<?= BASE_URL ?>/modules/patients/index.php" class="nav-link <?= strpos($currentScript, 'patients/') !== false ? 'active' : '' ?>">
            <i class="bi bi-people"></i>
            <span>Patients Registry</span>
        </a>

        <a href="<?= BASE_URL ?>/modules/appointments/index.php" class="nav-link <?= strpos($currentScript, 'appointments/') !== false ? 'active' : '' ?>">
            <i class="bi bi-calendar2-check"></i>
            <span>Appointments</span>
        </a>

        <a href="<?= BASE_URL ?>/modules/laboratory/orders.php" class="nav-link <?= strpos($currentScript, 'laboratory/orders') !== false ? 'active' : '' ?>">
            <i class="bi bi-vial"></i>
            <span>Lab Orders</span>
        </a>

        <a href="<?= BASE_URL ?>/modules/pharmacy/inventory.php" class="nav-link <?= strpos($currentScript, 'pharmacy/inventory') !== false ? 'active' : '' ?>">
            <i class="bi bi-box-seam"></i>
            <span>Pharmacy Stock</span>
        </a>

        <a href="<?= BASE_URL ?>/modules/billing/invoices.php" class="nav-link <?= strpos($currentScript, 'billing/invoices') !== false ? 'active' : '' ?>">
            <i class="bi bi-credit-card"></i>
            <span>Invoices & Billing</span>
        </a>

        <a href="<?= BASE_URL ?>/modules/reports/index.php" class="nav-link <?= strpos($currentScript, 'reports/') !== false ? 'active' : '' ?>">
            <i class="bi bi-graph-up-arrow"></i>
            <span>Analytics & Reports</span>
        </a>

        <?php if (in_array($primaryRole, ['super_admin', 'hospital_admin'], true)): ?>
            <span class="nav-section-label mt-2">Administration</span>
            <a href="<?= BASE_URL ?>/modules/admin/users.php" class="nav-link <?= strpos($currentScript, 'admin/users') !== false ? 'active' : '' ?>">
                <i class="bi bi-shield-person"></i>
                <span>Staff & Roles</span>
            </a>
            <a href="<?= BASE_URL ?>/modules/admin/departments.php" class="nav-link <?= strpos($currentScript, 'admin/departments') !== false ? 'active' : '' ?>">
                <i class="bi bi-diagram-3"></i>
                <span>Departments</span>
            </a>
            <a href="<?= BASE_URL ?>/modules/admin/salaries.php" class="nav-link <?= strpos($currentScript, 'admin/salaries') !== false ? 'active' : '' ?>">
                <i class="bi bi-wallet2"></i>
                <span>Salary Management</span>
            </a>
        <?php endif; ?>
    </nav>
</aside>
