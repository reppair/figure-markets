# 05 Market watch

Channel authorization, the `MarketWatch` and `MarketStats` components on the dashboard, and the demo user. Design: [architecture.md](../architecture.md) broadcasting and frontend sections, decisions 9 to 11; [design decisions](../design-decisions.md) 20 to 26.

## Outcome

- A logged-in user opens the dashboard, sees a select listing the open markets with `HASH-USDC` selected (the first market when it is not open), and the stats card for the selected market below it.
- The browser subscribes to the selected market's channel only. A `market.updated` broadcast on it re-renders the card; the select and the rest of the page are untouched. No timer, no polling.
- `composer run setup` seeds the demo user, so a fresh clone can log in.

## Flow

```
MarketWatch::render()                            symbol is null on the first render
  symbols = Market::open()->orderBy('symbol')->pluck('symbol')
  symbol not among them → HASH-USDC, else first  covers the first render, a closed or unknown symbol, a tampered value
  no open market → callout: start composer run dev (sync + live feed), market:sync alone fills the list only
  select wire:model.live="symbol", one option per market
  <livewire:market-stats :symbol :wire:key="$symbol">
```

```
MarketStats (symbol)
  #[Computed] market()                          Market where symbol, no open scope: a market closed by a re-sync keeps its last values until the next page load
  getListeners()                                echo-private:markets.{id},.market.updated → $refresh, nothing for an unknown symbol
                                                the browser subscribes to this market's channel only, so every event it receives re-renders the card, which re-queries the row the listener already wrote
  card root x-data destroy()                    Echo.leave('markets.{id}') one tick after Livewire's own cleanup, so a replaced card stops receiving its old market
  render()
    market missing → one line: market not available
    stats: last price with 24h change and percentage, bid, ask, 24h high, low, volume, trade count, last updated
```

## Classes

| Class | Responsibility |
|-------|----------------|
| `App\Events\MarketUpdated` | `broadcastOn()` becomes `PrivateChannel("markets.{$market->id}")` (decision 26). Name and payload unchanged. |
| `routes/channels.php` | `Broadcast::channel('markets.{id}', fn () => true)`. Guests are rejected by the broadcasting auth route before the callback runs (architecture decision 10). |
| `App\Livewire\MarketWatch` | `public ?string $symbol`. `render()` plucks the open symbols in order, falls back to `DEFAULT_SYMBOL` (`HASH-USDC`) or else the first one when `$symbol` is not among them, passes the symbols to the view. One child, keyed by symbol, so a change re-mounts it (decision 23). |
| `App\Livewire\MarketStats` | `public string $symbol`. `#[Computed] market(): ?Market`. `getListeners()` as above, no handler method. The view's root carries the Alpine `destroy()` that leaves the channel (decision 26). `render()` passes a `label => value` list built by `format*` methods on the component: prices with `number_format` at `price_precision`, the percentage ratio times 100 at two decimals with `%`, volume at eight decimals with trailing zeros trimmed, trade count with a thousands separator, timestamp in UTC as `Y-m-d H:i:s UTC`, null as `—`, zero as a formatted zero (decision 24). |
| `resources/views/components/market-stat.blade.php` | Anonymous Blade component, `label` and `value` props. Rendered once per stat. |
| `resources/views/dashboard.blade.php` | Placeholder replaced by `<livewire:market-watch />`. Route and middleware unchanged. |
| `Database\Seeders\DatabaseSeeder` | `User::query()->where('email', 'demo@example.com')->firstOr(fn () => User::factory()->create([...]))` with name `Demo User`; the factory supplies the verified timestamp and the password `password`. `composer run setup` runs `migrate --force --seed` (decision 25). |

Flux: `flux:select` with `wire:model.live` and one `flux:select.option` per market, `flux:callout` when no market is open, `flux:heading` and `flux:text` in the card. All free components.

## Tests

| File | Covers |
|------|--------|
| `tests/Feature/Broadcasting/MarketsChannelTest.php` | `POST /broadcasting/auth` for `private-markets.7`: a logged-in user gets 200, a guest gets 403 |
| `tests/Unit/Events/MarketUpdatedTest.php` | channel name becomes `private-markets.{id}` (phase 4 test amended) |
| `tests/Feature/Livewire/MarketWatchTest.php` | dashboard renders `MarketWatch` for a user (the guest redirect is already in `DashboardTest`); open markets from the factory are listed in symbol order and a closed one is not; `HASH-USDC` is selected when open and its `MarketStats` child is present; the first market is selected when the default is closed; selecting another market renders its card; an unknown symbol and a closed symbol set on the component both fall back; a table with no open market shows the callout and no select |
| `tests/Feature/Livewire/MarketStatsTest.php` | a factory market renders every stat formatted at its `price_precision`; volume trimming for a fraction and a whole number; `withoutOrderBook()` and a null `price_updated_at` show `—`; `untraded()` shows formatted zeros; after the row is updated, dispatching the market's own Echo event re-renders with the new price; the component listens on its own market's channel and no other, and on nothing for an unknown symbol; the card root leaves its channel on destroy, and not for an unknown symbol; an unknown symbol renders no stats |
| `tests/Feature/DatabaseSeederTest.php` | seeding twice leaves one `demo@example.com` user; the seeded user reaches the dashboard; the documented password logs in |

Component tests use the factory states from decision 9 and need no login, the dashboard route test covers that. Values asserted with `assertSee` are factory-controlled; structure is asserted through `assertSet`, `assertViewHas`, `assertSeeLivewire`, `getListeners()`, and `assertSeeHtml` / `assertDontSeeHtml` against the `data-lw-market` and `data-lw-no-markets` attributes, the select's `wire:model.live` and the `Echo.leave` call.

## Steps

| # | Step | Verify | Status |
|---|------|--------|--------|
| 1 | Add the `markets.{id}` callback to `routes/channels.php`; move `MarketUpdated` to the per-market channel | `MarketsChannelTest` and `MarketUpdatedTest` pass | done |
| 2 | `php artisan make:component MarketStat --view`; `php artisan make:livewire MarketStats --class`; write both | `MarketStatsTest` passes | done |
| 3 | `php artisan make:livewire MarketWatch --class`; write it; replace the dashboard placeholder and delete the unused `placeholder-pattern` component | `MarketWatchTest` passes | done |
| 4 | Demo user in `DatabaseSeeder`; `--seed` on the `setup` script | `DatabaseSeederTest` passes; `php artisan db:seed` twice on the local database left one demo user | done |
| 5 | `laravel-simplifier` pass on implementation, then on tests | Three passes: no changes, no changes, one `firstWhere()` | done |
| 6 | `composer test` | 113 tests, Pint, PHPStan green | done |
| 7 | Smoke: `composer run dev`, log in, `HASH-USDC` is selected; the card ticks about once a second and the select does not change; select a quiet market, the card stays still and the network tab shows no Livewire request while `HASH-USDC` keeps ticking; the Reverb log shows the subscription to `private-markets.<id>` and the unsubscribe from the previous one | Observed on 2026-10-02 against UAT with `composer run dev`: ticks on the selected card only, select untouched, subscribe and unsubscribe per switch | done |
| 8 | `update-docs`: add `docs/market-watch.md` with the two components, the per-market subscription, display rules, the demo user and the push-not-poll explanation; link from README; amend the broadcast section of `docs/market-listen.md` | Doc present, linked | done |

## Out of scope

- Watching several markets at once, the selection in the URL, Alpine patching the DOM from the payload (decisions 21 to 23, 26).
- A market changing status while the page is open; the next page load reflects it.
- README run instructions, assumptions, gaps and improvements (phase 6).
