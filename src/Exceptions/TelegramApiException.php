<?php

declare(strict_types=1);

namespace Siberfx\TelegramBot\Exceptions;

class TelegramApiException extends TelegramException
{
    /**
     * @param  array<string, mixed>  $parameters
     */
    public function __construct(
        public readonly string $method,
        public readonly int $errorCode,
        public readonly string $description,
        public readonly array $parameters = [],
    ) {
        parent::__construct("Telegram API [{$method}] failed ({$errorCode}): {$description}", $errorCode);
    }

    /**
     * @param  array<string, mixed>  $response
     */
    public static function fromResponse(string $method, array $response, int $status): self
    {
        return new self(
            $method,
            (int) ($response['error_code'] ?? $status),
            (string) ($response['description'] ?? 'Unexpected response from Telegram.'),
            (array) ($response['parameters'] ?? []),
        );
    }

    /** Seconds to wait before retrying (HTTP 429 flood control). */
    public function retryAfter(): ?int
    {
        return isset($this->parameters['retry_after']) ? (int) $this->parameters['retry_after'] : null;
    }

    /** New chat id when a group was migrated to a supergroup. */
    public function migratedToChatId(): ?int
    {
        return isset($this->parameters['migrate_to_chat_id']) ? (int) $this->parameters['migrate_to_chat_id'] : null;
    }
}
