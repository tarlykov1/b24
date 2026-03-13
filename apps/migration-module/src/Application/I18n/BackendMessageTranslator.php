<?php

declare(strict_types=1);

namespace MigrationModule\Application\I18n;

final class BackendMessageTranslator
{
    private const DEFAULT_LOCALE = 'en';

    /**
     * @var array<string, array<string, string>>
     */
    private const DICTIONARY = [
        'en' => [
            'MIGRATION_STARTED' => 'Migration started',
            'MIGRATION_PAUSED' => 'Migration paused',
            'MIGRATION_RESUMED' => 'Migration resumed',
            'MIGRATION_COMPLETED' => 'Migration completed',
            'WARNING_RATE_LIMIT' => 'Warning: rate limit is close',
            'ERROR_API_CONNECTION' => 'Error: connection to Bitrix24 API failed',
        ],
        'ru' => [
            'MIGRATION_STARTED' => 'Миграция запущена',
            'MIGRATION_PAUSED' => 'Миграция поставлена на паузу',
            'MIGRATION_RESUMED' => 'Миграция продолжена',
            'MIGRATION_COMPLETED' => 'Миграция завершена',
            'WARNING_RATE_LIMIT' => 'Предупреждение: лимит запросов почти исчерпан',
            'ERROR_API_CONNECTION' => 'Ошибка: не удалось подключиться к Bitrix24 API',
        ],
    ];

    public function translate(string $messageCode, string $locale = self::DEFAULT_LOCALE): string
    {
        $normalizedLocale = $this->normalizeLocale($locale);

        return self::DICTIONARY[$normalizedLocale][$messageCode]
            ?? self::DICTIONARY[self::DEFAULT_LOCALE][$messageCode]
            ?? $messageCode;
    }

    private function normalizeLocale(string $locale): string
    {
        return match (strtolower($locale)) {
            'ru', 'ru_ru', 'ru-ru' => 'ru',
            default => self::DEFAULT_LOCALE,
        };
    }
}
