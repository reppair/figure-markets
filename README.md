# Figure Markets

Demo application showing real-time cryptocurrency market prices from the public Figure Markets API. A signed-in user selects a market and sees its price update live.

## Specifications

- [Statement of work](specs/statement-of-work.md): requirements, provider interface, acceptance criteria.
- [Architecture](specs/architecture.md): design decisions, components, failure handling, testing priorities.

## Stack

- PHP 8.4, Laravel 13
- Livewire 4 starter kit (Flux UI, Fortify auth, Blaze)
- Tailwind CSS 4, Vite
- SQLite (local default)
- Pest 5, Pint, Larastan

## Requirements

- PHP 8.4 with the `sqlite3` extension
- Composer 2
- Node 22 and npm

## Setup

```bash
composer setup
```

Runs `composer install`, copies `.env.example` to `.env`, generates the app key, migrates the SQLite database, installs npm dependencies and builds assets.

## Development

```bash
composer run dev
```

Starts the PHP server, queue worker, log tail and Vite dev server together.

## Quality checks

```bash
composer test          # pint check + phpstan + full test suite
php artisan test --compact
composer lint          # fix code style
composer types:check   # phpstan
```

## Continuous integration

GitHub Actions runs `composer setup` and `composer ci:check` (Pint, PHPStan, Pest) on every push to `main` and on every pull request. See `.github/workflows/tests.yml`.

## Project setup decisions

- **Git ignore.** Laravel default plus OS and editor files. AI assistant config (`AGENTS.md`, `CLAUDE.md`, `boost.json`, `.mcp.json`, `.claude/`, `.junie/`, etc.) is ignored so each developer generates their own locally.
- **Laravel Boost.** Installed as a dev dependency only. It is not enabled in the repository. Run `php artisan boost:install` locally to generate the MCP server config, guidelines and skills for your editor.
- **Local install, no Docker.** The app runs directly on a local PHP and Node setup. Laravel Sail is not used to keep setup simple and avoid container overhead.
- **CI from the start.** The GitHub Actions workflow from the starter kit is kept and enforced from the first commit, so code style, static analysis and tests gate every change.
- **Minimal auth.** Only registration and login are used. Password reset and other auth scaffolding from the starter kit is left as-is but not configured or extended, to avoid SMTP and other setup that is out of scope.
