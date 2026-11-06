<?php

declare(strict_types=1);

namespace Siberfx\TelegramBot\Testing;

use Siberfx\TelegramBot\Incoming\Update;

/**
 * Builds realistic Telegram updates for tests and the console tinker.
 */
final class UpdateFactory
{
    private int $updateId = 1;

    private int $messageId = 1;

    /** @var array<string, mixed> */
    private array $user;

    /** @var array<string, mixed> */
    private array $chat;

    /**
     * @param  array<string, mixed>  $user
     * @param  array<string, mixed>  $chat
     */
    public function __construct(array $user = [], array $chat = [])
    {
        $this->user = array_filter($user + [
            'id' => 100000001,
            'is_bot' => false,
            'first_name' => 'Test',
            'username' => 'test_user',
            'language_code' => 'en',
        ], static fn (mixed $v): bool => $v !== null);

        $this->chat = $chat + [
            'id' => $this->user['id'],
            'type' => 'private',
            'first_name' => $this->user['first_name'],
            'username' => $this->user['username'] ?? null,
        ];
    }

    /** @param array<string, mixed> $user */
    public function withUser(array $user): self
    {
        $this->user = array_replace($this->user, $user);

        return $this;
    }

    /** @param array<string, mixed> $chat */
    public function withChat(array $chat): self
    {
        $this->chat = array_replace($this->chat, $chat);

        return $this;
    }

    /** @return array<string, mixed> */
    public function user(): array
    {
        return $this->user;
    }

    /** @return array<string, mixed> */
    public function chat(): array
    {
        return $this->chat;
    }

    /** @param array<string, mixed> $fields */
    public function message(array $fields): Update
    {
        return $this->make('message', $this->messagePayload($fields));
    }

    public function text(string $text): Update
    {
        $fields = ['text' => $text];

        if (preg_match('~^/[\w@-]+~', $text, $m) === 1) {
            $fields['entities'] = [['type' => 'bot_command', 'offset' => 0, 'length' => strlen($m[0])]];
        }

        return $this->message($fields);
    }

    public function photo(?string $caption = null): Update
    {
        $id = $this->fileId('photo');

        return $this->message(array_filter([
            'photo' => [
                ['file_id' => $id.'-s', 'file_unique_id' => $id.'-s', 'width' => 90, 'height' => 90, 'file_size' => 1200],
                ['file_id' => $id, 'file_unique_id' => $id, 'width' => 800, 'height' => 800, 'file_size' => 64000],
            ],
            'caption' => $caption,
        ]));
    }

    public function video(?string $caption = null): Update
    {
        return $this->file('video', ['duration' => 5, 'width' => 640, 'height' => 360, 'mime_type' => 'video/mp4'], $caption);
    }

    public function audio(?string $caption = null): Update
    {
        return $this->file('audio', ['duration' => 30, 'title' => 'Song', 'mime_type' => 'audio/mpeg'], $caption);
    }

    public function voice(): Update
    {
        return $this->file('voice', ['duration' => 3, 'mime_type' => 'audio/ogg']);
    }

    public function document(string $fileName = 'document.pdf', ?string $caption = null): Update
    {
        return $this->file('document', ['file_name' => $fileName, 'mime_type' => 'application/pdf'], $caption);
    }

    public function sticker(string $emoji = '👍'): Update
    {
        return $this->file('sticker', ['emoji' => $emoji, 'width' => 512, 'height' => 512, 'is_animated' => false, 'is_video' => false, 'type' => 'regular']);
    }

    public function location(float $latitude, float $longitude): Update
    {
        return $this->message(['location' => ['latitude' => $latitude, 'longitude' => $longitude]]);
    }

    public function contact(string $phoneNumber, string $firstName, ?string $lastName = null): Update
    {
        return $this->message(['contact' => array_filter([
            'phone_number' => $phoneNumber,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'user_id' => $this->user['id'],
        ])]);
    }

    /**
     * A button press. $message is the bot message carrying the keyboard (so the
     * pressed button's label can be resolved).
     *
     * @param  array<string, mixed>|null  $message
     */
    public function callback(string $data, ?array $message = null): Update
    {
        $message ??= $this->messagePayload(['text' => '...'], fromBot: true);

        return $this->make('callback_query', [
            'id' => (string) random_int(100000, PHP_INT_MAX),
            'from' => $this->user,
            'chat_instance' => (string) $this->chat['id'],
            'message' => $message,
            'data' => $data,
        ]);
    }

    /** @param array<string, mixed> $payload */
    public function make(string $type, array $payload): Update
    {
        return Update::fromArray(['update_id' => $this->updateId++, $type => $payload]);
    }

    /**
     * Turn a recorded send* call of the bot into a Telegram message payload.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function botMessage(array $params, int $messageId): array
    {
        return array_filter([
            'message_id' => $messageId,
            'date' => time(),
            'chat' => $this->chat,
            'from' => ['id' => 1, 'is_bot' => true, 'first_name' => 'Bot', 'username' => 'bot'],
            'text' => $params['text'] ?? null,
            'caption' => $params['caption'] ?? null,
            'reply_markup' => $params['reply_markup'] ?? null,
        ], static fn (mixed $v): bool => $v !== null);
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function file(string $type, array $extra, ?string $caption = null): Update
    {
        $id = $this->fileId($type);

        return $this->message(array_filter([
            $type => ['file_id' => $id, 'file_unique_id' => $id, 'file_size' => 2048] + $extra,
            'caption' => $caption,
        ]));
    }

    /**
     * @param  array<string, mixed>  $fields
     * @return array<string, mixed>
     */
    private function messagePayload(array $fields, bool $fromBot = false): array
    {
        return [
            'message_id' => $this->messageId++,
            'from' => $fromBot ? ['id' => 1, 'is_bot' => true, 'first_name' => 'Bot', 'username' => 'bot'] : $this->user,
            'chat' => array_filter($this->chat, static fn (mixed $v): bool => $v !== null),
            'date' => time(),
        ] + $fields;
    }

    private function fileId(string $type): string
    {
        return 'fake-'.$type.'-'.bin2hex(random_bytes(4));
    }
}
