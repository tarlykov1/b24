<?php

declare(strict_types=1);

namespace MigrationModule\Infrastructure\Config;

final class SimpleYaml
{
    /** @param array<string,mixed> $data */
    public function dump(array $data, int $indent = 0): string
    {
        $lines = [];
        foreach ($data as $key => $value) {
            $prefix = str_repeat(' ', $indent) . $key . ':';
            if (is_array($value)) {
                if ($value === []) {
                    $lines[] = $prefix . ' {}';
                    continue;
                }
                $lines[] = $prefix;
                $lines[] = $this->dump($value, $indent + 2);
                continue;
            }

            $lines[] = $prefix . ' ' . $this->scalar($value);
        }

        return implode("\n", $lines);
    }

    /** @return array<string,mixed> */
    public function parse(string $yaml): array
    {
        $result = [];
        $stack = [[&$result, -1]];
        foreach (preg_split('/\r?\n/', $yaml) ?: [] as $line) {
            if (trim($line) === '' || str_starts_with(trim($line), '#')) {
                continue;
            }

            preg_match('/^(\s*)([^:]+):\s*(.*)$/', $line, $m);
            if ($m === []) {
                continue;
            }
            $level = strlen($m[1]);
            $key = trim($m[2]);
            $raw = trim($m[3]);

            while (count($stack) > 1 && $level <= $stack[count($stack) - 1][1]) {
                array_pop($stack);
            }

            $parent =& $stack[count($stack) - 1][0];
            if ($raw === '') {
                $parent[$key] = [];
                $stack[] = [&$parent[$key], $level];
                continue;
            }

            $parent[$key] = $this->cast($raw);
        }

        return $result;
    }

    private function scalar(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        $s = (string) $value;
        if ($s === '' || preg_match('/[:#\s]/', $s)) {
            return '"' . str_replace('"', '\\"', $s) . '"';
        }

        return $s;
    }

    private function cast(string $raw): mixed
    {
        $trim = trim($raw, "\"'");
        if ($raw === 'true' || $raw === 'false') {
            return $raw === 'true';
        }
        if (is_numeric($trim) && !str_contains($trim, '.')) {
            return (int) $trim;
        }
        if (is_numeric($trim)) {
            return (float) $trim;
        }

        return $trim;
    }
}
