<?php
/**
 * KVM Hospital Management System — NexusCare
 * Shared Layout: Header & Topbar
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../helpers/functions.php';

$currentUser = current_user();
$primaryRole = $currentUser['primary_role'] ?? 'guest';
$pageTitle = $pageTitle ?? 'Hospital Operations';

// Count active FlowLens alerts for topbar indicator if logged in
$activeAlertCount = 0;
if (is_logged_in()) {
    try {
        $db = get_db();
        $stmt = $db->query("SELECT COUNT(*) FROM `flowlens_alerts` WHERE `status` = 'active'");
        $activeAlertCount = (int)$stmt->fetchColumn();
    } catch (Exception $e) {
        $activeAlertCount = 0;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> — <?= APP_NAME ?></title>
    <meta name="description" content="NexusCare: Advanced centralized hospital management system with QueueFlow, BedMatrix, CareTrail, FlowLens, and TrustLedger.">
    
    <!-- Bootstrap 5.3.2 & Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.min.css">
    
    <!-- Custom Healthcare SaaS Design System -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">

    <!-- Chart.js 4.4.0 -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
</head>
<body class="<?= !empty($darkTheme) ? 'dark-dashboard' : '' ?>">
<div class="app-wrapper">
    <!-- Sidebar -->
    <?php require_once __DIR__ . '/sidebar.php'; ?>

    <!-- Main Content Area -->
    <div class="main-content">
        <!-- Topbar -->
        <header class="topbar">
            <div class="d-flex align-items-center gap-3">
                <button class="btn btn-sm btn-outline-secondary d-lg-none" id="sidebarToggle" type="button">
                    <i class="bi bi-list fs-5"></i>
                </button>
                
                <?php if (!empty($darkTheme)): ?>
                    <!-- Executive Dark Dashboard Search Bar -->
                    <div class="position-relative d-none d-md-block">
                        <i class="bi bi-search position-absolute top-50 translate-middle-y text-secondary" style="left: 14px; z-index: 5;"></i>
                        <input type="text" class="form-control topbar-search-dark" placeholder="Search patients, token #, doctors, or modules..." id="globalDashboardSearch">
                    </div>
                <?php else: ?>
                    <div class="d-none d-md-flex flex-column">
                        <span class="fw-bold fs-6 text-dark"><?= e($pageTitle) ?></span>
                        <small class="text-muted" style="font-size: 0.75rem;">NexusCare Central Intelligence Platform</small>
                    </div>
                <?php endif; ?>
            </div>

            <div class="d-flex align-items-center gap-3">
                <?php if (!empty($darkTheme)): ?>
                    <!-- Notification Bell with Red Badge -->
                    <div class="dropdown">
                        <button type="button" class="btn-icon-dark" data-bs-toggle="dropdown" aria-expanded="false" title="Notifications">
                            <i class="bi bi-bell"></i>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger border border-dark" style="font-size: 0.65rem; padding: 0.25rem 0.45rem;">
                                3
                            </span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0 mt-2 bg-dark text-white p-2" style="min-width: 280px; font-size: 0.85rem;">
                            <li class="px-2 py-1 text-secondary small fw-bold text-uppercase">Recent Alerts</li>
                            <li><hr class="dropdown-divider border-secondary border-opacity-25 my-1"></li>
                            <li class="p-2 border-bottom border-secondary border-opacity-10 d-flex gap-2">
                                <i class="bi bi-exclamation-triangle-fill text-danger fs-6"></i>
                                <div>
                                    <div class="fw-bold text-white small">Low Stock Alert</div>
                                    <div class="text-secondary small">Amoxicillin 500mg (12 units left)</div>
                                </div>
                            </li>
                            <li class="p-2 border-bottom border-secondary border-opacity-10 d-flex gap-2">
                                <i class="bi bi-clock-history text-warning fs-6"></i>
                                <div>
                                    <div class="fw-bold text-white small">Expiring Soon</div>
                                    <div class="text-secondary small">Dolo 650mg (expires in 5 days)</div>
                                </div>
                            </li>
                            <li class="p-2 d-flex gap-2">
                                <i class="bi bi-info-circle-fill text-info fs-6"></i>
                                <div>
                                    <div class="fw-bold text-white small">New Lab Result</div>
                                    <div class="text-secondary small">Patient ID: PAT-2026-0043</div>
                                </div>
                            </li>
                        </ul>
                    </div>

                    <!-- User Badge (Doctor / Executive) -->
                    <?php 
                        $displayName = $currentUser['full_name'] ?? $currentUser['username'] ?? 'Dr. Anita Desai';
                        if ($primaryRole === 'doctor' && !str_starts_with($displayName, 'Dr.')) {
                            $displayName = 'Dr. ' . $displayName;
                        }
                    ?>
                    <div class="dropdown">
                        <div class="dark-user-badge" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <div class="rounded-circle d-flex align-items-center justify-content-center bg-info bg-opacity-25 text-info fw-bold" style="width: 32px; height: 32px; font-size: 0.85rem; border: 1px solid rgba(14, 165, 233, 0.4);">
                                <i class="bi bi-person-fill"></i>
                            </div>
                            <div class="d-none d-sm-flex flex-column text-start">
                                <span class="fw-semibold text-white" style="font-size: 0.82rem; line-height: 1.1;">
                                    <?= e($displayName) ?>
                                </span>
                                <span class="text-secondary" style="font-size: 0.7rem;">
                                    <?= e(ucfirst(str_replace('_', ' ', $primaryRole))) ?>
                                </span>
                            </div>
                            <i class="bi bi-chevron-down text-secondary ms-1" style="font-size: 0.7rem;"></i>
                        </div>
                        <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0 mt-2 bg-dark text-white">
                            <li class="px-3 py-2 border-bottom border-secondary border-opacity-25">
                                <small class="text-secondary d-block">Signed in as</small>
                                <strong class="text-info"><?= e($currentUser['email'] ?? $currentUser['username'] ?? 'Doctor') ?></strong>
                            </li>
                            <li>
                                <a class="dropdown-item py-2 text-white" href="<?= BASE_URL ?>/dashboard.php">
                                    <i class="bi bi-speedometer2 me-2 text-primary"></i>Executive Dashboard
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item py-2 text-white" href="<?= BASE_URL ?>/modules/queueflow/index.php">
                                    <i class="bi bi-hourglass-split me-2 text-info"></i>Live QueueFlow
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item py-2 text-white" href="<?= BASE_URL ?>/modules/trustledger/index.php">
                                    <i class="bi bi-shield-check me-2 text-success"></i>TrustLedger Audit
                                </a>
                            </li>
                            <li><hr class="dropdown-divider border-secondary border-opacity-25"></li>
                            <li>
                                <a class="dropdown-item py-2 text-danger" href="<?= BASE_URL ?>/logout.php">
                                    <i class="bi bi-box-arrow-right me-2"></i>Sign Out
                                </a>
                            </li>
                        </ul>
                    </div>

                    <!-- Live Date & Clock -->
                    <div class="dark-clock-display d-none d-md-flex">
                        <span id="liveClockDate"><?= date('M d, Y') ?></span>
                        <span class="time" id="liveClockTime"><?= date('h:i A') ?></span>
                    </div>

                    <script>
                        function updateLiveClock() {
                            const now = new Date();
                            const options = { month: 'short', day: 'numeric', year: 'numeric' };
                            const dateEl = document.getElementById('liveClockDate');
                            const timeEl = document.getElementById('liveClockTime');
                            if (dateEl) dateEl.textContent = now.toLocaleDateString('en-US', options);
                            if (timeEl) {
                                let hours = now.getHours();
                                const minutes = String(now.getMinutes()).padStart(2, '0');
                                const ampm = hours >= 12 ? 'PM' : 'AM';
                                hours = hours % 12;
                                hours = hours ? String(hours).padStart(2, '0') : '12';
                                timeEl.textContent = `${hours}:${minutes} ${ampm}`;
                            }
                        }
                        setInterval(updateLiveClock, 1000);
                    </script>

                <?php else: ?>
                    <!-- Standard Light Topbar Indicators -->
                    <?php if ($activeAlertCount > 0 && has_role('super_admin', 'hospital_admin', 'doctor', 'nurse')): ?>
                        <a href="<?= BASE_URL ?>/modules/flowlens/index.php" class="btn btn-sm btn-outline-danger position-relative px-2 py-1" title="FlowLens Active Bottlenecks">
                            <i class="bi bi-exclamation-triangle-fill me-1"></i>
                            <span class="d-none d-sm-inline">FlowLens</span>
                            <span class="badge bg-danger rounded-pill ms-1"><?= $activeAlertCount ?></span>
                        </a>
                    <?php endif; ?>

                    <?php if ($currentUser): ?>
                        <div class="dropdown">
                            <div class="user-badge" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <div class="user-avatar">
                                    <?= strtoupper(substr($currentUser['username'] ?? 'U', 0, 1)) ?>
                                </div>
                                <div class="d-none d-sm-flex flex-column text-start">
                                    <span class="fw-semibold text-dark" style="font-size: 0.85rem; line-height: 1.1;">
                                        <?= e($currentUser['full_name'] ?? $currentUser['username']) ?>
                                    </span>
                                    <span class="badge bg-primary text-uppercase" style="font-size: 0.65rem; width: fit-content; margin-top: 2px;">
                                        <?= e(str_replace('_', ' ', $primaryRole)) ?>
                                    </span>
                                </div>
                                <i class="bi bi-chevron-down text-muted ms-1" style="font-size: 0.75rem;"></i>
                            </div>
                            <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 mt-2">
                                <li class="px-3 py-2 border-bottom">
                                    <small class="text-muted d-block">Signed in as</small>
                                    <strong class="text-dark"><?= e($currentUser['email'] ?? $currentUser['username'] ?? 'Staff') ?></strong>
                                </li>
                                <li>
                                    <a class="dropdown-item py-2" href="<?= BASE_URL ?>/dashboard.php">
                                        <i class="bi bi-speedometer2 me-2 text-primary"></i>Executive Dashboard
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item py-2" href="<?= BASE_URL ?>/modules/trustledger/index.php">
                                        <i class="bi bi-shield-check me-2 text-success"></i>TrustLedger Audit
                                    </a>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <a class="dropdown-item py-2 text-danger" href="<?= BASE_URL ?>/logout.php">
                                        <i class="bi bi-box-arrow-right me-2"></i>Sign Out
                                    </a>
                                </li>
                            </ul>
                        </div>
                    <?php else: ?>
                        <a href="<?= BASE_URL ?>/login.php" class="btn btn-nexus-primary btn-sm">Sign In</a>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </header>

        <!-- Flash Notice Alerts -->
        <div class="container-fluid px-4 pt-3">
            <?php $flash = get_flash(); if ($flash): ?>
                <div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show shadow-sm" role="alert">
                    <i class="bi <?= $flash['type'] === 'success' ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill' ?> me-2"></i>
                    <?= e($flash['message']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="content-body flex-grow-1 px-4 py-3">
