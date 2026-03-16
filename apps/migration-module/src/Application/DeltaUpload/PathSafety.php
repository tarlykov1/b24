<?php

declare(strict_types=1);

namespace MigrationModule\Application\DeltaUpload;

use RuntimeException;

final class PathSafety
{
    public static function normalizeRelative(string $path): string
    {
        $normalized = str_replace('\\', '/', $path);
        $normalized = preg_replace('#/+#', '/', $normalized) ?? $normalized;
        $normalized = ltrim($normalized, '/');
        if ($normalized === '' || str_contains($normalized, "\0")) {
            throw new RuntimeException('invalid_relative_path');
        }

        $parts = [];
        foreach (explode('/', $normalized) as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }
            if ($part === '..') {
                throw new RuntimeException('path_traversal_detected');
            }
            $parts[] = $part;
        }

        return implode('/', $parts);
    }

    public static function ensureInsideRoot(string $root, string $candidate): string
    {
        $rootReal = realpath($root);
        if ($rootReal === false) {
            throw new RuntimeException('missing_root');
        }

        $full = $rootReal . '/' . self::normalizeRelative($candidate);
        $dir = dirname($full);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        $dirReal = realpath($dir);
        if ($dirReal === false || !str_starts_with($dirReal, $rootReal)) {
            throw new RuntimeException('path_escape_detected');
        }

        return $full;
    }
}
