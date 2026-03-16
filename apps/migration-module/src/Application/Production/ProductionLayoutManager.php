<?php

declare(strict_types=1);

namespace MigrationModule\Application\Production;

final class ProductionLayoutManager
{
    /** @return array{root:string,created:array<int,string>} */
    public function ensure(string $root): array
    {
        $dirs = [
            'bin', 'config', 'runtime', 'runtime/logs', 'runtime/queue',
            'storage', 'storage/backups', 'storage/snapshots', 'web', 'src', 'upgrades',
        ];
        $created = [];
        foreach ($dirs as $dir) {
            $path = rtrim($root, '/') . '/' . $dir;
            if (!is_dir($path)) {
                mkdir($path, 0750, true);
                $created[] = $path;
            }
        }

        return ['root' => $root, 'created' => $created];
    }
}
