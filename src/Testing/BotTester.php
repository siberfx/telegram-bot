<?php

declare(strict_types=1);

namespace Siberfx\TelegramBot\Testing;

use Closure;
use PHPUnit\Framework\Assert as PHPUnit;
use Siberfx\TelegramBot\Api\FakeClient;
use Siberfx\TelegramBot\Incoming\Update;
use Siberfx\TelegramBot\TelegramBot;

/**
 * Fluent testing API:
 *
 *     Telegram::fake()
 *         ->receives('/start')
 *         ->assertReply('Welcome!')
 *         ->receives('call me Selim')
 *         ->assertReply('Hello Selim');
 */
final class BotTester
{
    /** Methods that count as "replies". */
    private const array REPLY_METHODS = [
        'sendMessage', 'sendPhoto', 'sendVideo', 'sendAudio', 'sendVoice', 'sendDocument',
        'sendAnimation', 'sendSticker', 'sendLocation', 'sendContact', 'sendVenue',
        'sendPoll', 'sendDice', 'sendMediaGroup', 'sendVideoNote', 'sendInvoice',
    ];

    /** @var list<array{method: string, params: array<string, mixed>}> Replies not yet asserted. */
    private array $queue = [];

    /** @var array<string, mixed>|null The last bot message carrying a keyboard. */
    private ?array $lastKeyboardMessage = null;

    private int $botMessageId = 5000;

    public readonly UpdateFactory $updates;

    public function __construct(
        public readonly TelegramBot $bot,
        public readonly FakeClient $client,
        ?UpdateFactory $updates = null,
    ) {
        $this->updates = $updates ?? new UpdateFactory;
    }

    /* -----------------------------------------------------------------
     |  Context
     | ----------------------------------------------------------------- */

    /** @param array<string, mixed> $user */
    public function setUser(array $user): self
    {
        $this->updates->withUser($user);

        if (isset($user['id']) && $this->updates->chat()['type'] === 'private') {
            $this->updates->withChat(['id' => $user['id']]);
        }

        return $this;
    }

    /** @param array<string, mixed> $chat */
    public function setChat(array $chat): self
    {
        $this->updates->withChat($chat);

        return $this;
    }

    public function inGroup(int $chatId = -100123456789, string $title = 'Test group'): self
    {
        return $this->setChat(['id' => $chatId, 'type' => 'supergroup', 'title' => $title, 'first_name' => null, 'username' => null]);
    }

    /* -----------------------------------------------------------------
     |  Receiving
     | ----------------------------------------------------------------- */

    public function receives(string $text): self
    {
        return $this->receivesUpdate($this->updates->text($text));
    }

    public function receivesImages(?string $caption = null): self
    {
        return $this->receivesUpdate($this->updates->photo($caption));
    }

    public function receivesVideos(?string $caption = null): self
    {
        return $this->receivesUpdate($this->updates->video($caption));
    }

    public function receivesAudio(?string $caption = null): self
    {
        return $this->receivesUpdate($this->updates->audio($caption));
    }

    public function receivesVoice(): self
    {
        return $this->receivesUpdate($this->updates->voice());
    }

    public function receivesFiles(string $fileName = 'document.pdf', ?string $caption = null): self
    {
        return $this->receivesUpdate($this->updates->document($fileName, $caption));
    }

    public function receivesSticker(string $emoji = '👍'): self
    {
        return $this->receivesUpdate($this->updates->sticker($emoji));
    }

    public function receivesLocation(float $latitude = 41.0082, float $longitude = 28.9784): self
    {
        return $this->receivesUpdate($this->updates->location($latitude, $longitude));
    }

    public function receivesContact(string $phoneNumber = '+905550000000', string $firstName = 'Test', ?string $lastName = null): self
    {
        return $this->receivesUpdate($this->updates->contact($phoneNumber, $firstName, $lastName));
    }

    /** Simulate pressing an inline button with the given callback data. */
    public function receivesCallback(string $data): self
    {
        return $this->receivesUpdate($this->updates->callback($data, $this->lastKeyboardMessage));
    }

    /** Press a button (by label) of the last keyboard the bot sent. */
    public function clicks(string $buttonText): self
    {
        $markup = (array) ($this->lastKeyboardMessage['reply_markup'] ?? []);

        foreach ((array) ($markup['inline_keyboard'] ?? []) as $row) {
            foreach ($row as $button) {
                if (($button['text'] ?? null) === $buttonText) {
                    return $this->receivesCallback((string) ($button['callback_data'] ?? $buttonText));
                }
            }
        }

        foreach ((array) ($markup['keyboard'] ?? []) as $row) {
            foreach ($row as $button) {
                if (($button['text'] ?? null) === $buttonText) {
                    return $this->receives($buttonText);
                }
            }
        }

        PHPUnit::fail("The bot did not show a button labelled [{$buttonText}].");
    }

    /** @param Update|array<string, mixed> $update */
    public function receivesUpdate(Update|array $update): self
    {
        $this->client->flush();
        $this->bot->handle($update);

        $this->queue = array_values(array_filter(
            $this->client->calls(),
            static fn (array $call): bool => in_array($call['method'], self::REPLY_METHODS, true),
        ));

        foreach ($this->queue as $call) {
            if (isset($call['params']['reply_markup'])) {
                $this->lastKeyboardMessage = $this->updates->botMessage($call['params'], ++$this->botMessageId);
            }
        }

        return $this;
    }

