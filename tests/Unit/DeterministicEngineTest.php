<?php

declare(strict_types=1);

use MigrationModule\Application\Execution\Deterministic\ExecutionGraphBuilder;
use MigrationModule\Application\Execution\Deterministic\ExecutionPlanBuilder;
use MigrationModule\Application\Execution\Deterministic\FailureClassifier;
use MigrationModule\Application\Execution\Deterministic\ReplayProtectionService;
use MigrationModule\Prototype\Storage\MySqlStorage;
use PHPUnit\Framework\TestCase;

final class DeterministicEngineTest extends TestCase
{
    public function testStablePlanHashing(): void
    {
        $builder = new ExecutionPlanBuilder();
        $config = ['mapping_version' => 'v1', 'cutoff_policy' => ['ts' => '2025-01-01']];
        $entities = ['users' => [['id' => '1']], 'tasks' => [['id' => '2']]];

        $a = $builder->build($config, ['id' => 's'], ['id' => 't'], $entities, 'execute');
        $b = $builder->build($config, ['id' => 's'], ['id' => 't'], $entities, 'execute');

        self::assertSame($a['plan_id'], $b['plan_id']);
        self::assertSame($a['plan_hash'], $b['plan_hash']);
    }

    public function testDeterministicSorting(): void
    {
        $graph = (new ExecutionGraphBuilder())->build([
            'plan_id' => 'plan_x',
            'phases' => [],
            'entities' => [
                'tasks' => [['id' => '9']],
                'users' => [['id' => '2']],
                'contacts' => [['id' => '3']],
            ],
        ]);

        self::assertSame('users', $graph['nodes'][0]['entity_type']);
    }

    public function testReplayProtection(): void
    {
        $path = sys_get_temp_dir() . '/deterministic-test-' . uniqid('', true) . '.sqlite';
        $storage = new MySqlStorage($path);
        $storage->initSchema();
        $storage->createJob('execute');
        $service = new ReplayProtectionService($storage);

        $key = $service->key('plan_1', 'write_entities', 'users', '1', 'h');
        self::assertFalse($service->alreadySuccessful($key));
        $service->remember($key, 'job_x', 'plan_1', 'write_entities', 'users', '1', 'h', 'success');
        self::assertTrue($service->alreadySuccessful($key));
    }

    public function testFailureClassification(): void
    {
        $classifier = new FailureClassifier();

        self::assertSame('rate-limit', $classifier->classify(new RuntimeException('rate exceeded')));
        self::assertSame('permission', $classifier->classify(new RuntimeException('permission denied')));
    }
}
