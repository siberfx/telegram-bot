<?php

declare(strict_types=1);

namespace Siberfx\TelegramBot\Tests\Feature;

use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;
use Siberfx\TelegramBot\Tests\TestCase;

final class ConsoleTest extends TestCase
{
    #[Test]
    public function tinker_chats_with_the_bot_without_telegram(): void
    {
        $this->artisan('telegram-bot:tinker')
            ->expectsQuestion('<fg=green>You</>', '/start')
            ->expectsOutputToContain('Welcome!')
            ->expectsQuestion('<fg=green>You</>', 'onboard')
            ->expectsOutputToContain('What is your name?')
            ->expectsQuestion('<fg=green>You</>', 'Selim')
            ->expectsOutputToContain('#1 Red')
            ->expectsQuestion('<fg=green>You</>', '#1')
            ->expectsOutputToContain('Selim likes Red (red)')
            ->expectsQuestion('<fg=green>You</>', ':location 41.0 29.0')
            ->expectsOutputToContain('Fallback')
            ->expectsQuestion('<fg=green>You</>', 'exit')
            ->assertSuccessful();
    }

    #[Test]
    public function it_generates_conversations(): void
    {
        $path = app_path('Telegram/Conversations/OrderConversation.php');
        File::delete($path);

        $this->artisan('make:telegram-conversation', ['name' => 'OrderConversation'])->assertSuccessful();

        $this->assertFileExists($path);
        $this->assertStringContainsString('class OrderConversation extends Conversation', (string) file_get_contents($path));

        File::deleteDirectory(app_path('Telegram'));
    }
}
