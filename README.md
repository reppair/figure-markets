# Figure Markets

Demo application showing real-time cryptocurrency market prices from the public Figure Markets API. A signed-in user selects a market and sees its price update live.

## Specifications

- [Statement of work](specs/statement-of-work.md): requirements, provider interface, acceptance criteria.
- [Architecture](specs/architecture.md): design decisions, components, failure handling, testing priorities.
- [Figure Markets API](docs/figure-markets-api.md): provider endpoints, verified WebSocket rules, configuration and test fixtures.

## Stack

- PHP 8.4, Laravel 13
- Livewire 4 starter kit (Flux UI, Fortify auth, Blaze)
- Laravel Reverb and Laravel Echo for the application WebSocket
- Tailwind CSS 4, Vite
- SQLite (local default)
- Pest 5, Pint, Larastan

## Requirements

- PHP 8.4 with the `sqlite3` extension
- Composer 2
- Node 22 and npm

## Dependencies

Added on top of the starter kit, each tied to a requirement in the [statement of work](specs/statement-of-work.md):

- **`laravel/reverb`**: the application's own WebSocket server. Browsers receive market updates through it and never connect to the provider (R5, R7). Installed with `php artisan install:broadcasting --reverb`, which also published `config/broadcasting.php`, `config/reverb.php` and `routes/channels.php`.
- **`laravel-echo`, `pusher-js`** (npm): the browser client for Reverb, configured in `resources/js/echo.js`.
- **`ratchet/pawl`**: WebSocket client for the backend connection to the provider feed (R2, R7). Laravel ships no WebSocket client; Pawl builds on `react/socket` and `ratchet/rfc6455`, which Reverb already depends on, so it adds no further transitive dependencies.
- **Guzzle 7**: Reverb 1.x requires `guzzlehttp/psr7` 2.x, so the installer downgraded Guzzle from 8 to 7. Laravel 13 supports both.

The Reverb variables in `.env.example` are local-only defaults so the app runs right after `composer setup`.

## Setup

```bash
composer setup
```

Runs `composer install`, copies `.env.example` to `.env`, generates the app key, migrates the SQLite database, installs npm dependencies and builds assets.

## Configuration

The Figure Markets provider URLs live in `config/services.php` under `figure_markets` and default to the UAT environment, so no environment variables are needed to run locally. To use production, set `FIGURE_MARKETS_REST_URL` and `FIGURE_MARKETS_WS_URL` in `.env`; the values are listed in the [statement of work](specs/statement-of-work.md#external-interface).

## Development

```bash
composer run dev
```

Starts the PHP server, queue worker, log tail, Vite dev server and the Reverb WebSocket server together.

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
