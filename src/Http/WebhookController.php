<?php

declare(strict_types=1);

namespace Siberfx\TelegramBot\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Siberfx\TelegramBot\BotManager;
use Siberfx\TelegramBot\Jobs\ProcessUpdate;
use Throwable;

final class WebhookController
{
    public function __invoke(Request $request, BotManager $manager, string $bot): JsonResponse
    {
        abort_unless($manager->has($bot), 404);

        $secret = (string) ($manager->config($bot)['webhook_secret'] ?? '');

        if ($secret !== '' && ! hash_equals($secret, (string) $request->header('X-Telegram-Bot-Api-Secret-Token'))) {
            abort(403);
        }

        $payload = $request->json()->all();

        if (! isset($payload['update_id'])) {
            return new JsonResponse(['ok' => false, 'error' => 'Invalid update.'], 400);
        }

        if (config('telegram-bot.queue.enabled')) {
            ProcessUpdate::dispatch($bot, $payload)
                ->onConnection(config('telegram-bot.queue.connection'))
                ->onQueue(config('telegram-bot.queue.queue'));

            return new JsonResponse(['ok' => true]);
        }

        try {
            $manager->bot($bot)->handle($payload);
        } catch (Throwable $e) {
            if (! config('telegram-bot.webhook.swallow_exceptions', true)) {
                throw $e;
            }

            report($e);
        }

        return new JsonResponse(['ok' => true]);
    }
}
