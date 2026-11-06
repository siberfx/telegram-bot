<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Queue;
use Siberfx\TelegramBot\Facades\Telegram;
use Siberfx\TelegramBot\Jobs\ProcessUpdate;

it('handles webhook updates with a valid secret', function () {
    $tester = Telegram::fake();

    $this->postJson('/telegram/main/webhook', textUpdate('/start'), webhookHeaders())
        ->assertOk()
        ->assertJson(['ok' => true]);

    $tester->assertSent('sendMessage', fn (array $p) => $p['text'] === 'Welcome!');
});

it('rejects an invalid secret', function () {
    $this->postJson('/telegram/main/webhook', textUpdate('/start'), webhookHeaders('wrong'))->assertForbidden();
});

it('returns 404 for unknown bots', function () {
    $this->postJson('/telegram/nope/webhook', textUpdate('/start'))->assertNotFound();
});

it('rejects payloads that are not updates', function () {
    $this->postJson('/telegram/main/webhook', ['foo' => 'bar'], webhookHeaders())->assertStatus(400);
});

it('can queue updates', function () {
    Queue::fake();
    config(['telegram-bot.queue.enabled' => true]);

    $this->postJson('/telegram/main/webhook', textUpdate('/start'), webhookHeaders())->assertOk();

    Queue::assertPushed(ProcessUpdate::class, fn (ProcessUpdate $job) => $job->bot === 'main');
});
