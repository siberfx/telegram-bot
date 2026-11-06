<?php

declare(strict_types=1);

namespace Siberfx\TelegramBot\Tests\Feature;

use Closure;
use PHPUnit\Framework\Attributes\Test;
use Siberfx\TelegramBot\Enums\ChatType;
use Siberfx\TelegramBot\Facades\Telegram;
use Siberfx\TelegramBot\Incoming\IncomingMessage;
use Siberfx\TelegramBot\Middleware\Heard;
use Siberfx\TelegramBot\Middleware\Matching;
use Siberfx\TelegramBot\Middleware\Received;
use Siberfx\TelegramBot\Middleware\Sending;
use Siberfx\TelegramBot\TelegramBot;
use Siberfx\TelegramBot\Tests\TestCase;

final class MatchingTest extends TestCase
{
    #[Test]
    public function it_loads_the_routes_file_and_matches_patterns(): void
    {
        Telegram::fake()
            ->receives('/start')
            ->assertReply('Welcome!')
            ->receives('call me Selim')
            ->assertReply('Hello Selim')
            ->receives('CALL ME Ada Lovelace')
            ->assertReply('Hello Ada Lovelace')
            ->receives('something else')
            ->assertReply('Fallback');
    }

    #[Test]
    public function it_strips_the_bot_username_from_commands(): void
    {
        Telegram::fake()->receives('/start@my_test_bot')->assertReply('Welcome!');
    }

    #[Test]
    public function it_supports_optional_and_multiple_parameters(): void
    {
        $tester = Telegram::fake(setup: function (TelegramBot $bot) {
            $bot->hears('/start {payload?}', fn (TelegramBot $bot, ?string $payload = null) => $bot->reply('payload='.($payload ?? 'none')));
            $bot->hears('{a} plus {b}', fn (TelegramBot $bot, string $a, string $b) => $bot->reply((string) ((int) $a + (int) $b)));
        });

        $tester->receives('/start')->assertReply('payload=none')
            ->receives('/start ref-42')->assertReply('payload=ref-42')
            ->receives('2 plus 3')->assertReply('5');
    }

    #[Test]
    public function it_listens_for_attachments(): void
    {
        $tester = Telegram::fake(setup: function (TelegramBot $bot) {
            $bot->receivesImages(fn (TelegramBot $bot) => $bot->reply('image '.$bot->message()->images()[0]->fileId()));
            $bot->receivesLocation(fn (TelegramBot $bot) => $bot->reply('lat '.$bot->message()->location()->latitude));
            $bot->receivesContact(fn (TelegramBot $bot) => $bot->reply('phone '.$bot->message()->contact()->phoneNumber));
            $bot->receivesFiles(fn (TelegramBot $bot) => $bot->reply('file '.$bot->message()->documents()[0]->fileName()));
            $bot->receivesVoice(fn (TelegramBot $bot) => $bot->reply('voice'));
        });

        $tester->receivesImages()->assertReplyMatches('/^image fake-photo-[0-9a-f]+$/')
            ->receivesLocation(52.37, 4.89)->assertReply('lat 52.37')
            ->receivesContact('+31600000000')->assertReply('phone +31600000000')
            ->receivesFiles('invoice.pdf')->assertReply('file invoice.pdf')
            ->receivesVoice()->assertReply('voice');
    }

    #[Test]
    public function it_filters_by_chat_type_and_groups(): void
    {
        $tester = Telegram::fake(setup: function (TelegramBot $bot) {
            $bot->hears('secret', fn (TelegramBot $bot) => $bot->reply('private only'))->in(ChatType::Private);
            $bot->group(['chat_type' => ['group', 'supergroup']], function (TelegramBot $bot) {
                $bot->hears('team', fn (TelegramBot $bot) => $bot->reply('group only'));
            });
        });

        $tester->receives('secret')->assertReply('private only')
            ->receives('team')->assertReplyNothing()
            ->inGroup()
            ->receives('team')->assertReply('group only')
            ->receives('secret')->assertReplyNothing();
    }

    #[Test]
    public function it_runs_received_heard_matching_and_sending_middleware(): void
    {
        $tester = Telegram::fake(setup: function (TelegramBot $bot) {
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
        });

        $tester->receives('anything at all')->assertReplies(['HEARD', 'HELLO VIA INTENT'])
            ->receives('blocked')->assertReplyNothing();
    }

    #[Test]
    public function it_dispatches_event_listeners_and_handles_exceptions(): void
    {
        $tester = Telegram::fake(setup: function (TelegramBot $bot) {
            $bot->on('new_chat_members', fn (TelegramBot $bot, array $members) => $bot->reply('Welcome '.$members[0]['first_name']));
            $bot->hears('boom', fn () => throw new \DomainException('Kaboom'));
            $bot->exception(\DomainException::class, fn (\DomainException $e, TelegramBot $bot) => $bot->reply('Error: '.$e->getMessage()));
        });

        $tester->receivesUpdate($tester->updates->message(['new_chat_members' => [['id' => 7, 'is_bot' => false, 'first_name' => 'Ada']]]))
            ->assertReply('Welcome Ada')
            ->receives('boom')
            ->assertReply('Error: Kaboom');
    }

    #[Test]
    public function it_answers_callback_queries_automatically(): void
    {
        $tester = Telegram::fake(setup: function (TelegramBot $bot) {
            $bot->hears('like', fn (TelegramBot $bot) => $bot->editMessage('Thanks!'));
            $bot->hears('toast', fn (TelegramBot $bot) => $bot->answerCallback('Done', alert: true));
        });

        $tester->receivesCallback('like')->assertCallbackAnswered()->assertSent('editMessageText', fn (array $p) => $p['text'] === 'Thanks!');
        $tester->receivesCallback('toast')->assertCallbackAnswered('Done');
        $this->assertCount(1, $tester->client->calls('answerCallbackQuery'));
    }
}
