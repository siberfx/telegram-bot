<?php

declare(strict_types=1);

namespace Siberfx\TelegramBot\Tests\Feature;

use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Siberfx\TelegramBot\Facades\Telegram;
use Siberfx\TelegramBot\Jobs\ProcessUpdate;
use Siberfx\TelegramBot\Testing\UpdateFactory;
use Siberfx\TelegramBot\Tests\TestCase;

final class WebhookTest extends TestCase
{
    #[Test]
    public function it_handles_webhook_updates_with_a_valid_secret(): void
    {
        $tester = Telegram::fake();
        $update = (new UpdateFactory)->text('/start')->toArray();

        $this->postJson('/telegram/main/webhook', $update, ['X-Telegram-Bot-Api-Secret-Token' => 'top-secret'])
            ->assertOk()
            ->assertJson(['ok' => true]);

        $tester->assertSent('sendMessage', fn (array $p) => $p['text'] === 'Welcome!');
    }

    #[Test]
    public function it_rejects_invalid_secrets_and_unknown_bots(): void
    {
        $update = (new UpdateFactory)->text('/start')->toArray();

        $this->postJson('/telegram/main/webhook', $update, ['X-Telegram-Bot-Api-Secret-Token' => 'wrong'])->assertForbidden();
        $this->postJson('/telegram/nope/webhook', $update)->assertNotFound();
    }

    #[Test]
    public function it_can_queue_updates(): void
    {
        Queue::fake();
        config(['telegram-bot.queue.enabled' => true]);

        $this->postJson('/telegram/main/webhook', (new UpdateFactory)->text('/start')->toArray(), ['X-Telegram-Bot-Api-Secret-Token' => 'top-secret'])
            ->assertOk();

        Queue::assertPushed(ProcessUpdate::class, fn (ProcessUpdate $job) => $job->bot === 'main');
    }
}
