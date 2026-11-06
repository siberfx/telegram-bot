<?php

declare(strict_types=1);

namespace Siberfx\TelegramBot\Storage;

use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Support\Arr;

/**
 * Simple persistent key/value storage scoped to a user, chat or the bot.
 * Values should be scalars or arrays (see cache.serializable_classes).
 *
 *     $bot->userStorage()->put('name', 'Selim');
 *     $bot->userStorage()->get('name');
 */
final readonly class Storage
{
    public function __construct(
        private Cache $cache,
        private string $key,
    ) {}

    public function get(string $key, mixed $default = null): mixed
    {
        return Arr::get($this->all(), $key, $default);
    }

    /** @param string|array<string, mixed> $key */
    public function put(string|array $key, mixed $value = null): self
    {
        $data = $this->all();

        foreach (is_array($key) ? $key : [$key => $value] as $k => $v) {
            Arr::set($data, (string) $k, $v);
        }

        return $this->save($data);
    }

    public function has(string $key): bool
    {
        return Arr::has($this->all(), $key);
    }

    public function forget(string ...$keys): self
    {
        $data = $this->all();
        Arr::forget($data, $keys);

        return $this->save($data);
    }

    public function increment(string $key, int|float $by = 1): int|float
    {
        $value = $this->get($key, 0) + $by;
        $this->put($key, $value);

        return $value;
    }

    public function decrement(string $key, int|float $by = 1): int|float
    {
        return $this->increment($key, -$by);
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        return (array) $this->cache->get($this->key, []);
    }

    public function flush(): self
    {
        $this->cache->forget($this->key);

        return $this;
    }

    /** @param array<string, mixed> $data */
    private function save(array $data): self
    {
        $this->cache->forever($this->key, $data);

        return $this;
    }
}
