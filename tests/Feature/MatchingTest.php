<?php

declare(strict_types=1);

use Siberfx\TelegramBot\Enums\ChatType;
use Siberfx\TelegramBot\Facades\Telegram;
use Siberfx\TelegramBot\Incoming\IncomingMessage;
use Siberfx\TelegramBot\Middleware\Heard;
use Siberfx\TelegramBot\Middleware\Matching;
use Siberfx\TelegramBot\Middleware\Received;
use Siberfx\TelegramBot\Middleware\Sending;
use Siberfx\TelegramBot\TelegramBot;

describe('patterns', function () {
    it('loads the routes file and matches patterns', function () {
        Telegram::fake()
            ->receives('/start')
            ->assertReply('Welcome!')
            ->receives('call me Selim')
            ->assertReply('Hello Selim')
            ->receives('CALL ME Ada Lovelace')
            ->assertReply('Hello Ada Lovelace')
            ->receives('something else')
            ->assertReply('Fallback');
    });

    it('strips the bot username from commands', function () {
        Telegram::fake()->receives('/start@my_test_bot')->assertReply('Welcome!');
    });

    it('supports optional and multiple parameters', function () {
        Telegram::fake(setup: function (TelegramBot $bot) {
            $bot->hears('/start {payload?}', fn (TelegramBot $bot, ?string $payload = null) => $bot->reply('payload='.($payload ?? 'none')));
            $bot->hears('{a} plus {b}', fn (TelegramBot $bot, string $a, string $b) => $bot->reply((string) ((int) $a + (int) $b)));
        })
            ->receives('/start')->assertReply('payload=none')
            ->receives('/start ref-42')->assertReply('payload=ref-42')
            ->receives('2 plus 3')->assertReply('5');
    });

    it('exposes the matched parameters', function () {
        $matches = null;

        Telegram::fake(setup: function (TelegramBot $bot) use (&$matches) {
            $bot->hears('order {id}', function (TelegramBot $bot) use (&$matches) {
                $matches = $bot->matches();
            });
        })->receives('order 1042');

        expect($matches)->toBe(['id' => '1042']);
    });
});

describe('attachments', function () {
    it('listens for every attachment type', function () {
        Telegram::fake(setup: function (TelegramBot $bot) {
            $bot->receivesImages(fn (TelegramBot $bot) => $bot->reply('image '.$bot->message()->images()[0]->fileId()));
            $bot->receivesLocation(fn (TelegramBot $bot) => $bot->reply('lat '.$bot->message()->location()->latitude));
            $bot->receivesContact(fn (TelegramBot $bot) => $bot->reply('phone '.$bot->message()->contact()->phoneNumber));
            $bot->receivesFiles(fn (TelegramBot $bot) => $bot->reply('file '.$bot->message()->documents()[0]->fileName()));
            $bot->receivesVoice(fn (TelegramBot $bot) => $bot->reply('voice'));
        })
            ->receivesImages()->assertReplyMatches('/^image fake-photo-[0-9a-f]+$/')
            ->receivesLocation(52.37, 4.89)->assertReply('lat 52.37')
            ->receivesContact('+31600000000')->assertReply('phone +31600000000')
            ->receivesFiles('invoice.pdf')->assertReply('file invoice.pdf')
            ->receivesVoice()->assertReply('voice');
    });

    it('does not match captions against text patterns', function () {
        Telegram::fake(setup: function (TelegramBot $bot) {
            $bot->hears('hello', fn (TelegramBot $bot) => $bot->reply('text'));
            $bot->receivesImages(fn (TelegramBot $bot) => $bot->reply('caption: '.$bot->message()->caption));
        })
            ->receivesImages('hello')
            ->assertReply('caption: hello')
            ->assertReplyNothing();
    });
});

