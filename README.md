<h1 align="center">siberfx/telegram-bot</h1>

<p align="center">
A clean, modern Telegram bot framework for <b>Laravel 13</b> and <b>PHP 8.4+</b>.<br>
Patterns, conversations, keyboards, attachments, middleware, storage, long polling,<br>
a console <b>tinker</b> and a fluent <b>testing</b> API — Telegram-first, fully typed.
</p>

<p align="center">
<a href="https://packagist.org/packages/siberfx/telegram-bot"><img src="https://img.shields.io/packagist/v/siberfx/telegram-bot.svg?style=flat-square" alt="Latest Version"></a>
<img src="https://img.shields.io/badge/php-%5E8.4-777BB4?style=flat-square&logo=php&logoColor=white" alt="PHP ^8.4">
<img src="https://img.shields.io/badge/laravel-13.x-FF2D20?style=flat-square&logo=laravel&logoColor=white" alt="Laravel 13">
<a href="LICENSE.md"><img src="https://img.shields.io/badge/license-MIT-blue.svg?style=flat-square" alt="MIT License"></a>
</p>

```php
$bot->hears('call me {name}', function (TelegramBot $bot, string $name) {
    $bot->userStorage()->put('name', $name);
    $bot->reply("Nice to meet you, {$name}!");
});
```

---

## Table of contents

