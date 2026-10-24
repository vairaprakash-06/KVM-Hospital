<?php
/**
 * KVM Hospital Management System — NexusCare
 * Utility script to generate database/kvm_hospital.sqlite from schema.sql and seed.sql
 */

declare(strict_types=1);

$baseDir = dirname(__DIR__);
$schemaSql = file_get_contents($baseDir . '/database/schema.sql');
$seedSql = file_get_contents($baseDir . '/database/seed.sql');

$sqliteFile = $baseDir . '/database/kvm_hospital.sqlite';
if (file_exists($sqliteFile)) {
    unlink($sqliteFile);
}

$pdo = new PDO('sqlite:' . $sqliteFile);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('PRAGMA foreign_keys = OFF;');

function splitSqlStatements(string $sql): array {
    $statements = [];
    $current = '';
    $inString = false;
    $length = strlen($sql);
    
    for ($i = 0; $i < $length; $i++) {
        $c = $sql[$i];
        if ($c === "'") {
            $isEscaped = ($i > 0 && $sql[$i - 1] === '\\');
            if (!$isEscaped) {
                $inString = !$inString;
            }
        }
        if ($c === ';' && !$inString) {
            $stmt = trim($current);
            if ($stmt !== '') {
                $statements[] = $stmt;
            }
            $current = '';
        } else {
            $current .= $c;
        }
    }
    $stmt = trim($current);
    if ($stmt !== '') {
        $statements[] = $stmt;
    }
    return $statements;
}

echo "1. Parsing schema.sql into SQLite tables...\n";

$cleanSchema = preg_replace('/--.*$/m', '', $schemaSql);
$cleanSchema = preg_replace('/CREATE\s+DATABASE[^;]+;/i', '', $cleanSchema);
$cleanSchema = preg_replace('/^\s*USE\s+[^;]+;/im', '', $cleanSchema);
$cleanSchema = preg_replace('/SET\s+FOREIGN_KEY_CHECKS[^;]+;/i', '', $cleanSchema);

$statements = splitSqlStatements($cleanSchema);
$indexesToCreate = [];

foreach ($statements as $stmt) {
    if (empty($stmt)) continue;

    if (stripos($stmt, 'DROP TABLE') === 0) {
        $pdo->exec($stmt);
        continue;
    }

    if (stripos($stmt, 'CREATE TABLE') === 0) {
        if (!preg_match('/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`?([a-zA-Z0-9_]+)`?\s*\((.*)\)\s*(?:ENGINE\s*=\s*[a-zA-Z0-9]+)?/is', $stmt, $m)) {
            continue;
        }

        $tableName = $m[1];
        $body = $m[2];

        $lines = [];
        $depth = 0;
        $cur = '';
        for ($i = 0; $i < strlen($body); $i++) {
            $char = $body[$i];
            if ($char === '(') $depth++;
            elseif ($char === ')') $depth--;
            
            if ($char === ',' && $depth === 0) {
                $lines[] = trim($cur);
                $cur = '';
            } else {
                $cur .= $char;
            }
        }
        if (trim($cur) !== '') {
            $lines[] = trim($cur);
        }

        $newLines = [];
        foreach ($lines as $line) {
            if (preg_match('/^(?:UNIQUE\s+)?(?:KEY|INDEX)\s+`?([a-zA-Z0-9_]+)`?\s*\((.+)\)/i', $line, $idxMatch)) {
                $isUnique = stripos($line, 'UNIQUE') === 0;
                $idxName = $idxMatch[1];
                $idxCols = $idxMatch[2];
                $uniqStr = $isUnique ? 'UNIQUE' : '';
                $indexesToCreate[] = "CREATE $uniqStr INDEX IF NOT EXISTS `{$idxName}` ON `{$tableName}` ({$idxCols})";
                continue;
            }

            $line = preg_replace('/`?id`?\s+INT\s+AUTO_INCREMENT\s+PRIMARY\s+KEY/i', '`id` INTEGER PRIMARY KEY AUTOINCREMENT', $line);
            $line = preg_replace('/INT\s+AUTO_INCREMENT/i', 'INTEGER PRIMARY KEY AUTOINCREMENT', $line);
            $line = preg_replace('/ON\s+UPDATE\s+CURRENT_TIMESTAMP/i', '', $line);
            $line = preg_replace('/\bTIMESTAMP\b/i', 'DATETIME', $line);
            $line = preg_replace('/ENUM\s*\([^)]+\)/i', 'TEXT', $line);

            $newLines[] = $line;
        }

        $newTableSql = "CREATE TABLE `{$tableName}` (\n  " . implode(",\n  ", $newLines) . "\n)";
        $pdo->exec($newTableSql);
    }
}

