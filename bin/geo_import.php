#!/usr/bin/env php
<?php
/**
 * GeoNames Geographic Data Importer
 *
 * Downloads and imports geographic place data from GeoNames.org into the
 * geo_places table. Supports per-country or full global imports.
 *
 * Usage:
 *   php bin/geo_import.php PH              # Import Philippines (~150k places, all 42k+ barangays)
 *   php bin/geo_import.php US              # Import United States
 *   php bin/geo_import.php PH,US,JP        # Import multiple countries
 *   php bin/geo_import.php all             # Import ALL countries (~12M records, ~400MB download)
 *   php bin/geo_import.php --status        # Show import status
 *   php bin/geo_import.php --link-barangays # Link existing barangays to geo_places
 *
 * Data source: https://download.geonames.org/export/dump/
 * License: Creative Commons Attribution 4.0
 *
 * The import is idempotent — running it again for the same country will
 * replace existing data (REPLACE INTO).
 */

// ── Bootstrap ───────────────────────────────────────────────────────────────
$rootPath = dirname(__DIR__);

// Load .env
$envFile = $rootPath . '/.env';
if (!file_exists($envFile)) {
    // Try parent directory (container deployments)
    $envFile = dirname($rootPath) . '/.env';
}
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        if (strpos($line, '=') !== false) {
            [$key, $val] = explode('=', $line, 2);
            $key = trim($key);
            $val = trim($val, " \t\n\r\0\x0B\"'");
            $_ENV[$key] = $val;
            putenv("$key=$val");
        }
    }
}

// Database connection
$dbHost = $_ENV['DB_HOST'] ?? '127.0.0.1';
$dbName = $_ENV['DB_NAME'] ?? '';
$dbUser = $_ENV['DB_USER'] ?? '';
$dbPass = $_ENV['DB_PASS'] ?? '';

if (!$dbName) {
    fwrite(STDERR, "Error: DB_NAME not set. Create .env or set environment variables.\n");
    exit(1);
}

try {
    $pdo = new PDO(
        "mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4",
        $dbUser,
        $dbPass,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::MYSQL_ATTR_LOCAL_INFILE => true,
        ]
    );
} catch (PDOException $e) {
    fwrite(STDERR, "Database connection failed: " . $e->getMessage() . "\n");
    exit(1);
}

// ── Parse arguments ─────────────────────────────────────────────────────────
$args = array_slice($argv, 1);
if (empty($args)) {
    printUsage();
    exit(0);
}

$command = strtolower($args[0]);

if ($command === '--status') {
    showStatus($pdo);
    exit(0);
}

if ($command === '--link-barangays') {
    linkBarangays($pdo);
    exit(0);
}

if ($command === '--admin1') {
    importAdmin1Codes($pdo);
    exit(0);
}

// Parse country codes
$force = in_array('--force', $args);
$countryCodes = [];
foreach ($args as $arg) {
    if (str_starts_with($arg, '--')) continue;
    if (strtolower($arg) === 'all') {
        $countryCodes = ['allCountries'];
        break;
    }
    foreach (explode(',', strtoupper($arg)) as $cc) {
        $cc = trim($cc);
        if (preg_match('/^[A-Z]{2}$/', $cc)) {
            $countryCodes[] = $cc;
        }
    }
}

if (empty($countryCodes)) {
    fwrite(STDERR, "Error: No valid country codes provided.\n");
    printUsage();
    exit(1);
}

// Ensure geo_places table exists
ensureTable($pdo);

// Import each country
foreach ($countryCodes as $cc) {
    importCountry($pdo, $cc, $force);
}

// Import admin1 codes for hierarchy resolution
importAdmin1Codes($pdo);

// Link existing barangays to geo_places
linkBarangays($pdo);

echo "\n✅ Import complete.\n";

// ── Functions ───────────────────────────────────────────────────────────────

function printUsage(): void
{
    echo <<<USAGE
GeoNames Geographic Data Importer
==================================
Usage:
  php bin/geo_import.php <country_codes>   Import geographic data for countries
  php bin/geo_import.php all               Import ALL countries (~12M records)
  php bin/geo_import.php --status          Show import status
  php bin/geo_import.php --link-barangays  Link existing barangays to geo_places

Examples:
  php bin/geo_import.php PH                Import Philippines (42,000+ barangays)
  php bin/geo_import.php PH,US,JP          Import multiple countries
  php bin/geo_import.php all --force       Re-import everything

Data source: https://download.geonames.org/export/dump/
License: Creative Commons Attribution 4.0

USAGE;
}

