# siberfx/telegram-bot

A clean, modern Telegram bot framework for **Laravel 13** and **PHP 8.4+**.

Everything you know from BotMan — `hears()`, conversations, questions with buttons, attachments, middleware, storage, a console tinker and a testing API — rebuilt Telegram‑first with a smaller, typed API.

```php
$bot->hears('call me {name}', function (TelegramBot $bot, string $name) {
    $bot->userStorage()->put('name', $name);
    $bot->reply("Hello {$name}!");
});
```

- [Installation](#installation)
- [Listening](#listening)
- [Replying](#replying)
- [Keyboards & questions](#keyboards--questions)
- [Conversations](#conversations)
- [Attachments & files](#attachments--files)
- [Middleware](#middleware)
- [Storage](#storage)
- [Events & exceptions](#events--exceptions)
- [Tinker](#tinker)
- [Running the bot](#running-the-bot-polling--webhook)
- [Testing](#testing)
- [Multiple bots](#multiple-bots)
- [Coming from BotMan](#coming-from-botman)

## Installation

```bash
composer require siberfx/telegram-bot
php artisan telegram-bot:install
```

The install command publishes `config/telegram-bot.php` and creates `routes/telegram.php`. Add your token:

```dotenv
TELEGRAM_BOT_TOKEN=123456:ABC-your-token
TELEGRAM_BOT_USERNAME=my_bot
TELEGRAM_WEBHOOK_SECRET=a-long-random-string
```

Then talk to your bot right away — without Telegram:

```bash
php artisan telegram-bot:tinker
```

## Listening

Handlers live in `routes/telegram.php`, where `$bot` is the `TelegramBot` instance.

```php
use Siberfx\TelegramBot\TelegramBot;

$bot->hears('hello', fn (TelegramBot $bot) => $bot->reply('Hi!'));

// Parameters are passed positionally after $bot. "?" makes them optional.
$bot->hears('/start {payload?}', function (TelegramBot $bot, ?string $payload = null) { /* deep link */ });
$bot->hears('{a} plus {b}', fn (TelegramBot $bot, string $a, string $b) => $bot->reply($a + $b));

// Patterns are case-insensitive regular expressions matched against the whole message.
$bot->hears(['hi', 'hey', '.*bonjour.*'], fn (TelegramBot $bot) => $bot->reply('👋'));

// Handlers can be closures, invokable classes, "Class@method", [Class::class, 'method'] or a Conversation class.
$bot->hears('/order', OrderConversation::class);
$bot->hears('/report', ReportHandler::class);

$bot->fallback(fn (TelegramBot $bot) => $bot->reply('Sorry, I did not get that.'));
```

`/start@my_bot` is normalized to `/start`, so commands work in groups too. Callback data from inline buttons is matched by `hears()` as well:

```php
$bot->hears('like:{id}', fn (TelegramBot $bot, string $id) => $bot->answerCallback("Liked #{$id}"));
```

### Filters & groups

```php
$bot->hears('/admin', AdminHandler::class)
    ->privateOnly()              // or ->groupsOnly(), ->in(ChatType::Supergroup)
    ->fromUsers(1234567)
    ->forChats(-100987654321)
    ->middleware(new IsAdmin);

$bot->group(['chat_type' => 'private', 'middleware' => [Throttle::class]], function (TelegramBot $bot) {
    $bot->hears('/settings', SettingsHandler::class);
});
```

## Replying

```php
$bot->reply('Plain text');
$bot->reply(OutgoingMessage::create('<b>Bold</b>')->html()->silent()->withoutPreview());
$bot->randomReply(['Hi!', 'Hello!', 'Hey!']);

$bot->types();                 // "typing…"
$bot->typesAndWaits(1.5);

$bot->say('Server is back up', $chatId);          // anywhere in your app, no incoming message needed
Telegram::say('Maintenance at 22:00', [$id1, $id2]);

$bot->editMessage('Updated text');                 // edits the message of the current update (e.g. the pressed button's message)
$bot->deleteMessage();
$bot->answerCallback('Saved!', alert: true);       // callbacks are auto-answered when you don't

// Every Bot API method, through the sending middleware:
$bot->api()->sendPoll(['chat_id' => $bot->chatId(), 'question' => 'Lunch?', 'options' => [['text' => 'Yes'], ['text' => 'No']]]);
$bot->sendRequest('setChatTitle', ['chat_id' => $id, 'title' => 'New title']);
```

Replies in forum topics automatically go to the same topic. Set `TELEGRAM_PARSE_MODE=HTML` to change the default parse mode.

## Keyboards & questions

```php
use Siberfx\TelegramBot\Outgoing\{Button, Keyboard, OutgoingMessage, Question};

$bot->reply(Question::create('Pick one')
    ->addButtons([
        Button::create('🍕 Pizza')->value('food:pizza'),
        Button::create('🍣 Sushi')->value('food:sushi'),
        Button::create('Menu')->url('https://example.com/menu'),
    ])
    ->columns(2));

$bot->reply(OutgoingMessage::create('Share your number?')->keyboard(
    Keyboard::reply()
        ->row(Button::create('📱 Share')->requestContact(), Button::create('📍 Location')->requestLocation())
        ->resize()->oneTime()
));

$bot->reply('Keyboard gone', ['reply_markup' => Keyboard::remove()]);
```

`Button` also supports `webApp()`, `loginUrl()`, `switchInlineQuery()`, `copyText()` and `pay()`.

## Conversations

```bash
php artisan make:telegram-conversation OnboardingConversation
```

```php
use Siberfx\TelegramBot\Conversations\Conversation;
use Siberfx\TelegramBot\Incoming\Answer;

final class OnboardingConversation extends Conversation
{
    protected string $name = '';

    public function run(): void
    {
        // A closure: $this is the conversation, properties survive between messages.
        $this->ask('What is your name?', function (Answer $answer) {
            $this->name = $answer->text();
            $this->askColor();
        });
    }

    public function askColor(): void
    {
        $question = Question::create("Hi {$this->name}, pick a color")
            ->addButtons([Button::create('Red')->value('red'), Button::create('Blue')->value('blue')]);

        // Pattern callbacks; unknown answers repeat the question.
        $this->ask($question, [
            ['pattern' => 'red', 'callback' => 'finish'],           // a method name — no closure serialization
            ['pattern' => 'blue', 'callback' => fn () => $this->repeat('Blue is sold out, try again')],
        ]);
    }

    public function finish(Answer $answer): void
    {
        $this->say("{$this->name} likes {$answer->text()}");   // text() = button label, value() = "red"
    }

    public function stopsConversation(IncomingMessage $message): bool
    {
        return $message->text === '/cancel';
    }
}
```

```php
$bot->hears('/onboard', OnboardingConversation::class);
$bot->hears('/cancel', fn ($bot) => $bot->reply('Cancelled'))->stopsConversation();
$bot->hears('/help', HelpHandler::class)->skipsConversation();   // answers without breaking the flow

Telegram::startConversation(new SurveyConversation, chatId: $user->telegram_id); // proactively
```

Conversations are stored per chat and user in the cache (`conversations.ttl`, default 30 minutes). The payload is serialized to a string, so it works with Laravel 13's `cache.serializable_classes = false` hardening. When a button is pressed, the inline keyboard is removed (`conversations.remove_keyboard_on_answer`).

## Attachments & files

```php
$bot->receivesImages(function (TelegramBot $bot) {
    $image = $bot->message()->images()[0];            // largest size
    $bot->downloadTo($image, storage_path("app/{$image->fileUniqueId()}.jpg"));
});

$bot->receivesVideos(...);  $bot->receivesAudio(...);   $bot->receivesVoice(...);
$bot->receivesFiles(...);   $bot->receivesStickers(...); $bot->receivesAttachments(...);
$bot->receivesLocation(fn ($bot) => $bot->reply($bot->message()->location()->latitude));
$bot->receivesContact(fn ($bot) => $bot->reply($bot->message()->contact()->phoneNumber));
```

Sending attachments:

```php
use Siberfx\TelegramBot\Attachments\{Image, Document, Video, Location, Contact};

$bot->reply(OutgoingMessage::create('A cat', Image::url('https://example.com/cat.jpg')));
$bot->reply(OutgoingMessage::create('Your invoice', Document::path(storage_path('invoice.pdf'))));   // uploaded
$bot->reply(OutgoingMessage::create('', Image::id($fileId)));                                        // re-send
$bot->reply(OutgoingMessage::create('', Location::create(41.0082, 28.9784)));
```

`$bot->fileUrl($attachment)` returns the download URL and `$bot->download($attachment)` returns the contents.

## Middleware

Implement one or more interfaces from `Siberfx\TelegramBot\Middleware`:

| Interface   | Runs                                              | Signature |
|-------------|---------------------------------------------------|-----------|
| `Received`  | for every incoming message                        | `received(IncomingMessage $message, Closure $next, TelegramBot $bot)` |
| `Captured`  | when a message answers a conversation question    | `captured(IncomingMessage $message, Closure $next, TelegramBot $bot)` |
| `Matching`  | to decide whether a pattern matches               | `matching(IncomingMessage $message, string $pattern, bool $regexMatched): bool` |
| `Heard`     | right before a matched handler                    | `heard(IncomingMessage $message, Closure $next, TelegramBot $bot)` |
| `Sending`   | for every Bot API request                         | `sending(array $request, Closure $next, TelegramBot $bot)` |

```php
final class DetectIntent implements Received, Matching
{
    public function received(IncomingMessage $message, Closure $next, TelegramBot $bot): mixed
    {
        return $next($message->addExtra('intent', Nlp::intent($message->text)));   // return null to drop the update
    }

    public function matching(IncomingMessage $message, string $pattern, bool $regexMatched): bool
    {
        return $regexMatched || $message->extra('intent') === $pattern;
    }
}

$bot->middleware()->add(new DetectIntent);        // registers every hook it implements
$bot->hears('book_flight', BookFlight::class)->middleware(OnlyVerifiedUsers::class);
```

Global middleware can also be listed in `config('telegram-bot.middleware')`.

## Storage

```php
$bot->userStorage()->put('name', 'Selim');
$bot->userStorage()->get('name');
$bot->chatStorage()->increment('messages');
$bot->botStorage()->put(['version' => 2, 'settings.lang' => 'tr']);
$bot->userStorage($userId)->forget('name');
```

## Events & exceptions

```php
// Update types and message fields
$bot->on('new_chat_members', fn (TelegramBot $bot, array $members) => $bot->reply('Welcome!'));
$bot->on('inline_query', fn (TelegramBot $bot, array $query) => $bot->api()->answerInlineQuery([...]));
$bot->on(['my_chat_member', 'chat_join_request'], ChatMembershipHandler::class);

$bot->exception(ModelNotFoundException::class, fn (Throwable $e, TelegramBot $bot) => $bot->reply('Not found.'));
```

Laravel events `Siberfx\TelegramBot\Events\UpdateReceived` and `RequestSent` are dispatched for every update and API request.

## Tinker

```bash
php artisan telegram-bot:tinker            # --bot=, --user=, --group
```

```
You: /menu
Bot Pick one
     #1 🍕 Pizza   #2 🍣 Sushi
     #3 Menu
    type #N to press a button
You: #1
  💬 toast: You picked pizza
Bot (edited) Enjoy your pizza!
```

Your real routes run against a fake client — nothing reaches Telegram. Besides text you can send `#N` (press a button), `:image`, `:video`, `:voice`, `:file`, `:sticker`, `:location 41.0 29.0`, `:contact +90555… Name`, `:callback data`, `:raw {json}`, `:reset` and `:whoami`.

Inside `php artisan tinker` the facade works as usual: `Telegram::say('hi', 123)`, `Telegram::me()`.

## Running the bot (polling & webhook)

**Local development — long polling, no public URL needed:**

```bash
php artisan telegram-bot:poll --delete-webhook
```

**Production — webhook:**

```bash
php artisan telegram-bot:webhook set          # uses TELEGRAM_WEBHOOK_URL or route('telegram-bot.webhook')
php artisan telegram-bot:webhook info
php artisan telegram-bot:webhook delete --drop-pending
php artisan telegram-bot:commands             # sync the "/" menu from config
```

The webhook is served at `POST /telegram/{bot}/webhook` and verifies Telegram's `X-Telegram-Bot-Api-Secret-Token` header against `webhook_secret`. Set `TELEGRAM_QUEUE=true` to answer immediately and handle updates in a queued `ProcessUpdate` job. Handler exceptions are reported but still return 200, so Telegram does not retry the same update forever (`webhook.swallow_exceptions`).

## Testing

```php
use Siberfx\TelegramBot\Facades\Telegram;

it('greets', function () {
    Telegram::fake()                               // loads routes/telegram.php against a fake client
        ->receives('/start')
        ->assertReply('Hello Test! Type /help to see what I can do.')
        ->receives('call me Selim')
        ->assertReply('Okay, I will call you Selim.');
});

it('runs the onboarding conversation', function () {
    Telegram::fake()
        ->receives('/onboard')->assertReply('What is your name?')->assertConversationActive()
        ->receives('Selim')->assertQuestion('Hi Selim, pick a color', ['Red', 'Blue'])
        ->clicks('Red')->assertReply('Selim likes Red')
        ->assertNoConversation();
});

it('handles photos in groups', function () {
    Telegram::fake(setup: fn (TelegramBot $bot) => $bot->receivesImages(fn ($bot) => $bot->reply('Nice!')))
        ->inGroup()
        ->setUser(['id' => 42, 'first_name' => 'Ada'])
        ->receivesImages('caption')
        ->assertReply('Nice!');
});
```

Receiving: `receives`, `receivesImages`, `receivesVideos`, `receivesAudio`, `receivesVoice`, `receivesFiles`, `receivesSticker`, `receivesLocation`, `receivesContact`, `receivesCallback`, `clicks`, `receivesUpdate`.
Assertions: `assertReply`, `assertReplies`, `assertReplyIn`, `assertReplyContains`, `assertReplyMatches`, `assertReplyIsNot`, `assertReplyNothing`, `assertQuestion`, `assertReplyType`, `assertSent`, `assertNotSent`, `assertTyping`, `assertCallbackAnswered`, `assertConversationActive`, `assertNoConversation`.

## Multiple bots

```php
'bots' => [
    'main'    => ['token' => env('TELEGRAM_BOT_TOKEN'), 'routes' => base_path('routes/telegram.php')],
    'support' => ['token' => env('SUPPORT_BOT_TOKEN'), 'routes' => base_path('routes/support-bot.php')],
],
```

```php
Telegram::bot('support')->say('New ticket', $agentChatId);
telegram('support')->setCommands();
```

```bash
php artisan telegram-bot:webhook set --bot=support
php artisan telegram-bot:tinker --bot=support
```

## Coming from BotMan

| BotMan                                   | siberfx/telegram-bot                         |
|------------------------------------------|----------------------------------------------|
| `$botman->hears('x', fn (BotMan $b) …)`  | `$bot->hears('x', fn (TelegramBot $b) …)`    |
| `$answer->getText()` / `getValue()`      | `$answer->text()` / `value()`                |
| `$bot->getUser()->getFirstName()`        | `$bot->user()->firstName`                    |
| `$bot->getMessage()->getImages()`        | `$bot->message()->images()`                  |
| `$botman->middleware->received(...)`     | `$bot->middleware()->received(...)`          |
| `$bot->userStorage()->save([...])`       | `$bot->userStorage()->put([...])`            |
| `BotManTester`                           | `Telegram::fake()`                           |
| `php artisan botman:tinker`              | `php artisan telegram-bot:tinker`            |
| drivers, `loadDriver()`                  | Telegram only — every Bot API method via `$bot->api()` |

## License

MIT
