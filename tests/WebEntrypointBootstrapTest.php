<?php

declare(strict_types=1);

function runWebEntrypoint(?int &$exitCode = null, ?string &$stderr = null): string
{
    $cmd = 'php web/index.php';
    $proc = proc_open(
        $cmd,
        [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
        __DIR__ . '/..'
    );

    if (!is_resource($proc)) {
        throw new RuntimeException('Cannot execute web entrypoint test process.');
    }

    $stdout = stream_get_contents($pipes[1]) ?: '';
    fclose($pipes[1]);
    $stderrOut = stream_get_contents($pipes[2]) ?: '';
    fclose($pipes[2]);

    $exitCode = proc_close($proc);
    $stderr = $stderrOut;

    return $stdout;
}

function assertTrue(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException('FAIL: ' . $message);
    }
}

$vendorAutoload = __DIR__ . '/../vendor/autoload.php';
assertTrue(!is_file($vendorAutoload), 'test expects vendor/autoload.php to be absent in offline layout');

putenv('DB_NAME');
putenv('DB_USER');
$_ENV['DB_NAME'] = '';
$_ENV['DB_USER'] = '';

$output = runWebEntrypoint($code, $stderr);

assertTrue($code === 0, 'web/index.php should not crash in vendor-less mode');
assertTrue(!str_contains(strtolower($stderr ?? ''), 'fatal error'), 'stderr must not include fatal error');
assertTrue(str_contains($output, 'Bitrix Migration Installer (MySQL-only)'), 'installer page should be rendered as controlled fallback');

echo "Web entrypoint bootstrap test passed\n";
