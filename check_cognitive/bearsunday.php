<?php

/**
 * BEAR.Sunday cognitive complexity analysis - analyzes vendor/bear/* and vendor/ray/*.
 */

$baseDir = dirname(__DIR__);
$reposDir = $baseDir . '/repos';
$dataDir = $baseDir . '/reports/data';
$repoName = 'bearsunday';
$repoPath = "$reposDir/$repoName";
$reportPath = "$dataDir/cognitive_$repoName.json";

// Load config
$config = require "$baseDir/config.php";
$bearConfig = $config[$repoName];
$vendorDirs = $bearConfig['vendorDirs'] ?? [];

if (!is_dir($dataDir)) {
    mkdir($dataDir, 0777, true);
}

echo "=== Cognitive Complexity: BEAR.Sunday (from BEAR.Package vendor) ===\n";

// Repository should already be cloned by PHPStan check
if (!is_dir($repoPath)) {
    echo "Error: Repository not found. Run PHPStan check first.\n";
    exit(1);
}

// Use global cognitive tool from project root
$ccaBin = "$baseDir/vendor/bin/phpcca";
if (!file_exists($ccaBin)) {
    echo "Error: cognitive-code-analysis not installed. Run: composer require phauthentic/cognitive-code-analysis\n";
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

// Run cognitive analysis on all directories and merge results
$allResults = [];
$totalTime = 0;

foreach ($srcDirs as $srcDir) {
    $tempReport = "/tmp/cognitive_temp_" . basename(dirname($srcDir)) . ".json";

    $startTime = microtime(true);
    exec("$ccaBin analyse $srcDir --report-type=json --report-file=$tempReport 2>&1", $output, $status);
    $totalTime += microtime(true) - $startTime;

    if (file_exists($tempReport)) {
        $json = json_decode(file_get_contents($tempReport), true);
        if (is_array($json)) {
            foreach ($json as $class => $data) {
                $allResults[$class] = $data;
            }
        }
        unlink($tempReport);
    }
}

// Save combined results
file_put_contents($reportPath, json_encode($allResults, JSON_PRETTY_PRINT));

// Save timing
$timingFile = "$dataDir/timing.json";
$timing = file_exists($timingFile) ? json_decode(file_get_contents($timingFile), true) : [];
$timing[$repoName]['cognitive'] = round($totalTime, 1);
file_put_contents($timingFile, json_encode($timing, JSON_PRETTY_PRINT) . "\n");

// Calculate statistics
$totalMethods = 0;
$totalScore = 0;
$maxScore = 0;
$maxMethod = '';

foreach ($allResults as $className => $classData) {
    if (isset($classData['methods']) && is_array($classData['methods'])) {
        foreach ($classData['methods'] as $methodName => $methodData) {
            $totalMethods++;
            $score = $methodData['score'] ?? 0;
            $totalScore += $score;
            if ($score > $maxScore) {
                $maxScore = $score;
                $maxMethod = "$className::$methodName";
            }
        }
    }
}

$avgScore = $totalMethods > 0 ? round($totalScore / $totalMethods, 2) : 0;

echo "\nResults:\n";
echo "  Methods analyzed:        $totalMethods\n";
echo "  Total cognitive score:   $totalScore\n";
echo "  Avg cognitive/method:    $avgScore\n";
echo "  Max cognitive score:     $maxScore\n";
echo "  Most complex method:     $maxMethod\n";
echo "\nReport saved to: $reportPath\n";
