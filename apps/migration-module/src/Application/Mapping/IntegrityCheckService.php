<?php

declare(strict_types=1);

namespace MigrationModule\Application\Mapping;

final class IntegrityCheckService
{
    public function __construct(private readonly ReferenceResolverService $resolver)
    {
    }

    /** @param array<int,array{entity_type:string,source_id:string}> $references */
    public function validateReferences(string $jobId, array $references): bool
    {
        foreach ($references as $reference) {
            $this->resolver->resolve($jobId, $reference['entity_type'], $reference['source_id']);
        }

        return true;
    }
}
