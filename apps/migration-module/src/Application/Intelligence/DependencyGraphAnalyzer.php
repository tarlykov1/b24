<?php

declare(strict_types=1);

namespace MigrationModule\Application\Intelligence;

final class DependencyGraphAnalyzer
{
    /**
     * @param array<string,list<string>> $graph
     * @return array{safe_order:list<string>,cycles:list<array{from:string,to:string}>,levels:list<list<string>>}
     */
    public function analyze(array $graph): array
    {
        $normalized = $this->normalize($graph);
        $inDegree = array_fill_keys(array_keys($normalized), 0);

        foreach ($normalized as $node => $dependencies) {
            foreach ($dependencies as $dependency) {
                if (!array_key_exists($dependency, $inDegree)) {
                    $inDegree[$dependency] = 0;
                    $normalized[$dependency] = [];
                }
                $inDegree[$node]++;
            }
        }

        $queue = [];
        foreach ($inDegree as $node => $degree) {
            if ($degree === 0) {
                $queue[] = $node;
            }
        }
        sort($queue);

        $safeOrder = [];
        $levels = [];
        while ($queue !== []) {
            $level = $queue;
            $levels[] = $level;
            $queue = [];

            foreach ($level as $node) {
                $safeOrder[] = $node;
                foreach ($normalized as $target => $dependencies) {
                    if (in_array($node, $dependencies, true)) {
                        $inDegree[$target]--;
                        if ($inDegree[$target] === 0) {
                            $queue[] = $target;
                        }
                    }
                }
            }

            sort($queue);
        }

        $cycles = [];
        foreach ($inDegree as $node => $degree) {
            if ($degree <= 0) {
                continue;
            }
            foreach ($normalized[$node] as $dependency) {
                if (($inDegree[$dependency] ?? 0) > 0) {
                    $cycles[] = ['from' => $node, 'to' => $dependency];
                }
            }
        }

        return [
            'safe_order' => $safeOrder,
            'cycles' => array_values(array_unique($cycles, SORT_REGULAR)),
            'levels' => $levels,
        ];
    }

    /** @param array<string,list<string>> $graph @return array<string,list<string>> */
    private function normalize(array $graph): array
    {
        $nodes = ['users', 'departments', 'groups', 'crm_entities', 'tasks', 'files'];
        $normalized = [];

        foreach ($nodes as $node) {
            $normalized[$node] = array_values(array_unique($graph[$node] ?? []));
        }

        foreach ($graph as $node => $dependencies) {
            if (!array_key_exists($node, $normalized)) {
                $normalized[$node] = array_values(array_unique($dependencies));
            }
        }

        return $normalized;
    }
}
