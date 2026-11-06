<?php

declare(strict_types=1);

namespace Siberfx\TelegramBot\Tests\Feature;

use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Siberfx\TelegramBot\Attachments\Image;
use Siberfx\TelegramBot\Attachments\Location;
use Siberfx\TelegramBot\Enums\ParseMode;
use Siberfx\TelegramBot\Exceptions\TelegramApiException;
use Siberfx\TelegramBot\Facades\Telegram;
use Siberfx\TelegramBot\Outgoing\Button;
use Siberfx\TelegramBot\Outgoing\Keyboard;
use Siberfx\TelegramBot\Outgoing\OutgoingMessage;
use Siberfx\TelegramBot\TelegramBot;
use Siberfx\TelegramBot\Tests\TestCase;

final class MessagingTest extends TestCase
{
    #[Test]
    public function it_builds_requests_for_messages_attachments_and_keyboards(): void
    {
        [$method, $params] = OutgoingMessage::create('<b>Hi</b>')
            ->html()
            ->silent()
            ->keyboard(Keyboard::reply()->buttons([Button::create('Share')->requestContact(), Button::create('Cancel')], 2)->resize()->oneTime())
            ->toRequest(42);

        $this->assertSame('sendMessage', $method);
        $this->assertSame([
            'chat_id' => 42,
            'text' => '<b>Hi</b>',
            'parse_mode' => 'HTML',
            'reply_markup' => [
                'keyboard' => [[['text' => 'Share', 'request_contact' => true], ['text' => 'Cancel']]],
                'resize_keyboard' => true,
                'one_time_keyboard' => true,
            ],
            'disable_notification' => true,
        ], $params);

        [$method, $params] = OutgoingMessage::create('A cat', Image::url('https://example.com/cat.jpg'))->toRequest(42, ParseMode::MarkdownV2);
        $this->assertSame('sendPhoto', $method);
        $this->assertSame(['chat_id' => 42, 'photo' => 'https://example.com/cat.jpg', 'caption' => 'A cat', 'parse_mode' => 'MarkdownV2'], $params);

        [$method, $params] = OutgoingMessage::create('ignored', Location::create(1.5, 2.5))->toRequest(42);
        $this->assertSame('sendLocation', $method);
        $this->assertSame(['chat_id' => 42, 'latitude' => 1.5, 'longitude' => 2.5], $params);
    }

    #[Test]
    public function the_bot_can_type_reply_randomly_say_and_use_storage(): void
    {
        $tester = Telegram::fake(setup: function (TelegramBot $bot) {
            $bot->hears('count', function (TelegramBot $bot) {
                $bot->types();
                $bot->reply('count='.$bot->userStorage()->increment('count'));
            });
            $bot->hears('random', fn (TelegramBot $bot) => $bot->randomReply(['a', 'b']));
        });

        $tester->receives('count')->assertTyping()->assertReply('count=1')
            ->receives('count')->assertReply('count=2')
            ->receives('random')->assertReplyIn(['a', 'b']);

        $tester->client->flush();
        Telegram::say('Broadcast', [1, 2]);
        $this->assertCount(2, $tester->client->calls('sendMessage'));
    }

    #[Test]
    public function it_exposes_every_api_method_dynamically(): void
    {
        $tester = Telegram::fake(setup: fn () => null);

        Telegram::api()->sendDice(['chat_id' => 1, 'emoji' => '🎲']);

        $tester->assertSent('sendDice', fn (array $p) => $p['emoji'] === '🎲');
    }

    #[Test]
    public function the_http_client_calls_the_bot_api_and_throws_on_errors(): void
    {
        Http::fake([
            'api.telegram.org/bot123456:TEST-TOKEN/sendMessage' => Http::response(['ok' => true, 'result' => ['message_id' => 9]]),
            'api.telegram.org/bot123456:TEST-TOKEN/getChat' => Http::response(['ok' => false, 'error_code' => 400, 'description' => 'Bad Request: chat not found'], 400),
        ]);

        $result = Telegram::say('Hello', 99);
        $this->assertSame(['message_id' => 9], $result);

        Http::assertSent(fn ($request) => $request['chat_id'] === 99 && $request['text'] === 'Hello');

        try {
            Telegram::api()->getChat(['chat_id' => 1]);
            $this->fail('Expected exception.');
        } catch (TelegramApiException $e) {
            $this->assertSame(400, $e->errorCode);
            $this->assertSame('Bad Request: chat not found', $e->description);
            $this->assertStringNotContainsString('TEST-TOKEN', $e->getMessage());
        }
    }
}
