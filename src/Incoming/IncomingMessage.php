<?php

declare(strict_types=1);

namespace Siberfx\TelegramBot\Incoming;

use Illuminate\Support\Arr;
use Siberfx\TelegramBot\Attachments\Animation;
use Siberfx\TelegramBot\Attachments\Attachment;
use Siberfx\TelegramBot\Attachments\AttachmentType;
use Siberfx\TelegramBot\Attachments\Audio;
use Siberfx\TelegramBot\Attachments\Contact;
use Siberfx\TelegramBot\Attachments\Document;
use Siberfx\TelegramBot\Attachments\Image;
use Siberfx\TelegramBot\Attachments\Location;
use Siberfx\TelegramBot\Attachments\Sticker;
use Siberfx\TelegramBot\Attachments\Video;
use Siberfx\TelegramBot\Attachments\Voice;

/**
 * A normalized view of a message (or callback query) the bot received.
 */
final class IncomingMessage
{
    /** @var array<string, mixed> Data added by middleware (e.g. NLP intents). */
    private array $extras = [];

    /**
     * @param  list<Attachment>  $attachments
     * @param  array<string, mixed>  $payload  The raw Telegram Message object.
     */
    public function __construct(
        public readonly string $text,
        public readonly int|string|null $chatId,
        public readonly int|string|null $senderId,
        public readonly ?User $sender = null,
        public readonly ?Chat $chat = null,
        public readonly ?int $messageId = null,
        public readonly ?int $threadId = null,
        public readonly bool $isTopicMessage = false,
        public readonly ?string $caption = null,
        public readonly array $attachments = [],
        public readonly ?string $callbackQueryId = null,
        public readonly ?string $callbackData = null,
        public readonly array $payload = [],
        public readonly ?Update $update = null,
    ) {}

    public static function fromUpdate(Update $update): ?self
    {
        $callback = $update->callbackQuery();
        $message = $update->message() ?? [];

        if ($message === [] && $callback === null) {
            return null;
        }

        $from = $callback['from'] ?? $message['from'] ?? null;
        $chat = $message['chat'] ?? null;

        return new self(
            text: $callback !== null ? (string) ($callback['data'] ?? '') : (string) ($message['text'] ?? ''),
            chatId: $chat['id'] ?? null,
            senderId: $from['id'] ?? $message['sender_chat']['id'] ?? $chat['id'] ?? null,
            sender: is_array($from) ? User::fromPayload($from) : null,
            chat: is_array($chat) ? Chat::fromPayload($chat) : null,
            messageId: isset($message['message_id']) ? (int) $message['message_id'] : null,
            threadId: isset($message['message_thread_id']) ? (int) $message['message_thread_id'] : null,
            isTopicMessage: (bool) ($message['is_topic_message'] ?? false),
            caption: $callback === null ? ($message['caption'] ?? null) : null,
            attachments: $callback === null ? self::extractAttachments($message) : [],
            callbackQueryId: isset($callback['id']) ? (string) $callback['id'] : null,
            callbackData: isset($callback['data']) ? (string) $callback['data'] : null,
            payload: $message,
            update: $update,
        );
    }

    /**
     * A message with no content, used to give a context to conversations
     * started proactively for a chat.
     */
    public static function synthetic(int|string $chatId, int|string|null $userId = null): self
    {
        return new self('', $chatId, $userId ?? $chatId);
    }

    /**
     * @param  array<string, mixed>  $message
     * @return list<Attachment>
     */
    private static function extractAttachments(array $message): array
    {
        $attachments = [];

        if (! empty($message['photo']) && is_array($message['photo'])) {
            // Telegram sends several sizes; the last one is the largest.
            $attachments[] = Image::fromPayload((array) end($message['photo']));
        }

        if (isset($message['animation'])) {
            $attachments[] = Animation::fromPayload($message['animation']);
        } elseif (isset($message['document'])) {
            $attachments[] = Document::fromPayload($message['document']);
        }

        foreach (['video' => Video::class, 'video_note' => Video::class, 'audio' => Audio::class, 'voice' => Voice::class, 'sticker' => Sticker::class] as $key => $class) {
            if (isset($message[$key])) {
                $attachments[] = $class::fromPayload($message[$key]);
            }
        }

        if (isset($message['location'])) {
            $attachments[] = Location::fromPayload($message['location']);
        }

        if (isset($message['contact'])) {
            $attachments[] = Contact::fromPayload($message['contact']);
        }

        return $attachments;
    }

    public function isCallback(): bool
    {
        return $this->callbackQueryId !== null;
    }

    public function isEdited(): bool
    {
        return $this->update?->isEdited() ?? false;
    }

    public function isCommand(): bool
    {
        return str_starts_with($this->text, '/');
    }

    /** Whether there is anything hears()/receives*() could match. */
    public function hasContent(): bool
    {
        return $this->text !== '' || $this->attachments !== [] || $this->isCallback();
    }

    /** @return list<Attachment> */
    public function attachments(?AttachmentType $type = null): array
    {
        if ($type === null) {
            return $this->attachments;
        }

        return array_values(array_filter($this->attachments, static fn (Attachment $a): bool => $a->type() === $type));
    }

    public function hasAttachment(?AttachmentType $type = null): bool
    {
        return $this->attachments($type) !== [];
    }

    /** @return list<Image> */
    public function images(): array
    {
        return $this->attachments(AttachmentType::Image);
    }

    /** @return list<Video> */
    public function videos(): array
    {
        return $this->attachments(AttachmentType::Video);
    }

    /** @return list<Audio> */
    public function audio(): array
    {
        return $this->attachments(AttachmentType::Audio);
    }

    public function voice(): ?Voice
    {
        return $this->attachments(AttachmentType::Voice)[0] ?? null;
    }

    /** @return list<Document> */
    public function documents(): array
    {
        return $this->attachments(AttachmentType::Document);
    }

    public function sticker(): ?Sticker
    {
        return $this->attachments(AttachmentType::Sticker)[0] ?? null;
    }

    public function location(): ?Location
    {
        return $this->attachments(AttachmentType::Location)[0] ?? null;
    }

    public function contact(): ?Contact
    {
        return $this->attachments(AttachmentType::Contact)[0] ?? null;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return Arr::get($this->payload, $key, $default);
    }

    public function addExtra(string $key, mixed $value): self
    {
        $this->extras[$key] = $value;

        return $this;
    }

    public function extra(string $key, mixed $default = null): mixed
    {
        return $this->extras[$key] ?? $default;
    }

    /** @return array<string, mixed> */
    public function extras(): array
    {
        return $this->extras;
    }
}
