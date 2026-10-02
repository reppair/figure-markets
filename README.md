# Figure Markets

Demo application showing real-time cryptocurrency market prices from the public Figure Markets API. A signed-in user selects a market and sees its price update live.

## How it works

`market:sync` fills the `markets` table from the provider's REST API. `market:listen` holds one WebSocket connection to the provider, subscribes to every open market, writes each update to the table and broadcasts it through Reverb on that market's private channel. The dashboard lists the open markets, subscribes the selected market's card to its channel and re-renders the card on each broadcast. The browser never talks to the provider and never polls. Design and reasoning: [architecture](specs/architecture.md).

## Specifications and documentation

- [Statement of work](specs/statement-of-work.md): requirements, provider interface, acceptance criteria.
- [Architecture](specs/architecture.md): design decisions, components, failure handling, testing priorities.
- [Design decisions](specs/design-decisions.md): code-level decisions taken during the build, with alternatives and reasoning.
- [Figure Markets API](docs/figure-markets-api.md): provider endpoints, verified WebSocket rules, configuration and test fixtures.
- [Data model](docs/data-model.md): the markets table, model, status enum, payload classes and factory.
- [Market sync](docs/market-sync.md): what `market:sync` writes, how it fails, and the pagination and rate-limit gaps.
- [Market listener](docs/market-listen.md): the `market:listen` lifecycle, what each provider message does, broadcast shape, failure behaviour.
- [Market watch](docs/market-watch.md): the dashboard components, per-market subscription, what the card shows, access and the demo user.

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

The Reverb variables in `.env.example` are local-only defaults so the app runs right after `composer run setup`.

## Setup

```bash
composer run setup
```

Runs `composer install`, copies `.env.example` to `.env`, generates the app key, migrates and seeds the SQLite database, installs npm dependencies and builds assets. The seed creates the local demo login `demo@example.com` / `password`; it is idempotent and not meant for a deployment.

## Development

```bash
composer run dev
```

Starts the PHP server, queue worker, log tail, Vite dev server, the Reverb WebSocket server and the market listener (`market:listen`) together. The listener syncs the market list at start and after every connection that delivered updates, so no manual sync is needed. Open the app, log in with the demo user and pick a market on the dashboard. `HASH-USDC` is selected first because it ticks most on UAT; the card updates as trades happen, the rest of the page does not.

## Configuration

The Figure Markets provider URLs live in `config/services.php` under `figure_markets` and default to the UAT environment, so no environment variables are needed to run locally. To use production, set `FIGURE_MARKETS_REST_URL` and `FIGURE_MARKETS_WS_URL` in `.env`; the values are listed in the [statement of work](specs/statement-of-work.md#external-interface).

## Assumptions

| Assumption | If it does not hold |
|------------|---------------------|
| UAT is the provider by default; production is two variables in `.env` | A different environment is a configuration change, never a code change |
| One REST page lists every market: 16 on UAT, 17 on production, page cap 50 | Markets past the first page are missed and marked closed, see the gaps below |
| The `markets` table is the only store: one row per market, current values only | History or charts would need a second table |
| SQLite is the local database and may round 18-decimal values | PostgreSQL keeps `decimal(36, 18)` exact; the card rounds to the market's `price_precision` either way |
| A market that never traded reports zero, and the card shows zero, not a dash | The dash is reserved for values the provider did not send |
| Every logged-in user may watch every market; the demo user is for local use only | Restricting markets per user would be one real rule in the channel callback, which now admits any login |
| The dashboard shows one market at a time | Several cards is a view change; the card component already exists per market |

Reasoning for each: [design decisions](specs/design-decisions.md) 7, 8, 12, 23 and 25, and the architecture [trade-offs](specs/architecture.md#trade-offs).

## Not completed

| Gap | Effect | Reference |
|-----|--------|-----------|
| REST pagination is not walked | A provider with more than 50 visible markets is partially synced and the rest marked closed | [decision 12](specs/design-decisions.md), [market sync](docs/market-sync.md) |
| `Retry-After` on 429 is ignored | Three fixed retries 500 ms apart, then the sync fails and the existing rows stay | [decision 13](specs/design-decisions.md) |
| Reconnect is reactive | The listener waits for the provider's 30-minute close and reconnects about a second later | [market listener](docs/market-listen.md) |
| No `MarketFeed` interface in front of Pawl | The socket lifecycle (reconnect, resubscribe, backoff, shutdown) is verified by hand, not by tests | [decision 18](specs/design-decisions.md) |
| Broadcasting is synchronous inside the listener loop | With Reverb down, a burst of updates can delay the keepalive ping past 30 seconds and force a reconnect | [market listener](docs/market-listen.md) |
| The card re-renders through one Livewire request per update | Fine at one tick per second; a market changing status while the page is open shows on the next load | [decision 21](specs/design-decisions.md), [market watch](docs/market-watch.md) |
| No environment guard on the demo seeder | `composer run setup` seeds the demo user in any environment | [decision 25](specs/design-decisions.md) |

## With more time

| Change | Why |
|--------|-----|
| Queued broadcasting | Takes the Reverb call out of the listener loop and removes the keepalive risk above |
| Patch the card from the broadcast payload with Alpine | Removes the request per tick; costs browser tests |
| Scheduled `market:sync` | Reflects status changes without waiting for a reconnect; excluded by the statement of work |
| Snapshot history table, per-user on-demand subscriptions, metrics | History and charts, fewer provider subscriptions, visibility into the feed |
| Deployment on Laravel Cloud | Managed Reverb, Serverless Postgres, `market:listen` as an always-on background process |

The full lists are the architecture's [trade-offs and later](specs/architecture.md#later) sections and the [design decisions](specs/design-decisions.md), where each entry records what was considered and why it was not chosen.

## Quality checks

```bash
composer test          # pint check + phpstan + full test suite
php artisan test --compact
composer lint          # fix code style
composer types:check   # phpstan
```

## Continuous integration

GitHub Actions runs `composer run setup` and `composer ci:check` (Pint, PHPStan, Pest) on every push to `main` and on every pull request. See `.github/workflows/tests.yml`.

## Project setup decisions

- **Git ignore.** Laravel default plus OS and editor files. AI assistant config (`AGENTS.md`, `CLAUDE.md`, `boost.json`, `.mcp.json`, `.claude/`, `.junie/`, etc.) is ignored so each developer generates their own locally.
- **Laravel Boost.** Installed as a dev dependency only. It is not enabled in the repository. Run `php artisan boost:install` locally to generate the MCP server config, guidelines and skills for your editor.
- **Local install, no Docker.** The app runs directly on a local PHP and Node setup. Laravel Sail is not used to keep setup simple and avoid container overhead.
- **CI from the start.** The GitHub Actions workflow from the starter kit is kept and enforced from the first commit, so code style, static analysis and tests gate every change.
- **Minimal auth.** Only registration and login are used. Password reset and other auth scaffolding from the starter kit is left as-is but not configured or extended, to avoid SMTP and other setup that is out of scope.
