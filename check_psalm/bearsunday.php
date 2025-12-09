<?php

/**
 * BEAR.Sunday Psalm analysis - analyzes vendor/bear/* and vendor/ray/*.
 */

$baseDir = dirname(__DIR__);
$reposDir = $baseDir . '/repos';
$dataDir = $baseDir . '/reports/data';
$repoName = 'bearsunday';
$repoPath = "$reposDir/$repoName";
$reportPath = "$dataDir/psalm_$repoName.json";

// Load config
$config = require "$baseDir/config.php";
$bearConfig = $config[$repoName];
$vendorDirs = $bearConfig['vendorDirs'] ?? [];

echo "=== Psalm: BEAR.Sunday (from BEAR.Package vendor) ===\n";

// Repository should already be cloned by PHPStan check
if (!is_dir($repoPath)) {
    echo "Error: Repository not found. Run PHPStan check first.\n";
    exit(1);
}

chdir($repoPath);

// Check if Psalm is available
$psalmBin = 'vendor/bin/psalm';
if (!file_exists($psalmBin)) {
    echo "Psalm not found, installing...\n";
    exec('composer require --dev vimeo/psalm --no-interaction --ignore-platform-reqs 2>&1');
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

// Initialize Psalm config
$psalmConfig = "$repoPath/psalm.xml";
if (!file_exists($psalmConfig)) {
    echo "Generating Psalm config...\n";
    exec("$psalmBin --init 2>&1", $initOutput);
}

// Run Psalm (timed)
echo "Running Psalm...\n";
$dirs = implode(' ', array_map('escapeshellarg', $srcDirs));
$psalmCommand = "$psalmBin $dirs --output-format=json --no-progress 2>&1";

$startTime = microtime(true);
exec($psalmCommand, $psalmOutput);
$elapsed = round(microtime(true) - $startTime, 1);

$jsonOutput = implode("\n", $psalmOutput);

// Try to parse as JSON, if it fails, assume no errors
$jsonData = json_decode($jsonOutput, true);
if (!is_array($jsonData)) {
    // Check if output contains "No errors found"
    if (strpos($jsonOutput, 'No errors found') !== false) {
        $jsonData = [];
    } else {
        $jsonData = null;
    }
}

// Save JSON data
file_put_contents($reportPath, json_encode($jsonData, JSON_PRETTY_PRINT));

// Save timing
$timingFile = "$dataDir/timing.json";
$timing = file_exists($timingFile) ? json_decode(file_get_contents($timingFile), true) : [];
$timing[$repoName]['psalm'] = $elapsed;
file_put_contents($timingFile, json_encode($timing, JSON_PRETTY_PRINT) . "\n");

$errorCount = is_array($jsonData) ? count($jsonData) : 'unknown';

echo "\nCompleted: $errorCount errors found.\n";
echo "Report saved to: $reportPath\n";
