<?php
/**
 * KVM Hospital Management System — NexusCare
 * Central Common Authentication Entry Point
 */

declare(strict_types=1);

require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/helpers/functions.php';
require_once __DIR__ . '/../app/middleware/csrf.php';
require_once __DIR__ . '/../app/services/AuditService.php';

$db = get_db_or_null();
$auditService = $db !== null ? new AuditService($db) : null;
$errorMessage = '';

if ($db === null) {
    $errorMessage = 'Notice: Centralized MySQL database is currently disconnected. In your Vercel Project Settings, add DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD environment variables to enable authentication.';
}

// If already logged in, redirect to respective dashboard
if (is_logged_in()) {
    $primaryRole = $_SESSION['user']['primary_role'] ?? 'hospital_admin';
    redirect(get_dashboard_route_for_role($primaryRole));
}

// Process POST Login Request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($db === null) {
        $errorMessage = "Cannot authenticate: Database connection is required. Please add MySQL credentials in your Vercel Project Settings > Environment Variables.";
    } else {
        verify_csrf();

    $identifier = trim($_POST['identifier'] ?? '');
    $password = (string)($_POST['password'] ?? '');
    $ip = get_client_ip();

    // 1. Throttling / Lockout check (Max 5 failed attempts in 15 mins)
    $failedCount = $auditService->getRecentFailedAttemptsCount($ip, 15);
    if ($failedCount >= 5) {
        $errorMessage = "Too many failed login attempts from your IP address. For hospital security, login is locked for 15 minutes.";
        $auditService->recordLoginAttempt($identifier, 'locked_out');
    } elseif (empty($identifier) || empty($password)) {
        $errorMessage = "Please enter both your hospital username or email and password.";
    } else {
        // 2. Fetch user
        $stmt = $db->prepare("
            SELECT * FROM `users`
            WHERE `username` = :id1 OR `email` = :id2
            LIMIT 1
        ");
        $stmt->execute(['id1' => $identifier, 'id2' => $identifier]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            // 3. Check account status
            if ($user['account_status'] !== 'active') {
                $auditService->recordLoginAttempt($identifier, 'failed');
                $errorMessage = "Your hospital account is currently {$user['account_status']}. Contact administration.";
            } else {
                // 4. Fetch User Roles
                $roleStmt = $db->prepare("
                    SELECT r.role_name, r.display_name
                    FROM `user_role_assignments` ura
                    JOIN `roles` r ON ura.role_id = r.id
                    WHERE ura.user_id = :uid
                    ORDER BY r.id ASC
                ");
                $roleStmt->execute(['uid' => $user['id']]);
                $roles = $roleStmt->fetchAll();

                $roleNames = array_column($roles, 'role_name');
                if (empty($roleNames)) {
                    $roleNames = ['patient'];
                }
                $primaryRole = $roleNames[0];

                // Role Restriction: Keep login ONLY for Admin, Doctor, and Patient
                $allowedLoginRoles = ['super_admin', 'hospital_admin', 'doctor', 'patient'];
                $hasAllowedRole = !empty(array_intersect($roleNames, $allowedLoginRoles));

                if (!$hasAllowedRole) {
                    $auditService->recordLoginAttempt($identifier, 'failed');
                    $errorMessage = "Access Restricted: Operational stations (Reception Desk, Nursing Station, Diagnostic Lab, Pharmacy Unit, Billing Center) are consolidated under the Administrator portal. Please sign in with an Administrator account.";
                } else {
                    // 5. Fetch Permissions for these roles
                $permStmt = $db->prepare("
                    SELECT DISTINCT p.permission_key
                    FROM `user_role_assignments` ura
                    JOIN `role_permissions` rp ON ura.role_id = rp.role_id
                    JOIN `permissions` p ON rp.permission_id = p.id
                    WHERE ura.user_id = :uid
                ");
                $permStmt->execute(['uid' => $user['id']]);
                $permissions = $permStmt->fetchAll(PDO::FETCH_COLUMN);

                // 6. Fetch Full Name and Linked Entity IDs (Doctor ID, Patient ID, Staff ID)
                $fullName = $user['username'];
                $doctorId = null;
                $patientId = null;
                $staffId = null;

                // Check doctor profile
                $dStmt = $db->prepare("SELECT id, first_name, last_name, doctor_code FROM `doctor_profiles` WHERE user_id = :uid");
                $dStmt->execute(['uid' => $user['id']]);
                if ($doc = $dStmt->fetch()) {
                    $fullName = "Dr. " . $doc['first_name'] . " " . $doc['last_name'];
                    $doctorId = (int)$doc['id'];
                }

                // Check staff profile
                $sStmt = $db->prepare("SELECT id, first_name, last_name, designation FROM `staff_profiles` WHERE user_id = :uid");
                $sStmt->execute(['uid' => $user['id']]);
                if ($staff = $sStmt->fetch()) {
                    $fullName = $staff['first_name'] . " " . $staff['last_name'];
                    $staffId = (int)$staff['id'];
                }

                // Check patient profile
                $pStmt = $db->prepare("SELECT id, first_name, last_name, patient_id FROM `patient_profiles` WHERE user_id = :uid");
                $pStmt->execute(['uid' => $user['id']]);
                if ($pat = $pStmt->fetch()) {
                    $fullName = $pat['first_name'] . " " . $pat['last_name'];
                    $patientId = (int)$pat['id'];
                }

                // 7. Secure Session Creation & ID Regeneration
                session_regenerate_id(true);

                $_SESSION['user'] = [
                    'id'           => (int)$user['id'],
                    'username'     => $user['username'],
                    'email'        => $user['email'],
                    'full_name'    => $fullName,
                    'roles'        => $roleNames,
                    'primary_role' => $primaryRole,
                    'permissions'  => $permissions,
                    'doctor_id'    => $doctorId,
                    'patient_id'   => $patientId,
                    'staff_id'     => $staffId,
                    'logged_at'    => time()
                ];

                // Update user last login
                $db->prepare("UPDATE `users` SET `last_login_at` = NOW(), `failed_login_attempts` = 0 WHERE `id` = :uid")
                   ->execute(['uid' => $user['id']]);

                // Record security audit
                $auditService->recordLoginAttempt($identifier, 'success');
                $auditService->log(
                    actionType: 'LOGIN',
                    entityType: 'UserSession',
                    entityId: (int)$user['id'],
                    description: "User {$user['username']} logged in with role [{$primaryRole}].",
                    userId: (int)$user['id'],
                    roleName: $primaryRole
                );

                // Redirect to intended or role destination
                $intendedUrl = $_SESSION['intended_url'] ?? '';
                unset($_SESSION['intended_url']);

                if ($intendedUrl && !str_contains($intendedUrl, 'login.php')) {
                    redirect($intendedUrl);
                } else {
                    redirect(get_dashboard_route_for_role($primaryRole));
                }
            }
            }
        } else {
            // Failed password or user not found
            $auditService->recordLoginAttempt($identifier, 'failed');
            $errorMessage = "Invalid credentials. Please verify your username/email and password.";
        }
    }
}
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NexusCare Common Portal Sign-In — KVM Hospital</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .login-page {
            min-height: 100vh;
            background: radial-gradient(circle at 10% 20%, #0f1e36 0%, #081120 90%);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
        }
        .login-card {
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.35);
            max-width: 980px;
            width: 100%;
            overflow: hidden;
            display: flex;
            flex-wrap: wrap;
        }
        .login-banner {
            background: linear-gradient(135deg, #0f1e36 0%, #1e3a8a 50%, #0d9488 100%);
            color: #fff;
            padding: 3rem 2.5rem;
            flex: 1 1 420px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .login-form-side {
            padding: 3rem 2.5rem;
            flex: 1 1 420px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .demo-pill {
            cursor: pointer;
            transition: all 0.2s ease;
            font-size: 0.72rem;
            padding: 0.35rem 0.65rem;
        }
        .demo-pill:hover {
            transform: translateY(-2px);
            box-shadow: 0 2px 6px rgba(0,0,0,0.15);
        }
    </style>
</head>
<body class="login-page">

<div class="login-card">
    <!-- Left Banner -->
    <div class="login-banner">
        <div>
            <div class="d-flex align-items-center gap-3 mb-4">
                <div class="brand-icon" style="width: 48px; height: 48px; font-size: 1.6rem;">
                    <i class="bi bi-hospital"></i>
                </div>
                <div>
                    <h3 class="mb-0 fw-bold">NexusCare</h3>
                    <small class="text-info text-uppercase fw-semibold" style="letter-spacing: 0.08em;">KVM Hospital Management</small>
                </div>
            </div>

            <h4 class="fw-bold mb-3">One Unified Hospital System Across All 15 Departments</h4>
            <p class="text-light opacity-75 fs-6 mb-4">
                Centralized relational MySQL architecture with live operations intelligence, automated data audit, and dynamic resource allocation.
            </p>

            <div class="d-flex flex-column gap-2 mb-4">
                <div class="d-flex align-items-center gap-2 text-light" style="font-size: 0.85rem;">
                    <i class="bi bi-check-circle-fill text-info"></i>
                    <span><strong>QueueFlow:</strong> Live patient queue tracking & waiting estimation</span>
                </div>
                <div class="d-flex align-items-center gap-2 text-light" style="font-size: 0.85rem;">
                    <i class="bi bi-check-circle-fill text-info"></i>
                    <span><strong>BedMatrix:</strong> Dynamic ICU & ward bed allocation with race-lock</span>
                </div>
                <div class="d-flex align-items-center gap-2 text-light" style="font-size: 0.85rem;">
                    <i class="bi bi-check-circle-fill text-info"></i>
                    <span><strong>CareTrail:</strong> End-to-end chronological patient encounter journey</span>
                </div>
                <div class="d-flex align-items-center gap-2 text-light" style="font-size: 0.85rem;">
                    <i class="bi bi-check-circle-fill text-info"></i>
                    <span><strong>FlowLens:</strong> Explainable rule-based bottleneck detector</span>
                </div>
                <div class="d-flex align-items-center gap-2 text-light" style="font-size: 0.85rem;">
                    <i class="bi bi-check-circle-fill text-info"></i>
                    <span><strong>TrustLedger:</strong> Smart DBMS audit trail & relational integrity</span>
                </div>
            </div>
        </div>

        <div class="border-top border-white border-opacity-10 pt-3 text-light opacity-50" style="font-size: 0.75rem;">
            DBMS Capstone Project &copy; <?= date('Y') ?> KVM Hospital System
        </div>
    </div>

    <!-- Right Login Form Side -->
    <div class="login-form-side">
        <div class="mb-4">
            <h4 class="fw-bold text-dark mb-1">Sign In to Your Workspace</h4>
            <p class="text-muted small">One common portal for Hospital Admin, Doctors, and Patients</p>
        </div>

        <?php if (!empty($errorMessage)): ?>
            <div class="alert alert-danger alert-dismissible fade show py-2" role="alert">
                <i class="bi bi-shield-x me-2"></i><?= e($errorMessage) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php elseif (Database::isSqlite()): ?>
            <div class="alert border-0 py-2 px-3 small d-flex align-items-center gap-2 mb-3" style="background: rgba(14, 165, 233, 0.08); border-left: 3px solid #0284c7 !important; color: #0369a1; border-radius: 8px;">
                <i class="bi bi-check-circle-fill text-primary fs-6"></i>
                <div class="flex-grow-1" style="font-size: 0.8rem; line-height: 1.35;">
                    <strong>Live Demonstration Database Active:</strong> Pre-seeded with 89 departments, 178 doctors &amp; 2,670 appointments across Admin, Doctor, and Patient portals.
                </div>
            </div>
        <?php endif; ?>

        <form method="POST" action="login.php">
            <?= csrf_field() ?>

            <div class="mb-3">
                <label for="identifier" class="form-label small fw-semibold text-secondary">Username or Email Address</label>
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0"><i class="bi bi-person text-muted"></i></span>
                    <input type="text" class="form-control border-start-0" id="identifier" name="identifier" 
                           placeholder="e.g. admin or dr.sharma" required autofocus
                           value="<?= e($_POST['identifier'] ?? '') ?>">
                </div>
            </div>

            <div class="mb-4">
                <label for="password" class="form-label small fw-semibold text-secondary">Password</label>
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0"><i class="bi bi-lock text-muted"></i></span>
                    <input type="password" class="form-control border-start-0" id="password" name="password" 
                           placeholder="Enter password" required>
                </div>
            </div>

            <button type="submit" class="btn btn-nexus-primary w-100 py-2 fs-6 mb-3">
                <i class="bi bi-box-arrow-in-right me-2"></i>Sign In to NexusCare
            </button>
        </form>

        <div class="text-center mb-4">
            <span class="text-muted small">New patient?</span>
            <a href="register.php" class="small fw-semibold text-decoration-none text-primary ms-1">Register Portal Account</a>
        </div>

        <!-- Quick Demo Accounts Shortcuts for Viva & Evaluation (Admin, Doctor, Patient Only) -->
        <div class="bg-light p-3 rounded border">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="fw-bold text-secondary text-uppercase" style="font-size: 0.68rem; letter-spacing: 0.05em;">
                    <i class="bi bi-lightning-charge-fill text-warning me-1"></i>VIVA DEMO ACCOUNTS (PASSWORD: PASSWORD123!)
                </span>
            </div>
            <div class="d-flex flex-wrap gap-2 mb-2">
                <span class="badge bg-dark demo-pill px-3 py-2" onclick="fillCreds('superadmin', 'Password123!')" title="Universal Master Governance">
                    <i class="bi bi-shield-lock me-1"></i>Super Admin
                </span>
                <span class="badge bg-primary demo-pill px-3 py-2" onclick="fillCreds('admin', 'Password123!')" title="Full Hospital & All Desks Access">
                    <i class="bi bi-speedometer2 me-1"></i>Hospital Admin
                </span>
                <span class="badge bg-info text-dark demo-pill px-3 py-2" onclick="fillCreds('dr.sharma', 'Password123!')" title="Doctor Workspace">
                    <i class="bi bi-heart-pulse me-1"></i>Dr. Sharma (Cardio)
                </span>
                <span class="badge bg-secondary text-white demo-pill px-3 py-2" onclick="fillCreds('patient.arun', 'Password123!')" title="Patient Self-Service Portal">
                    <i class="bi bi-person-badge me-1"></i>Patient Arun
                </span>
            </div>
            <div class="p-2 rounded bg-white border small text-muted" style="font-size: 0.72rem; line-height: 1.35;">
                <i class="bi bi-shield-check text-primary me-1"></i>
                <strong>Administrator Master Access:</strong> Reception Desk, Nursing Station, Diagnostic Lab, Pharmacy Unit, and Billing Center are unified directly under Admin login.
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function fillCreds(user, pass) {
        document.getElementById('identifier').value = user;
        document.getElementById('password').value = pass;
    }
</script>
</body>
</html>
