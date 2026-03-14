<?php

declare(strict_types=1);

return [
    'batch_size' => (int) ($_ENV['MIGRATION_BATCH_SIZE'] ?? 100),
    'chunk_size' => (int) ($_ENV['MIGRATION_CHUNK_SIZE'] ?? 500),
    'retry_policy' => [
        'max_retries' => (int) ($_ENV['MIGRATION_MAX_RETRIES'] ?? 3),
        'base_delay_ms' => (int) ($_ENV['MIGRATION_RETRY_BASE_DELAY_MS'] ?? 200),
        'max_delay_ms' => (int) ($_ENV['MIGRATION_RETRY_MAX_DELAY_MS'] ?? 2000),
    ],
    'files' => [
        'temp_dir' => $_ENV['MIGRATION_FILES_TEMP_DIR'] ?? '.prototype/files',
        'verify_checksum' => true,
    ],
];
