<?php

declare(strict_types=1);

use Siberfx\TelegramBot\Testing\UpdateFactory;
use Siberfx\TelegramBot\Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test case
|--------------------------------------------------------------------------
|
| Every feature test boots a Testbench application with the package
| provider and a "main" bot whose routes live in tests/Fixtures/routes.php.
|
*/

pest()->extend(TestCase::class)->in('Feature');

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

/**
 * A raw Telegram text update, ready to be posted to the webhook.
 *
 * @return array<string, mixed>
 */
function textUpdate(string $text): array
{
    return (new UpdateFactory)->text($text)->toArray();
}

/** @return array<string, string> */
function webhookHeaders(string $secret = 'top-secret'): array
{
    return ['X-Telegram-Bot-Api-Secret-Token' => $secret];
}
