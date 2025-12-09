<?php

/**
 * Analyze cognitive complexity bucket distribution
 */

$framework = $argv[1] ?? 'laravel';
$dataFile = __DIR__ . "/reports/data/cognitive_$framework.json";

if (!file_exists($dataFile)) {
    echo "Error: $dataFile not found\n";
    exit(1);
}

echo "Loading $framework cognitive complexity data...\n";
$json = json_decode(file_get_contents($dataFile), true);

if (!is_array($json)) {
    echo "Error: Invalid JSON\n";
    exit(1);
}

// Collect all method scores
$scores = [];
foreach ($json as $className => $classData) {
    if (isset($classData['methods']) && is_array($classData['methods'])) {
        foreach ($classData['methods'] as $methodName => $methodData) {
            $scores[] = $methodData['score'] ?? 0;
        }
    }
}

$totalMethods = count($scores);
if ($totalMethods === 0) {
    echo "No methods found\n";
    exit(1);
}

sort($scores);

// Calculate buckets
$buckets = [
    '0-1' => 0,
    '1-3' => 0,
    '3-5' => 0,
    '5-10' => 0,
    '10+' => 0,
];

foreach ($scores as $score) {
    if ($score < 1) {
        $buckets['0-1']++;
    } elseif ($score < 3) {
        $buckets['1-3']++;
    } elseif ($score < 5) {
        $buckets['3-5']++;
    } elseif ($score < 10) {
        $buckets['5-10']++;
    } else {
        $buckets['10+']++;
    }
}

// Calculate percentiles
$p50_idx = (int)($totalMethods * 0.50);
$p75_idx = (int)($totalMethods * 0.75);
$p90_idx = (int)($totalMethods * 0.90);
$p95_idx = (int)($totalMethods * 0.95);
$p99_idx = (int)($totalMethods * 0.99);

echo "\n";
echo "=== Cognitive Complexity Analysis: $framework ===\n\n";
echo "Total Methods: " . number_format($totalMethods) . "\n\n";

echo "Percentiles:\n";
echo sprintf("  P50 (median): %.3f\n", $scores[$p50_idx]);
echo sprintf("  P75:          %.3f\n", $scores[$p75_idx]);
echo sprintf("  P90:          %.3f\n", $scores[$p90_idx]);
echo sprintf("  P95:          %.3f\n", $scores[$p95_idx]);
echo sprintf("  P99:          %.3f\n", $scores[$p99_idx]);
echo sprintf("  Max:          %.3f\n\n", max($scores));

echo "Bucket Distribution:\n";
foreach ($buckets as $range => $count) {
    $pct = ($count / $totalMethods) * 100;
    echo sprintf("  Complexity %5s: %6d methods (%5.2f%%)\n", $range, $count, $pct);
}

// Find methods with complexity >= 10
echo "\n";
echo "Methods with complexity >= 10:\n";
$complex_methods = [];
foreach ($json as $className => $classData) {
    if (isset($classData['methods']) && is_array($classData['methods'])) {
        foreach ($classData['methods'] as $methodName => $methodData) {
            $score = $methodData['score'] ?? 0;
            if ($score >= 10) {
                $complex_methods[] = [
                    'class' => $className,
                    'method' => $methodName,
                    'score' => $score,
                ];
            }
        }
    }
}

// Sort by score descending
usort($complex_methods, fn($a, $b) => $b['score'] <=> $a['score']);

if (count($complex_methods) > 0) {
    echo sprintf("  Total: %d methods\n\n", count($complex_methods));
    echo "  Top 10 most complex methods:\n";
    foreach (array_slice($complex_methods, 0, 10) as $m) {
        echo sprintf("    %.3f - %s::%s\n", $m['score'], $m['class'], $m['method']);
    }
} else {
    echo "  None!\n";
}

// Also show complexity 5+ methods
echo "\n";
echo "Methods with complexity >= 5:\n";
$complex5_methods = [];
foreach ($json as $className => $classData) {
    if (isset($classData['methods']) && is_array($classData['methods'])) {
        foreach ($classData['methods'] as $methodName => $methodData) {
            $score = $methodData['score'] ?? 0;
            if ($score >= 5) {
                $complex5_methods[] = [
                    'class' => $className,
                    'method' => $methodName,
                    'score' => $score,
                ];
            }
        }
    }
}

usort($complex5_methods, fn($a, $b) => $b['score'] <=> $a['score']);

if (count($complex5_methods) > 0) {
    echo sprintf("  Total: %d methods\n\n", count($complex5_methods));
    foreach ($complex5_methods as $m) {
        echo sprintf("    %.3f - %s::%s\n", $m['score'], $m['class'], $m['method']);
    }
} else {
    echo "  None!\n";
}