- [Why this package](#why-this-package)
- [Requirements](#requirements)
- [Installation](#installation)
- [Quick start (5 minutes)](#quick-start-5-minutes)
- [Configuration](#configuration)
- [The routes file](#the-routes-file)
- [Listening for messages](#listening-for-messages)
  - [Patterns & parameters](#patterns--parameters)
  - [Handler types](#handler-types)
  - [Attachments](#listening-for-attachments)
  - [Callback queries (buttons)](#callback-queries-buttons)
  - [Filters](#filters)
  - [Groups](#groups)
  - [Fallback](#fallback)
- [Replying & sending](#replying--sending)
  - [Formatting](#formatting)
  - [Sending to any chat](#sending-to-any-chat)
  - [Editing & deleting](#editing--deleting)
  - [Typing indicators](#typing-indicators)
  - [Raw Bot API access](#raw-bot-api-access)
- [Keyboards & questions](#keyboards--questions)
- [Conversations](#conversations)
- [Sending attachments](#sending-attachments)
- [Downloading files](#downloading-files)
- [The incoming message](#the-incoming-message)
- [Middleware](#middleware)
- [Storage](#storage)
- [Events](#events)
- [Exceptions](#exceptions)
- [Tinker — chat in your terminal](#tinker--chat-in-your-terminal)
- [Running the bot](#running-the-bot)
  - [Long polling (development)](#long-polling-development)
  - [Webhook (production)](#webhook-production)
  - [Queued updates](#queued-updates)
- [Multiple bots](#multiple-bots)
- [Testing](#testing)
- [Recipes](#recipes)
- [Artisan commands](#artisan-commands)
- [Coming from BotMan](#coming-from-botman)
- [Security](#security)
- [Troubleshooting](#troubleshooting)
- [Changelog](#changelog) · [License](#license)

---

## Why this package

| | |
|---|---|
| 🎯 **Telegram-first** | No driver abstraction to fight. Every Bot API feature — topics, web apps, polls, payments, inline mode — is one call away. |
| 🧩 **Familiar API** | `hears()`, conversations, questions, middleware, storage — if you know BotMan you already know this. |
| 🧪 **Testable** | `Telegram::fake()->receives('/start')->assertReply('Welcome!')` — no HTTP, no mocks. |
| 💬 **Tinker** | Chat with your real bot logic in the terminal, press buttons with `#1`, send photos and locations. |
| 🔁 **Polling & webhook** | Long polling for local development, a secured webhook (optionally queued) for production. |
| 🤖 **Multiple bots** | Any number of bots, each with its own token, routes file and webhook. |
| 🛡️ **Modern Laravel** | PHP 8.4, readonly value objects, enums, works with Laravel 13's hardened cache unserialization, Octane/queue safe. |

## Requirements

- PHP **8.4** or **8.5**
- Laravel **13.x**
- A bot token from [@BotFather](https://t.me/BotFather)

## Installation

```bash
composer require siberfx/telegram-bot
php artisan telegram-bot:install
```

The install command:

1. publishes `config/telegram-bot.php`,
2. creates `routes/telegram.php` with a working example bot,
3. prints the `.env` keys you need (including a freshly generated webhook secret).

```dotenv
TELEGRAM_BOT_TOKEN=123456789:AAH-your-token-from-botfather
TELEGRAM_BOT_USERNAME=my_awesome_bot
TELEGRAM_WEBHOOK_SECRET=6f1c2a...random
```

The service provider and the `Telegram` facade are auto-discovered.

## Quick start (5 minutes)

**1. Write a handler** in `routes/telegram.php`:

```php
use Siberfx\TelegramBot\TelegramBot;

$bot->hears('/start', function (TelegramBot $bot) {
    $bot->reply("Hello {$bot->user()->firstName}! 👋");
});

$bot->fallback(fn (TelegramBot $bot) => $bot->reply('Try /start'));
```

**2. Try it without Telegram:**

```bash
php artisan telegram-bot:tinker
```

```
You: /start
Bot Hello Tinker! 👋
```

**3. Run it for real** (no public URL needed):

```bash
php artisan telegram-bot:poll --delete-webhook
```

Open your bot in Telegram and send `/start`. 🎉

**4. Go to production** with a webhook:

```bash
php artisan telegram-bot:webhook set
```

## Configuration

`config/telegram-bot.php` (all keys are optional except the token):

```php
return [
    'default' => env('TELEGRAM_BOT', 'main'),

    'bots' => [
        'main' => [
            'token'           => env('TELEGRAM_BOT_TOKEN'),
            'username'        => env('TELEGRAM_BOT_USERNAME'),
            'routes'          => base_path('routes/telegram.php'),  // where hears() etc. live
            'webhook_url'     => env('TELEGRAM_WEBHOOK_URL'),       // null = route('telegram-bot.webhook')
            'webhook_secret'  => env('TELEGRAM_WEBHOOK_SECRET'),    // verified on every webhook call
            'allowed_updates' => [],                                // e.g. ['message', 'callback_query']
            'commands'        => [                                  // synced by telegram-bot:commands
                'start' => 'Start the bot',
                'help'  => 'Show what I can do',
            ],
        ],
    ],

    'api_url'    => env('TELEGRAM_API_URL', 'https://api.telegram.org'),  // or your local Bot API server
    'http'       => ['timeout' => 30, 'retries' => 2],
    'parse_mode' => env('TELEGRAM_PARSE_MODE'),  // HTML | MarkdownV2 | Markdown | null

    'webhook' => [
        'enabled'            => true,
        'path'               => 'telegram/{bot}/webhook',
        'middleware'         => [],
        'swallow_exceptions' => true,  // report errors but answer 200 so Telegram doesn't retry forever
    ],

    'queue' => [
        'enabled'    => env('TELEGRAM_QUEUE', false),
        'connection' => env('TELEGRAM_QUEUE_CONNECTION'),
        'queue'      => env('TELEGRAM_QUEUE_NAME'),
    ],

    'cache'         => ['store' => env('TELEGRAM_CACHE_STORE'), 'prefix' => 'telegram-bot'],
    'conversations' => ['ttl' => 1800, 'remove_keyboard_on_answer' => true],
    'callbacks'     => ['auto_answer' => true],
    'middleware'    => [],  // global middleware class names

    'tinker' => [
        'user'    => ['id' => 100000001, 'first_name' => 'Tinker', 'username' => 'tinker', 'language_code' => 'en'],
        'chat_id' => 100000001,
    ],
];
```

| Option | Description |
|---|---|
| `bots.*.routes` | PHP file that registers the bot's handlers. `$bot` is in scope. |
| `bots.*.webhook_secret` | Sent as `secret_token` on `setWebhook` and compared (timing-safe) with the `X-Telegram-Bot-Api-Secret-Token` header. |
| `parse_mode` | Default parse mode for all outgoing text and captions. Per-message `->html()` / `->markdown()` overrides it. |
| `cache.store` | Cache store for conversations and storage. Use a persistent store (redis, database) in production. |
| `conversations.ttl` | Seconds a conversation waits for an answer before it expires. |
| `callbacks.auto_answer` | Answers callback queries automatically when your handler didn't, so the button spinner stops. |

## The routes file

Every bot has a routes file (default `routes/telegram.php`). It is loaded once per bot instance, right before the first update is handled. `$bot` is the `Siberfx\TelegramBot\TelegramBot` instance:

```php
<?php

use App\Telegram\Conversations\OnboardingConversation;
use App\Telegram\Handlers\StartHandler;
use Siberfx\TelegramBot\TelegramBot;

/** @var TelegramBot $bot */

$bot->hears('/start {payload?}', StartHandler::class);
$bot->hears('/onboard', OnboardingConversation::class);
$bot->fallback(fn (TelegramBot $bot) => $bot->reply('Unknown command.'));
```

Large bots can split handlers into several files and `require` them, or use invokable handler classes.

## Listening for messages

### Patterns & parameters

```php
// Exact text (case-insensitive)
$bot->hears('hello', fn (TelegramBot $bot) => $bot->reply('Hi!'));

// Required parameter
$bot->hears('call me {name}', fn (TelegramBot $bot, string $name) => $bot->reply("Hi {$name}"));

// Several parameters — passed positionally after $bot
$bot->hears('{a} plus {b}', fn (TelegramBot $bot, string $a, string $b) => $bot->reply((string) ($a + $b)));

// Optional parameter (the space before it is optional too): "/start" and "/start ref-42" both match
$bot->hears('/start {payload?}', function (TelegramBot $bot, ?string $payload = null) {
    $bot->reply($payload ? "Invited by {$payload}" : 'Welcome!');
});

// Regular expressions — patterns are regex fragments matched against the whole message
$bot->hears('.*(weather|forecast).*', fn (TelegramBot $bot) => $bot->reply('☀️'));
$bot->hears('order #?(?<id>\d+)', fn (TelegramBot $bot, string $id) => $bot->reply("Order {$id}"));

// Several patterns for one handler
$bot->hears(['hi', 'hey', 'hello'], fn (TelegramBot $bot) => $bot->reply('👋'));
```

> `/start@my_bot` is normalized to `/start`, so commands work the same in groups.

All matched parameters are also available via `$bot->matches()`.

> When several patterns match the same message, **every** matching handler runs, in registration order. Make patterns specific (e.g. `/start link-{token}` vs `/start {payload?}`) or handle the cases inside one handler.

### Handler types

```php
$bot->hears('a', fn (TelegramBot $bot) => ...);                 // closure
$bot->hears('b', StartHandler::class);                          // invokable class (resolved from the container)
$bot->hears('c', 'App\Telegram\Handlers\Menu@show');            // Class@method
$bot->hears('d', [MenuHandler::class, 'show']);                 // [Class, method]
$bot->hears('e', OnboardingConversation::class);                // starts a conversation
```

```php
namespace App\Telegram\Handlers;

use App\Services\ReferralService;
use Siberfx\TelegramBot\TelegramBot;

final readonly class StartHandler
{
    public function __construct(private ReferralService $referrals) {}   // dependency injection

    public function __invoke(TelegramBot $bot, ?string $payload = null): void
    {
        if ($payload !== null) {
            $this->referrals->track($payload, $bot->user()->id);
        }

        $bot->reply('Welcome aboard!');
    }
}
```

### Listening for attachments

```php
$bot->receivesImages(fn (TelegramBot $bot) => $bot->reply('Nice photo!'));
$bot->receivesVideos(fn (TelegramBot $bot) => $bot->reply('Cool video!'));
$bot->receivesAudio(fn (TelegramBot $bot) => $bot->reply('🎵'));
$bot->receivesVoice(fn (TelegramBot $bot) => $bot->reply('I heard you.'));
$bot->receivesFiles(fn (TelegramBot $bot) => $bot->reply('File received.'));
$bot->receivesStickers(fn (TelegramBot $bot) => $bot->reply($bot->message()->sticker()->get('emoji')));
$bot->receivesLocation(function (TelegramBot $bot) {
    $location = $bot->message()->location();
    $bot->reply("📍 {$location->latitude}, {$location->longitude}");
});
$bot->receivesContact(fn (TelegramBot $bot) => $bot->reply('Thanks, '.$bot->message()->contact()->firstName));
$bot->receivesAttachments(fn (TelegramBot $bot) => $bot->reply('Got an attachment'));   // any of the above
```

Captions are not matched by `hears()`; read them with `$bot->message()->caption`.

### Callback queries (buttons)

The `callback_data` of a pressed inline button is matched by `hears()` just like text:

```php
$bot->hears('/menu', function (TelegramBot $bot) {
    $bot->reply(Question::create('What would you like?')->addButtons([
        Button::create('🍕 Pizza')->value('order:pizza'),
        Button::create('🍣 Sushi')->value('order:sushi'),
    ]));
});

$bot->hears('order:{item}', function (TelegramBot $bot, string $item) {
    $bot->answerCallback("Added {$item} to your cart");   // toast
    $bot->editMessage("🛒 {$item} is on its way!");          // edits the message that had the buttons
});
```

If you don't call `answerCallback()`, the package answers the query for you after the handler runs (`callbacks.auto_answer`).

### Filters

```php
use Siberfx\TelegramBot\Enums\ChatType;

$bot->hears('/stats', StatsHandler::class)
    ->privateOnly();                        // only in private chats

$bot->hears('/rules', RulesHandler::class)
    ->groupsOnly();                         // groups and supergroups

$bot->hears('/announce {text}', AnnounceHandler::class)
    ->in(ChatType::Channel, ChatType::Supergroup)
    ->fromUsers(11111111, 22222222)         // only these user ids
    ->forChats(-1001234567890);             // only in this chat
```

### Groups

Share filters and middleware between handlers:

```php
$bot->group([
    'chat_type'  => 'private',
    'users'      => config('telegram.admins'),
    'middleware' => [App\Telegram\Middleware\LogCommands::class],
], function (TelegramBot $bot) {
    $bot->hears('/ban {user}', BanHandler::class);
    $bot->hears('/broadcast {text}', BroadcastHandler::class);
});
```

| Group key | Same as |
|---|---|
| `middleware` | `->middleware(...)` |
| `chat_type` | `->in(...)` |
| `chats` | `->forChats(...)` |
| `users` | `->fromUsers(...)` |

### Fallback

Runs when nothing matched (and no conversation took the message):

```php
$bot->fallback(function (TelegramBot $bot) {
    $bot->reply("Sorry, I didn't get that. Try /help.");
});
```

## Replying & sending

```php
$bot->reply('Plain text');

$bot->reply('With extra Bot API params', [
    'disable_notification' => true,
    'reply_parameters'     => ['message_id' => $bot->message()->messageId],
]);

$bot->randomReply(['Hi!', 'Hello!', 'Hey there!']);
```

`reply()` sends to the chat of the current message — and to the same **forum topic** when the message came from one.

### Formatting

```php
use Siberfx\TelegramBot\Outgoing\OutgoingMessage;

$bot->reply(OutgoingMessage::create('<b>Bold</b> and <i>italic</i>')->html());
$bot->reply(OutgoingMessage::create('*Bold* and _italic_')->markdown());   // MarkdownV2

$bot->reply(
    OutgoingMessage::create('Quiet, protected, no preview: https://laravel.com')
        ->silent()            // no notification sound
        ->protect()           // no forwarding / saving
        ->withoutPreview()    // no link preview
        ->replyTo($bot->message()->messageId)
        ->with(['message_effect_id' => '5104841245755180586'])   // any extra parameter
);
```

Set `TELEGRAM_PARSE_MODE=HTML` to use HTML everywhere by default.

### Sending to any chat

`say()` works anywhere — controllers, jobs, listeners, notifications, tinker:

```php
use Siberfx\TelegramBot\Facades\Telegram;

Telegram::say('Deployment finished ✅', $chatId);
Telegram::say('Maintenance tonight at 22:00', [$chat1, $chat2, $chat3]);
Telegram::say(OutgoingMessage::create('<b>New order</b> #1042')->html(), config('telegram.orders_chat'));

telegram()->say('Same thing via the helper', $chatId);
```

### Editing & deleting

```php
$bot->editMessage('Updated text');                       // the current update's message
$bot->editMessage('Updated', messageId: 123, chatId: $chatId);
$bot->editMessage(OutgoingMessage::create('Done')->keyboard(Keyboard::inline()->row(Button::create('Undo')->value('undo'))));

$sent = $bot->reply('Processing…');                      // returns the sent Message array
// ... work ...
$bot->editMessage('Finished!', $sent['message_id']);

$bot->deleteMessage();                                   // the current message
$bot->deleteMessage($sent['message_id']);
```

### Typing indicators

```php
use Siberfx\TelegramBot\Enums\ChatAction;

$bot->types();                                   // "typing…"
$bot->types(ChatAction::UploadPhoto);            // "sending photo…"
$bot->typesAndWaits(1.5);                        // show typing, then wait 1.5 s
```

### Raw Bot API access

Every Bot API method is available — through the [sending middleware](#middleware):

```php
$bot->api()->sendPoll([
    'chat_id'  => $bot->chatId(),
    'question' => 'Pizza tonight?',
    'options'  => [['text' => 'Yes'], ['text' => 'No']],
]);

$bot->api()->sendDice(['chat_id' => $bot->chatId(), 'emoji' => '🎲']);
$bot->api()->pinChatMessage(['chat_id' => $bot->chatId(), 'message_id' => 42]);

$bot->sendRequest('setChatTitle', ['chat_id' => $chatId, 'title' => 'Laravel Devs']);

$me = Telegram::me();                     // getMe
```

Errors throw `Siberfx\TelegramBot\Exceptions\TelegramApiException` with `errorCode`, `description`, `retryAfter()` and `migratedToChatId()`.

## Keyboards & questions

### Questions (inline buttons)

```php
use Siberfx\TelegramBot\Outgoing\Button;
use Siberfx\TelegramBot\Outgoing\Question;

$bot->reply(
    Question::create('Rate our service')
        ->addButtons([
            Button::create('⭐')->value('rate:1'),
            Button::create('⭐⭐')->value('rate:2'),
            Button::create('⭐⭐⭐')->value('rate:3'),
        ])
        ->columns(3)
);
```

### Inline keyboards

```php
use Siberfx\TelegramBot\Outgoing\Keyboard;

$keyboard = Keyboard::inline()
    ->row(Button::create('✅ Approve')->value('approve:15'), Button::create('❌ Reject')->value('reject:15'))
    ->row(Button::create('🌐 Open in browser')->url('https://example.com/requests/15'))
    ->row(Button::create('🚀 Open app')->webApp('https://example.com/app'))
    ->row(Button::create('🔗 Share')->switchInlineQuery('request 15'))
    ->row(Button::create('📋 Copy code')->copyText('PROMO-2026'));

$bot->reply(OutgoingMessage::create('Request #15')->keyboard($keyboard));
```

### Reply keyboards

```php
$bot->reply(OutgoingMessage::create('Choose an option')->keyboard(
    Keyboard::reply()
        ->row(Button::create('📱 Share phone')->requestContact(), Button::create('📍 Share location')->requestLocation())
        ->buttons([Button::create('Help'), Button::create('Settings'), Button::create('About')], columns: 3)
        ->resize()
        ->oneTime()
        ->placeholder('Pick something…')
));

$bot->reply('Keyboard removed', ['reply_markup' => Keyboard::remove()]);
$bot->reply('What is your email?', ['reply_markup' => Keyboard::forceReply('you@example.com')]);
```

| Button method | Purpose |
|---|---|
| `value($data)` | callback data (inline). Buttons without an action use their label as data. |
| `url($url)` | open a link |
| `webApp($url)` | open a Telegram Web App |
| `loginUrl($url)` | Telegram Login |
| `switchInlineQuery($q, currentChat: false)` | start inline mode |
| `copyText($text)` | copy text to clipboard |
| `pay()` | payment button (invoices) |
| `requestContact()` / `requestLocation()` | reply keyboards only |

## Conversations

Conversations are multi-step dialogs that remember state between messages.

```bash
php artisan make:telegram-conversation OrderPizzaConversation
```

```php
namespace App\Telegram\Conversations;

use App\Models\Order;
use Siberfx\TelegramBot\Conversations\Conversation;
use Siberfx\TelegramBot\Incoming\Answer;
use Siberfx\TelegramBot\Incoming\IncomingMessage;
use Siberfx\TelegramBot\Outgoing\Button;
use Siberfx\TelegramBot\Outgoing\Question;

final class OrderPizzaConversation extends Conversation
{
    protected string $size = '';
    protected string $address = '';

    public function run(): void
    {
        $this->askSize();
    }

    public function askSize(): void
    {
        $question = Question::create('Which size? 🍕')
            ->addButtons([
                Button::create('Small')->value('S'),
                Button::create('Medium')->value('M'),
                Button::create('Large')->value('L'),
            ])
            ->columns(3);

        // A closure: $this is the conversation; properties survive between messages.
        $this->ask($question, function (Answer $answer) {
            if (! $answer->isInteractive()) {
                return $this->repeat('Please use the buttons 👇');
            }

            $this->size = $answer->value();      // "M"
            $this->say("{$answer->text()} it is!");  // "Medium it is!"
            $this->askAddress();
        });
    }

    public function askAddress(): void
    {
        // A method name: no closure serialization at all.
        $this->ask('Where should we deliver it?', 'confirm');
    }

    public function confirm(Answer $answer): void
    {
        $this->address = $answer->text();

        $question = Question::create("Size {$this->size} to {$this->address}. Confirm?")
            ->addButtons([Button::create('✅ Yes')->value('yes'), Button::create('❌ No')->value('no')])
            ->columns(2);

        // Pattern callbacks: the first matching pattern wins, unknown answers repeat the question.
        $this->ask($question, [
            ['pattern' => 'yes|yep|sure', 'callback' => 'placeOrder'],
            ['pattern' => 'no|nope',      'callback' => fn () => $this->say('Order cancelled.')],
        ]);
    }

    public function placeOrder(): void
    {
        $order = Order::create([
            'telegram_user_id' => $this->bot()->user()->id,
            'size' => $this->size,
            'address' => $this->address,
        ]);

        $this->types()->say("🎉 Order #{$order->id} placed!");
    }

    // Typing /cancel ends the conversation at any step.
    public function stopsConversation(IncomingMessage $message): bool
    {
        return $message->text === '/cancel';
    }
}
```

```php
// routes/telegram.php
$bot->hears('/pizza', OrderPizzaConversation::class);
// or: $bot->hears('/pizza', fn (TelegramBot $bot) => $bot->startConversation(new OrderPizzaConversation));
```

### `ask()` — what can come next

| `$next` | Called with |
|---|---|
| `Closure` | `(Answer $answer, Conversation $conversation)`, bound to the conversation (`$this`) |
| `'methodName'` | `$this->methodName(Answer $answer)` — recommended, nothing to serialize |
| `[['pattern' => ..., 'callback' => ...], ...]` | the first entry whose pattern matches `value()` or `text()`; none → `repeat()` |

The `Answer` object: `text()` (typed text or the pressed button's **label**), `value()` (button value, otherwise the text), `isInteractive()`, `message()` (the full `IncomingMessage`).

### Leaving a conversation

```php
// A command that ends any running conversation, then runs normally
$bot->hears('/cancel', fn (TelegramBot $bot) => $bot->reply('Cancelled 👍'))->stopsConversation();

// A command that answers without interrupting the conversation
$bot->hears('/help', HelpHandler::class)->skipsConversation();
```

Inside a conversation you can override `stopsConversation(IncomingMessage $message)` and `skipsConversation(IncomingMessage $message)` too.

### Starting a conversation proactively

```php
// e.g. from a job: ask a customer for feedback a day after delivery
Telegram::startConversation(new FeedbackConversation($order->id), chatId: $order->customer->telegram_id);
```

### How state is stored

- One conversation per **chat + user** (two people in a group can each have their own).
- Stored in the cache configured by `cache.store` for `conversations.ttl` seconds.
- Properties must be serializable (scalars, arrays, enums, Eloquent models…). The bot instance is never stored.
- The payload is serialized to a string first, so it works with Laravel 13's `cache.serializable_classes = false` default.
- `$bot->hasConversation()` and `$bot->stopConversation()` check or clear it manually.

## Sending attachments

```php
use Siberfx\TelegramBot\Attachments\{Animation, Audio, Contact, Document, Image, Location, Sticker, Video, Voice};
use Siberfx\TelegramBot\Outgoing\OutgoingMessage;

// From a URL (Telegram downloads it)
$bot->reply(OutgoingMessage::create('A cute cat', Image::url('https://example.com/cat.jpg')));

// Upload a local file (multipart)
$bot->reply(OutgoingMessage::create('Your invoice', Document::path(storage_path('app/invoices/1042.pdf'))));

// Re-send a file that is already on Telegram's servers
$bot->reply(OutgoingMessage::create('', Video::id($fileId)));

$bot->reply(OutgoingMessage::create('Listen', Audio::url('https://example.com/song.mp3')));
$bot->reply(OutgoingMessage::create('', Voice::path(storage_path('app/voice.ogg'))));
$bot->reply(OutgoingMessage::create('', Animation::url('https://example.com/party.gif')));
$bot->reply(OutgoingMessage::create('', Sticker::id('CAACAgIAAxkBAAE...')));
$bot->reply(OutgoingMessage::create('', Location::create(41.0082, 28.9784)));
$bot->reply(OutgoingMessage::create('', Contact::create('+905551112233', 'Selim')));

// Captions support formatting and keyboards
$bot->reply(
    OutgoingMessage::create('<b>New arrival</b>', Image::url($product->image_url))
        ->html()
        ->keyboard(Keyboard::inline()->row(Button::create('Buy now')->url($product->url)))
);
```

## Downloading files

```php
$bot->receivesImages(function (TelegramBot $bot) {
    $image = $bot->message()->images()[0];   // the largest size

    $url      = $bot->fileUrl($image);                                   // download URL (calls getFile)
    $contents = $bot->download($image);                                  // raw contents
    $path     = $bot->downloadTo($image, storage_path("app/photos/{$image->fileUniqueId()}.jpg"));

    $bot->reply("Saved {$image->fileSize()} bytes, {$image->get('width')}×{$image->get('height')}");
});

$bot->receivesFiles(function (TelegramBot $bot) {
    $document = $bot->message()->documents()[0];

    Storage::put("uploads/{$document->fileName()}", $bot->download($document));
});
```

File attachments expose `fileId()`, `fileUniqueId()`, `fileName()`, `fileSize()`, `mimeType()` and `get($key)` for any raw field.

## The incoming message

```php
$message = $bot->message();     // Siberfx\TelegramBot\Incoming\IncomingMessage

$message->text;                 // text, or callback data for button presses
$message->caption;              // media caption
$message->chatId;
$message->senderId;
$message->messageId;
$message->threadId;             // forum topic id
$message->chat;                 // Chat: id, type (ChatType enum), title, username, isPrivate(), isGroup(), isChannel()
$message->sender;               // User: id, firstName, lastName, username, languageCode, isPremium, fullName(), mention()

$message->isCallback();
$message->isCommand();
$message->images(); $message->videos(); $message->audio(); $message->documents();
$message->voice(); $message->sticker(); $message->location(); $message->contact();
$message->attachments();        // all, or ->attachments(AttachmentType::Image)
$message->get('reply_to_message.text');   // any raw field (dot notation)
$message->extra('intent');      // data added by middleware

$bot->user();                   // shortcut for $message->sender
$bot->chatId();
$bot->update();                 // the raw Update (->type(), ->payload(), ->get('...'))
```

## Middleware

Middleware hooks into five points of the lifecycle. Implement one or more interfaces from `Siberfx\TelegramBot\Middleware`:

| Interface | Runs | Return |
|---|---|---|
| `Received` | for every incoming message, before anything else | `$next($message)`, or anything else to **drop** the update |
| `Captured` | when a message is the answer to a conversation question | `$next($message)` |
| `Matching` | while matching `hears()` patterns | `bool` |
| `Heard` | right before a matched handler runs | `$next($message)` |
| `Sending` | for every Bot API request made by the bot | `$next($request)` |

### Example: only allow whitelisted users

```php
namespace App\Telegram\Middleware;

use Closure;
use Siberfx\TelegramBot\Incoming\IncomingMessage;
use Siberfx\TelegramBot\Middleware\Received;
use Siberfx\TelegramBot\TelegramBot;

final class Whitelist implements Received
{
    public function received(IncomingMessage $message, Closure $next, TelegramBot $bot): mixed
    {
        if (! in_array($message->senderId, config('telegram.allowed_users'), true)) {
            return null;   // swallow the update silently
        }

        return $next($message);
    }
}
```

### Example: NLP intents with `Matching`

```php
final class DetectIntent implements Received, Matching
{
    public function __construct(private NlpClient $nlp) {}

    public function received(IncomingMessage $message, Closure $next, TelegramBot $bot): mixed
    {
        return $next($message->addExtra('intent', $this->nlp->intent($message->text)));
    }

    // When matching middleware exists, it decides; $regexMatched tells whether the pattern itself matched.
    public function matching(IncomingMessage $message, string $pattern, bool $regexMatched): bool
    {
        return $regexMatched || $message->extra('intent') === $pattern;
    }
}

$bot->hears('book_flight', BookFlightHandler::class)->middleware(DetectIntent::class);
```

### Example: rate limiting with `Heard`

```php
use Illuminate\Support\Facades\RateLimiter;

final class Throttle implements Heard
{
    public function heard(IncomingMessage $message, Closure $next, TelegramBot $bot): mixed
    {
        $key = 'telegram:'.$message->senderId;

        if (RateLimiter::tooManyAttempts($key, 10)) {
            return $bot->reply('Slow down a little 🙂');
        }

        RateLimiter::hit($key, 60);

        return $next($message);
    }
}
```

### Example: log all outgoing requests with `Sending`

```php
final class LogRequests implements Sending
{
    public function sending(array $request, Closure $next, TelegramBot $bot): mixed
    {
        $result = $next($request);
        Log::channel('telegram')->info($request['method'], $request['params']);

        return $result;
    }
}
```

### Registering middleware

```php
// Globally — config/telegram-bot.php
'middleware' => [App\Telegram\Middleware\Whitelist::class],

// In the routes file — add() registers every hook the class implements
$bot->middleware()->add(new DetectIntent(app(NlpClient::class)));
$bot->middleware()->received(Whitelist::class);
$bot->middleware()->sending(new LogRequests);

// Per handler (Matching and Heard middleware)
$bot->hears('/admin', AdminHandler::class)->middleware(Throttle::class);

// Per group
$bot->group(['middleware' => [Throttle::class]], fn (TelegramBot $bot) => ...);
```

## Storage

Persistent key/value storage scoped to a user, a chat or the whole bot (backed by your cache store):

```php
$bot->userStorage()->put('name', 'Selim');
$bot->userStorage()->put(['lang' => 'tr', 'settings.notifications' => true]);   // dot notation
$bot->userStorage()->get('name');                       // "Selim"
$bot->userStorage()->get('settings.notifications');     // true
$bot->userStorage()->has('lang');
$bot->userStorage()->forget('lang');
$bot->userStorage()->all();
$bot->userStorage()->flush();

$bot->chatStorage()->increment('messages');             // per chat
$bot->botStorage()->put('maintenance', false);          // global

Telegram::userStorage($telegramUserId)->get('name');    // from anywhere
```

Store scalars and arrays (Laravel 13 blocks unserializing objects from the cache by default).

## Events

### Telegram events

Listen to any **update type** or any **message field**. Handlers receive `(TelegramBot $bot, mixed $data, Update $update)`:

```php
$bot->on('new_chat_members', function (TelegramBot $bot, array $members) {
    $names = implode(', ', array_column($members, 'first_name'));
    $bot->reply("Welcome {$names}! Please read the /rules.");
});

$bot->on('left_chat_member', fn (TelegramBot $bot, array $member) => $bot->reply("Bye {$member['first_name']} 👋"));

$bot->on('inline_query', function (TelegramBot $bot, array $query) {
    $bot->api()->answerInlineQuery([
        'inline_query_id' => $query['id'],
        'results' => [[
            'type' => 'article',
            'id' => '1',
            'title' => 'Say hi',
            'input_message_content' => ['message_text' => "Hi {$query['query']}!"],
        ]],
    ]);
});

$bot->on('my_chat_member', fn (TelegramBot $bot, array $change) => logger('Bot status changed', $change));
$bot->on('edited_message', fn (TelegramBot $bot, array $message) => $bot->reply('I saw that edit 👀'));
$bot->on('successful_payment', PaymentReceivedHandler::class);
$bot->on(['chat_join_request', 'poll_answer'], SomeHandler::class);
```

### Laravel events

| Event | When |
|---|---|
| `Siberfx\TelegramBot\Events\UpdateReceived` | every update (`$event->bot`, `$event->update`) |
| `Siberfx\TelegramBot\Events\RequestSent` | every Bot API request (`method`, `params`, `result`) |

```php
Event::listen(UpdateReceived::class, fn (UpdateReceived $e) => Metrics::increment('telegram.updates'));
```

## Exceptions

```php
use Illuminate\Database\Eloquent\ModelNotFoundException;

$bot->exception(ModelNotFoundException::class, function (Throwable $e, TelegramBot $bot) {
    $bot->reply('I could not find that 🤷');
});

$bot->exception(Throwable::class, function (Throwable $e, TelegramBot $bot) {
    report($e);
    $bot->reply('Something went wrong, please try again later.');
});
```

Unhandled exceptions bubble up. In the webhook they are reported and a `200` is still returned, so Telegram does not keep retrying the update (`webhook.swallow_exceptions`).

## Tinker — chat in your terminal

```bash
php artisan telegram-bot:tinker
php artisan telegram-bot:tinker --bot=support --user=42 --group
```

Your real routes, conversations and middleware run against a **fake client** — nothing is sent to Telegram, and conversations/storage live in memory for the session.

```
  Telegram bot tinker — bot main
  Routes: /var/www/app/routes/telegram.php

You: /pizza
Bot Which size? 🍕
     #1 Small   #2 Medium   #3 Large
    type #N to press a button
You: #2
  (keyboard removed)
Bot Medium it is!
Bot Where should we deliver it?
You: Istiklal Cd. 1, Istanbul
Bot Size M to Istiklal Cd. 1, Istanbul. Confirm?
     #1 ✅ Yes   #2 ❌ No
You: #1
  … typing
Bot 🎉 Order #17 placed!
```

| Input | Sends |
|---|---|
| any text | a text message (`/start`, `hello`…) |
| `#N` | press button N of the last keyboard (inline or reply) |
| `:image [caption]` · `:video [caption]` · `:audio [caption]` · `:voice` | media |
| `:file [name]` | a document |
| `:sticker [emoji]` | a sticker |
| `:location <lat> <lng>` | a location |
| `:contact <phone> [name]` | a contact |
| `:callback <data>` | a callback query |
| `:raw <json>` | a raw update (`update_id` is added) |
| `:reset` | clear the running conversation |
| `:whoami` | show the fake user and chat |
| `exit` | quit |

Configure the fake user in `config('telegram-bot.tinker')`.

> In plain `php artisan tinker` the facade talks to the **real** API: `Telegram::me()`, `Telegram::say('hi', 123)`.

## Running the bot

### Long polling (development)

No public URL, no tunnel:

```bash
php artisan telegram-bot:poll --delete-webhook
php artisan telegram-bot:poll --bot=support --timeout=50
php artisan telegram-bot:poll --once     # fetch one batch and exit (handy in scripts)
```

```
 INFO  Polling @my_awesome_bot — press Ctrl+C to stop.
14:02:11 #827340012 message @selim /start
14:02:15 #827340013 callback_query @selim order:pizza
```

Telegram refuses `getUpdates` while a webhook is set, hence `--delete-webhook`.

### Webhook (production)

The package registers `POST /telegram/{bot}/webhook` (name: `telegram-bot.webhook`).

```bash
php artisan telegram-bot:webhook set                                  # TELEGRAM_WEBHOOK_URL or the package route
php artisan telegram-bot:webhook set --url=https://bot.example.com/telegram/main/webhook --drop-pending
php artisan telegram-bot:webhook info
php artisan telegram-bot:webhook delete
php artisan telegram-bot:commands                                     # sync the "/" menu from config
```

- Telegram only calls **HTTPS** URLs. Locally you can use `herd share`, `expose` or `ngrok` and pass `--url`.
- With `webhook_secret` set, requests without the right `X-Telegram-Bot-Api-Secret-Token` header get a `403`.
- The route has no session/CSRF middleware. Add your own via `webhook.middleware`.
- Unknown bot names return `404`, invalid payloads `400`.

Deploy script example:

```bash
php artisan config:cache
php artisan telegram-bot:webhook set
php artisan telegram-bot:commands
```

### Queued updates

Answer Telegram instantly and process updates in your queue workers:

```dotenv
TELEGRAM_QUEUE=true
TELEGRAM_QUEUE_CONNECTION=redis
TELEGRAM_QUEUE_NAME=telegram
```

```bash
php artisan queue:work redis --queue=telegram
```

Each update becomes a `Siberfx\TelegramBot\Jobs\ProcessUpdate` job with `$tries = 1` (replies must not be sent twice).

## Multiple bots

```php
// config/telegram-bot.php
'default' => 'main',

'bots' => [
    'main' => [
        'token'  => env('TELEGRAM_BOT_TOKEN'),
        'routes' => base_path('routes/telegram.php'),
        'webhook_secret' => env('TELEGRAM_WEBHOOK_SECRET'),
    ],
    'support' => [
        'token'  => env('SUPPORT_BOT_TOKEN'),
        'routes' => base_path('routes/telegram-support.php'),
        'webhook_secret' => env('SUPPORT_WEBHOOK_SECRET'),
        'commands' => ['ticket' => 'Open a support ticket'],
    ],
],
```

```php
Telegram::bot('support')->say('New ticket #88', $agentsChatId);
telegram('support')->setCommands();

public function __construct(private BotManager $bots) {}       // or inject the manager
$this->bots->bot('support')->me();

public function handle(TelegramBot $bot) {}                     // injecting TelegramBot gives the default bot
```

```bash
php artisan telegram-bot:webhook set --bot=support      # → /telegram/support/webhook
php artisan telegram-bot:poll --bot=support
php artisan telegram-bot:tinker --bot=support
```

## Testing

`Telegram::fake()` swaps the bot for one with an in-memory client and cache, loads your routes file and returns a fluent tester. Replies are asserted **in order**. The examples use [Pest](https://pestphp.com); the tester works the same inside PHPUnit test methods.

```php
use Siberfx\TelegramBot\Facades\Telegram;
use Siberfx\TelegramBot\TelegramBot;

test('start greets the user', function () {
    Telegram::fake()
        ->receives('/start')
        ->assertReply('Hello Test! 👋');
});

test('the pizza conversation', function () {
    Telegram::fake()
        ->receives('/pizza')
        ->assertQuestion('Which size? 🍕', ['Small', 'Medium', 'Large'])
        ->assertConversationActive()
        ->clicks('Medium')
        ->assertReplies(['Medium it is!', 'Where should we deliver it?'])
        ->receives('Istiklal Cd. 1')
        ->assertQuestion()
        ->clicks('✅ Yes')
        ->assertTyping()
        ->assertReplyMatches('/Order #\d+ placed/')
        ->assertNoConversation();

    expect(Order::count())->toBe(1);
});

test('cancel stops the conversation', function () {
    Telegram::fake()
        ->receives('/pizza')->assertQuestion()
        ->receives('/cancel')->assertNoConversation();
});

test('photos in groups', function () {
    Telegram::fake(setup: function (TelegramBot $bot) {         // define handlers inline, routes file is skipped
        $bot->receivesImages(fn (TelegramBot $bot) => $bot->reply('Nice!'))->groupsOnly();
    })
        ->receivesImages()->assertReplyNothing()                // private chat → ignored
        ->inGroup()
        ->setUser(['id' => 42, 'first_name' => 'Ada'])
        ->receivesImages('sunset')->assertReply('Nice!');
});

test('callback buttons', function () {
    Telegram::fake()
        ->receivesCallback('order:pizza')
        ->assertCallbackAnswered('Added pizza to your cart')
        ->assertSent('editMessageText', fn (array $params) => str_contains($params['text'], 'pizza'));
});

test('sending attachments', function () {
    Telegram::fake()
        ->receives('/invoice')
        ->assertReplyType('sendDocument', fn (array $params) => $params['caption'] === 'Your invoice');
});
```

Faking the API response of a method:

```php
$tester = Telegram::fake();
$tester->client->respondWith('getChatMemberCount', 1337);

$tester->receives('/members')->assertReply('We are 1337 members!');
```

| Receiving | Assertions |
|---|---|
| `receives($text)` | `assertReply($text)` · `assertReplies([...])` |
| `receivesImages($caption = null)` | `assertReplyIn([...])` · `assertReplyIsNot($text)` |
| `receivesVideos()` · `receivesAudio()` · `receivesVoice()` | `assertReplyContains($needle)` · `assertReplyMatches($regex)` |
| `receivesFiles($name)` · `receivesSticker($emoji)` | `assertReplyNothing()` |
| `receivesLocation($lat, $lng)` | `assertQuestion($text = null, $buttons = null)` |
| `receivesContact($phone, $name)` | `assertReplyType($method, $callback = null)` |
| `receivesCallback($data)` | `assertSent($method, $callback = null)` · `assertNotSent($method)` |
| `clicks($buttonLabel)` | `assertTyping()` · `assertCallbackAnswered($text = null)` |
| `receivesUpdate(array\|Update)` | `assertConversationActive()` · `assertNoConversation()` |
| `setUser([...])` · `setChat([...])` · `inGroup()` | `reply()` · `replies()` · `requests()` for custom checks |

Need raw updates? `$tester->updates` is an `UpdateFactory` (`text()`, `photo()`, `callback()`, `message([...])`, `make($type, $payload)`).

Webhook feature tests work too:

```php
$tester = Telegram::fake();

$this->postJson('/telegram/main/webhook', (new UpdateFactory)->text('/start')->toArray(), [
    'X-Telegram-Bot-Api-Secret-Token' => config('telegram-bot.bots.main.webhook_secret'),
])->assertOk();

$tester->assertSent('sendMessage');
```

## Recipes

### Deep linking (`t.me/my_bot?start=ref-42`)

```php
$bot->hears('/start {payload?}', function (TelegramBot $bot, ?string $payload = null) {
    if ($payload && str_starts_with($payload, 'ref-')) {
        Referral::record(substr($payload, 4), $bot->user()->id);
    }

    $bot->reply('Welcome!');
});
```

### Link Telegram accounts to your users

```php
// Web: show a link like https://t.me/my_bot?start=link-{token}
$bot->hears('/start link-{token}', function (TelegramBot $bot, string $token) {
    $user = User::where('telegram_link_token', $token)->first();

    if (! $user) {
        return $bot->reply('This link has expired.');
    }

    $user->update(['telegram_id' => $bot->user()->id, 'telegram_link_token' => null]);
    $bot->reply("Connected to {$user->email} ✅");
});
```

### Notify users from your app

```php
final class OrderShipped implements ShouldQueue
{
    use Queueable;

    public function __construct(public Order $order) {}

    public function handle(): void
    {
        Telegram::say(
            OutgoingMessage::create("📦 Order <b>#{$this->order->id}</b> has shipped!")
                ->html()
                ->keyboard(Keyboard::inline()->row(Button::create('Track')->url($this->order->tracking_url))),
            $this->order->user->telegram_id,
        );
    }
}
```

### Broadcast with flood control

```php
use Siberfx\TelegramBot\Exceptions\TelegramApiException;

User::whereNotNull('telegram_id')->lazy()->each(function (User $user) {
    try {
        Telegram::say('New feature is live 🚀', $user->telegram_id);
    } catch (TelegramApiException $e) {
        if ($e->retryAfter()) {
            sleep($e->retryAfter());
        } elseif ($e->errorCode === 403) {
            $user->update(['telegram_id' => null]);   // the user blocked the bot
        }
    }

    usleep(40_000);   // stay under ~30 messages/second
});
```

### Localized replies

```php
$bot->middleware()->received(new class implements Received {
    public function received(IncomingMessage $message, Closure $next, TelegramBot $bot): mixed
    {
        app()->setLocale($message->sender?->languageCode ?? 'en');

        return $next($message);
    }
});

$bot->hears('/start', fn (TelegramBot $bot) => $bot->reply(__('bot.welcome')));
```

### Admin-only commands

```php
$bot->group(['users' => config('telegram.admins'), 'chat_type' => 'private'], function (TelegramBot $bot) {
    $bot->hears('/stats', fn (TelegramBot $bot) => $bot->reply('Users: '.User::count()));
    $bot->hears('/maintenance {state}', function (TelegramBot $bot, string $state) {
        $bot->botStorage()->put('maintenance', $state === 'on');
        $bot->reply("Maintenance {$state}");
    });
});
```

### Paginated inline lists

```php
$bot->hears('products( page:{page})?', function (TelegramBot $bot, ?string $page = null) {
    $products = Product::paginate(5, page: (int) ($page ?? 1));

    $keyboard = Keyboard::inline()->buttons(
        $products->map(fn ($p) => Button::create($p->name)->value("product:{$p->id}"))->all()
    );

    $nav = array_filter([
        $products->onFirstPage() ? null : Button::create('⬅️')->value('products page:'.($products->currentPage() - 1)),
        $products->hasMorePages() ? Button::create('➡️')->value('products page:'.($products->currentPage() + 1)) : null,
    ]);

    if ($nav) {
        $keyboard->row(...$nav);
    }

    $message = OutgoingMessage::create("Products — page {$products->currentPage()}")->keyboard($keyboard);

    $bot->message()->isCallback() ? $bot->editMessage($message) : $bot->reply($message);
});
```

### Handling voice messages with AI transcription

```php
$bot->receivesVoice(function (TelegramBot $bot) {
    $bot->types();
    $audio = $bot->download($bot->message()->voice());
    $text  = app(Transcriber::class)->transcribe($audio);   // your speech-to-text service

    $bot->reply("You said: {$text}");
});
```

## Artisan commands

| Command | Description |
|---|---|
| `telegram-bot:install [--force]` | Publish config and create `routes/telegram.php` |
| `telegram-bot:tinker [--bot=] [--user=] [--group]` | Chat with your bot in the console (no Telegram calls) |
| `telegram-bot:poll [--bot=] [--timeout=30] [--once] [--delete-webhook]` | Run with long polling |
| `telegram-bot:webhook {set\|info\|delete} [--bot=] [--url=] [--drop-pending]` | Manage the webhook |
| `telegram-bot:commands [--bot=] [--list] [--delete]` | Sync the bot's command menu from config |
| `make:telegram-conversation {name}` | Generate `app/Telegram/Conversations/{Name}.php` |

## Coming from BotMan

The concepts map one-to-one; the API is just Telegram-specific and typed.

| BotMan | siberfx/telegram-bot |
|---|---|
| `$botman->hears('x', fn (BotMan $bot) …)` | `$bot->hears('x', fn (TelegramBot $bot) …)` |
| `routes/botman.php` | `routes/telegram.php` |
| `$botman->listen()` | automatic (webhook / poll / tinker) |
| `$bot->reply()` · `say()` · `randomReply()` · `types()` | same |
| `$bot->getUser()->getFirstName()` | `$bot->user()->firstName` |
| `$bot->getMessage()->getText()` | `$bot->message()->text` |
| `$bot->getMessage()->getImages()` | `$bot->message()->images()` |
| `$answer->getText()` / `getValue()` / `isInteractiveMessageReply()` | `$answer->text()` / `value()` / `isInteractive()` |
| `Question::create()->addButtons([Button::create()->value()])` | same |
| `$this->ask($q, function (Answer $a) {…})` | same, plus method names & pattern arrays |
| `$botman->middleware->received(...)` | `$bot->middleware()->received(...)` |
| `$bot->userStorage()->save([...])` / `find()` | `$bot->userStorage()->put([...])` / `all()` |
| `$botman->on('event', …)` | `$bot->on('event', …)` (any update type or message field) |
| `$botman->exception(...)` | `$bot->exception(...)` |
| `BotManTester` (`$this->bot->receives()->assertReply()`) | `Telegram::fake()->receives()->assertReply()` |
| `php artisan botman:tinker` | `php artisan telegram-bot:tinker` |
| drivers, `loadDriver()`, `sendRequest()` | Telegram only — `$bot->api()->anyMethod([...])`, `sendRequest()` |

## Security

- Always set `webhook_secret` in production — the webhook rejects calls without the matching header.
- The bot token never appears in exception messages; keep it out of logs and version control.
- Validate user input like any other request: `hears()` parameters are raw user text.
- Use `->fromUsers()` / groups for admin features; Telegram user ids cannot be spoofed via the webhook when the secret is set.
- Conversations and storage live in your cache — use a private, persistent store.

If you discover a security issue, please email **info@siberfx.com** instead of opening an issue.

## Troubleshooting

| Problem | Solution |
|---|---|
| `Conflict: can't use getUpdates method while webhook is active` | `php artisan telegram-bot:poll --delete-webhook` |
| Webhook gets no updates | `php artisan telegram-bot:webhook info` shows `last_error_message`. Check HTTPS, the secret, and that the route isn't behind auth middleware. |
| `403` on the webhook | The `webhook_secret` changed — run `telegram-bot:webhook set` again. |
| Buttons keep spinning | Your handler threw before finishing, or `callbacks.auto_answer` is disabled. |
| Conversation forgets its answers | You're using the `array` cache store in production, or the conversation expired (`conversations.ttl`). |
| Handlers not found | Check `bots.*.routes` and run `php artisan config:clear`. |
| Commands work in private but not in groups | Disable privacy mode in @BotFather, or address the bot with `/cmd@your_bot`. |
| `Too Many Requests: retry after N` | Respect `$e->retryAfter()`; keep broadcasts under ~30 msg/s. |

## Changelog

See [CHANGELOG.md](CHANGELOG.md).

## Contributing

```bash
composer install
composer test        # Pest
composer lint        # Pint
```

The package's own suite is written in [Pest 4](https://pestphp.com) on top of Orchestra Testbench (`tests/Pest.php` binds the Testbench `TestCase` to `tests/Feature`). Pull requests are welcome — please add a Pest test for new behaviour.

## License

The MIT License (MIT). See [LICENSE.md](LICENSE.md).
