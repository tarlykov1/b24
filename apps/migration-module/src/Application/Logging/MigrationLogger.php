<?php

declare(strict_types=1);

namespace MigrationModule\Application\Logging;

use MigrationModule\Application\I18n\BackendMessageTranslator;
use MigrationModule\Domain\Log\LogRecord;
use MigrationModule\Infrastructure\Persistence\MigrationRepository;

final class MigrationLogger
{
    private BackendMessageTranslator $translator;

    public function __construct(?BackendMessageTranslator $translator = null)
    {
        $this->translator = $translator ?? new BackendMessageTranslator();
    }

    public function log(LogRecord $record): void
    {
    }

    public function log(string $jobId, LogRecord $record, string $channel = 'technical'): void
    {
        $row = [
            'job_id' => $jobId,
            'timestamp' => (new \DateTimeImmutable())->format(DATE_ATOM),
            'level' => $record->level,
            'channel' => $channel,
            'entity_type' => $record->entityType,
            'entity_id' => $record->oldId,
            'message' => $record->action,
            'new_id' => $record->newId,
            'error' => $record->error,
            'retry_count' => $record->retryCount,
            'duration_ms' => $record->durationMs,
        ];

        $this->repository->appendLog($row);
        error_log('[migration:' . $jobId . '] ' . $record->level . ' ' . $record->action);
    }

    public function localizeMessageCode(string $messageCode, string $locale = 'en'): string
    {
        return $this->translator->translate($messageCode, $locale);
    }
}
