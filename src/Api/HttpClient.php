<?php

declare(strict_types=1);

namespace Siberfx\TelegramBot\Api;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use SensitiveParameter;
use Siberfx\TelegramBot\Contracts\Client;
use Siberfx\TelegramBot\Exceptions\TelegramApiException;
use Siberfx\TelegramBot\Exceptions\TelegramException;

final class HttpClient implements Client
{
    private readonly string $baseUrl;

    public function __construct(
        private readonly HttpFactory $http,
        #[SensitiveParameter] private readonly string $token,
        string $baseUrl = 'https://api.telegram.org',
        private readonly int $timeout = 30,
        private readonly int $retries = 2,
    ) {
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    public function call(string $method, array $params = []): mixed
    {
        if ($this->token === '') {
            throw new TelegramException('The Telegram bot token is not configured.');
        }

        $params = array_filter($params, static fn (mixed $value): bool => $value !== null);

        // Long polling keeps the connection open for "timeout" seconds.
        $timeout = max($this->timeout, (int) ($params['timeout'] ?? 0) + 10);

        $request = $this->http
            ->baseUrl("{$this->baseUrl}/bot{$this->token}")
            ->acceptJson()
            ->timeout($timeout)
            ->retry(
                max(1, $this->retries + 1),
                250,
                static fn (\Throwable $e): bool => $e instanceof ConnectionException,
                throw: false,
            );

        if ($this->hasFiles($params)) {
            foreach ($params as $name => $value) {
                if ($value instanceof InputFile) {
                    $request = $request->attach($name, $value->open(), $value->filename());
                    unset($params[$name]);
                }
            }

            $fields = array_map(
                static fn (mixed $value): string => match (true) {
                    is_array($value), is_object($value) => json_encode($value, JSON_THROW_ON_ERROR),
                    is_bool($value) => $value ? 'true' : 'false',
                    default => (string) $value,
                },
                $params,
            );

            $response = $request->asMultipart()->post($method, $fields);
        } else {
            $response = $request->asJson()->post($method, $params);
        }

        $data = $response->json();

        if (! is_array($data) || ($data['ok'] ?? false) !== true) {
            throw TelegramApiException::fromResponse($method, is_array($data) ? $data : [], $response->status());
        }

        return $data['result'] ?? true;
    }

    public function fileUrl(string $filePath): string
    {
        return "{$this->baseUrl}/file/bot{$this->token}/".ltrim($filePath, '/');
    }

    /** @param array<string, mixed> $params */
    private function hasFiles(array $params): bool
    {
        return array_any($params, static fn (mixed $value): bool => $value instanceof InputFile);
    }
}
