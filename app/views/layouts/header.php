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
</head>
<body>
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
                <div class="d-none d-md-flex flex-column">
                    <span class="fw-bold fs-6 text-dark"><?= e($pageTitle) ?></span>
                    <small class="text-muted" style="font-size: 0.75rem;">NexusCare Central Intelligence Platform</small>
                </div>
            </div>

            <div class="d-flex align-items-center gap-3">
                <!-- FlowLens Realtime Alert Indicator -->
                <?php if ($activeAlertCount > 0 && has_role('super_admin', 'hospital_admin', 'doctor', 'nurse')): ?>
                    <a href="<?= BASE_URL ?>/modules/flowlens/index.php" class="btn btn-sm btn-outline-danger position-relative px-2 py-1" title="FlowLens Active Bottlenecks">
                        <i class="bi bi-exclamation-triangle-fill me-1"></i>
                        <span class="d-none d-sm-inline">FlowLens</span>
                        <span class="badge bg-danger rounded-pill ms-1"><?= $activeAlertCount ?></span>
                    </a>
                <?php endif; ?>

                <!-- User Badge -->
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
                                <strong class="text-dark"><?= e($currentUser['email']) ?></strong>
                            </li>
                            <li>
                                <a class="dropdown-item py-2" href="<?= get_dashboard_route_for_role($primaryRole) ?>">
                                    <i class="bi bi-speedometer2 me-2 text-primary"></i>My Dashboard
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
