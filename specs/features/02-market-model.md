# 02 Market model and payloads

The `markets` table, the `Market` model with its factory and `open()` scope, and the two payload classes that normalize provider data. Design: [architecture.md](../architecture.md) data section, [design decisions](../design-decisions.md) 1 to 10. Provider facts: [docs/figure-markets-api.md](../../docs/figure-markets-api.md).

## Outcome

- A `markets` table that holds identity and live columns for one market per row.
- `App\Models\Market` with casts, the `MarketStatus` enum and an `open()` scope.
- `RestMarketPayload` and `WebSocketMarketPayload` turning provider arrays into model attributes, throwing `MalformedMarketPayload` when a required key is missing.
- Factory with `closed()`, `withoutOrderBook()` and `untraded()` states.
- Unit tests for both payloads against the captured fixtures, feature tests for the model.

## Data model

Table `markets`, migration created with `make:model Market -mf --pest`.

| Column | Type | Null | Notes |
|--------|------|------|-------|
| `id` | id | no | |
| `symbol` | string, unique | no | Provider `symbol`, equals `marketId` on the WebSocket |
| `display_name` | string | no | `displayName` |
| `base_asset` | string | no | `denom` |
| `quote_asset` | string | no | `quoteDenom` |
| `market_type` | string | no | `marketType`, stored as received, not used (decision 6) |
| `status` | string | no | `MarketStatus` enum, `open` or `closed` (decision 1) |
| `price_precision` | unsignedSmallInteger | no | `pricePrecision` |
| `last_price` | decimal(36, 18) | yes | `lastTradedPrice`; `0` for an untraded market (decision 8) |
| `best_bid` | decimal(36, 18) | yes | absent without an order book |
| `best_ask` | decimal(36, 18) | yes | absent without an order book |
| `price_change_24h` | decimal(36, 18) | yes | |
| `percentage_change_24h` | decimal(12, 6) | yes | provider float (decision 4) |
| `high_24h` | decimal(36, 18) | yes | |
| `low_24h` | decimal(36, 18) | yes | |
| `volume_24h` | decimal(36, 18) | yes | |
| `trade_count_24h` | unsignedInteger | yes | |
| `price_updated_at` | timestamp(6) | yes | WebSocket `publishTime` only, never set from REST |
| `created_at`, `updated_at` | timestamps | | |

Every nullable live column carries a `->comment()` stating that null means the provider sent nothing (decision 5). Casts: `decimal:18` for prices and volumes, `decimal:6` for the percentage, `int` for counts and precision, `immutable_datetime` for `price_updated_at`, `MarketStatus` for `status`.

## Classes

| Class | Responsibility |
|-------|----------------|
| `App\Enums\MarketStatus` | `Open = 'open'`, `Closed = 'closed'`. `fromProvider(string): self` maps `OPEN` to `Open`, anything else to `Closed`. |
| `App\Models\Market` | Casts above, `scopeOpen()`, factory. No relationships. |
| `App\Services\FigureMarkets\RestMarketPayload` | readonly. `fromRecord(array $record): self`. Properties: `symbol`, `displayName`, `baseAsset`, `quoteAsset`, `marketType`, `status`, `pricePrecision`, `lastPrice`, `bestBid`, `bestAsk`, `priceChange24h`, `percentageChange24h`, `high24h`, `low24h`, `volume24h`, `tradeCount24h`. `toAttributes(): array` returns every column above except `price_updated_at`. Requires `symbol`. |
| `App\Services\FigureMarkets\WebSocketMarketPayload` | readonly. `fromMessage(array $message): self`. Properties: `symbol` (from `marketId`), the live fields, `publishedAt` (`CarbonImmutable` from `publishTime`). `toAttributes(): array` returns the live columns and `price_updated_at`, never an identity column (decision 10). Requires `marketId` and `publishTime`. |
| `App\Services\FigureMarkets\MalformedMarketPayload` | `InvalidArgumentException` subclass. `missing(string $key): self`. |

Rules shared by both payloads, written out in each (decision 2):

- Only the required keys throw. Every other key defaults to null when absent.
- Numeric strings are passed through unchanged; the model casts them. `percentageChange24h` is cast to string on the payload so the decimal cast receives a string, never a float.
- `status` goes through `MarketStatus::fromProvider()` on the REST payload only. The WebSocket payload ignores `status` and `pricePrecision` (decision 10).

## Factory

`MarketFactory` default: unique symbol in the form `AAA-USD`, matching display name, assets from the symbol, `market_type` `CRYPTO`, `status` `Open`, precision 2 to 8, random prices with bid below ask, 24h stats, no `price_updated_at`. States: `closed()`, `withoutOrderBook()`, `untraded()` (decision 9).

## Tests

| File | Covers |
|------|--------|
| `tests/Unit/Services/FigureMarkets/RestMarketPayloadTest.php` | `markets.json` first record maps to every attribute; a record without an order book has null bid and ask; the `USDC-USD` record keeps `0` as last price; `HALTED` status maps to `Closed`; missing `symbol` throws |
| `tests/Unit/Services/FigureMarkets/WebSocketMarketPayloadTest.php` | `market-snapshot.json` maps to attributes including `price_updated_at` with microseconds; `market-snapshot-no-book.json` has null bid and ask; missing `marketId` or `publishTime` throws (dataset) |
| `tests/Unit/Enums/MarketStatusTest.php` | `fromProvider()` for `OPEN` and a dataset of the other nine provider values |
| `tests/Feature/Models/MarketTest.php` | factory creates a valid row; `open()` excludes closed markets; decimal casts return strings with 18 decimals; `price_updated_at` round-trips microseconds; `withoutOrderBook()` and `untraded()` states |

`fixture(string $path): array` helper in `tests/Pest.php` reads and decodes `tests/Fixtures/{$path}`.

## Steps

| # | Step | Verify | Status |
|---|------|--------|--------|
| 1 | `php artisan make:enum MarketStatus` and `make:model Market -mf --pest --no-interaction`; write migration, model, factory, enum | `php artisan migrate:fresh` runs; `Market::factory()->create()` in a test | todo |
| 2 | `fixture()` helper in `tests/Pest.php` | Used by payload tests | todo |
| 3 | `MalformedMarketPayload`, `RestMarketPayload`, `WebSocketMarketPayload` | Unit tests above pass | todo |
| 4 | Model and enum tests | Feature and unit tests above pass | todo |
| 5 | `laravel-simplifier` pass on implementation, then on tests | No findings left unapplied or logged in the decisions file | todo |
| 6 | `composer test` | Pint, PHPStan, Pest green | todo |
| 7 | `update-docs`: add `docs/data-model.md` describing the table, the enum and both payload classes with their field mappings | Doc present, linked from README | todo |

## Out of scope

- Syncing from REST, marking absent markets closed (phase 3).
- The stale guard and upsert from WebSocket updates (phase 4).
- Display formatting of prices and zero values (phase 5).
