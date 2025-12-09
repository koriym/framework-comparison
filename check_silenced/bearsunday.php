<?php

/**
 * BEAR.Sunday silenced issues analysis - analyzes vendor/bear/* and vendor/ray/*.
 */

$baseDir = dirname(__DIR__);
$reposDir = $baseDir . '/repos';
$dataDir = $baseDir . '/reports/data';
$repoName = 'bearsunday';
$repoPath = "$reposDir/$repoName";
$reportPath = "$dataDir/silenced_$repoName.json";

// Load config
$config = require "$baseDir/config.php";
$bearConfig = $config[$repoName];
$vendorDirs = $bearConfig['vendorDirs'] ?? [];

if (!is_dir($dataDir)) {
    mkdir($dataDir, 0777, true);
}

echo "=== Silenced Issues: BEAR.Sunday (from BEAR.Package vendor) ===\n";

// Repository should already be cloned by PHPStan check
if (!is_dir($repoPath)) {
    echo "Error: Repository not found. Run PHPStan check first.\n";
    exit(1);
}

// Collect all package directories from vendor/bear/* and vendor/ray/*
$packageDirs = [];
foreach ($vendorDirs as $vendorDir) {
    $fullPath = "$repoPath/$vendorDir";
    if (!is_dir($fullPath)) {
        continue;
    }

    // Find all package directories
    $packages = glob("$fullPath/*", GLOB_ONLYDIR);
    foreach ($packages as $pkgDir) {
        $packageDirs[] = $pkgDir;
    }
}

if (empty($packageDirs)) {
    echo "Error: No packages found in " . implode(', ', $vendorDirs) . "\n";
    exit(1);
}

echo "Analyzing " . count($packageDirs) . " packages...\n";

// Load silenced issues checker
require_once __DIR__ . '/_common.php';

$totalCounts = [
    'phpstan_ignore' => 0,
    'psalm_suppress' => 0,
    'phpcs_ignore' => 0,
    'coverage_ignore' => 0,
    'phpstan_baseline' => 0,
    'psalm_baseline' => 0,
];

$totalTime = 0;

foreach ($packageDirs as $pkgDir) {
    $srcDir = "$pkgDir/src";

    $startTime = microtime(true);

    // Count inline annotations
    if (is_dir($srcDir)) {
        $totalCounts['phpstan_ignore'] += countPattern($srcDir, '@phpstan-ignore');
        $totalCounts['psalm_suppress'] += countPattern($srcDir, '@psalm-suppress');
        $totalCounts['phpcs_ignore'] += countPattern($srcDir, 'phpcs:(ignore|disable)', true);
        $totalCounts['coverage_ignore'] += countPattern($srcDir, '@codeCoverageIgnore');
    }

    // Count PHPStan baseline entries
    $phpstanBaselines = glob("$pkgDir/phpstan*baseline*.neon");
    foreach ($phpstanBaselines as $baseline) {
        $content = file_get_contents($baseline);
        $totalCounts['phpstan_baseline'] += preg_match_all('/message:/', $content);
    }

    // Count Psalm baseline entries
    $psalmBaselines = glob("$pkgDir/psalm*baseline*.xml");
    foreach ($psalmBaselines as $baseline) {
        $content = file_get_contents($baseline);
        $totalCounts['psalm_baseline'] += preg_match_all('/<code>/', $content);
    }

    $totalTime += microtime(true) - $startTime;
}

// Save results
file_put_contents($reportPath, json_encode($totalCounts, JSON_PRETTY_PRINT));

// Save timing
$timingFile = "$dataDir/timing.json";
$timing = file_exists($timingFile) ? json_decode(file_get_contents($timingFile), true) : [];
$timing[$repoName]['silenced'] = round($totalTime, 1);
file_put_contents($timingFile, json_encode($timing, JSON_PRETTY_PRINT) . "\n");

echo "\nResults:\n";
echo "  @phpstan-ignore:      " . $totalCounts['phpstan_ignore'] . "\n";
echo "  @psalm-suppress:      " . $totalCounts['psalm_suppress'] . "\n";
echo "  phpcs:ignore:         " . $totalCounts['phpcs_ignore'] . "\n";
echo "  @codeCoverageIgnore:  " . $totalCounts['coverage_ignore'] . "\n";
echo "  PHPStan Baseline:     " . $totalCounts['phpstan_baseline'] . "\n";
echo "  Psalm Baseline:       " . $totalCounts['psalm_baseline'] . "\n";
echo "\nReport saved to: $reportPath\n";
