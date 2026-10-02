# Architecture

High-level design for the requirements in [statement-of-work.md](statement-of-work.md). Requirement IDs (R1 to R10) refer to that document. Feature-level details are specified separately per feature.

## Overview

```
Figure REST ──(market:sync)──▶ markets table ◀──(upsert tick)── market:listen ◀──WS── Figure WebSocket
                                   │                                   │
                                   ▼                                   ▼ MarketUpdated (ShouldBroadcastNow)
                           Livewire dashboard ◀── Echo ◀── Reverb ◀────┘
```

Two feeds, one table, one normalizer. The REST sync fills the market list and an initial price. The WebSocket listener keeps the live columns current and broadcasts each update. The page reads the table on load and re-renders on broadcast. The browser talks only to the application (R1, R5, R6, R7).

## Decisions

| # | Topic | Decision | Why |
|---|-------|----------|-----|
| 1 | Application WebSocket | Laravel Reverb + Laravel Echo | First-party, installed via `install:broadcasting --reverb`. |
| 2 | Provider WebSocket client | `ratchet/pawl` | Laravel has no WebSocket client. Pawl wraps `react/socket` and `ratchet/rfc6455`, which Reverb already depends on. Fallback if it does not install cleanly: `amphp/websocket-client`. |
| 3 | Listener runtime | `php artisan market:listen`, long-running | One process, one provider connection, independent of web traffic. Started by `artisan dev`. |
| 4 | Storage | `markets` table, current price and 24h stats on the same row | Simplest thing that gives the UI a list and a current price. History is not required; it would live in a separate table if it were. |
| 5 | Numeric precision | `decimal(36, 18)` columns with `decimal:18` casts for prices and volumes | The provider sends up to 18 decimals. SQLite stores these as `numeric` and may round locally; PostgreSQL keeps them exact. |
| 6 | Sync trigger | Once, at listener process start | The listener needs the symbol list before subscribing. Never on page load. Scheduled re-sync is a later improvement. |
| 7 | Subscriptions | All open markets at process start | 16 markets today, limit is 50 per connection. Keeps the listener independent of user activity. |
| 8 | Disappeared markets | A market missing from the REST list is marked `status = closed`, never deleted | Keeps rows and history of what existed; the UI lists open markets only. |
| 9 | Frontend updates | Livewire component with an Echo listener, re-render per update (filter rule under Frontend below) | No custom JavaScript, testable with Livewire helpers. One roundtrip per update is fine at event-driven rates. |
| 10 | Access | Behind the existing login, one private `markets` channel, demo user seeded for local use | Matches the auth-gated dashboard. Channel authorization is one callback. `DatabaseSeeder` creates a demo user; README and `.env.example` mark it local-only. |
| 11 | Channel layout | Single `PrivateChannel('markets')` for all updates | Livewire registers Echo listeners once at mount, so a per-market dynamic channel would not follow the selected market. One channel also avoids symbols with dots in channel names. Per-market channels when client count or tick rate grows. |
| 12 | Provider environment | UAT by default, production via environment variables | Safe default against rate limits; one config change to switch. |
| 13 | Broadcast dispatch | `ShouldBroadcastNow` from the listener | No queue worker needed for broadcasting. |
| 14 | Session cap | Rely on the server closing the connection at 30 minutes, then reconnect with backoff | Proactive reconnect is a later improvement. |

## Components

### Data

**`App\Models\Market`**, table `markets`.

| Group | Columns |
|-------|---------|
| Identity | `symbol` (unique), `display_name`, `base_asset`, `quote_asset`, `market_type`, `status`, `price_precision` |
| Live | `last_price`, `best_bid`, `best_ask`, `price_change_24h`, `percentage_change_24h`, `high_24h`, `low_24h`, `volume_24h`, `trade_count_24h`, `price_updated_at` |

Scope `open()`. Factory for tests.

### Provider integration (R1, R2, R4)

- **`App\Services\FigureMarkets\RestClient`**: `Http` facade with timeout and retry. Base URL from `config('services.figure_markets.rest_url')`. `markets()` walks pagination.
- **`App\Services\FigureMarkets\MarketPayload`**: the single normalizer. Maps a REST market record or a WebSocket update to `Market` attributes. The only class that knows provider field names.
- **`App\Actions\SyncMarkets`**: REST → normalize → upsert by `symbol`, then mark markets absent from the response as `closed`. Never writes `price_updated_at`. Called by the `market:sync` command and the listener at start.
- **`App\Console\Commands\ListenToMarkets`** (`market:listen`): sync, load open markets, connect, subscribe each symbol with its own `channelUuid`, keep a `PING` timer under 30 seconds. On each message: normalize, skip if stale, upsert the row, then dispatch `MarketUpdated`. On close or error: reconnect with backoff and resubscribe. Registered with `DevCommands` so `composer run dev` starts it.

