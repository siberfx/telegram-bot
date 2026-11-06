<?php

declare(strict_types=1);

use Siberfx\TelegramBot\Facades\Telegram;

it('runs a conversation with closures, methods and buttons', function () {
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
});

it('runs pattern closures and repeats unknown answers', function () {
    Telegram::fake()
        ->receives('onboard')->assertReply('What is your name?')
        ->receives('Ada')->assertQuestion()
        ->receives('green')->assertQuestion('Hi Ada, pick a color')
        ->clicks('Blue')->assertReply('Ada likes Blue');
});

it('lets stopping and skipping commands interact with conversations', function () {
    Telegram::fake()
        ->receives('onboard')->assertReply('What is your name?')
        ->receives('/ping')->assertReply('pong')
        ->assertConversationActive()
        ->receives('stop')->assertReply('Stopped.')
        ->assertNoConversation()
        ->receives('Selim')->assertReply('Fallback');
});

it('scopes conversations per user', function () {
    $tester = Telegram::fake();

    $tester->receives('onboard')->assertReply('What is your name?');

    $tester->setUser(['id' => 555, 'first_name' => 'Other'])
        ->receives('Selim')
        ->assertReply('Fallback');
});

it('can stop a conversation manually', function () {
    $tester = Telegram::fake()->receives('onboard')->assertConversationActive();

    $tester->bot->stopConversation($tester->updates->chat()['id'], $tester->updates->user()['id']);

    $tester->assertNoConversation();
});