    /* -----------------------------------------------------------------
     |  Assertions (replies are consumed in order)
     | ----------------------------------------------------------------- */

    public function assertReply(string $expected): self
    {
        PHPUnit::assertSame($expected, $this->textOf($this->nextReply()), 'The bot reply did not match.');

        return $this;
    }

    public function assertReplyContains(string $needle): self
    {
        PHPUnit::assertStringContainsString($needle, $this->textOf($this->nextReply()));

        return $this;
    }

    public function assertReplyMatches(string $regex): self
    {
        PHPUnit::assertMatchesRegularExpression($regex, $this->textOf($this->nextReply()));

        return $this;
    }

    /** @param list<string> $candidates */
    public function assertReplyIn(array $candidates): self
    {
        PHPUnit::assertContains($this->textOf($this->nextReply()), $candidates);

        return $this;
    }

    public function assertReplyIsNot(string $text): self
    {
        PHPUnit::assertNotSame($text, $this->textOf($this->nextReply()));

        return $this;
    }

    /** @param list<string> $expected */
    public function assertReplies(array $expected): self
    {
        foreach ($expected as $text) {
            $this->assertReply($text);
        }

        return $this;
    }

    /** Assert there are no (more) replies for the last received message. */
    public function assertReplyNothing(): self
    {
        PHPUnit::assertSame([], array_map(fn (array $c): string => $c['method'].': '.$this->textOf($c), $this->queue), 'The bot sent unexpected replies.');

        return $this;
    }

    /**
     * Assert the next reply is a question with buttons.
     *
     * @param  list<string>|null  $buttons  Expected button labels
     */
    public function assertQuestion(?string $text = null, ?array $buttons = null): self
    {
        $reply = $this->nextReply();
        $keyboard = (array) ($reply['params']['reply_markup']['inline_keyboard'] ?? []);

        PHPUnit::assertNotSame([], $keyboard, 'The bot reply is not a question with buttons.');

        if ($text !== null) {
            PHPUnit::assertSame($text, $this->textOf($reply));
        }

        if ($buttons !== null) {
            PHPUnit::assertSame($buttons, array_column(array_merge(...$keyboard), 'text'));
        }

        return $this;
    }

    /** Assert the next reply used the given method (sendPhoto, sendLocation...). */
    public function assertReplyType(string $method, ?Closure $callback = null): self
    {
        $reply = $this->nextReply();
        PHPUnit::assertSame($method, $reply['method']);

        if ($callback !== null) {
            PHPUnit::assertNotFalse($callback($reply['params']), "The [{$method}] parameters did not pass the given truth test.");
        }

        return $this;
    }

    /** Assert any API call was made (not consumed from the reply queue). */
    public function assertSent(string $method, ?Closure $callback = null): self
    {
        $calls = $this->client->calls($method);

        if ($callback !== null) {
            $calls = array_filter($calls, static fn (array $call): bool => (bool) $callback($call['params']));
        }

        PHPUnit::assertNotEmpty($calls, "The expected [{$method}] request was not sent.");

        return $this;
    }

    public function assertNotSent(string $method): self
    {
        PHPUnit::assertEmpty($this->client->calls($method), "The unexpected [{$method}] request was sent.");

        return $this;
    }

    public function assertTyping(): self
    {
        return $this->assertSent('sendChatAction');
    }

    public function assertCallbackAnswered(?string $text = null): self
    {
        return $this->assertSent('answerCallbackQuery', static fn (array $p): bool => $text === null || ($p['text'] ?? null) === $text);
    }

    public function assertConversationActive(): self
    {
        PHPUnit::assertTrue($this->bot->hasConversation($this->updates->chat()['id'], $this->updates->user()['id']), 'No conversation is active.');

        return $this;
    }

    public function assertNoConversation(): self
    {
        PHPUnit::assertFalse($this->bot->hasConversation($this->updates->chat()['id'], $this->updates->user()['id']), 'A conversation is still active.');

        return $this;
    }

    /* -----------------------------------------------------------------
     |  Inspection
     | ----------------------------------------------------------------- */

    /**
     * Pop the next reply for custom assertions.
     *
     * @return array{method: string, params: array<string, mixed>}
     */
    public function reply(): array
    {
        return $this->nextReply();
    }

    /** @return list<array{method: string, params: array<string, mixed>}> */
    public function replies(): array
    {
        return $this->queue;
    }

    /** @return list<array{method: string, params: array<string, mixed>}> */
    public function requests(): array
    {
        return $this->client->calls();
    }

    /** @return array{method: string, params: array<string, mixed>} */
    private function nextReply(): array
    {
        PHPUnit::assertNotEmpty($this->queue, 'The bot did not reply (or all replies were already asserted).');

        return array_shift($this->queue);
    }

    /** @param array{method: string, params: array<string, mixed>} $call */
    private function textOf(array $call): string
    {
        return (string) ($call['params']['text'] ?? $call['params']['caption'] ?? '');
    }
}
