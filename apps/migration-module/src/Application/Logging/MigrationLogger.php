<?php

declare(strict_types=1);

namespace MigrationModule\Application\Logging;

use MigrationModule\Application\I18n\BackendMessageTranslator;
use MigrationModule\Domain\Log\LogRecord;

final class MigrationLogger
{
    private BackendMessageTranslator $translator;

    public function __construct(?BackendMessageTranslator $translator = null)
    {
        $this->translator = $translator ?? new BackendMessageTranslator();
    }

    public function log(LogRecord $record): void
    {
        // TODO: write structured logs to migration_log and system sink.
    }

    public function localizeMessageCode(string $messageCode, string $locale = 'en'): string
    {
        return $this->translator->translate($messageCode, $locale);
    }
}
