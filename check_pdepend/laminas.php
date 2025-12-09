<?php

/**
 * PDepend analysis for Laminas (multi-package).
 */

$baseDir = dirname(__DIR__);
$reposDir = $baseDir . '/repos';
$dataDir = $baseDir . '/reports/data';
$pdependBin = $baseDir . '/bin/pdepend.phar';
$config = require "$baseDir/config.php";

$repoName = 'laminas';
$packages = $config['laminas']['packages'];

echo "=== PDepend Analysis: Laminas (multi-package) ===\n";

// Ensure all packages are cloned
$analyzePaths = [];
foreach ($packages as $packageName => $packageConfig) {
    $packagePath = "$reposDir/$repoName/$packageName";
    $packageRepo = $packageConfig['repo'];
    $packageBranch = $packageConfig['branch'];

    if (!is_dir($packagePath)) {
        echo "Cloning $packageRepo...\n";
        $branchArg = $packageBranch ? " --branch $packageBranch" : '';
        $cloneCommand = "git clone --depth 1$branchArg https://github.com/$packageRepo.git $packagePath";
        exec($cloneCommand, $output, $status);
        if ($status !== 0) {
            echo "Warning: Failed to clone $packageRepo\n";
            continue;
        }
    }

    $srcPath = "$packagePath/src";
    if (is_dir($srcPath)) {
        $analyzePaths[] = $srcPath;
    }
}

if (empty($analyzePaths)) {
    echo "Error: No source directories found\n";
    exit(1);
}

// Run pdepend
$summaryXml = "$dataDir/pdepend_$repoName.xml";
$chartOutput = "$dataDir/pdepend_chart_$repoName.svg";
$pyramidOutput = "$dataDir/pdepend_pyramid_$repoName.svg";

echo "Running PDepend analysis on " . count($analyzePaths) . " packages...\n";

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

$outputStr = implode("\n", $output);
if (strpos($outputStr, 'Critical error:') !== false) {
    echo "Warning: PDepend had errors\n";
    echo $outputStr . "\n";
}

if (!file_exists($summaryXml)) {
    echo "Error: Summary XML not generated\n";
    exit(1);
}

// Parse XML
$xml = simplexml_load_file($summaryXml);
$json = [
    'files' => (int)$xml['files'],
    'loc' => (int)$xml['loc'],
    'ncloc' => (int)$xml['ncloc'],
    'packages' => [],
];

foreach ($xml->package as $package) {
    $packageName = (string)$package['name'];

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

    $totalCoupling = $packageCE + $packageCA;
    $instability = $totalCoupling > 0 ? round($packageCE / $totalCoupling, 3) : 0;
    $abstractness = $packageClasses > 0 ? round($packageAbstract / $packageClasses, 3) : 0;
    $distance = abs($abstractness + $instability - 1);
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
