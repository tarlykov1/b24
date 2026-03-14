<?php

declare(strict_types=1);

namespace MigrationModule\Infrastructure\Bitrix;

final class BitrixRestClient
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $authToken,
        private readonly int $timeoutSeconds = 30,
        private readonly int $maxRetries = 4,
        private readonly int $baseBackoffMs = 200,
    ) {
    }

    /** @param array<string,mixed> $params */
    public function call(string $method, array $params = []): array
    {
        $attempt = 0;
        while (true) {
            try {
                $response = $this->performHttpCall($method, $params);
                if (($response['error'] ?? null) !== null) {
                    $error = (string) $response['error'];
                    if ($this->isRetryableBitrixError($error) && $attempt < $this->maxRetries) {
                        $this->backoffDelay($attempt++, $response);
                        continue;
                    }

                    throw new \RuntimeException(sprintf('Bitrix API error for %s: %s', $method, $error));
                }

                return (array) ($response['result'] ?? []);
            } catch (\RuntimeException $exception) {
                if ($attempt >= $this->maxRetries) {
                    throw $exception;
                }

                $this->backoffDelay($attempt++);
            }
        }
    }

    /** @param list<array{method:string,params:array<string,mixed>}> $commands */
    public function batch(array $commands): array
    {
        $cmd = [];
        foreach ($commands as $index => $command) {
            $method = $command['method'];
            $query = http_build_query($command['params']);
            $cmd['cmd_' . $index] = $method . ($query === '' ? '' : '?' . $query);
        }

        return $this->call('batch', ['cmd' => $cmd, 'halt' => 0]);
    }

    /** @param array<string,mixed> $filter */
    public function list(string $method, array $filter = [], int $start = 0, int $selectLimit = 50): array
    {
        $result = [];
        $next = $start;
        do {
            $payload = [
                'filter' => $filter,
                'start' => $next,
            ];

            if (str_ends_with($method, '.list')) {
                $payload['select'] = ['*', 'UF_*'];
            }

            $response = $this->call($method, $payload);
            $items = (array) ($response['items'] ?? $response);
            foreach ($items as $item) {
                if (is_array($item)) {
                    $result[] = $item;
                }
            }
            $next = isset($response['next']) ? (int) $response['next'] : -1;

            if (count($items) >= $selectLimit) {
                usleep(1000 * 80);
            }
        } while ($next >= 0);

        return $result;
    }

    /** @param array<string,mixed> $params */
    private function performHttpCall(string $method, array $params): array
    {
        $url = rtrim($this->baseUrl, '/') . '/' . $method . '.json';
        $params['auth'] = $this->authToken;

        $ch = curl_init($url);
        if ($ch === false) {
            throw new \RuntimeException('Unable to initialize cURL');
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($params),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT => $this->timeoutSeconds,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
        ]);

        $raw = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if (!is_string($raw) || $raw === '') {
            throw new \RuntimeException('Bitrix call returned empty response: ' . $error);
        }

        if ($httpCode >= 500 || $httpCode === 429) {
            throw new \RuntimeException('Bitrix HTTP failure code=' . $httpCode);
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            throw new \RuntimeException('Invalid Bitrix JSON response');
        }

        return $decoded;
    }

    /** @param array<string,mixed> $response */
    private function backoffDelay(int $attempt, array $response = []): void
    {
        $retryAfter = isset($response['retry_after']) ? (int) $response['retry_after'] * 1000 : 0;
        $delay = max($retryAfter, (int) ($this->baseBackoffMs * (2 ** $attempt)));
        usleep($delay * 1000);
    }

    private function isRetryableBitrixError(string $error): bool
    {
        return in_array($error, ['QUERY_LIMIT_EXCEEDED', 'INTERNAL_SERVER_ERROR', 'TOO_MANY_REQUESTS'], true);
    }
}
