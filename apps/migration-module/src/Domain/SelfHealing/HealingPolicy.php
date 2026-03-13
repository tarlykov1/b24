<?php

declare(strict_types=1);

namespace MigrationModule\Domain\SelfHealing;

enum HealingPolicy: string
{
    case CONSERVATIVE = 'conservative';
    case STANDARD = 'standard';
    case AGGRESSIVE = 'aggressive';

    public function allowsAutoCreate(): bool
    {
        return $this !== self::CONSERVATIVE;
    }

    public function allowsAggressiveRetries(): bool
    {
        return $this === self::AGGRESSIVE;
    }

    public function allowsUnsafeMerge(): bool
    {
        return false;
    }
}
