<?php

declare(strict_types=1);

namespace Siberfx\TelegramBot\Attachments;

use Siberfx\TelegramBot\Api\InputFile;

/**
 * A file based attachment. The source is either a URL, a Telegram file_id
 * or (when created with ::path()) a local file that gets uploaded.
 */
abstract class FileAttachment extends Attachment
{
    final public function __construct(
        public readonly string $source,
        array $payload = [],
        public readonly bool $local = false,
    ) {
        parent::__construct($payload);
    }

    /** Send a file Telegram should download from the internet. */
    public static function url(string $url): static
    {
        return new static($url);
    }

    /** Re-send a file that already lives on Telegram's servers. */
    public static function id(string $fileId): static
    {
        return new static($fileId);
    }

    /** Upload a local file. */
    public static function path(string $path): static
    {
        InputFile::fromPath($path);

        return new static($path, local: true);
    }

    /** @param array<string, mixed> $payload */
    public static function fromPayload(array $payload): static
    {
        return new static((string) ($payload['file_id'] ?? ''), $payload);
    }

    public function fileId(): ?string
    {
        if (isset($this->payload['file_id'])) {
            return (string) $this->payload['file_id'];
        }

        return $this->local || str_contains($this->source, '://') ? null : $this->source;
    }

    public function fileUniqueId(): ?string
    {
        return $this->get('file_unique_id');
    }

    public function fileSize(): ?int
    {
        return $this->get('file_size');
    }

    public function fileName(): ?string
    {
        return $this->get('file_name') ?? ($this->local ? basename($this->source) : null);
    }

    public function mimeType(): ?string
    {
        return $this->get('mime_type');
    }

    public function toInput(): string|InputFile
    {
        return $this->local ? InputFile::fromPath($this->source) : $this->source;
    }

    public function toRequest(): array
    {
        return [$this->type()->method(), [$this->type()->field() => $this->toInput()]];
    }
}
