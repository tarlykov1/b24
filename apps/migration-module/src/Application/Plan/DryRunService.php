<?php

declare(strict_types=1);

namespace MigrationModule\Application\Plan;

final class DryRunService
{
    public function __construct(private readonly MigrationPlanningService $planningService)
    {
    }

    /**
     * @param array<string, array<int, array<string, mixed>>> $source
     * @param array<string, array<int, array<string, mixed>>> $target
     * @return array<string,mixed>
     */
    public function execute(string $jobId, array $source, array $target, bool $incremental = false): array
    {
        $plan = $this->planningService->buildPlan($jobId, $source, $target, $incremental);
        $manual = array_values(array_filter($plan['items'], static fn (array $item): bool => $item['action'] === 'manual_review'));
        $conflicts = array_values(array_filter($plan['items'], static fn (array $item): bool => $item['action'] === 'conflict'));

        return [
            'mode' => 'dry_run',
            'write_operations' => 0,
            'summary' => [
                'to_create' => $plan['summary']['create'],
                'to_update' => $plan['summary']['update'],
                'to_skip' => $plan['summary']['skip'],
                'conflicts' => $plan['summary']['conflict'],
                'manual_review' => $plan['summary']['manual_review'],
            ],
            'restored_links' => $this->collectRestoredLinks($plan['items']),
            'manual_review_items' => $manual,
            'conflicts' => $conflicts,
            'plan' => $plan,
        ];
    }

    /** @param array<int,array<string,mixed>> $items @return array<int,string> */
    private function collectRestoredLinks(array $items): array
    {
        $links = [];
        foreach ($items as $item) {
            foreach ((array) ($item['dependencies'] ?? []) as $dependency) {
                $links[] = sprintf('%s=>%s', $item['entity_type'], (string) $dependency);
            }
        }

        return array_values(array_unique($links));
    }
}
