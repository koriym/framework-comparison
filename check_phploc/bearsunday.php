<?php

/**
 * BEAR.Sunday phploc analysis - analyzes vendor/bear/* and vendor/ray/*.
 */

$baseDir = dirname(__DIR__);
$reposDir = $baseDir . '/repos';
$dataDir = $baseDir . '/reports/data';
$repoName = 'bearsunday';
$repoPath = "$reposDir/$repoName";
$reportPath = "$dataDir/phploc_$repoName.json";

// Load config
$config = require "$baseDir/config.php";
$bearConfig = $config[$repoName];
$vendorDirs = $bearConfig['vendorDirs'] ?? [];

if (!is_dir($dataDir)) {
    mkdir($dataDir, 0777, true);
}

echo "=== phploc: BEAR.Sunday (from BEAR.Package vendor) ===\n";

// Check for phploc
$phplocPhar = "$baseDir/phploc.phar";
if (!file_exists($phplocPhar)) {
    echo "Downloading phploc...\n";
    exec("curl -sL https://phar.phpunit.de/phploc.phar -o $phplocPhar 2>&1");
    chmod($phplocPhar, 0755);
}
$phplocBin = "php $phplocPhar";

// Repository should already be cloned by PHPStan check
if (!is_dir($repoPath)) {
    echo "Error: Repository not found. Run PHPStan check first.\n";
    exit(1);
}

// Collect all src directories from vendor/bear/* and vendor/ray/*
$srcDirs = [];
foreach ($vendorDirs as $vendorDir) {
    $fullPath = "$repoPath/$vendorDir";
    if (!is_dir($fullPath)) {
        continue;
    }

    // Find all package directories
    $packages = glob("$fullPath/*", GLOB_ONLYDIR);
    foreach ($packages as $pkgDir) {
        $srcDir = "$pkgDir/src";
        if (is_dir($srcDir)) {
            $srcDirs[] = $srcDir;
        }
    }
}

if (empty($srcDirs)) {
    echo "Error: No source directories found in " . implode(', ', $vendorDirs) . "\n";
    exit(1);
}

echo "Analyzing " . count($srcDirs) . " packages...\n";

// Run phploc (timed)
$tempReport = "/tmp/phploc_$repoName.json";
$dirs = implode(' ', array_map('escapeshellarg', $srcDirs));

$startTime = microtime(true);
exec("$phplocBin --log-json=$tempReport $dirs 2>&1");
$elapsed = round(microtime(true) - $startTime, 1);

// Save timing
$timingFile = "$dataDir/timing.json";
$timing = file_exists($timingFile) ? json_decode(file_get_contents($timingFile), true) : [];
$timing[$repoName]['phploc'] = $elapsed;
file_put_contents($timingFile, json_encode($timing, JSON_PRETTY_PRINT) . "\n");

if (file_exists($tempReport)) {
    copy($tempReport, $reportPath);
    $json = json_decode(file_get_contents($tempReport), true);

    echo "\nResults:\n";
    echo "  Lines of Code (LOC):     " . ($json['loc'] ?? '-') . "\n";
    echo "  Logical LOC (LLOC):      " . ($json['lloc'] ?? '-') . "\n";
    echo "  Classes:                 " . ($json['classes'] ?? '-') . "\n";
    echo "  Methods:                 " . ($json['methods'] ?? '-') . "\n";

    unlink($tempReport);
}

echo "\nReport saved to: $reportPath\n";
