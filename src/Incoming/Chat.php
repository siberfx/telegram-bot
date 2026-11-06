<?php

declare(strict_types=1);

namespace Siberfx\TelegramBot\Incoming;

use Siberfx\TelegramBot\Enums\ChatType;

final readonly class Chat
{
    /** @param array<string, mixed> $payload */
    public function __construct(
        public int|string $id,
        public ?ChatType $type,
        public ?string $title = null,
        public ?string $username = null,
        public bool $isForum = false,
        public array $payload = [],
    ) {}

    /** @param array<string, mixed> $payload */
    public static function fromPayload(array $payload): self
    {
        return new self(
            $payload['id'] ?? 0,
            ChatType::tryFrom((string) ($payload['type'] ?? '')),
            $payload['title'] ?? null,
            $payload['username'] ?? null,
            (bool) ($payload['is_forum'] ?? false),
            $payload,
        );
    }

    public function isPrivate(): bool
    {
        return $this->type === ChatType::Private;
    }

    public function isGroup(): bool
    {
        return $this->type?->isGroup() ?? false;
    }

    public function isChannel(): bool
    {
        return $this->type === ChatType::Channel;
    }
}