foreach ($indexesToCreate as $idxSql) {
    try {
        $pdo->exec($idxSql);
    } catch (Exception $e) {
        // Ignored
    }
}

echo "2. Importing seed.sql dataset...\n";

$fnRegistrar = function($pdo, $name, $cb) {
    if (method_exists($pdo, 'createFunction')) {
        @$pdo->createFunction($name, $cb);
    } else {
        @$pdo->sqliteCreateFunction($name, $cb);
    }
};
$fnRegistrar($pdo, 'NOW', fn() => date('Y-m-d H:i:s'));
$fnRegistrar($pdo, 'CURDATE', fn() => date('Y-m-d'));
$fnRegistrar($pdo, 'CURTIME', fn() => date('H:i:s'));

$cleanSeed = preg_replace('/--.*$/m', '', $seedSql);
$cleanSeed = preg_replace('/CREATE\s+DATABASE[^;]+;/i', '', $cleanSeed);
$cleanSeed = preg_replace('/^\s*USE\s+[^;]+;/im', '', $cleanSeed);
$cleanSeed = preg_replace('/SET\s+FOREIGN_KEY_CHECKS[^;]+;/i', '', $cleanSeed);

$cleanSeed = preg_replace_callback('/NOW\(\)\s*-\s*INTERVAL\s+([0-9]+)\s+([A-Za-z]+)\s*([\+\-])\s*INTERVAL\s+([0-9]+)\s+([A-Za-z]+)/i', function($m) {
    $arg1 = '-' . $m[1] . ' ' . strtolower($m[2]) . 's';
    $sign = $m[3] === '-' ? '-' : '+';
    $arg2 = $sign . $m[4] . ' ' . strtolower($m[5]) . 's';
    return "datetime('now', '{$arg1}', '{$arg2}')";
}, $cleanSeed);

$cleanSeed = preg_replace_callback('/NOW\(\)\s*-\s*INTERVAL\s+([0-9]+)\s+([A-Za-z]+)/i', function($m) {
    return "datetime('now', '-" . $m[1] . " " . strtolower($m[2]) . "s')";
}, $cleanSeed);

$cleanSeed = preg_replace_callback('/NOW\(\)\s*\+\s*INTERVAL\s+([0-9]+)\s+([A-Za-z]+)/i', function($m) {
    return "datetime('now', '+" . $m[1] . " " . strtolower($m[2]) . "s')";
}, $cleanSeed);

$cleanSeed = preg_replace_callback('/CURDATE\(\)\s*\+\s*INTERVAL\s+([0-9]+)\s+([A-Za-z]+)/i', function($m) {
    return "date('now', '+" . $m[1] . " " . strtolower($m[2]) . "s')";
}, $cleanSeed);

$cleanSeed = preg_replace_callback('/CURDATE\(\)\s*-\s*INTERVAL\s+([0-9]+)\s+([A-Za-z]+)/i', function($m) {
    return "date('now', '-" . $m[1] . " " . strtolower($m[2]) . "s')";
}, $cleanSeed);

$seedStatements = splitSqlStatements($cleanSeed);
$importedCount = 0;

foreach ($seedStatements as $stmt) {
    if (empty($stmt)) continue;
    try {
        $pdo->exec($stmt);
        $importedCount++;
    } catch (Exception $e) {
        echo "Seed notice: " . $e->getMessage() . "\n";
    }
}

