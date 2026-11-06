<?php

declare(strict_types=1);

namespace Siberfx\TelegramBot\Contracts;

use Siberfx\TelegramBot\Exceptions\TelegramApiException;

interface Client
{
    /**
     * Call a Bot API method and return its unwrapped "result".
     *
     * @param  array<string, mixed>  $params
     *
     * @throws TelegramApiException
     */
    public function call(string $method, array $params = []): mixed;

    /**
     * Build the download URL for a file_path returned by getFile.
     */
    public function fileUrl(string $filePath): string;
}
