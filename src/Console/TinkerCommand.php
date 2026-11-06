<?php

declare(strict_types=1);

namespace Siberfx\TelegramBot\Console;

use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Illuminate\Console\Command;
use JsonException;
use Siberfx\TelegramBot\Api\FakeClient;
use Siberfx\TelegramBot\BotManager;
use Siberfx\TelegramBot\Testing\BotTester;
use Siberfx\TelegramBot\Testing\UpdateFactory;
use Symfony\Component\Console\Attribute\AsCommand;
use Throwable;

/**
 * Chat with your bot in the terminal. Nothing is sent to Telegram: your
 * routes run against a fake client and every request is rendered here.
 */
#[AsCommand(name: 'telegram-bot:tinker')]
final class TinkerCommand extends Command
{
    protected $signature = 'telegram-bot:tinker
        {--bot= : The bot to tinker with (defaults to the default bot)}
        {--user= : Fake user id}
        {--group : Simulate a supergroup chat}';

    protected $description = 'Chat with your Telegram bot in the console without contacting Telegram';

    /** @var list<array{type: string, text: string, data: ?string, url: ?string}> */
    private array $buttons = [];

    public function handle(BotManager $manager): int
    {
        $name = (string) ($this->option('bot') ?: $manager->getDefaultBot());

        if (! $manager->has($name)) {
            $this->components->error("Telegram bot [{$name}] is not configured.");

            return self::FAILURE;
        }

        $config = (array) config('telegram-bot.tinker', []);
        $user = (array) ($config['user'] ?? []);

        if ($this->option('user')) {
            $user['id'] = (int) $this->option('user');
        }

        $updates = new UpdateFactory($user);
        $updates->withChat(['id' => $this->option('user') ? $user['id'] : ($config['chat_id'] ?? $updates->user()['id'])]);

        if ($this->option('group')) {
            $updates->withChat(['id' => -100100000001, 'type' => 'supergroup', 'title' => 'Tinker group', 'first_name' => null, 'username' => null]);
        }

        $client = new FakeClient;
        $bot = $manager->make($name, $client, new Repository(new ArrayStore));
        $tester = new BotTester($bot, $client, $updates);

        $routes = (string) $bot->config('routes', '');
        $this->intro($name, $routes, is_file($routes));

        while (true) {
            $input = $this->ask('<fg=green>You</>');

            if ($input === null) {
                continue;
            }

            $input = trim((string) $input);

            if (in_array($input, ['exit', 'quit', ':q', ':quit', ':exit'], true)) {
                $this->line('<fg=gray>Bye 👋</>');

                return self::SUCCESS;
            }

            try {
                if (! $this->dispatch($input, $tester, $updates)) {
                    continue;
                }
            } catch (Throwable $e) {
                $this->renderException($e);

                continue;
            }

            $this->render($tester->requests());
        }
    }