echo "3. Ingesting 240 Tamil sample patients dataset...\n";
$samplePatientsFile = $baseDir . '/database/kvm_hospital_240_tamil_sample_patients.sql';
if (file_exists($samplePatientsFile)) {
    $sampleSql = file_get_contents($samplePatientsFile);
    $pattern = "/\(\s*'([^']+)'\s*,\s*'([^']+)'\s*,\s*([0-9]+)\s*,\s*'([^']+)'\s*,\s*'([^']+)'\s*,\s*'([^']+)'\s*\)/";
    if (preg_match_all($pattern, $sampleSql, $matches, PREG_SET_ORDER)) {
        $existing = [];
        foreach ($pdo->query("SELECT id, patient_id FROM patient_profiles") as $r) {
            $existing[$r['patient_id']] = (int)$r['id'];
        }

        $cities = [
            'Chennai', 'Coimbatore', 'Madurai', 'Tiruchirappalli', 'Salem',
            'Tirunelveli', 'Tiruppur', 'Erode', 'Vellore', 'Thanjavur',
            'Dindigul', 'Kanchipuram', 'Nagercoil', 'Cuddalore', 'Kumbakonam'
        ];
        $bloodGroups = ['O+', 'A+', 'B+', 'AB+', 'O-', 'A-', 'B-'];

        $insertStmt = $pdo->prepare("
            INSERT INTO `patient_profiles` (
                `patient_id`, `first_name`, `last_name`, `dob`, `gender`,
                `blood_group`, `phone`, `email`, `address`, `emergency_contact_name`,
                `emergency_contact_phone`, `allergies`, `medical_history`, `created_at`
            ) VALUES (
                :pid, :fn, :ln, :dob, :gender,
                :bg, :phone, :email, :addr, :em_name,
                :em_phone, :allergies, :history, :created_at
            )
        ");

        $updateStmt = $pdo->prepare("
            UPDATE `patient_profiles` SET
                `first_name` = :fn,
                `last_name` = :ln,
                `dob` = :dob,
                `gender` = :gender,
                `blood_group` = :bg,
                `phone` = :phone,
                `email` = :email,
                `address` = :addr,
                `emergency_contact_name` = :em_name,
                `emergency_contact_phone` = :em_phone,
                `allergies` = :allergies,
                `medical_history` = :history
            WHERE `patient_id` = :pid
        ");

        foreach ($matches as $idx => $match) {
            $code       = trim($match[1]);
            $fullName   = trim($match[2]);
            $age        = (int)$match[3];
            $gender     = strtolower(trim($match[4]));
            $phone      = trim($match[5]);
            $careStatus = trim($match[6]);

            $nameParts = preg_split('/\s+/', $fullName, 2);
            $firstName = $nameParts[0];
            $lastName  = $nameParts[1] ?? '';

            $birthYear  = 2026 - $age;
            $month      = sprintf('%02d', ($idx % 12) + 1);
            $day        = sprintf('%02d', ($idx % 28) + 1);
            $dob        = "{$birthYear}-{$month}-{$day}";

            $bg         = $bloodGroups[$idx % count($bloodGroups)];
            $city       = $cities[$idx % count($cities)];
            $streetNum  = ($idx * 7 % 180) + 1;
            $address    = "{$streetNum}, Main Road, {$city}, Tamil Nadu";
            
            $cleanFn    = strtolower(preg_replace('/[^a-z0-9]/i', '', $firstName));
            $cleanLn    = strtolower(preg_replace('/[^a-z0-9]/i', '', $lastName));
            $emailPrefix = $cleanLn !== '' ? "{$cleanFn}.{$cleanLn}" : $cleanFn;
            $email      = "{$emailPrefix}" . ($idx + 1) . "@example.com";

            $emName     = ($gender === 'female' ? 'Kavitha ' : 'Ramesh ') . ($lastName !== '' ? $lastName : 'Kumar');
            $emPhone    = '+91 94440 ' . sprintf('%05d', ($idx + 100));

            $allergies  = ($idx % 5 === 0) ? 'Dust allergy' : (($idx % 9 === 0) ? 'Penicillin' : 'None');
            $history    = ($careStatus === 'Inpatient Admitted') 
                            ? "Inpatient care. Medical condition monitored. Care status: {$careStatus}."
                            : "Outpatient general clinical follow-up. Routine vital signs normal.";

            $createdAt  = date('Y-m-d H:i:s', strtotime("-{$idx} hours"));

            if (isset($existing[$code])) {
                $updateStmt->execute([
                    'pid'       => $code,
                    'fn'        => $firstName,
                    'ln'        => $lastName,
                    'dob'       => $dob,
                    'gender'    => $gender,
                    'bg'        => $bg,
                    'phone'     => $phone,
                    'email'     => $email,
                    'addr'      => $address,
                    'em_name'   => $emName,
                    'em_phone'  => $emPhone,
                    'allergies' => $allergies,
                    'history'   => $history
                ]);
            } else {
                $insertStmt->execute([
                    'pid'        => $code,
                    'fn'         => $firstName,
                    'ln'         => $lastName,
                    'dob'        => $dob,
                    'gender'     => $gender,
                    'bg'         => $bg,
                    'phone'      => $phone,
                    'email'      => $email,
                    'addr'       => $address,
                    'em_name'    => $emName,
                    'em_phone'   => $emPhone,
                    'allergies'  => $allergies,
                    'history'    => $history,
                    'created_at' => $createdAt
                ]);
            }
        }
        echo "Successfully imported 240 Tamil sample patients.\n";
    }
}

echo "Successfully generated {$sqliteFile} (" . filesize($sqliteFile) . " bytes) with {$importedCount} statements.\n";
