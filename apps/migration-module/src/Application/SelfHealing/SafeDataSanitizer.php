<?php

declare(strict_types=1);

namespace MigrationModule\Application\SelfHealing;

final class SafeDataSanitizer
{
    /** @param array<string,mixed> $entity @return array<string,mixed> */
    public function sanitize(array $entity): array
    {
        $result = [];
        foreach ($entity as $field => $value) {
            $result[$field] = $this->sanitizeValue($field, $value);
        }

        return $result;
    }

    private function sanitizeValue(string $field, mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            $clean = trim($value);
            $clean = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $clean) ?? $clean;
            if (str_contains($field, 'email')) {
                $clean = mb_strtolower($clean);
            }
            if (str_contains($field, 'phone')) {
                $clean = preg_replace('/[^\d\+]/', '', $clean) ?? $clean;
            }
            if (str_contains($field, 'timezone') && $clean === '') {
                $clean = 'UTC';
            }
            if (mb_strlen($clean) > 255) {
                $clean = mb_substr($clean, 0, 255);
            }

            return $clean;
        }

        if (is_bool($value) || is_int($value) || is_float($value)) {
            return $value;
        }

        if (is_array($value)) {
            if ($value === []) {
                return [];
            }

            $normalized = [];
            foreach ($value as $k => $v) {
                $normalized[$k] = $this->sanitizeValue((string) $k, $v);
            }

            return $normalized;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->setTimezone(new \DateTimeZone('UTC'))->format(DATE_ATOM);
        }

        if (str_contains($field, 'amount') || str_contains($field, 'price')) {
            return round((float) $value, 2);
        }

        return (string) $value;
    }
}
