<?php

declare(strict_types=1);

$commands = [
    'php bin/migration-module audit:run',
    'php bin/migration-module audit:summary',
    'php bin/migration-module audit:ownership',
    'php bin/migration-module audit:report',
];

foreach ($commands as $command) {
    exec($command . ' 2>&1', $output, $code);
    if ($code !== 0) {
        fwrite(STDERR, "[fail] {$command}\n" . implode("\n", $output) . "\n");
        exit(1);
    }
    echo "[ok] {$command}\n";
}
