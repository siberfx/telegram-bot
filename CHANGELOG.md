# Changelog

All notable changes to `siberfx/telegram-bot` are documented in this file.

## 0.1.2 - 2026-11-06

### Added
- GitHub Actions `tests` workflow: Pest on PHP 8.4 and 8.5 with lowest and stable dependencies, plus Windows.
- GitHub Actions `lint` workflow: `composer validate --strict` and Pint.
- Dependabot updates for GitHub Actions.
- CI status badges in the README.

### Changed
- Workflows use `actions/checkout@v7` and `actions/cache@v6`.

## 0.1.1 - 2026-11-06

### Changed
- The test suite is now written in Pest 4 (`composer test` runs `pest`), with additional coverage for parameters, captions, user filters, middleware validation, unhandled exceptions and webhook payload validation.

## 0.1.0 - 2026-11-06

First public release.

### Added
- `hears()` patterns with required/optional parameters, attachment listeners, `on()` update/message events and `fallback()`.
- Command filters (chat type, chats, users), route groups and stop/skip conversation flags.
- Received, Captured, Matching, Heard and Sending middleware.
- Conversations with closures, method names or pattern callbacks, `repeat()` and proactive starts.
- Outgoing messages, questions, inline/reply keyboards and file, location and contact attachments.
- User, chat and bot storage.
- Multiple bots, webhook route with secret verification and optional queueing.
- Artisan commands: `telegram-bot:install`, `telegram-bot:tinker`, `telegram-bot:poll`, `telegram-bot:webhook`, `telegram-bot:commands`, `make:telegram-conversation`.
- `Telegram::fake()` fluent testing API.
