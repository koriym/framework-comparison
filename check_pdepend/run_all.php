<?php

/**
 * Run PDepend analysis on all frameworks.
 */

$frameworks = ['bearsunday', 'cakephp', 'codeigniter', 'laminas', 'laravel', 'symfony', 'yii2'];

echo "Running PDepend analysis on all frameworks...\n";
echo "This may take several minutes.\n\n";

foreach ($frameworks as $fw) {
    echo str_repeat('=', 60) . "\n";
    passthru("php " . __DIR__ . "/$fw.php");
    echo "\n";
}

echo str_repeat('=', 60) . "\n";
echo "All PDepend analyses complete!\n";
