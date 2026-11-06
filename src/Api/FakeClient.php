<?php

declare(strict_types=1);

namespace Siberfx\TelegramBot\Api;

use Closure;
use Siberfx\TelegramBot\Contracts\Client;

/**
 * An in-memory client used by tests and the console tinker.
 * Every call is recorded and answered with a plausible Bot API result.
 */
final class FakeClient implements Client
{
    /** @var list<array{method: string, params: array<string, mixed>}> */
    private array $calls = [];

    /** @var array<string, list<mixed>> */
    private array $responses = [];

    private int $messageId = 1000;

    public function call(string $method, array $params = []): mixed
    {
        $params = array_filter($params, static fn (mixed $value): bool => $value !== null);
        $this->calls[] = ['method' => $method, 'params' => $params];

        if (isset($this->responses[$method]) && $this->responses[$method] !== []) {
            $response = count($this->responses[$method]) > 1
                ? array_shift($this->responses[$method])
                : $this->responses[$method][0];

            return $response instanceof Closure ? $response($params) : $response;
        }

        return $this->defaultResponse($method, $params);
    }

    public function fileUrl(string $filePath): string
    {
        return 'https://api.telegram.org/file/botFAKE-TOKEN/'.ltrim($filePath, '/');
    }

    /**
     * Queue responses for a method. The last one is repeated for later calls.
     */
    public function respondWith(string $method, mixed ...$responses): self
    {
        $this->responses[$method] = array_values($responses);

        return $this;
    }

    /** @return list<array{method: string, params: array<string, mixed>}> */
    public function calls(?string $method = null): array
    {
        if ($method === null) {
            return $this->calls;
        }

        return array_values(array_filter($this->calls, static fn (array $call): bool => $call['method'] === $method));
    }

    public function flush(): void
    {
        $this->calls = [];
    }

    /** @param array<string, mixed> $params */
    private function defaultResponse(string $method, array $params): mixed
    {
        return match (true) {
            str_starts_with($method, 'send') && $method !== 'sendChatAction',
            str_starts_with($method, 'edit') => [
                'message_id' => isset($params['message_id']) ? (int) $params['message_id'] : ++$this->messageId,
                'date' => time(),
                'chat' => ['id' => $params['chat_id'] ?? 0, 'type' => 'private'],
                'from' => ['id' => 1, 'is_bot' => true, 'first_name' => 'FakeBot', 'username' => 'fake_bot'],
                'text' => $params['text'] ?? null,
                'caption' => $params['caption'] ?? null,
                'reply_markup' => $params['reply_markup'] ?? null,
            ],
            $method === 'getMe' => ['id' => 1, 'is_bot' => true, 'first_name' => 'FakeBot', 'username' => 'fake_bot'],
            $method === 'getFile' => [
                'file_id' => $params['file_id'] ?? 'file',
                'file_unique_id' => 'unique-'.($params['file_id'] ?? 'file'),
                'file_path' => 'files/'.($params['file_id'] ?? 'file'),
            ],
            $method === 'getUpdates' => [],
            $method === 'getWebhookInfo' => ['url' => '', 'has_custom_certificate' => false, 'pending_update_count' => 0],
            $method === 'getMyCommands' => [],
            default => true,
        };
    }
}