Listener rules:

- **Stale guard**: an update whose `publishTime` is not after the row's `price_updated_at` is skipped. Only the listener writes `price_updated_at`, from `publishTime`; REST records carry no timestamp, so the check applies only when the row already has one.
- **Lifecycle**: the connect and subscribe loop is wrapped so any exception logs, backs off and reconnects. The process exits only on SIGTERM.
- **Ordering**: the row is written before the event is dispatched, because the Livewire component re-queries on the event.
- **Logging**: connect, disconnect with reason, resubscribe count, skipped malformed and stale messages.
- **Backoff**: 1s, 2s, 4s, doubling to a 30s cap, with ±20% jitter.
- **Broadcast failure**: log and continue. The row is already updated, so the next page load is correct.
- **Broadcast timeout**: broadcasting from the listener is a blocking HTTP call to Reverb inside the event loop. `broadcasting.connections.reverb.client_options.timeout` is set to 2 seconds so a slow Reverb cannot starve the PING timer.

### Broadcasting (R5)

- **`App\Events\MarketUpdated`** implements `ShouldBroadcastNow`. Channel `PrivateChannel('markets')`, broadcast name `market.updated`, payload is the market `id` plus the normalized attributes.
- **`routes/channels.php`**: `markets` authorizes any authenticated user.

### Frontend (R3, R8)

- **`App\Livewire\MarketWatch`** on the dashboard. A select bound to `$symbol`, kept in the URL with `#[Url]`, loads the market from the database. The component listens on `echo-private:markets,.market.updated` and re-queries the market when the payload `id` matches the selected one; other updates are ignored. Shows last price, bid, ask, 24h high, low, volume, and when the price was last updated.

### Configuration

`config/services.php` gets `figure_markets.rest_url` and `figure_markets.ws_url` from `FIGURE_MARKETS_REST_URL` and `FIGURE_MARKETS_WS_URL`. `.env.example` carries the UAT values. Reverb variables come from the broadcasting install.

## Failure handling (R9)

| Failure | Behaviour |
|---------|-----------|
| REST unavailable at sync | Log, keep existing rows, listener subscribes to what exists. Retry with backoff while the table is empty. |
| REST returns 429 | Respect `Retry-After` when present, otherwise the listener backoff. Keep existing rows. |
| Provider WebSocket drops or the 30-minute cap closes it | Reconnect with backoff, resubscribe all symbols. |
| Malformed or stale update | Log and skip. |
| Broadcast to Reverb fails | Log and continue. |
| Unexpected exception in the listener | Caught, logged, backoff, reconnect. The process does not exit. `artisan dev` would also restart it, but only five times. |
| Browser loses its WebSocket | Echo reconnects on its own. The page shows the last known price and its timestamp. |

## Testing (R10)

In order of importance:

1. `MarketPayload`: REST record and WebSocket update to attributes, using fixtures captured from the UAT API under `tests/Fixtures/figure-markets/`.
2. `SyncMarkets` with `Http::fake`: pagination, upsert, closing absent markets, failure path.
3. Update handling extracted from the socket loop: given a decoded update, the row is updated, stale updates are skipped, and `MarketUpdated` is dispatched (`Event::fake`). The socket lifecycle itself is not unit-tested.
4. `MarketUpdated` channel and payload shape.
5. `MarketWatch`: requires login, lists open markets from factories, selecting a market shows it, an update for the selected id refreshes it, an update for another id does not.
6. `market:sync` command smoke test.

## Running

`composer run dev` starts the web server, queue, log tail, Vite, `reverb:start` and `market:listen`. SQLite database. Log in with the seeded demo user.

## Trade-offs

- Livewire re-render per update (testable, no custom JavaScript) versus Alpine patching the DOM from the payload. Switch when update rate or client count grows.
- One `markets` channel so every client receives every update and filters by id, versus per-market channels. Switch together with the point above.
- Synchronous broadcast from the listener with a 2 second timeout, versus queueing broadcasts through a worker.
- Database as the single store for list, initial price and factories, versus cache. History would be a separate table.
- SQLite locally rounds 18-decimal values; PostgreSQL in production does not.
- Reactive reconnect after the 30-minute server close versus proactive reconnect before it.

## Later

- Deployment. Laravel Cloud would fit: managed Reverb, Serverless Postgres, `database` cache and session drivers, `market:listen` as an always-on background process with scale-to-zero disabled.
- Hourly scheduled `market:sync`.
- Proactive reconnect before the session cap.
- Snapshot history table, per-user on-demand subscriptions, metrics.
