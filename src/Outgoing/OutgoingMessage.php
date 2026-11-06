<?php

declare(strict_types=1);

namespace Siberfx\TelegramBot\Outgoing;

use Siberfx\TelegramBot\Attachments\Attachment;
use Siberfx\TelegramBot\Enums\ParseMode;

/**
 *     OutgoingMessage::create('<b>Hello</b>')->html()->keyboard(Keyboard::inline()->row(...));
 *     OutgoingMessage::create('A cat', Image::url('https://...'));
 */
class OutgoingMessage
{
    protected ?ParseMode $parseMode = null;

    /** @var Keyboard|array<string, mixed>|null */
    protected Keyboard|array|null $keyboard = null;

    /** @var array<string, mixed> Extra Bot API parameters. */
    protected array $params = [];

    final public function __construct(
        protected string $text = '',
        protected ?Attachment $attachment = null,
    ) {}

    public static function create(string $text = '', ?Attachment $attachment = null): static
    {
        return new static($text, $attachment);
    }

    public function text(): string
    {
        return $this->text;
    }

    public function attachment(): ?Attachment
    {
        return $this->attachment;
    }

    public function withText(string $text): static
    {
        $this->text = $text;

        return $this;
    }

    public function withAttachment(Attachment $attachment): static
    {
        $this->attachment = $attachment;

        return $this;
    }

    /** @param Keyboard|array<string, mixed> $keyboard Keyboard or raw reply_markup */
    public function keyboard(Keyboard|array $keyboard): static
    {
        $this->keyboard = $keyboard;

        return $this;
    }

    public function parseMode(?ParseMode $mode): static
    {
        $this->parseMode = $mode;

        return $this;
    }

    public function html(): static
    {
        return $this->parseMode(ParseMode::Html);
    }

    public function markdown(): static
    {
        return $this->parseMode(ParseMode::MarkdownV2);
    }

    /** Deliver without a notification sound. */
    public function silent(bool $silent = true): static
    {
        return $this->with(['disable_notification' => $silent]);
    }

    /** Prevent forwarding and saving. */
    public function protect(bool $protect = true): static
    {
        return $this->with(['protect_content' => $protect]);
    }

    public function withoutPreview(): static
    {
        return $this->with(['link_preview_options' => ['is_disabled' => true]]);
    }

    public function replyTo(int $messageId): static
    {
        return $this->with(['reply_parameters' => ['message_id' => $messageId]]);
    }

    /** @param array<string, mixed> $params */
    public function with(array $params): static
    {
        $this->params = array_replace($this->params, $params);

        return $this;
    }

    /** @return array<string, mixed>|null */
    public function replyMarkup(): ?array
    {
        return $this->keyboard instanceof Keyboard ? $this->keyboard->toArray() : $this->keyboard;
    }

    /**
     * @return array{0: string, 1: array<string, mixed>}
     */
    public function toRequest(int|string $chatId, ?ParseMode $defaultParseMode = null): array
    {
        $params = ['chat_id' => $chatId];
        $parseMode = $this->parseMode ?? $defaultParseMode;

        if ($this->attachment === null) {
            $method = 'sendMessage';
            $params['text'] = $this->text;
            $params['parse_mode'] = $parseMode?->value;
        } else {
            [$method, $attachmentParams] = $this->attachment->toRequest();
            $params += $attachmentParams;

            if ($this->text !== '' && $this->attachment->type()->supportsCaption()) {
                $params['caption'] = $this->text;
                $params['parse_mode'] = $parseMode?->value;
            }
        }

        $params['reply_markup'] = $this->replyMarkup();

        return [$method, array_filter(array_replace($params, $this->params), static fn (mixed $v): bool => $v !== null)];
    }

    /**
     * @return array{0: string, 1: array<string, mixed>}
     */
    public function toEditRequest(int|string $chatId, int $messageId, ?ParseMode $defaultParseMode = null): array
    {
        $parseMode = $this->parseMode ?? $defaultParseMode;
        $isCaption = $this->attachment !== null;

        $params = [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            $isCaption ? 'caption' : 'text' => $this->text,
            'parse_mode' => $parseMode?->value,
            'reply_markup' => $this->replyMarkup(),
        ];

        return [
            $isCaption ? 'editMessageCaption' : 'editMessageText',
            array_filter(array_replace($params, $this->params), static fn (mixed $v): bool => $v !== null),
        ];
    }

    public function __toString(): string
    {
        return $this->text;
    }
}
