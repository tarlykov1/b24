<?php

declare(strict_types=1);

namespace MigrationModule\Application\GoLive;

use DateTimeImmutable;
use RuntimeException;

final class CutoverPlanService
{
    /** @var array<string,array{currentVersion:int,versions:array<int,array<string,mixed>>,status:string}> */
    private array $plans = [];

    /** @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public function create(string $planId, array $payload, string $actorId): array
    {
        if (isset($this->plans[$planId])) {
            throw new RuntimeException('Cutover plan already exists');
        }

        $version = $this->buildVersion(1, $payload, $actorId, 'create');
        $this->plans[$planId] = [
            'currentVersion' => 1,
            'versions' => [1 => $version],
            'status' => 'draft',
        ];

        return $this->snapshot($planId);
    }

    /** @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public function revise(string $planId, array $payload, string $actorId, string $reason): array
    {
        $plan = $this->plans[$planId] ?? null;
        if ($plan === null) {
            throw new RuntimeException('Cutover plan not found');
        }

        $nextVersion = $plan['currentVersion'] + 1;
        $version = $this->buildVersion($nextVersion, $payload, $actorId, $reason);
        $this->plans[$planId]['versions'][$nextVersion] = $version;
        $this->plans[$planId]['currentVersion'] = $nextVersion;

        return $this->snapshot($planId);
    }

    /** @return array<string,mixed> */
    public function snapshot(string $planId): array
    {
        $plan = $this->plans[$planId] ?? null;
        if ($plan === null) {
            throw new RuntimeException('Cutover plan not found');
        }

        $currentVersion = $plan['currentVersion'];

        return [
            'planId' => $planId,
            'status' => $plan['status'],
            'currentVersion' => $currentVersion,
            'plan' => $plan['versions'][$currentVersion]['payload'],
            'versionHistory' => array_values($plan['versions']),
        ];
    }

    /** @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    private function buildVersion(int $version, array $payload, string $actorId, string $reason): array
    {
        return [
            'version' => $version,
            'payload' => $payload,
            'changedBy' => $actorId,
            'reason' => $reason,
            'changedAt' => (new DateTimeImmutable())->format(DATE_ATOM),
            'checksum' => hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)),
        ];
    }
}
