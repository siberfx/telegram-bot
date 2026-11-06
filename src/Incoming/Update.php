<?php

declare(strict_types=1);

namespace Siberfx\TelegramBot\Incoming;

use Illuminate\Support\Arr;
use InvalidArgumentException;

/**
 * Immutable wrapper around a raw Telegram Update object.
 */
final readonly class Update
{
    /** Update types that carry a Message object. */
    public const array MESSAGE_TYPES = [
        'message', 'edited_message', 'channel_post', 'edited_channel_post',
        'business_message', 'edited_business_message',
    ];

    /** @param array<string, mixed> $data */
    public function __construct(public array $data)
    {
        if (! isset($data['update_id'])) {
            throw new InvalidArgumentException('A Telegram update must contain an [update_id].');
        }
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self($data);
    }

    public static function fromJson(string $json): self
    {
        return new self((array) json_decode($json, true, 512, JSON_THROW_ON_ERROR));
    }

    public function id(): int
    {
        return (int) $this->data['update_id'];
    }

    /** e.g. "message", "callback_query", "inline_query", "my_chat_member" ... */
    public function type(): ?string
    {
        foreach (array_keys($this->data) as $key) {
            if ($key !== 'update_id') {
                return (string) $key;
            }
        }

        return null;
    }

    /** @return array<string, mixed> */
    public function payload(): array
    {
        $type = $this->type();

        return $type === null ? [] : (array) $this->data[$type];
    }

    /**
     * The Message object of the update (for callback queries: the message the
     * button was attached to).
     *
     * @return array<string, mixed>|null
     */
    public function message(): ?array
    {
        $type = $this->type();

        if (in_array($type, self::MESSAGE_TYPES, true)) {
            return (array) $this->data[$type];
        }

        if ($type === 'callback_query' && isset($this->data['callback_query']['message'])) {
            return (array) $this->data['callback_query']['message'];
        }

        return null;
    }

    /** @return array<string, mixed>|null */
    public function callbackQuery(): ?array
    {
        return isset($this->data['callback_query']) ? (array) $this->data['callback_query'] : null;
    }

    public function isEdited(): bool
    {
        return str_starts_with((string) $this->type(), 'edited_');
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return Arr::get($this->data, $key, $default);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->data;
    }
}
