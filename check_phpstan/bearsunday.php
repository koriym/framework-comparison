<?php

/**
 * BEAR.Sunday PHPStan analysis - analyzes vendor/bear/* and vendor/ray/*.
 */

$baseDir = dirname(__DIR__);
$reposDir = $baseDir . '/repos';
$dataDir = $baseDir . '/reports/data';
$repoName = 'bearsunday';
$repoPath = "$reposDir/$repoName";
$reportPath = "$dataDir/phpstan_$repoName.json";

// Load config
$config = require "$baseDir/config.php";
$bearConfig = $config[$repoName];
$framework = $bearConfig['repo'];
$branch = $bearConfig['branch'] ?? null;
$vendorDirs = $bearConfig['vendorDirs'] ?? [];

if (!is_dir($dataDir)) {
    mkdir($dataDir, 0777, true);
}

echo "=== PHPStan: BEAR.Sunday (from BEAR.Package vendor) ===\n";

// Clone the repository if not exists, or re-clone if branch changed
$needsClone = false;
if (!is_dir($repoPath)) {
    $needsClone = true;
} elseif ($branch) {
    $currentBranch = trim(shell_exec("git -C $repoPath rev-parse --abbrev-ref HEAD 2>/dev/null") ?: '');
    if ($currentBranch === 'HEAD') {
        $currentBranch = trim(shell_exec("git -C $repoPath describe --tags --exact-match HEAD 2>/dev/null") ?: 'HEAD');
    }
    if ($currentBranch !== $branch) {
        echo "Branch mismatch: have '$currentBranch', need '$branch'. Re-cloning...\n";
        exec("rm -rf " . escapeshellarg($repoPath));
        $needsClone = true;
    }
}

if ($needsClone) {
    echo "Cloning $framework" . ($branch ? " (branch: $branch)" : "") . "...\n";
    $branchArg = $branch ? " --branch $branch" : '';
    $cloneCommand = "git clone --depth 1$branchArg https://github.com/$framework.git $repoPath";
    exec($cloneCommand, $cloneOutput, $cloneStatus);

    if ($cloneStatus !== 0) {
        echo "Error: Failed to clone $framework.\n";
        exit(1);
    }
}

chdir($repoPath);

// Install dependencies to populate vendor/bear/* and vendor/ray/*
echo "Installing dependencies...\n";
exec('composer install --no-interaction --ignore-platform-reqs --optimize-autoloader 2>&1', $composerOutput, $composerStatus);

if ($composerStatus !== 0) {
    echo "Error: Failed to install dependencies.\n";
    exit(1);
}

// Check if PHPStan is available
$phpstanBin = 'vendor/bin/phpstan';
if (!file_exists($phpstanBin)) {
    echo "PHPStan not found, installing...\n";
    exec('composer require --dev phpstan/phpstan --no-interaction --ignore-platform-reqs 2>&1');
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

// Run PHPStan (timed)
echo "Running PHPStan level 8...\n";
$dirs = implode(' ', array_map('escapeshellarg', $srcDirs));
$phpstanCommand = "$phpstanBin analyze $dirs --level=8 --memory-limit=512M --no-progress --error-format=prettyJson 2>&1";

$startTime = microtime(true);
exec($phpstanCommand, $phpstanOutput, $phpstanStatus);
$elapsed = round(microtime(true) - $startTime, 1);

// Debug output on error
if ($phpstanStatus !== 0 && empty($phpstanOutput)) {
    echo "Warning: PHPStan exited with status $phpstanStatus but produced no output\n";
}

// Filter out non-JSON lines (like "Note: Using configuration file...")
$jsonLines = [];
$inJson = false;
foreach ($phpstanOutput as $line) {
    if (!$inJson && $line === '{') {
        $inJson = true;
    }
    if ($inJson) {
        $jsonLines[] = $line;
    }
}
$jsonOutput = implode("\n", $jsonLines);
file_put_contents($reportPath, $jsonOutput);

// Save timing (analysis only, not setup)
$timingFile = "$dataDir/timing.json";
$timing = file_exists($timingFile) ? json_decode(file_get_contents($timingFile), true) : [];
$timing[$repoName]['phpstan'] = $elapsed;
file_put_contents($timingFile, json_encode($timing, JSON_PRETTY_PRINT) . "\n");

$jsonData = json_decode($jsonOutput, true);
$fileErrors = $jsonData['totals']['file_errors'] ?? null;

if ($fileErrors !== null) {
    echo "\nCompleted: $fileErrors errors found.\n";
} else {
    echo "\nCompleted (check report for details).\n";
}
echo "Report saved to: $reportPath\n";
