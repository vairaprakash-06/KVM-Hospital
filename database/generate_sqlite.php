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

echo "Successfully generated {$sqliteFile} (" . filesize($sqliteFile) . " bytes) with {$importedCount} statements.\n";
