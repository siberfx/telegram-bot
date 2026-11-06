<?php

declare(strict_types=1);

namespace Siberfx\TelegramBot\Conversations;

use Closure;
use Illuminate\Contracts\Cache\Repository as Cache;
use Laravel\SerializableClosure\SerializableClosure;
use Siberfx\TelegramBot\Outgoing\OutgoingMessage;

/**
 * Persists waiting conversations per chat + user.
 *
 * The payload is serialized to a plain string before it hits the cache, so it
 * works with Laravel 13's `cache.serializable_classes = false` hardening.
 */
final readonly class ConversationStore
{
    public function __construct(
        private Cache $cache,
        private string $prefix,
        private int $ttl,
    ) {}

    /**
     * @param  array<string, mixed>  $params
     */
    public function put(
        int|string $chatId,
        int|string|null $userId,
        Conversation $conversation,
        string|OutgoingMessage $question,
        Closure|string|array|SerializableClosure $next,
        array $params = [],
    ): void {
        $payload = $conversation->detached(fn (): string => serialize([
            'conversation' => $conversation,
            'question' => $question,
            'next' => $this->wrap($next),
            'params' => $params,
        ]));

        $this->cache->put($this->key($chatId, $userId), $payload, $this->ttl);
    }

    public function get(int|string $chatId, int|string|null $userId): ?PendingConversation
    {
        $payload = $this->cache->get($this->key($chatId, $userId));

        if (! is_string($payload)) {
            return null;
        }

        $data = @unserialize($payload);

        if (! is_array($data) || ! ($data['conversation'] ?? null) instanceof Conversation) {
            $this->forget($chatId, $userId);

            return null;
        }

        return new PendingConversation($data['conversation'], $data['question'], $data['next'], (array) $data['params']);
    }

    public function has(int|string $chatId, int|string|null $userId): bool
    {
        return $this->cache->has($this->key($chatId, $userId));
    }

    public function forget(int|string $chatId, int|string|null $userId): void
    {
        $this->cache->forget($this->key($chatId, $userId));
    }

    private function key(int|string $chatId, int|string|null $userId): string
    {
        return "{$this->prefix}:conversation:{$chatId}:".($userId ?? $chatId);
    }

    private function wrap(mixed $next): mixed
    {
        if ($next instanceof Closure) {
            return new SerializableClosure($next);
        }

        if (is_array($next)) {
            return array_map(fn (array $entry): array => [
                'pattern' => (string) $entry['pattern'],
                'callback' => $this->wrap($entry['callback']),
            ], $next);
        }

        return $next;
    }
}
