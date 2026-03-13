<?php

declare(strict_types=1);

namespace MigrationModule\Application\Verification;

use DateTimeImmutable;
use MigrationModule\Application\Logging\MigrationLogger;
use MigrationModule\Domain\Integrity\IntegrityIssue;
use MigrationModule\Infrastructure\Persistence\MigrationIntegrityIssueRepository;

final class VerificationService
{
    public function __construct(
        private readonly MigrationIntegrityIssueRepository $issueRepository,
        private readonly MigrationLogger $logger,
    ) {
    }

    /**
     * @param array<int, array<string, mixed>> $records
     * @return array{checked:int,issues:int}
     */
    public function verify(string $entityType, array $records): array
    {
        $issues = 0;

        foreach ($records as $record) {
            foreach ($this->detectIssues($entityType, $record) as $issue) {
                $this->issueRepository->save($issue);
                $this->logger->warning('integrity_check', $entityType, $issue->entityId, $issue->description);
                $issues++;
            }
        }

        $this->logger->info('integrity_check_completed', $entityType, null, sprintf('Checked: %d, issues: %d', count($records), $issues));

        return ['checked' => count($records), 'issues' => $issues];
    }

    /** @param array<string, mixed> $record
     *  @return array<int, IntegrityIssue>
     */
    private function detectIssues(string $entityType, array $record): array
    {
        return match ($entityType) {
            'users' => $this->checkUsers($record),
            'tasks' => $this->checkTasks($record),
            'crm_deals' => $this->checkDeals($record),
            'comments' => $this->checkComments($record),
            'files' => $this->checkFiles($record),
            default => [],
        };
    }

    /** @return array<int, IntegrityIssue> */
    private function checkUsers(array $record): array
    {
        $issues = [];
        if (empty($record['email'])) {
            $issues[] = $this->issue('users', (string) $record['id'], 'missing_email', 'User has no email');
        }
        if (!isset($record['active'])) {
            $issues[] = $this->issue('users', (string) $record['id'], 'missing_activity_flag', 'User activity flag is absent');
        }

        return $issues;
    }

    /** @return array<int, IntegrityIssue> */
    private function checkTasks(array $record): array
    {
        $issues = [];
        if (empty($record['author_id'])) {
            $issues[] = $this->issue('tasks', (string) $record['id'], 'missing_author', 'Task author is missing');
        }
        if (empty($record['responsible_id'])) {
            $issues[] = $this->issue('tasks', (string) $record['id'], 'missing_responsible', 'Task responsible is missing');
        }
        if (!empty($record['deadline']) && !strtotime((string) $record['deadline'])) {
            $issues[] = $this->issue('tasks', (string) $record['id'], 'invalid_deadline', 'Task deadline is invalid');
        }

        return $issues;
    }

    /** @return array<int, IntegrityIssue> */
    private function checkDeals(array $record): array
    {
        $issues = [];
        if (empty($record['company_id']) && empty($record['contact_id'])) {
            $issues[] = $this->issue('crm_deals', (string) $record['id'], 'missing_relation', 'Deal has no company/contact link');
        }
        if (empty($record['stage_id'])) {
            $issues[] = $this->issue('crm_deals', (string) $record['id'], 'missing_stage', 'Deal stage is absent');
        }
        if (empty($record['assigned_by_id'])) {
            $issues[] = $this->issue('crm_deals', (string) $record['id'], 'missing_responsible', 'Deal responsible is absent');
        }

        return $issues;
    }

    /** @return array<int, IntegrityIssue> */
    private function checkComments(array $record): array
    {
        if (!empty($record['entity_type']) && !empty($record['entity_id'])) {
            return [];
        }

        return [$this->issue('comments', (string) $record['id'], 'orphan_comment', 'Comment is not linked to entity')];
    }

    /** @return array<int, IntegrityIssue> */
    private function checkFiles(array $record): array
    {
        $issues = [];
        if (empty($record['url'])) {
            $issues[] = $this->issue('files', (string) $record['id'], 'missing_link', 'File url is absent');
        }
        if (isset($record['size']) && (int) $record['size'] <= 0) {
            $issues[] = $this->issue('files', (string) $record['id'], 'invalid_size', 'File size should be greater than zero');
        }

        return $issues;
    }

    private function issue(string $entityType, string $entityId, string $problemType, string $description): IntegrityIssue
    {
        return new IntegrityIssue($entityType, $entityId, $problemType, $description, new DateTimeImmutable());
    }
}