    /**
     * Turn the console input into an update. Returns false when no update was sent.
     */
    private function dispatch(string $input, BotTester $tester, UpdateFactory $updates): bool
    {
        if (preg_match('/^#(\d+)$/', $input, $m) === 1) {
            return $this->press((int) $m[1], $tester);
        }

        if (! str_starts_with($input, ':')) {
            $tester->receives($input);

            return true;
        }

        [$command, $argument] = array_pad(explode(' ', substr($input, 1), 2), 2, null);
        $argument = $argument !== null ? trim($argument) : null;

        switch ($command) {
            case 'help':
                $this->help();

                return false;
            case 'image':
            case 'photo':
                $tester->receivesImages($argument);
                break;
            case 'video':
                $tester->receivesVideos($argument);
                break;
            case 'audio':
                $tester->receivesAudio($argument);
                break;
            case 'voice':
                $tester->receivesVoice();
                break;
            case 'file':
            case 'document':
                $tester->receivesFiles($argument ?: 'document.pdf');
                break;
            case 'sticker':
                $tester->receivesSticker($argument ?: '👍');
                break;
            case 'location':
                [$lat, $lng] = array_pad(preg_split('/[\s,]+/', (string) $argument) ?: [], 2, null);
                $tester->receivesLocation((float) ($lat ?? 41.0082), (float) ($lng ?? 28.9784));
                break;
            case 'contact':
                [$phone, $first] = array_pad(explode(' ', (string) $argument, 2), 2, null);
                $tester->receivesContact($phone ?: '+905550000000', $first ?: 'Tinker');
                break;
            case 'callback':
                $tester->receivesCallback((string) $argument);
                break;
            case 'raw':
                $tester->receivesUpdate($this->rawUpdate((string) $argument));
                break;
            case 'reset':
                $tester->bot->stopConversation($updates->chat()['id'], $updates->user()['id']);
                $this->buttons = [];
                $this->line('<fg=gray>Conversation cleared.</>');

                return false;
            case 'whoami':
                $this->line('<fg=gray>user: '.json_encode($updates->user(), JSON_UNESCAPED_UNICODE).'</>');
                $this->line('<fg=gray>chat: '.json_encode($updates->chat(), JSON_UNESCAPED_UNICODE).'</>');

                return false;
            default:
                $this->line("<fg=red>Unknown command [:{$command}]. Type :help.</>");

                return false;
        }

        return true;
    }

    private function press(int $number, BotTester $tester): bool
    {
        $button = $this->buttons[$number - 1] ?? null;

        if ($button === null) {
            $this->line("<fg=red>There is no button #{$number}.</>");

            return false;
        }

        if ($button['type'] === 'reply') {
            $tester->receives($button['text']);

            return true;
        }

        if ($button['data'] === null) {
            $this->line('<fg=gray>This button opens '.($button['url'] ?? 'an external action').'.</>');

            return false;
        }

        $tester->receivesCallback($button['data']);

        return true;
    }

    /** @return array<string, mixed> */
    private function rawUpdate(string $json): array
    {
        try {
            $data = (array) json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new \InvalidArgumentException('Invalid JSON: '.$e->getMessage());
        }

        return $data + ['update_id' => random_int(1000, 999999)];
    }

    /** @param list<array{method: string, params: array<string, mixed>}> $requests */
    private function render(array $requests): void
    {
        $replied = false;

        foreach ($requests as ['method' => $method, 'params' => $params]) {
            $replied = true;

            match ($method) {
                'sendMessage' => $this->botLine((string) ($params['text'] ?? '')),
                'editMessageText' => $this->botLine((string) ($params['text'] ?? ''), '(edited) '),
                'editMessageCaption' => $this->botLine((string) ($params['caption'] ?? ''), '(caption edited) '),
                'sendPhoto', 'sendVideo', 'sendAudio', 'sendVoice', 'sendDocument', 'sendAnimation', 'sendSticker' => $this->mediaLine($method, $params),
                'sendLocation' => $this->botLine("📍 {$params['latitude']}, {$params['longitude']}"),
                'sendContact' => $this->botLine('👤 '.trim(($params['first_name'] ?? '').' '.($params['last_name'] ?? '')).' '.($params['phone_number'] ?? '')),
                'sendChatAction' => $this->line('<fg=gray>  … '.str_replace('_', ' ', (string) ($params['action'] ?? 'typing')).'</>'),
                'answerCallbackQuery' => isset($params['text'])
                    ? $this->line('<fg=yellow>  '.(($params['show_alert'] ?? false) ? '⚠ alert' : '💬 toast').': '.$params['text'].'</>')
                    : null,
                'editMessageReplyMarkup' => $this->line('<fg=gray>  (keyboard '.(isset($params['reply_markup']) ? 'updated' : 'removed').')</>'),
                'deleteMessage' => $this->line('<fg=gray>  (message deleted)</>'),
                default => $this->line('<fg=magenta>  → '.$method.'</> <fg=gray>'.json_encode($params, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).'</>'),
            };

            if (isset($params['reply_markup'])) {
                $this->renderKeyboard((array) $params['reply_markup']);
            }
        }

        if (! $replied) {
            $this->line('<fg=gray>  (no reply)</>');
        }
    }

