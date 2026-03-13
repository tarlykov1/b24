<?php

declare(strict_types=1);

namespace MigrationModule\Domain\Diff;

final class DiffCategory
{
    public const NEW_ENTITIES = 'new_entities';
    public const CHANGED_ENTITIES = 'changed_entities';
    public const CONFLICTING_ENTITIES = 'conflicting_entities';
    public const MISSING_TARGET_ENTITIES = 'missing_target_entities';
    public const MANUAL_REVIEW_REQUIRED = 'manual_review_required';
}
