<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

it('chats with the bot in tinker without contacting Telegram', function () {
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
});

it('fails tinker for unknown bots', function () {
    $this->artisan('telegram-bot:tinker', ['--bot' => 'nope'])->assertFailed();
});

it('generates conversation classes', function () {
    $path = app_path('Telegram/Conversations/OrderConversation.php');
    File::delete($path);

    $this->artisan('make:telegram-conversation', ['name' => 'OrderConversation'])->assertSuccessful();

    expect($path)->toBeFile()
        ->and(file_get_contents($path))->toContain('class OrderConversation extends Conversation');

    File::deleteDirectory(app_path('Telegram'));
});
