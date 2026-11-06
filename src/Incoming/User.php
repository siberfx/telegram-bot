<?php

declare(strict_types=1);

namespace Siberfx\TelegramBot\Incoming;

final readonly class User
{
    /** @param array<string, mixed> $payload */
    public function __construct(
        public int|string $id,
        public string $firstName,
        public ?string $lastName = null,
        public ?string $username = null,
        public ?string $languageCode = null,
        public bool $isBot = false,
        public bool $isPremium = false,
        public array $payload = [],
    ) {}

    /** @param array<string, mixed> $payload */
    public static function fromPayload(array $payload): self
    {
        return new self(
            $payload['id'] ?? 0,
            (string) ($payload['first_name'] ?? ''),
            $payload['last_name'] ?? null,
            $payload['username'] ?? null,
            $payload['language_code'] ?? null,
            (bool) ($payload['is_bot'] ?? false),
            (bool) ($payload['is_premium'] ?? false),
            $payload,
        );
    }

    public function fullName(): string
    {
        return trim($this->firstName.' '.($this->lastName ?? ''));
    }

    public function mention(): string
    {
        return $this->username !== null ? '@'.$this->username : $this->fullName();
    }
}
