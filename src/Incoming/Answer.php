<?php

declare(strict_types=1);

namespace Siberfx\TelegramBot\Incoming;

/**
 * The user's reply to a conversation question.
 */
final readonly class Answer
{
    public function __construct(
        private string $text,
        private ?string $value,
        private IncomingMessage $message,
    ) {}

    public static function fromMessage(IncomingMessage $message): self
    {
        if (! $message->isCallback()) {
            return new self($message->text, null, $message);
        }

        return new self(
            self::buttonText($message) ?? (string) $message->callbackData,
            $message->callbackData,
            $message,
        );
    }

    /** What the user typed, or the label of the button they pressed. */
    public function text(): string
    {
        return $this->text;
    }

    /** The button's value for interactive answers, otherwise the text. */
    public function value(): string
    {
        return $this->value ?? $this->text;
    }

    public function isInteractive(): bool
    {
        return $this->message->isCallback();
    }

    public function message(): IncomingMessage
    {
        return $this->message;
    }

    public function __toString(): string
    {
        return $this->text;
    }

    private static function buttonText(IncomingMessage $message): ?string
    {
        foreach ((array) $message->get('reply_markup.inline_keyboard', []) as $row) {
            foreach ((array) $row as $button) {
                if (($button['callback_data'] ?? null) === $message->callbackData) {
                    return $button['text'] ?? null;
                }
            }
        }

        return null;
    }
}
