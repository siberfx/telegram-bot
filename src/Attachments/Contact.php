<?php

declare(strict_types=1);

namespace Siberfx\TelegramBot\Attachments;

final class Contact extends Attachment
{
    public function __construct(
        public readonly string $phoneNumber,
        public readonly string $firstName,
        public readonly ?string $lastName = null,
        public readonly int|string|null $userId = null,
        array $payload = [],
    ) {
        parent::__construct($payload);
    }

    public static function create(string $phoneNumber, string $firstName, ?string $lastName = null): self
    {
        return new self($phoneNumber, $firstName, $lastName);
    }

    /** @param array<string, mixed> $payload */
    public static function fromPayload(array $payload): self
    {
        return new self(
            (string) ($payload['phone_number'] ?? ''),
            (string) ($payload['first_name'] ?? ''),
            $payload['last_name'] ?? null,
            $payload['user_id'] ?? null,
            $payload,
        );
    }

    public function type(): AttachmentType
    {
        return AttachmentType::Contact;
    }

    public function toRequest(): array
    {
        return ['sendContact', array_filter([
            'phone_number' => $this->phoneNumber,
            'first_name' => $this->firstName,
            'last_name' => $this->lastName,
        ], static fn (?string $value): bool => $value !== null)];
    }
}