    private function botLine(string $text, string $prefix = ''): void
    {
        $lines = explode("\n", $text);
        $this->line('<fg=cyan;options=bold>Bot</> '.$prefix.array_shift($lines));

        foreach ($lines as $line) {
            $this->line('    '.$line);
        }
    }

    /** @param array<string, mixed> $params */
    private function mediaLine(string $method, array $params): void
    {
        $type = strtolower(substr($method, 4));
        $source = $params[$type === 'photo' ? 'photo' : $type] ?? '';
        $source = is_object($source) ? 'upload: '.($source->path ?? 'file') : (string) $source;

        $this->botLine("[{$type}] {$source}".(isset($params['caption']) ? "\n{$params['caption']}" : ''));
    }

    /** @param array<string, mixed> $markup */
    private function renderKeyboard(array $markup): void
    {
        if (isset($markup['remove_keyboard'])) {
            $this->buttons = [];
            $this->line('<fg=gray>  (reply keyboard removed)</>');

            return;
        }

        $type = isset($markup['inline_keyboard']) ? 'inline' : (isset($markup['keyboard']) ? 'reply' : null);

        if ($type === null) {
            return;
        }

        $this->buttons = [];

        foreach ((array) $markup[$type === 'inline' ? 'inline_keyboard' : 'keyboard'] as $row) {
            $labels = [];

            foreach ((array) $row as $button) {
                $this->buttons[] = [
                    'type' => $type,
                    'text' => (string) ($button['text'] ?? ''),
                    'data' => $button['callback_data'] ?? null,
                    'url' => $button['url'] ?? $button['web_app']['url'] ?? null,
                ];

                $labels[] = '<fg=black;bg=white> #'.count($this->buttons).' '.($button['text'] ?? '').' </>';
            }

            $this->line('    '.implode(' ', $labels));
        }

        $this->line('<fg=gray>    type #N to press a button'.($type === 'reply' ? ' (reply keyboard)' : '').'</>');
    }

    private function renderException(Throwable $e): void
    {
        $this->line('<fg=white;bg=red> '.class_basename($e).' </> '.$e->getMessage());
        $this->line('<fg=gray>  at '.$e->getFile().':'.$e->getLine().'</>');
    }

    private function intro(string $name, string $routes, bool $routesExist): void
    {
        $this->newLine();
        $this->line("  <fg=cyan;options=bold>Telegram bot tinker</> — bot <options=bold>{$name}</>");
        $this->line('  <fg=gray>Routes: '.($routesExist ? $routes : 'not found ('.$routes.')').'</>');
        $this->line('  <fg=gray>Nothing is sent to Telegram. Type :help for commands, exit to quit.</>');
        $this->newLine();

        if (! $routesExist) {
            $this->components->warn('No routes file found. Run `php artisan telegram-bot:install` first.');
        }
    }

    private function help(): void
    {
        $this->table(['Input', 'Sends'], [
            ['any text', 'a text message (e.g. /start, hello)'],
            ['#N', 'press button N of the last keyboard'],
            [':image [caption]', 'a photo'],
            [':video [caption] / :audio [caption] / :voice', 'media'],
            [':file [name]', 'a document'],
            [':sticker [emoji]', 'a sticker'],
            [':location <lat> <lng>', 'a location'],
            [':contact <phone> [name]', 'a contact'],
            [':callback <data>', 'a callback query with the given data'],
            [':raw <json>', 'a raw update (update_id is added)'],
            [':reset', 'clear the running conversation'],
            [':whoami', 'show the fake user and chat'],
            ['exit', 'quit'],
        ]);
    }
}
