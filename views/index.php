<?php
/**
 * KVM Hospital Management System — NexusCare
 * Public Overview & Gateway Landing Page
 */

declare(strict_types=1);

require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/helpers/functions.php';

if (is_logged_in()) {
    $primaryRole = $_SESSION['user']['primary_role'] ?? 'hospital_admin';
    redirect(get_dashboard_route_for_role($primaryRole));
}

// Fetch database metrics (or fallback safely if cloud database is not connected yet)
$db = get_db_or_null();
$isDbConnected = false;
$patientCount = 248;
$doctorCount  = 18;
$deptCount    = 15;
$bedStats     = ['total' => 60, 'available' => 42, 'occupied' => 18];
$activeAlerts = 2;

if ($db !== null) {
    try {
        $patientCount = (int)$db->query("SELECT COUNT(*) FROM `patient_profiles`")->fetchColumn();
        $doctorCount  = (int)$db->query("SELECT COUNT(*) FROM `doctor_profiles`")->fetchColumn();
        $deptCount    = (int)$db->query("SELECT COUNT(*) FROM `departments` WHERE status = 'active'")->fetchColumn();
        $fetchedBedStats = $db->query("
            SELECT 
                COUNT(*) AS total,
                SUM(CASE WHEN status = 'available' THEN 1 ELSE 0 END) AS available,
                SUM(CASE WHEN status = 'occupied' THEN 1 ELSE 0 END) AS occupied
            FROM `beds`
        ")->fetch();
        if ($fetchedBedStats) {
            $bedStats = $fetchedBedStats;
        }
        $activeAlerts = (int)$db->query("SELECT COUNT(*) FROM `flowlens_alerts` WHERE status = 'active'")->fetchColumn();
        $isDbConnected = true;
    } catch (Throwable $e) {
        error_log("Landing page DB query error: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= APP_NAME ?> — Clinical & Operations Intelligence</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .hero-banner {
            background: linear-gradient(135deg, #081120 0%, #0f1e36 40%, #1e3a8a 100%);
            color: #fff;
            padding: 5rem 0 4rem;
            position: relative;
            overflow: hidden;
        }
        .hero-banner::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 40px;
            background: linear-gradient(to top, #f8fafc, transparent);
        }
        .feature-card {
            background: #fff;
            border-radius: 14px;
            border: 1px solid var(--nexus-border);
            padding: 2rem;
            height: 100%;
            transition: all 0.25s ease;
            box-shadow: var(--nexus-shadow-sm);
        }
        .feature-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--nexus-shadow-lg);
            border-color: var(--nexus-teal-500);
        }
    </style>
</head>
<body style="background-color: var(--nexus-surface-bg);">

<!-- Top Navigation -->
<nav class="navbar navbar-expand-lg bg-white border-bottom sticky-top py-3">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center gap-2" href="index.php">
            <div class="brand-icon" style="width: 38px; height: 38px; font-size: 1.25rem;">
                <i class="bi bi-hospital"></i>
            </div>
            <div>
                <span class="fw-bold fs-5 text-dark">NexusCare</span>
                <span class="badge bg-primary ms-1" style="font-size: 0.65rem;">DBMS Project</span>
            </div>
        </a>

        <div class="d-flex align-items-center gap-2">
            <a href="register.php" class="btn btn-outline-secondary btn-sm px-3">Patient Registration</a>
            <a href="login.php" class="btn btn-nexus-primary btn-sm px-4">Sign In</a>
        </div>
    </div>
</nav>

<!-- Hero Section -->
<header class="hero-banner">
    <div class="container text-center py-3">
        <div class="d-inline-flex align-items-center gap-2 px-3 py-1 rounded-pill mb-3" style="background: rgba(20, 184, 166, 0.15); border: 1px solid rgba(20, 184, 166, 0.3);">
            <i class="bi bi-database-check text-info"></i>
            <span class="text-light small fw-semibold">Centralized Relational MySQL Schema (kvm_hospital)</span>
            <?php if (!$isDbConnected): ?>
                <span class="badge bg-warning text-dark ms-1" style="font-size: 0.65rem;" title="MySQL connection pending. Connect MySQL via Vercel Environment Variables.">Demo Preview</span>
            <?php else: ?>
                <span class="badge bg-success ms-1" style="font-size: 0.65rem;">Live DB Connected</span>
            <?php endif; ?>
        </div>
        <h1 class="display-5 fw-bold text-white mb-3">
            KVM Hospital Management System
        </h1>
        <p class="lead text-light opacity-75 mx-auto mb-4" style="max-width: 760px;">
            A centralized healthcare operations platform connecting 89 hospital departments through one common authentication system and 5 differentiated clinical intelligence engines.
        </p>

        <div class="d-flex justify-content-center gap-3 mb-5">
            <a href="login.php" class="btn btn-nexus-primary px-4 py-2 fs-6">
                <i class="bi bi-box-arrow-in-right me-2"></i>Access Unified Login
            </a>
            <a href="#features" class="btn btn-outline-light px-4 py-2 fs-6">
                Explore Architecture
            </a>
        </div>

        <!-- Realtime Live Stats Bar from Database -->
        <div class="row g-3 justify-content-center" style="max-width: 900px; margin: 0 auto;">
            <div class="col-6 col-md-3">
                <div class="bg-white bg-opacity-10 p-3 rounded-3 border border-white border-opacity-10 text-center">
                    <h3 class="fw-bold mb-0 text-white"><?= $patientCount ?></h3>
                    <small class="text-light opacity-75">Registered Patients</small>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="bg-white bg-opacity-10 p-3 rounded-3 border border-white border-opacity-10 text-center">
                    <h3 class="fw-bold mb-0 text-white"><?= $doctorCount ?></h3>
                    <small class="text-light opacity-75">Attending Doctors</small>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="bg-white bg-opacity-10 p-3 rounded-3 border border-white border-opacity-10 text-center">
                    <h3 class="fw-bold mb-0 text-white"><?= (int)($bedStats['available'] ?? 0) ?> / <?= (int)($bedStats['total'] ?? 0) ?></h3>
                    <small class="text-light opacity-75">Available Beds</small>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="bg-white bg-opacity-10 p-3 rounded-3 border border-white border-opacity-10 text-center">
                    <h3 class="fw-bold mb-0 text-white"><?= $deptCount ?></h3>
                    <small class="text-light opacity-75">Clinical Depts</small>
                </div>
            </div>
        </div>
    </div>
</header>

<!-- 5 Differentiated Features Section -->
<section class="py-5" id="features">
    <div class="container py-4">
        <div class="text-center mb-5">
            <span class="text-uppercase text-teal fw-bold small" style="color: var(--nexus-teal-600); letter-spacing: 0.08em;">College Project Differentiation</span>
            <h2 class="fw-bold mt-1">Five Advanced Clinical Intelligence Engines</h2>
            <p class="text-secondary mx-auto" style="max-width: 650px;">
                Engineered beyond basic CRUD operations to demonstrate advanced database design, transactions, atomic queueing, and operational monitoring.
            </p>
        </div>

        <div class="row g-4">
            <!-- 1. QueueFlow -->
            <div class="col-md-6 col-lg-4">
                <div class="feature-card">
                    <div class="stat-icon teal mb-3">
                        <i class="bi bi-hourglass-split"></i>
                    </div>
                    <h5 class="fw-bold mb-2">1. QueueFlow</h5>
                    <div class="badge bg-light text-primary border mb-2">Live Flow Intelligence</div>
                    <p class="text-secondary small mb-3">
                        Atomic token allocation per doctor session, dynamic waiting time estimation based on historical consultation duration, and auditable priority overrides.
                    </p>
                    <ul class="list-unstyled small text-muted mb-0">
                        <li><i class="bi bi-check2 text-success me-1"></i>Race-safe token assignment</li>
                        <li><i class="bi bi-check2 text-success me-1"></i>Realtime AJAX auto-polling</li>
                    </ul>
                </div>
            </div>

            <!-- 2. BedMatrix -->
            <div class="col-md-6 col-lg-4">
                <div class="feature-card">
                    <div class="stat-icon blue mb-3">
                        <i class="bi bi-grid-3x3-gap"></i>
                    </div>
                    <h5 class="fw-bold mb-2">2. BedMatrix</h5>
                    <div class="badge bg-light text-primary border mb-2">Dynamic Bed Allocation</div>
                    <p class="text-secondary small mb-3">
                        Transaction-safe bed allocation preventing double booking via row-level locks, care-level compatibility matching, and sanitation state lifecycle tracking.
                    </p>
                    <ul class="list-unstyled small text-muted mb-0">
                        <li><i class="bi bi-check2 text-success me-1"></i>Row-locking concurrency protection</li>
                        <li><i class="bi bi-check2 text-success me-1"></i>Cleaning & transfer management</li>
                    </ul>
                </div>
            </div>

            <!-- 3. CareTrail -->
            <div class="col-md-6 col-lg-4">
                <div class="feature-card">
                    <div class="stat-icon emerald mb-3">
                        <i class="bi bi-signpost-split"></i>
                    </div>
                    <h5 class="fw-bold mb-2">3. CareTrail</h5>
                    <div class="badge bg-light text-primary border mb-2">Unified Patient Journey</div>
                    <p class="text-secondary small mb-3">
                        Chronological encounter audit connecting patient triage, queue entry, doctor notes, diagnostic labs, pharmacy dispensing, bed admissions, and billing.
                    </p>
                    <ul class="list-unstyled small text-muted mb-0">
                        <li><i class="bi bi-check2 text-success me-1"></i>Cross-department event linkage</li>
                        <li><i class="bi bi-check2 text-success me-1"></i>Chronological clinical timeline</li>
                    </ul>
                </div>
            </div>

            <!-- 4. FlowLens -->
            <div class="col-md-6 col-lg-6">
                <div class="feature-card">
                    <div class="stat-icon amber mb-3">
                        <i class="bi bi-radar"></i>
                    </div>
                    <h5 class="fw-bold mb-2">4. FlowLens</h5>
                    <div class="badge bg-light text-warning border mb-2">Hospital Bottleneck Detector</div>
                    <p class="text-secondary small mb-3">
                        Explainable rule-based operational surveillance querying database metrics against configured thresholds for queue wait delays, ICU bed saturation, and lab backlogs with resolution logging.
                    </p>
                    <ul class="list-unstyled small text-muted mb-0">
                        <li><i class="bi bi-check2 text-success me-1"></i>Zero external AI hallucinations — 100% database-driven</li>
                        <li><i class="bi bi-check2 text-success me-1"></i>Accountable resolution notes and history</li>
                    </ul>
                </div>
            </div>

            <!-- 5. TrustLedger -->
            <div class="col-md-6 col-lg-6">
                <div class="feature-card">
                    <div class="stat-icon danger mb-3">
                        <i class="bi bi-journal-check"></i>
                    </div>
                    <h5 class="fw-bold mb-2">5. TrustLedger</h5>
                    <div class="badge bg-light text-danger border mb-2">Smart DBMS Audit & Integrity</div>
                    <p class="text-secondary small mb-3">
                        Comprehensive immutable audit trail logging user actions, security attempts, role changes, and clinical modifications while strictly enforcing relational integrity constraints.
                    </p>
                    <ul class="list-unstyled small text-muted mb-0">
                        <li><i class="bi bi-check2 text-success me-1"></i>Foreign keys, CHECK constraints & transactions</li>
                        <li><i class="bi bi-check2 text-success me-1"></i>Negative inventory & collision prevention</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Viva / Demo Login Shortcuts -->
<section class="py-5 bg-white border-top">
    <div class="container text-center">
        <h4 class="fw-bold mb-2">Viva & Technical Demonstration Access</h4>
        <p class="text-muted small mb-4">All 9 department roles share one common login. Click below to sign in instantly.</p>
        
        <div class="d-flex flex-wrap justify-content-center gap-2" style="max-width: 800px; margin: 0 auto;">
            <a href="login.php" class="btn btn-outline-dark btn-sm">Super Admin</a>
            <a href="login.php" class="btn btn-outline-primary btn-sm">Hospital Admin</a>
            <a href="login.php" class="btn btn-outline-info text-dark btn-sm">Doctor (Cardiology)</a>
            <a href="login.php" class="btn btn-outline-secondary btn-sm">Receptionist</a>
            <a href="login.php" class="btn btn-outline-warning text-dark btn-sm">Nurse</a>
            <a href="login.php" class="btn btn-outline-success btn-sm">Lab Technician</a>
            <a href="login.php" class="btn btn-outline-danger btn-sm">Pharmacist</a>
            <a href="login.php" class="btn btn-outline-primary btn-sm">Billing Staff</a>
            <a href="login.php" class="btn btn-outline-secondary btn-sm">Patient Portal</a>
        </div>
    </div>
</section>

<footer class="py-4 bg-light border-top text-center text-muted small">
    <div class="container">
        <?= APP_NAME ?> — <?= APP_TAGLINE ?> | Database: MySQL 8.x / InnoDB | College DBMS Capstone
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
