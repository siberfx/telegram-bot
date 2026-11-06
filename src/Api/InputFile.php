<?php

declare(strict_types=1);

namespace Siberfx\TelegramBot\Api;

use Siberfx\TelegramBot\Exceptions\TelegramException;

/**
 * A local file that must be uploaded with multipart/form-data.
 */
final readonly class InputFile
{
    public function __construct(
        public string $path,
        public ?string $filename = null,
    ) {}

    public static function fromPath(string $path, ?string $filename = null): self
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new TelegramException("File [{$path}] does not exist or is not readable.");
        }

        return new self($path, $filename);
    }

    public function filename(): string
    {
        return $this->filename ?? basename($this->path);
    }

    /** @return resource */
    public function open(): mixed
    {
        $handle = fopen($this->path, 'rb');

        if ($handle === false) {
            throw new TelegramException("Unable to open file [{$this->path}].");
        }

        return $handle;
    }
}
