# 04 Market listener

The `market:listen` command, the `HandleMarketUpdateAction` it hands each message to, the `Backoff` class and the `MarketUpdated` event. Design: [architecture.md](../architecture.md) provider integration and broadcasting sections, [design decisions](../design-decisions.md) 3, 10 and 16 to 20. Provider facts: [docs/figure-markets-api.md](../../docs/figure-markets-api.md) WebSocket section.

## Outcome

- `php artisan market:listen` syncs, subscribes to every open market, writes each accepted update and broadcasts `MarketUpdated`, reconnects with backoff after a close or error, exits on SIGTERM or SIGINT.
- `composer run dev` starts it alongside Reverb.
- Stale, unknown and malformed messages are logged and skipped.

## Flow

```
handle()
  trap SIGTERM, SIGINT → stop
  loop until stopped
    SyncMarketsAction            failure → log, continue
    symbols = open markets       none → log, Sleep backoff, continue
    connect (Pawl)               failure → log, Sleep backoff, continue
      subscribe each symbol with its own UUID
      ping frame every 20s
      message → json_decode → HandleMarketUpdateAction::handle()
                 Market → print "HASH-USD 0.020000000000000000 at 10:37:55.734"
                 null   → print "Skipped a message, see the log for details."
      close → print and log code and reason, run loop ends
    backoff reset after a connection that was established
    Sleep backoff
```

```
HandleMarketUpdateAction::handle(array $message): ?Market
  WebSocketMarketPayload::fromMessage        MalformedMarketPayload → warning, null
  Market where symbol                        none → warning, null
  price_updated_at set and not before publishedAt → warning, null      (stale guard)
  $market->update(payload attributes)        the row is written first
  MarketUpdated::dispatch($market)           then the event, never from a model event
  return $market
```

## Classes

| Class | Responsibility |
|-------|----------------|
| `App\Console\Commands\ListenToMarketsCommand` | Signature `market:listen`. Constructor-free; `handle(SyncMarketsAction, HandleMarketUpdateAction, Backoff)`. Owns the loop above with `ratchet/pawl` and the ReactPHP loop. Prints to the console and logs: sync result, connect with symbol count, one line per written update, `Skipped a message, see the log for details.`, disconnect with code and reason, the reconnect delay, each error. Registered with `DevCommands::artisan('market:listen')` in `AppServiceProvider` (decision 18, 19). |
| `App\Actions\HandleMarketUpdateAction` | `handle(array $message): ?Market` as above. Catches only `MalformedMarketPayload`. Writes through `$market->update()`, then dispatches, then returns the market; null on every skip (decision 17). |
| `App\Services\FigureMarkets\Backoff` | `next(): float` returns the delay in seconds: 1, 2, 4 ... capped at 30, each with ±20% jitter. `reset()` returns to 1. Constants on the class. |
| `App\Events\MarketUpdated` | `ShouldBroadcastNow`. `__construct(public Market $market)`. `broadcastOn()` `PrivateChannel('markets')`, `broadcastAs()` `market.updated`, `broadcastWith()` `$market->toArray()` (decision 20). |

Configuration: `config/broadcasting.php` `reverb.client_options` gets `connect_timeout` and `timeout` of 2 seconds so a slow Reverb cannot starve the ping timer.

## Tests

| File | Covers |
|------|--------|
| `tests/Feature/Actions/HandleMarketUpdateActionTest.php` | `market-snapshot.json` on a factory `HASH-USD` row writes every live column and `price_updated_at` with microseconds, dispatches `MarketUpdated` carrying the already-updated market (`Event::fake`, assertion inside the dispatch callback reads the new price) and returns the market; `market-update.json` after the snapshot is accepted; the snapshot after the update returns null, no write, no event; a snapshot with the same `publishTime` as the row is skipped; a row without `price_updated_at` accepts any message; a message for an unknown symbol is skipped and logged; a message without `marketId` is skipped and logged |
| `tests/Unit/Services/FigureMarkets/BackoffTest.php` | ten calls to `next()` stay within ±20% of 1, 2, 4, 8, 16, 30, 30 ...; `reset()` returns to the 1s band |
| `tests/Unit/Events/MarketUpdatedTest.php` | channel is `private-markets`, name is `market.updated`, payload equals the market's array and contains `id` |

The command has no automated test (decision 18). Verification is the smoke run in step 7.

## Steps

| # | Step | Verify | Status |
|---|------|--------|--------|
| 1 | `php artisan make:class Services/FigureMarkets/Backoff`; write it | `BackoffTest` passes | todo |
| 2 | `php artisan make:event MarketUpdated`; write it; set `reverb.client_options` | `MarketUpdatedTest` passes | todo |
| 3 | `php artisan make:class Actions/HandleMarketUpdateAction`; write it | `HandleMarketUpdateActionTest` passes | todo |
| 4 | `php artisan make:command ListenToMarketsCommand`; write it; register with `DevCommands` | `php artisan dev --help` lists no error; `composer run dev` shows the listener tab | todo |
| 5 | `laravel-simplifier` pass on implementation, then on tests | No findings left unapplied or logged in the decisions file | todo |
| 6 | `composer test` | Pint, PHPStan, Pest green | todo |
| 7 | Smoke: `composer run dev`, watch the listener log connect and 16 subscriptions, Reverb log `market.updated` events for `HASH-USD`, `markets` rows gain `price_updated_at`; `Ctrl+C` exits cleanly | Observed | todo |
| 8 | `update-docs`: add `docs/market-listen.md` with a Mermaid version of the loop, stale guard semantics, failure table, the hand-verified lifecycle and the `MarketFeed` improvement; link from README | Doc present, linked | todo |

## Out of scope

- Channel authorization in `routes/channels.php`, the `MarketWatch` component, the demo user (phase 5).
- Proactive reconnect before the 30-minute cap, a testable `MarketFeed` interface (README improvements).
