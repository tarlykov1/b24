<?php

declare(strict_types=1);

namespace MigrationModule\Prototype\DB;

use MigrationModule\Prototype\Storage\MySqlStorage;

final class EntityGraphBuilder
{
    public function __construct(private readonly MySqlStorage $storage)
    {
    }

    public function build(string $jobId, array $snapshot): array
    {
        $edges = [];
        foreach ((array) ($snapshot['detected_relations'] ?? []) as $relation) {
            $from = (string) ($relation['from_table'] ?? '');
            $to = (string) ($relation['to_entity_hint'] ?? '');
            if ($from === '' || $to === '') {
                continue;
            }
            $edges[] = ['from' => $from, 'to' => $to];
        }

        $nodes = array_keys((array) ($snapshot['tables'] ?? []));
        $order = $this->topologicalOrder($nodes, $edges);
        $groups = [];
        foreach ((array) ($snapshot['entity_classifications'] ?? []) as $table => $group) {
            $groups[(string) $group][] = $table;
        }

        $graph = ['nodes' => $nodes, 'edges' => $edges, 'topological_order' => $order, 'groups' => $groups];
        $this->storage->saveEntityGraph($jobId, $graph);

        return $graph;
    }

    /** @param list<string> $nodes @param array<int,array{from:string,to:string}> $edges @return list<string> */
    private function topologicalOrder(array $nodes, array $edges): array
    {
        $inDegree = array_fill_keys($nodes, 0);
        $adj = array_fill_keys($nodes, []);
        foreach ($edges as $edge) {
            if (!isset($inDegree[$edge['from']], $inDegree[$edge['to']])) {
                continue;
            }
            $adj[$edge['from']][] = $edge['to'];
            $inDegree[$edge['to']]++;
        }

        $queue = [];
        foreach ($inDegree as $node => $degree) {
            if ($degree === 0) {
                $queue[] = $node;
            }
        }

        $order = [];
        while ($queue !== []) {
            $node = array_shift($queue);
            if (!is_string($node)) {
                continue;
            }
            $order[] = $node;
            foreach ((array) ($adj[$node] ?? []) as $next) {
                $inDegree[$next]--;
                if ($inDegree[$next] === 0) {
                    $queue[] = $next;
                }
            }
        }

        return $order === [] ? $nodes : $order;
    }
}
