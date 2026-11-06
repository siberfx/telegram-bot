<?php

declare(strict_types=1);

namespace Siberfx\TelegramBot\Attachments;

final class Location extends Attachment
{
    public function __construct(
        public readonly float $latitude,
        public readonly float $longitude,
        array $payload = [],
    ) {
        parent::__construct($payload);
    }

    public static function create(float $latitude, float $longitude): self
    {
        return new self($latitude, $longitude);
    }

    /** @param array<string, mixed> $payload */
    public static function fromPayload(array $payload): self
    {
        return new self((float) ($payload['latitude'] ?? 0), (float) ($payload['longitude'] ?? 0), $payload);
    }

    public function type(): AttachmentType
    {
        return AttachmentType::Location;
    }

    public function toRequest(): array
    {
        return ['sendLocation', ['latitude' => $this->latitude, 'longitude' => $this->longitude]];
    }
}
