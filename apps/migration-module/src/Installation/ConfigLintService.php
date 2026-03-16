<?php

declare(strict_types=1);

namespace MigrationModule\Installation;

final class ConfigLintService
{
    /** @param array<string,mixed> $config
     * @return array<string,mixed>
     */
    public function lint(array $config): array
    {
        $validator = new InstallConfigValidator();
        $result = $validator->validate($config);

        return [
            'ok' => count($result['blockers']) === 0,
            'blockers' => $result['blockers'],
            'warnings' => $result['warnings'],
            'recommendations' => $result['recommendations'],
        ];
    }
}
