<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Siberfx\TelegramBot\Attachments\Image;
use Siberfx\TelegramBot\Attachments\Location;
use Siberfx\TelegramBot\Enums\ParseMode;
use Siberfx\TelegramBot\Exceptions\TelegramApiException;
use Siberfx\TelegramBot\Facades\Telegram;
use Siberfx\TelegramBot\Outgoing\Button;
use Siberfx\TelegramBot\Outgoing\Keyboard;
use Siberfx\TelegramBot\Outgoing\OutgoingMessage;
use Siberfx\TelegramBot\Outgoing\Question;
use Siberfx\TelegramBot\TelegramBot;

describe('request building', function () {
    it('builds text messages with parse mode, options and reply keyboards', function () {
        [$method, $params] = OutgoingMessage::create('<b>Hi</b>')
            ->html()
            ->silent()
            ->keyboard(Keyboard::reply()->buttons([Button::create('Share')->requestContact(), Button::create('Cancel')], 2)->resize()->oneTime())
            ->toRequest(42);

        expect($method)->toBe('sendMessage')
            ->and($params)->toBe([
                'chat_id' => 42,
                'text' => '<b>Hi</b>',
                'parse_mode' => 'HTML',
                'reply_markup' => [
                    'keyboard' => [[['text' => 'Share', 'request_contact' => true], ['text' => 'Cancel']]],
                    'resize_keyboard' => true,
                    'one_time_keyboard' => true,
                ],
                'disable_notification' => true,
            ]);
    });

    it('builds attachments with captions', function () {
        [$method, $params] = OutgoingMessage::create('A cat', Image::url('https://example.com/cat.jpg'))->toRequest(42, ParseMode::MarkdownV2);

        expect($method)->toBe('sendPhoto')
            ->and($params)->toBe(['chat_id' => 42, 'photo' => 'https://example.com/cat.jpg', 'caption' => 'A cat', 'parse_mode' => 'MarkdownV2']);
    });

    it('drops captions for attachments that do not support them', function () {
        [$method, $params] = OutgoingMessage::create('ignored', Location::create(1.5, 2.5))->toRequest(42);

        expect($method)->toBe('sendLocation')
            ->and($params)->toBe(['chat_id' => 42, 'latitude' => 1.5, 'longitude' => 2.5]);
    });

    it('renders questions as inline keyboards', function () {
        [, $params] = Question::create('Pick')
            ->addButtons([Button::create('A')->value('a'), Button::create('B'), Button::create('Docs')->url('https://example.com')])
            ->columns(2)
            ->toRequest(1);

        expect($params['reply_markup'])->toBe(['inline_keyboard' => [
            [['text' => 'A', 'callback_data' => 'a'], ['text' => 'B', 'callback_data' => 'B']],
            [['text' => 'Docs', 'url' => 'https://example.com']],
        ]]);
    });
});

describe('the bot', function () {
    it('can type, reply randomly, say and use storage', function () {
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

        expect($tester->client->calls('sendMessage'))->toHaveCount(2);
    });

    it('exposes every api method dynamically', function () {
        $tester = Telegram::fake(setup: fn () => null);

        Telegram::api()->sendDice(['chat_id' => 1, 'emoji' => '🎲']);

        $tester->assertSent('sendDice', fn (array $p) => $p['emoji'] === '🎲');
    });

    it('stores nested values in storage', function () {
        $storage = Telegram::fake(setup: fn () => null)->bot->botStorage();

        $storage->put(['settings.lang' => 'tr', 'version' => 2]);

        expect($storage->get('settings.lang'))->toBe('tr')
            ->and($storage->all())->toBe(['settings' => ['lang' => 'tr'], 'version' => 2]);

        $storage->forget('settings.lang');

        expect($storage->has('settings.lang'))->toBeFalse();
    });
});

describe('http client', function () {
    beforeEach(function () {
        Http::fake([
            'api.telegram.org/bot123456:TEST-TOKEN/sendMessage' => Http::response(['ok' => true, 'result' => ['message_id' => 9]]),
            'api.telegram.org/bot123456:TEST-TOKEN/getChat' => Http::response(['ok' => false, 'error_code' => 400, 'description' => 'Bad Request: chat not found'], 400),
        ]);
    });

    it('calls the Bot API and returns the result', function () {
        expect(Telegram::say('Hello', 99))->toBe(['message_id' => 9]);

        Http::assertSent(fn (Request $request) => $request['chat_id'] === 99 && $request['text'] === 'Hello');
    });

    it('throws an exception without leaking the token', function () {
        expect(fn () => Telegram::api()->getChat(['chat_id' => 1]))
            ->toThrow(function (TelegramApiException $e) {
                expect($e->errorCode)->toBe(400)
                    ->and($e->description)->toBe('Bad Request: chat not found')
                    ->and($e->getMessage())->not->toContain('TEST-TOKEN');
            });
    });
});
