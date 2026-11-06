<?php

declare(strict_types=1);

namespace Siberfx\TelegramBot\Attachments;

use Illuminate\Support\Arr;

abstract class Attachment
{
    /** @param array<string, mixed> $payload Raw Telegram object (incoming attachments). */
    public function __construct(public readonly array $payload = []) {}

    abstract public function type(): AttachmentType;

    /**
     * Method and parameters needed to send this attachment.
     *
     * @return array{0: string, 1: array<string, mixed>}
     */
    abstract public function toRequest(): array;

    public function get(string $key, mixed $default = null): mixed
    {
        return Arr::get($this->payload, $key, $default);
    }
}