describe('filters', function () {
    it('filters by chat type and groups', function () {
        Telegram::fake(setup: function (TelegramBot $bot) {
            $bot->hears('secret', fn (TelegramBot $bot) => $bot->reply('private only'))->in(ChatType::Private);
            $bot->group(['chat_type' => ['group', 'supergroup']], function (TelegramBot $bot) {
                $bot->hears('team', fn (TelegramBot $bot) => $bot->reply('group only'));
            });
        })
            ->receives('secret')->assertReply('private only')
            ->receives('team')->assertReplyNothing()
            ->inGroup()
            ->receives('team')->assertReply('group only')
            ->receives('secret')->assertReplyNothing();
    });

    it('filters by user', function () {
        Telegram::fake(setup: function (TelegramBot $bot) {
            $bot->hears('/admin', fn (TelegramBot $bot) => $bot->reply('hi admin'))->fromUsers(42);
        })
            ->receives('/admin')->assertReplyNothing()
            ->setUser(['id' => 42])
            ->receives('/admin')->assertReply('hi admin');
    });
});

describe('middleware', function () {
    it('runs received, heard, matching and sending middleware', function () {
        Telegram::fake(setup: function (TelegramBot $bot) {
            $bot->middleware()->received(new class implements Received
            {
                public function received(IncomingMessage $message, Closure $next, TelegramBot $bot): mixed
                {
                    return $message->text === 'blocked' ? null : $next($message->addExtra('intent', 'greet'));
                }
            });

            $bot->middleware()->sending(new class implements Sending
            {
                public function sending(array $request, Closure $next, TelegramBot $bot): mixed
                {
                    $request['params']['text'] = strtoupper((string) ($request['params']['text'] ?? ''));

                    return $next($request);
                }
            });

            $intent = new class implements Matching
            {
                public function matching(IncomingMessage $message, string $pattern, bool $regexMatched): bool
                {
                    return $message->extra('intent') === $pattern;
                }
            };

            $heard = new class implements Heard
            {
                public function heard(IncomingMessage $message, Closure $next, TelegramBot $bot): mixed
                {
                    $bot->reply('heard');

                    return $next($message);
                }
            };

            $bot->hears('greet', fn (TelegramBot $bot) => $bot->reply('hello via intent'))->middleware($intent, $heard);
        })
            ->receives('anything at all')->assertReplies(['HEARD', 'HELLO VIA INTENT'])
            ->receives('blocked')->assertReplyNothing();
    });

    it('rejects classes that implement no middleware interface', function () {
        Telegram::fake(setup: fn (TelegramBot $bot) => $bot->middleware()->add(new stdClass));
    })->throws(InvalidArgumentException::class, 'does not implement any Telegram bot middleware interface');
});

describe('events and exceptions', function () {
    it('dispatches event listeners and handles exceptions', function () {
        $tester = Telegram::fake(setup: function (TelegramBot $bot) {
            $bot->on('new_chat_members', fn (TelegramBot $bot, array $members) => $bot->reply('Welcome '.$members[0]['first_name']));
            $bot->hears('boom', fn () => throw new DomainException('Kaboom'));
            $bot->exception(DomainException::class, fn (DomainException $e, TelegramBot $bot) => $bot->reply('Error: '.$e->getMessage()));
        });

        $tester->receivesUpdate($tester->updates->message(['new_chat_members' => [['id' => 7, 'is_bot' => false, 'first_name' => 'Ada']]]))
            ->assertReply('Welcome Ada')
            ->receives('boom')
            ->assertReply('Error: Kaboom');
    });

    it('rethrows unhandled exceptions', function () {
        Telegram::fake(setup: fn (TelegramBot $bot) => $bot->hears('boom', fn () => throw new RuntimeException('Unhandled')))
            ->receives('boom');
    })->throws(RuntimeException::class, 'Unhandled');
});

describe('callback queries', function () {
    it('answers callback queries automatically, but only once', function () {
        $tester = Telegram::fake(setup: function (TelegramBot $bot) {
            $bot->hears('like', fn (TelegramBot $bot) => $bot->editMessage('Thanks!'));
            $bot->hears('toast', fn (TelegramBot $bot) => $bot->answerCallback('Done', alert: true));
        });

        $tester->receivesCallback('like')
            ->assertCallbackAnswered()
            ->assertSent('editMessageText', fn (array $p) => $p['text'] === 'Thanks!');

        $tester->receivesCallback('toast')->assertCallbackAnswered('Done');

        expect($tester->client->calls('answerCallbackQuery'))->toHaveCount(1);
    });
});
