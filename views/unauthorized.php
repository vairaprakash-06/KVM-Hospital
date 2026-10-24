<?php
/**
 * KVM Hospital Management System — NexusCare
 * Access Denied & Security Boundary Notice
 */

declare(strict_types=1);

require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/helpers/functions.php';

$user = current_user();
$required = $_GET['required'] ?? 'elevated role';
$perm = $_GET['perm'] ?? '';
$reason = $_GET['reason'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Access Denied — NexusCare Security Boundary</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .unauth-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f8fafc;
            padding: 1.5rem;
        }
        .unauth-card {
            background: #fff;
            border-radius: 14px;
            border: 1px solid var(--nexus-border);
            box-shadow: var(--nexus-shadow-lg);
            max-width: 580px;
            width: 100%;
            padding: 2.5rem;
            text-align: center;
        }
        .shield-icon {
            width: 72px;
            height: 72px;
            border-radius: 50%;
            background: #fee2e2;
            color: #ef4444;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 2.2rem;
            margin-bottom: 1.25rem;
        }
    </style>
</head>
<body class="unauth-container">

<div class="unauth-card">
    <div class="shield-icon">
        <i class="bi bi-shield-slash-fill"></i>
    </div>
    
    <h3 class="fw-bold text-dark mb-2">Access Restricted (HTTP 403)</h3>
    <p class="text-secondary mb-4" style="font-size: 0.95rem;">
        Server-side role verification prevented access to this protected clinical module.
    </p>

    <div class="bg-light p-3 rounded text-start mb-4 border" style="font-size: 0.85rem;">
        <div class="mb-1"><strong>Authenticated User:</strong> <?= e($user['username'] ?? 'Anonymous') ?></div>
        <div class="mb-1"><strong>Current Active Role:</strong> <span class="badge bg-secondary"><?= e($user['primary_role'] ?? 'none') ?></span></div>
        <?php if ($required): ?>
            <div class="mb-1"><strong>Required Role(s):</strong> <span class="text-danger fw-semibold"><?= e(str_replace('_', ' ', $required)) ?></span></div>
        <?php endif; ?>
        <?php if ($perm): ?>
            <div><strong>Missing Permission:</strong> <code><?= e($perm) ?></code></div>
        <?php endif; ?>
    </div>

    <div class="d-flex justify-content-center gap-2">
        <?php if (is_logged_in()): ?>
            <a href="<?= get_dashboard_route_for_role($user['primary_role']) ?>" class="btn btn-nexus-primary">
                <i class="bi bi-speedometer2 me-1"></i>Return to My Dashboard
            </a>
            <a href="logout.php" class="btn btn-outline-danger">Sign Out</a>
        <?php else: ?>
            <a href="login.php" class="btn btn-nexus-primary">Sign In with Authorized Account</a>
        <?php endif; ?>
    </div>
</div>

</body>
</html>
