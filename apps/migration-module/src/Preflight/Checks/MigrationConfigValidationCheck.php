<?php

declare(strict_types=1);

namespace MigrationModule\Preflight\Checks;

use MigrationModule\Preflight\CheckContext;
use MigrationModule\Preflight\CheckInterface;
use MigrationModule\Preflight\CheckResult;

final class MigrationConfigValidationCheck implements CheckInterface
{
    public function __construct(private readonly CheckContext $context)
    {
    }

    public function run(): CheckResult
    {
        $requiredSections = ['source', 'target', 'workers'];
        $missing = [];
        foreach ($requiredSections as $section) {
            if (!isset($this->context->config[$section]) || !is_array($this->context->config[$section])) {
                $missing[] = $section;
            }
        }

        $policySections = ['entity_policies', 'conflict_policies', 'mapping_rules', 'retry_rules'];
        $policyMissing = [];
        foreach ($policySections as $section) {
            if (!isset($this->context->config[$section])) {
                $policyMissing[] = $section;
            }
        }

        if ($missing !== [] || $policyMissing !== []) {
            return new CheckResult(
                'migration_config_validation',
                'warning',
                'Missing required config sections: ' . implode(', ', array_merge($missing, $policyMissing)),
                ['missing_sections' => array_merge($missing, $policyMissing)],
                ['Populate missing sections in migration config before execution.'],
            );
        }

        return new CheckResult('migration_config_validation', 'ok', 'Required migration config sections are present.');
    }
}
