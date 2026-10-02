# 05 Market watch

Channel authorization, the `MarketWatch` and `MarketStats` components on the dashboard, and the demo user. Design: [architecture.md](../architecture.md) broadcasting and frontend sections, decisions 9 to 11; [design decisions](../design-decisions.md) 20 to 25.

## Outcome

- A logged-in user opens the dashboard, sees one checkbox per open market with the first one checked, and one stats card per checked market.
- A `market.updated` broadcast re-renders only the card for that market. Other cards, the checkboxes and the rest of the page are untouched. No timer, no polling.
- `composer run setup` seeds the demo user, so a fresh clone can log in.

## Flow

```
MarketWatch::mount()
  symbols = [first open market by symbol]       none → []
MarketWatch::render()
  markets = Market::open()->orderBy('symbol')
  symbols = symbols ∩ markets.symbol            drops anything not open
  no markets  → callout: no markets yet, run php artisan market:sync
  checkbox group wire:model.live="symbols", one checkbox per market
  no symbols  → hint: check a market to watch it
  one <livewire:market-stats :symbol :wire:key="$symbol"> per symbol
```

```
MarketStats (symbol)
  #[Computed] market()                          Market where symbol, no open scope: a market closed by a re-sync keeps its last values until the next page load
  #[On('echo-private:markets,.market.updated')] onMarketUpdated(array $event)
    event id ≠ market id → skipRender()         every client receives every tick; a card re-renders only for its own market
    match                → nothing to do, the re-render re-queries the row the listener already wrote
  render()
    market missing → one line: market not available
    stats: last price with 24h change and percentage, bid, ask, 24h high, low, volume, trade count, last updated
```

## Classes

| Class | Responsibility |
|-------|----------------|
| `routes/channels.php` | `Broadcast::channel('markets', fn () => true)`. Guests are rejected by the broadcasting auth route before the callback runs (architecture decision 10). |
| `App\Livewire\MarketWatch` | `public array $symbols`. `mount()` pre-checks the first open market. `render()` loads open markets ordered by symbol, intersects `$symbols` with them, passes both to the view. No URL state (decision 23). |
| `App\Livewire\MarketStats` | `public string $symbol`. `#[Computed] market(): ?Market`. The Echo listener above. `render()` passes a `label => value` list built by formatting methods on the component: prices with `number_format` at `price_precision`, percentage at two decimals with `%`, volume at eight decimals with trailing zeros trimmed, trade count with a thousands separator, timestamp `Y-m-d H:i:s UTC`, null as `—`, zero as a formatted zero (decision 24). |
| `resources/views/components/market-stat.blade.php` | Anonymous Blade component, `label` and `value` props. Rendered once per stat. |
| `resources/views/dashboard.blade.php` | Placeholder replaced by `<livewire:market-watch />`. Route and middleware unchanged. |
| `Database\Seeders\DatabaseSeeder` | `User::firstOrCreate(['email' => 'demo@example.com'], [...])` with name `Demo User` and the factory password `password`. `composer run setup` runs `migrate --force --seed` (decision 25). |

Flux: `flux:checkbox.group` with `wire:model.live`, `flux:checkbox` per market, `flux:callout` for the empty table, `flux:heading` and `flux:text` in the cards. All free components.

## Tests

| File | Covers |
|------|--------|
| `tests/Feature/Broadcasting/MarketsChannelTest.php` | `POST /broadcasting/auth` for `private-markets`: a logged-in user gets 200, a guest gets 403 |
| `tests/Feature/Livewire/MarketWatchTest.php` | dashboard redirects a guest to login; dashboard renders `MarketWatch` for a user; open markets from the factory are listed in symbol order and a closed one is not; the first market is pre-checked and its `MarketStats` child is present; setting two symbols renders two children; setting none shows the hint; an unknown symbol is dropped from `$symbols`; an empty table shows the callout and no checkboxes |
| `tests/Feature/Livewire/MarketStatsTest.php` | a factory market renders every stat formatted at its `price_precision`; `withoutOrderBook()` shows `—` for bid and ask; `untraded()` shows a formatted zero; a null `price_updated_at` shows `—`; after the row is updated, dispatching the Echo event with the market's id is not render-skipped and the new price is shown; the event with another id is render-skipped; an unknown symbol renders the not-available line |
| `tests/Feature/DatabaseSeederTest.php` | seeding twice leaves one `demo@example.com` user |

Component tests use `Livewire::actingAs()` and the factory states from decision 9. Values asserted with `assertSee` are factory-controlled; structure is asserted through `assertSet`, `assertSeeLivewire`, `assertRenderSkipped` and `wire:key`.

## Steps

| # | Step | Verify | Status |
|---|------|--------|--------|
| 1 | Add the `markets` callback to `routes/channels.php` | `MarketsChannelTest` passes | todo |
| 2 | `php artisan make:component MarketStat --view`; `php artisan make:livewire MarketStats` as a class component; write both | `MarketStatsTest` passes | todo |
| 3 | `php artisan make:livewire MarketWatch` as a class component; write it; replace the dashboard placeholder | `MarketWatchTest` passes | todo |
| 4 | Demo user in `DatabaseSeeder`; `--seed` on the `setup` script | `DatabaseSeederTest` passes; `composer run setup` on a fresh database logs in with `demo@example.com` / `password` | todo |
| 5 | `laravel-simplifier` pass on implementation, then on tests | No findings left unapplied or logged in the decisions file | todo |
| 6 | `composer test` | Pint, PHPStan, Pest green | todo |
| 7 | Smoke: `composer run dev`, log in, check `HASH-USD` and one quiet market; the `HASH-USD` card ticks about once a second, the other card and the checkboxes do not change; the browser network tab shows one Livewire request per tick for the `HASH-USD` card only | Observed | todo |
| 8 | `update-docs`: add `docs/market-watch.md` with the two components, the per-card filter, display rules, the demo user and the push-not-poll explanation; link from README | Doc present, linked | todo |

## Out of scope

- URL state for the selection, per-market channels, Alpine patching the DOM from the payload (decisions 21 to 23).
- A market changing status while the page is open; the next page load reflects it.
- README run instructions, assumptions, gaps and improvements (phase 6).
