<?php

/**
 * Common PDepend analysis logic.
 *
 * Expected variables before requiring this file:
 * - $framework: GitHub repo path (e.g., 'laravel/framework')
 * - $repoName: Short name for the framework
 * - $srcDir: Source directory to analyze (or null for custom vendorDirs)
 * - $vendorDirs: (optional) Array of vendor directories for multi-package frameworks
 * - $branch: (optional) Branch to checkout
 */

$baseDir = dirname(__DIR__);
$reposDir = $baseDir . '/repos';
$dataDir = $baseDir . '/reports/data';
$pdependBin = $baseDir . '/bin/pdepend.phar';

// Load config for branch info if not explicitly set
if (!isset($branch)) {
    $config = require "$baseDir/config.php";
    $branch = $config[$repoName]['branch'] ?? null;
}

if (!is_dir($reposDir)) {
    mkdir($reposDir, 0777, true);
}

if (!is_dir($dataDir)) {
    mkdir($dataDir, 0777, true);
}

$repoPath = "$reposDir/$repoName";

echo "=== PDepend Analysis: $framework ===\n";

// Clone the repository if not exists, or re-clone if branch changed
$needsClone = false;
if (!is_dir($repoPath)) {
    $needsClone = true;
} elseif ($branch) {
    // Check if current branch matches desired branch
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

// Determine paths to analyze
$analyzePaths = [];
if (isset($vendorDirs) && is_array($vendorDirs)) {
    // Multi-package framework (e.g., BEAR.Sunday, Laminas)
    foreach ($vendorDirs as $vendorDir) {
        $fullVendorPath = "$repoPath/$vendorDir";
        if (!is_dir($fullVendorPath)) {
            echo "Warning: Vendor directory not found: $fullVendorPath\n";
            continue;
        }
        // Find all src directories in vendor
        $packages = glob("$fullVendorPath/*/src", GLOB_ONLYDIR);
        $analyzePaths = array_merge($analyzePaths, $packages);
    }
} elseif ($srcDir) {
    // Single package framework
    $analyzePaths[] = "$repoPath/$srcDir";
}

if (empty($analyzePaths)) {
    echo "Error: No source directories found to analyze\n";
    exit(1);
}

// Run pdepend with summary-xml output
$summaryXml = "$dataDir/pdepend_$repoName.xml";
$chartOutput = "$dataDir/pdepend_chart_$repoName.svg";
$pyramidOutput = "$dataDir/pdepend_pyramid_$repoName.svg";

echo "Running PDepend analysis...\n";
echo "  Analyzing " . count($analyzePaths) . " directories\n";

$analyzePathsStr = implode(',', array_map('escapeshellarg', $analyzePaths));

$pdependCommand = sprintf(
    'php -d error_reporting=0 %s --summary-xml=%s --jdepend-chart=%s --overview-pyramid=%s %s 2>&1 | grep -v "Deprecated:" | grep -v "^$"',
    escapeshellarg($pdependBin),
    escapeshellarg($summaryXml),
    escapeshellarg($chartOutput),
    escapeshellarg($pyramidOutput),
    $analyzePathsStr
);

$startTime = microtime(true);
exec($pdependCommand, $output, $status);
$elapsed = round(microtime(true) - $startTime, 1);

// Check for critical errors
$outputStr = implode("\n", $output);
if (strpos($outputStr, 'Critical error:') !== false || strpos($outputStr, 'Fatal error:') !== false) {
    echo "Error: PDepend failed with critical error\n";
    echo $outputStr . "\n";
    // Don't exit - some frameworks may have issues with specific files
    echo "Continuing despite errors...\n";
}

// Parse XML and convert to JSON
if (!file_exists($summaryXml)) {
    echo "Error: Summary XML not generated\n";
    exit(1);
}

$xml = simplexml_load_file($summaryXml);
$json = [
    'files' => (int)$xml['files'],
    'loc' => (int)$xml['loc'],
    'ncloc' => (int)$xml['ncloc'],
    'packages' => [],
];

// Parse package-level metrics
foreach ($xml->package as $package) {
    $packageName = (string)$package['name'];

    // Calculate package metrics from classes
    $packageCE = 0;
    $packageCA = 0;
    $packageClasses = 0;
    $packageAbstract = 0;

    foreach ($package->class as $class) {
        $packageClasses++;
        $packageCE += (int)$class['ce'];
        $packageCA += (int)$class['ca'];
        if ((int)$class['impl'] > 0 || stripos((string)$class['name'], 'Interface') !== false) {
            $packageAbstract++;
        }
    }

    // Calculate Instability and Abstractness
    $totalCoupling = $packageCE + $packageCA;
    $instability = $totalCoupling > 0 ? round($packageCE / $totalCoupling, 3) : 0;
    $abstractness = $packageClasses > 0 ? round($packageAbstract / $packageClasses, 3) : 0;

    // Distance from main sequence (ideal = 0)
    $distance = abs($abstractness + $instability - 1);

    // Zone of Pain: I < 0.3, A < 0.3 (hard to change concrete classes)
    $isZoneOfPain = ($instability < 0.3 && $abstractness < 0.3 && $packageClasses > 0);

    $json['packages'][$packageName] = [
        'name' => $packageName,
        'classes' => $packageClasses,
        'abstract' => $packageAbstract,
        'ce' => $packageCE,
        'ca' => $packageCA,
        'instability' => $instability,
        'abstractness' => $abstractness,
        'distance' => $distance,
        'zoneOfPain' => $isZoneOfPain,
    ];
}

// Save JSON
$jsonFile = "$dataDir/pdepend_$repoName.json";
file_put_contents($jsonFile, json_encode($json, JSON_PRETTY_PRINT) . "\n");

// Save timing
$timingFile = "$dataDir/timing.json";
$timing = file_exists($timingFile) ? json_decode(file_get_contents($timingFile), true) : [];
$timing[$repoName]['pdepend'] = $elapsed;
file_put_contents($timingFile, json_encode($timing, JSON_PRETTY_PRINT) . "\n");

// Display summary
$zoneOfPainCount = count(array_filter($json['packages'], fn($p) => $p['zoneOfPain']));

echo "\nResults:\n";
echo "  Files:    " . $json['files'] . "\n";
echo "  LOC:      " . $json['loc'] . "\n";
echo "  NCLOC:    " . $json['ncloc'] . "\n";
echo "  Packages: " . count($json['packages']) . "\n";
echo "  Zone of Pain packages: $zoneOfPainCount\n";

// Show Zone of Pain packages
if ($zoneOfPainCount > 0) {
    echo "\nPackages in Zone of Pain (I < 0.3, A < 0.3):\n";
    $painPackages = array_filter($json['packages'], fn($p) => $p['zoneOfPain']);
    usort($painPackages, fn($a, $b) => $b['classes'] <=> $a['classes']);
    foreach (array_slice($painPackages, 0, 10) as $pkg) {
        printf("  I=%.2f, A=%.2f, Classes=%d - %s\n",
            $pkg['instability'], $pkg['abstractness'], $pkg['classes'], $pkg['name']);
    }
}

echo "\nReport saved to: $jsonFile\n";
echo "Charts saved to: $chartOutput, $pyramidOutput\n";
