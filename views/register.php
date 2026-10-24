<?php
/**
 * KVM Hospital Management System — NexusCare
 * Public Patient Registration Portal
 */

declare(strict_types=1);

require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/helpers/functions.php';
require_once __DIR__ . '/../app/middleware/csrf.php';
require_once __DIR__ . '/../app/services/AuditService.php';
require_once __DIR__ . '/../app/services/CareTrailService.php';

$db = get_db_or_null();
$audit = $db !== null ? new AuditService($db) : null;
$careTrail = $db !== null ? new CareTrailService($db) : null;
$error = '';
$success = '';

if ($db === null) {
    $error = 'Notice: Centralized MySQL database is currently disconnected. In your Vercel Project Settings, add DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD environment variables to register live accounts.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($db === null) {
        $error = "Registration requires an active MySQL connection. Please add database credentials in Vercel.";
    } else {
        verify_csrf();

    $firstName = sanitize_input($_POST['first_name'] ?? '');
    $lastName  = sanitize_input($_POST['last_name'] ?? '');
    $dob       = trim($_POST['dob'] ?? '');
    $gender    = trim($_POST['gender'] ?? '');
    $blood     = trim($_POST['blood_group'] ?? 'Unknown');
    $phone     = sanitize_input($_POST['phone'] ?? '');
    $email     = sanitize_input($_POST['email'] ?? '');
    $address   = sanitize_input($_POST['address'] ?? '');
    $emName    = sanitize_input($_POST['emergency_contact_name'] ?? '');
    $emPhone   = sanitize_input($_POST['emergency_contact_phone'] ?? '');
    $username  = sanitize_input($_POST['username'] ?? '');
    $password  = (string)($_POST['password'] ?? '');

    if (empty($firstName) || empty($lastName) || empty($dob) || empty($gender) || empty($phone) || empty($username) || empty($password)) {
        $error = "Please fill in all required fields marked with an asterisk (*).";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters long.";
    } else {
        // Check username / email uniqueness
        $chk = $db->prepare("SELECT id FROM `users` WHERE `username` = :u OR `email` = :e");
        $chk->execute(['u' => $username, 'e' => $email]);
        if ($chk->fetch()) {
            $error = "The selected username or email address is already registered in NexusCare.";
        } else {
            $db->beginTransaction();
            try {
                // 1. Create User account
                $passHash = password_hash($password, PASSWORD_BCRYPT);
                $insUser = $db->prepare("
                    INSERT INTO `users` (`username`, `email`, `password_hash`, `account_status`)
                    VALUES (:u, :e, :p, 'active')
                ");
                $insUser->execute(['u' => $username, 'e' => $email, 'p' => $passHash]);
                $userId = (int)$db->lastInsertId();

                // 2. Assign Patient Role (id = 9)
                $db->prepare("INSERT INTO `user_role_assignments` (`user_id`, `role_id`) VALUES (:uid, 9)")
                   ->execute(['uid' => $userId]);

                // 3. Generate Unique Patient Identifier: PAT-YYYY-XXXX
                $patientCode = generate_patient_id($db);

                // 4. Create Patient Profile
                $insPat = $db->prepare("
                    INSERT INTO `patient_profiles` (
                        `user_id`, `patient_id`, `first_name`, `last_name`, `dob`, `gender`,
                        `blood_group`, `phone`, `email`, `address`, `emergency_contact_name`,
                        `emergency_contact_phone`, `created_at`
                    ) VALUES (
                        :uid, :pid, :fn, :ln, :dob, :gender,
                        :bg, :phone, :email, :addr, :em_name,
                        :em_phone, NOW()
                    )
                ");
                $insPat->execute([
                    'uid'      => $userId,
                    'pid'      => $patientCode,
                    'fn'       => $firstName,
                    'ln'       => $lastName,
                    'dob'      => $dob,
                    'gender'   => $gender,
                    'bg'       => $blood,
                    'phone'    => $phone,
                    'email'    => $email,
                    'addr'     => $address,
                    'em_name'  => $emName,
                    'em_phone' => $emPhone
                ]);
                $patientRecordId = (int)$db->lastInsertId();

                // 5. CareTrail Initial Milestone Event
                $careTrail->recordEvent(
                    patientId: $patientRecordId,
                    eventType: 'registration',
                    eventTitle: "Patient Registered: {$patientCode}",
                    eventDescription: "Patient self-registered via online portal. Demographic records, emergency contacts, and portal account established.",
                    sourceTable: 'patient_profiles',
                    sourceId: $patientRecordId,
                    severity: 'info',
                    createdBy: $userId
                );

                // 6. TrustLedger Audit
                $audit->log(
                    actionType: 'CREATE',
                    entityType: 'PatientProfile',
                    entityId: $patientRecordId,
                    description: "Self-registered new patient {$firstName} {$lastName} ({$patientCode})",
                    userId: $userId,
                    roleName: 'patient'
                );

                $db->commit();
                $success = "Registration completed successfully! Your Patient ID is <strong>{$patientCode}</strong>. You may now sign in.";
            } catch (Exception $e) {
                $db->rollBack();
                error_log("Patient registration error: " . $e->getMessage());
                $error = "Registration failed due to a system error: " . $e->getMessage();
            }
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
    <title>Patient Portal Registration — NexusCare</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body style="background-color: #f1f5f9; min-height: 100vh;" class="py-5">

<div class="container">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="bg-primary text-white p-4" style="background: linear-gradient(135deg, #0f1e36 0%, #0d9488 100%) !important;">
                    <div class="d-flex align-items-center gap-3">
                        <div class="brand-icon">
                            <i class="bi bi-person-plus-fill"></i>
                        </div>
                        <div>
                            <h4 class="fw-bold mb-0">Patient Self-Registration</h4>
                            <small class="text-light opacity-75">Enroll in NexusCare & access QueueFlow, CareTrail, and Appointments</small>
                        </div>
                    </div>
                </div>

                <div class="card-body p-4 p-md-5">
                    <?php if ($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i><?= e($error) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <?php if (Database::isSqlite() && empty($error) && empty($success)): ?>
                        <div class="alert border-0 py-2 px-3 small d-flex align-items-center gap-2 mb-4" style="background: rgba(14, 165, 233, 0.08); border-left: 3px solid #0284c7 !important; color: #0369a1; border-radius: 8px;">
                            <i class="bi bi-database-check text-primary fs-6"></i>
                            <div class="flex-grow-1" style="font-size: 0.82rem;">
                                <strong>Live Demonstration Mode:</strong> New patient registrations will be created and saved in your active session database.
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($success): ?>
                        <div class="alert alert-success">
                            <i class="bi bi-check-circle-fill me-2"></i><?= $success ?>
                            <div class="mt-3">
                                <a href="login.php" class="btn btn-nexus-primary btn-sm">Proceed to Sign In</a>
                            </div>
                        </div>
                    <?php else: ?>

                    <form method="POST" action="register.php">
                        <?= csrf_field() ?>

                        <h6 class="text-uppercase text-secondary fw-bold mb-3" style="font-size: 0.8rem; letter-spacing: 0.05em;">
                            1. Personal & Contact Demographics
                        </h6>
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">First Name *</label>
                                <input type="text" name="first_name" class="form-control" required value="<?= e($_POST['first_name'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Last Name *</label>
                                <input type="text" name="last_name" class="form-control" required value="<?= e($_POST['last_name'] ?? '') ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold">Date of Birth *</label>
                                <input type="date" name="dob" class="form-control" required value="<?= e($_POST['dob'] ?? '') ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold">Gender *</label>
                                <select name="gender" class="form-select" required>
                                    <option value="">Select Gender</option>
                                    <option value="male" <?= ($_POST['gender'] ?? '') === 'male' ? 'selected' : '' ?>>Male</option>
                                    <option value="female" <?= ($_POST['gender'] ?? '') === 'female' ? 'selected' : '' ?>>Female</option>
                                    <option value="other" <?= ($_POST['gender'] ?? '') === 'other' ? 'selected' : '' ?>>Other</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold">Blood Group</label>
                                <select name="blood_group" class="form-select">
                                    <option value="Unknown">Select Blood Group</option>
                                    <option value="A+">A+</option>
                                    <option value="A-">A-</option>
                                    <option value="B+">B+</option>
                                    <option value="B-">B-</option>
                                    <option value="AB+">AB+</option>
                                    <option value="AB-">AB-</option>
                                    <option value="O+">O+</option>
                                    <option value="O-">O-</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Phone Number *</label>
                                <input type="text" name="phone" class="form-control" placeholder="+91 98000 00000" required value="<?= e($_POST['phone'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Email Address</label>
                                <input type="email" name="email" class="form-control" placeholder="you@example.com" value="<?= e($_POST['email'] ?? '') ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label small fw-semibold">Residential Address</label>
                                <textarea name="address" class="form-control" rows="2"><?= e($_POST['address'] ?? '') ?></textarea>
                            </div>
                        </div>

                        <h6 class="text-uppercase text-secondary fw-bold mb-3" style="font-size: 0.8rem; letter-spacing: 0.05em;">
                            2. Emergency Contact Information
                        </h6>
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Emergency Contact Person Name *</label>
                                <input type="text" name="emergency_contact_name" class="form-control" required value="<?= e($_POST['emergency_contact_name'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Emergency Contact Phone Number *</label>
                                <input type="text" name="emergency_contact_phone" class="form-control" required value="<?= e($_POST['emergency_contact_phone'] ?? '') ?>">
                            </div>
                        </div>

                        <h6 class="text-uppercase text-secondary fw-bold mb-3" style="font-size: 0.8rem; letter-spacing: 0.05em;">
                            3. Portal Account Credentials
                        </h6>
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Username *</label>
                                <input type="text" name="username" class="form-control" required value="<?= e($_POST['username'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Password * (Min 6 chars)</label>
                                <input type="password" name="password" class="form-control" required>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between align-items-center mt-4">
                            <a href="login.php" class="text-decoration-none small text-secondary">
                                <i class="bi bi-arrow-left me-1"></i>Back to Sign In
                            </a>
                            <button type="submit" class="btn btn-nexus-primary px-4 py-2">
                                Complete Registration
                            </button>
                        </div>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
