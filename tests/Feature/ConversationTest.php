<?php

declare(strict_types=1);

namespace Siberfx\TelegramBot\Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Siberfx\TelegramBot\Facades\Telegram;
use Siberfx\TelegramBot\Tests\TestCase;

final class ConversationTest extends TestCase
{
    #[Test]
    public function it_runs_a_conversation_with_closures_methods_and_buttons(): void
    {
        Telegram::fake()
            ->receives('onboard')
            ->assertReply('What is your name?')
            ->assertConversationActive()
            ->receives('Selim')
            ->assertQuestion('Hi Selim, pick a color', ['Red', 'Blue'])
            ->clicks('Red')
            ->assertReply('Selim likes Red (red)')
            ->assertSent('editMessageReplyMarkup')
            ->assertNoConversation();
    }

    #[Test]
    public function it_runs_pattern_closures_and_repeats_unknown_answers(): void
    {
        Telegram::fake()
            ->receives('onboard')->assertReply('What is your name?')
            ->receives('Ada')->assertQuestion()
            ->receives('green')->assertQuestion('Hi Ada, pick a color')
            ->clicks('Blue')->assertReply('Ada likes Blue');
    }

    #[Test]
    public function stopping_and_skipping_commands_interact_with_conversations(): void
    {
        Telegram::fake()
            ->receives('onboard')->assertReply('What is your name?')
            ->receives('/ping')->assertReply('pong')
            ->assertConversationActive()
            ->receives('stop')->assertReply('Stopped.')
            ->assertNoConversation()
            ->receives('Selim')->assertReply('Fallback');
    }

    #[Test]
    public function conversations_are_scoped_per_user(): void
    {
        $tester = Telegram::fake();

        $tester->receives('onboard')->assertReply('What is your name?');

        $tester->setUser(['id' => 555, 'first_name' => 'Other'])
            ->receives('Selim')
            ->assertReply('Fallback');
    }
}