function ensureTable(PDO $pdo): void
{
    $exists = $pdo->query("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'geo_places'")->fetch();
    if (!$exists) {
        echo "Creating geo_places table...\n";
        $pdo->exec("
            CREATE TABLE `geo_places` (
                `geoname_id`    INT UNSIGNED NOT NULL,
                `name`          VARCHAR(200) NOT NULL,
                `ascii_name`    VARCHAR(200) NOT NULL DEFAULT '',
                `latitude`      DECIMAL(10,7) NOT NULL DEFAULT 0,
                `longitude`     DECIMAL(10,7) NOT NULL DEFAULT 0,
                `feature_class` CHAR(1) NOT NULL DEFAULT '',
                `feature_code`  VARCHAR(10) NOT NULL DEFAULT '',
                `country_code`  CHAR(2) NOT NULL DEFAULT '',
                `admin1_code`   VARCHAR(20) NOT NULL DEFAULT '',
                `admin2_code`   VARCHAR(80) NOT NULL DEFAULT '',
                `admin3_code`   VARCHAR(20) NOT NULL DEFAULT '',
                `admin4_code`   VARCHAR(20) NOT NULL DEFAULT '',
                `population`    BIGINT UNSIGNED NOT NULL DEFAULT 0,
                `timezone`      VARCHAR(40) NOT NULL DEFAULT '',
                PRIMARY KEY (`geoname_id`),
                KEY `idx_geo_country` (`country_code`),
                KEY `idx_geo_feature` (`feature_class`, `feature_code`),
                KEY `idx_geo_latlon` (`latitude`, `longitude`),
                KEY `idx_geo_admin` (`country_code`, `admin1_code`, `admin2_code`),
                FULLTEXT KEY `ft_geo_name` (`name`, `ascii_name`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }
    // Ensure admin1 table
    $exists = $pdo->query("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'geo_admin1'")->fetch();
    if (!$exists) {
        $pdo->exec("
            CREATE TABLE `geo_admin1` (
                `code`       VARCHAR(20) NOT NULL,
                `name`       VARCHAR(200) NOT NULL,
                `ascii_name` VARCHAR(200) NOT NULL DEFAULT '',
                `geoname_id` INT UNSIGNED NOT NULL DEFAULT 0,
                PRIMARY KEY (`code`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }
    // Ensure import log
    $exists = $pdo->query("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'geo_import_log'")->fetch();
    if (!$exists) {
        $pdo->exec("
            CREATE TABLE `geo_import_log` (
                `country_code` CHAR(2) NOT NULL,
                `records`      INT UNSIGNED NOT NULL DEFAULT 0,
                `imported_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`country_code`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }
}

function importCountry(PDO $pdo, string $cc, bool $force): void
{
    $label = $cc === 'allCountries' ? 'ALL countries' : $cc;

    // Check if already imported
    if (!$force && $cc !== 'allCountries') {
        $existing = $pdo->prepare("SELECT records, imported_at FROM geo_import_log WHERE country_code = ?");
        $existing->execute([$cc]);
        $row = $existing->fetch();
        if ($row && $row['records'] > 0) {
            echo "⏭  $cc already imported ({$row['records']} records on {$row['imported_at']}). Use --force to re-import.\n";
            return;
        }
    }

    $url = "https://download.geonames.org/export/dump/{$cc}.zip";
    $tmpDir = sys_get_temp_dir() . '/geonames_import';
    @mkdir($tmpDir, 0755, true);

    $zipPath = "$tmpDir/{$cc}.zip";
    $txtPath = "$tmpDir/{$cc}.txt";

    // Download if not cached
    if (!file_exists($txtPath) || $force) {
        echo "📥 Downloading $label from GeoNames...\n";
        echo "   URL: $url\n";

        $ch = curl_init($url);
        $fp = fopen($zipPath, 'w');
        curl_setopt_array($ch, [
            CURLOPT_FILE            => $fp,
            CURLOPT_FOLLOWLOCATION  => true,
            CURLOPT_TIMEOUT         => 600,
            CURLOPT_CONNECTTIMEOUT  => 30,
            CURLOPT_PROGRESSFUNCTION => function ($res, $dlTotal, $dlNow) {
                if ($dlTotal > 0) {
                    $pct = round($dlNow / $dlTotal * 100);
                    $mb = round($dlNow / 1048576, 1);
                    echo "\r   Downloaded: {$mb}MB ({$pct}%)   ";
                }
                return 0;
            },
            CURLOPT_NOPROGRESS => false,
        ]);
        $ok = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        fclose($fp);
        echo "\n";

        if (!$ok || $httpCode !== 200) {
            fwrite(STDERR, "Error: Download failed (HTTP $httpCode) for $url\n");
            @unlink($zipPath);
            return;
        }

        // Extract
        echo "📦 Extracting...\n";
        $zip = new ZipArchive();
        if ($zip->open($zipPath) === true) {
            $zip->extractTo($tmpDir);
            $zip->close();
        } else {
            fwrite(STDERR, "Error: Failed to extract $zipPath\n");
            return;
        }
        @unlink($zipPath);
    }

    if (!file_exists($txtPath)) {
        fwrite(STDERR, "Error: Expected file $txtPath not found after extraction\n");
        return;
    }

    // Parse and import
    echo "📊 Importing $label into database...\n";

    $handle = fopen($txtPath, 'r');
    if (!$handle) {
        fwrite(STDERR, "Error: Cannot open $txtPath\n");
        return;
    }

    // Prepare batch insert
    $batchSize = 2000;
    $batch = [];
    $total = 0;
    $imported = 0;

    $stmt = $pdo->prepare("
        REPLACE INTO geo_places
            (geoname_id, name, ascii_name, latitude, longitude,
             feature_class, feature_code, country_code,
             admin1_code, admin2_code, admin3_code, admin4_code,
             population, timezone)
        VALUES
            (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    // Disable autocommit for speed
    $pdo->beginTransaction();

    while (($line = fgets($handle)) !== false) {
        $total++;
        $cols = explode("\t", rtrim($line, "\r\n"));
        if (count($cols) < 19) continue;

        // GeoNames TSV columns:
        // 0:geonameid 1:name 2:asciiname 3:alternatenames 4:latitude 5:longitude
        // 6:feature_class 7:feature_code 8:country_code 9:cc2
        // 10:admin1 11:admin2 12:admin3 13:admin4
        // 14:population 15:elevation 16:dem 17:timezone 18:modification_date

        $featureClass = $cols[6];
        // Import A (administrative) and P (populated places) features only
        // This filters out mountains, rivers, airports etc. to keep the DB focused
        if ($featureClass !== 'A' && $featureClass !== 'P') continue;

        $stmt->execute([
            (int)$cols[0],           // geoname_id
            mb_substr($cols[1], 0, 200),  // name
            mb_substr($cols[2], 0, 200),  // ascii_name
            (float)$cols[4],         // latitude
            (float)$cols[5],         // longitude
            $featureClass,           // feature_class
            mb_substr($cols[7], 0, 10),   // feature_code
            mb_substr($cols[8], 0, 2),    // country_code
            mb_substr($cols[10], 0, 20),  // admin1_code
            mb_substr($cols[11], 0, 80),  // admin2_code
            mb_substr($cols[12], 0, 20),  // admin3_code
            mb_substr($cols[13], 0, 20),  // admin4_code
            (int)$cols[14],          // population
            mb_substr($cols[17], 0, 40),  // timezone
        ]);
        $imported++;

        if ($imported % $batchSize === 0) {
            $pdo->commit();
            $pdo->beginTransaction();
            echo "\r   Imported: " . number_format($imported) . " places (scanned " . number_format($total) . " rows)   ";
        }
    }

    $pdo->commit();
    fclose($handle);

    echo "\r   Imported: " . number_format($imported) . " places (scanned " . number_format($total) . " rows)   \n";

    // Log import
    if ($cc !== 'allCountries') {
        $logStmt = $pdo->prepare("REPLACE INTO geo_import_log (country_code, records, imported_at) VALUES (?, ?, NOW())");
        $logStmt->execute([$cc, $imported]);
    } else {
        // For allCountries, log per-country counts
        $counts = $pdo->query("SELECT country_code, COUNT(*) as cnt FROM geo_places GROUP BY country_code")->fetchAll();
        $logStmt = $pdo->prepare("REPLACE INTO geo_import_log (country_code, records, imported_at) VALUES (?, ?, NOW())");
        foreach ($counts as $row) {
            $logStmt->execute([$row['country_code'], $row['cnt']]);
        }
    }

    // Clean up extracted text file (keep for re-runs, but it's large)
    if ($cc === 'allCountries') {
        @unlink($txtPath); // allCountries.txt is huge, don't keep
    }

    echo "✅ $label: " . number_format($imported) . " places imported.\n";
}

function importAdmin1Codes(PDO $pdo): void
{
    echo "📥 Importing admin1 codes (regions/states)...\n";
    $url = 'https://download.geonames.org/export/dump/admin1CodesASCII.txt';

    $data = @file_get_contents($url);
    if (!$data) {
        fwrite(STDERR, "Warning: Could not download admin1 codes from $url\n");
        return;
    }

    $stmt = $pdo->prepare("REPLACE INTO geo_admin1 (code, name, ascii_name, geoname_id) VALUES (?, ?, ?, ?)");
    $pdo->beginTransaction();
    $count = 0;

    foreach (explode("\n", $data) as $line) {
        $line = trim($line);
        if ($line === '') continue;
        $cols = explode("\t", $line);
        if (count($cols) < 4) continue;

        $stmt->execute([
            mb_substr($cols[0], 0, 20),
            mb_substr($cols[1], 0, 200),
            mb_substr($cols[2], 0, 200),
            (int)$cols[3],
        ]);
        $count++;
    }

    $pdo->commit();
    echo "✅ Imported $count admin1 codes.\n";
}

function linkBarangays(PDO $pdo): void
{
    echo "🔗 Linking existing barangays to geo_places...\n";

    // Check if geo_places has data
    $geoCount = (int)$pdo->query("SELECT COUNT(*) FROM geo_places WHERE country_code = 'PH'")->fetchColumn();
    if ($geoCount === 0) {
        echo "   No PH data in geo_places. Import PH first.\n";
        return;
    }

    // For each barangay without a geoname_id, try to match by name + coordinates
    $barangays = $pdo->query("SELECT id, name, city, lat, lng FROM barangays WHERE geoname_id IS NULL")->fetchAll();
    $linked = 0;

    $updateStmt = $pdo->prepare("UPDATE barangays SET geoname_id = ? WHERE id = ?");

    foreach ($barangays as $b) {
        // Try exact name match near the coordinates (within ~5km)
        $latMin = (float)$b['lat'] - 0.05;
        $latMax = (float)$b['lat'] + 0.05;
        $lngMin = (float)$b['lng'] - 0.05;
        $lngMax = (float)$b['lng'] + 0.05;

        $cleanName = preg_replace('/^barangay\s+/i', '', $b['name']);
        $geo = $pdo->prepare("
            SELECT geoname_id FROM geo_places
            WHERE country_code = 'PH'
              AND (name LIKE ? OR ascii_name LIKE ?)
              AND latitude BETWEEN ? AND ?
              AND longitude BETWEEN ? AND ?
            LIMIT 1
        ");
        $pattern = '%' . $cleanName . '%';
        $geo->execute([$pattern, $pattern, $latMin, $latMax, $lngMin, $lngMax]);
        $match = $geo->fetch();

        if ($match) {
            $updateStmt->execute([$match['geoname_id'], $b['id']]);
            $linked++;
        }
    }

    echo "✅ Linked $linked of " . count($barangays) . " barangays to geo_places.\n";
}

function showStatus(PDO $pdo): void
{
    ensureTable($pdo);

    echo "GeoNames Import Status\n";
    echo str_repeat('=', 50) . "\n\n";

    $total = (int)$pdo->query("SELECT COUNT(*) FROM geo_places")->fetchColumn();
    echo "Total places in database: " . number_format($total) . "\n\n";

    $logs = $pdo->query("SELECT country_code, records, imported_at FROM geo_import_log ORDER BY country_code")->fetchAll();
    if (empty($logs)) {
        echo "No imports yet. Run: php bin/geo_import.php PH\n";
        return;
    }

    echo sprintf("%-6s  %12s  %s\n", 'Code', 'Records', 'Imported At');
    echo str_repeat('-', 50) . "\n";
    foreach ($logs as $log) {
        echo sprintf("%-6s  %12s  %s\n", $log['country_code'], number_format($log['records']), $log['imported_at']);
    }

    // Barangay linking status
    $brgyTotal = (int)$pdo->query("SELECT COUNT(*) FROM barangays")->fetchColumn();
    $brgyLinked = (int)$pdo->query("SELECT COUNT(*) FROM barangays WHERE geoname_id IS NOT NULL")->fetchColumn();
    echo "\nBarangay links: $brgyLinked / $brgyTotal linked to geo_places\n";
}
