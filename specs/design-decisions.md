# Design decisions

Software design decisions made while building, in the order they were taken. The [architecture](architecture.md) holds the system-level decisions; this log holds the code-level ones and the reasoning, so later changes know what they are undoing. Each entry says what was decided, what else was considered, and why.

## 1. Market status is a backed enum

- **Decision**: `App\Enums\MarketStatus { Open = 'open', Closed = 'closed' }`, cast on the `Market` model. Normalizers map the provider's `OPEN` to `Open` and every other value to `Closed`. The provider's OpenAPI spec lists ten statuses: `UNKNOWN_MARKET_STATUS`, `PENDING`, `OPEN`, `CLOSED`, `PREOPEN`, `SUSPENDED`, `EXPIRED`, `TERMINATED`, `HALTED`, `MATCH_AND_CLOSE`. Only `OPEN` is tradeable.
- **Considered**: mirroring the provider with a ten-case enum; throwing on unknown provider values; storing the provider string lowercased.
- **Why**: the application only distinguishes tradeable from not. Nothing in scope tells `HALTED` from `SUSPENDED`, and a mirrored enum would break on the next provider addition. Mapping everything else to `Closed` keeps a sync from failing on a status we have not seen. The enum gives the `open()` scope, the factory and the UI one typed value. Confirmed against the spec on 2026-10-02 after the first version of this decision was taken from observed data alone.

## 2. Two separate payload DTOs, no shared base

- **Decision**: `App\Services\FigureMarkets\RestMarketPayload` and `App\Services\FigureMarkets\WebSocketMarketPayload` are independent readonly classes. Each has one named constructor from the provider array and its own `toAttributes()`. The only shared code is the `ReadsProviderValues` trait with the two value-coercion helpers `string()` and `int()`, which carry no field knowledge.
- **Considered**: one class with fields nullable by source; a static mapper returning arrays; two DTOs extending an abstract base holding the shared fields and mapping.
- **Why**: readability. Each file shows every field and its provider name in one place, with no parent to read. The cost is about ten properties and their mapping written twice, accepted for two sources that are not expected to grow. The coercion helpers were extracted to a trait after implementation because they are identical and say nothing about fields, so sharing them costs no readability. The abstract base would be easier to change later but harder to read now. A single class with nullable fields was rejected because the stale guard must never see a REST payload, and only distinct types enforce that.

## 3. Malformed provider payloads throw

- **Decision**: a payload's named constructor throws `App\Services\FigureMarkets\MalformedMarketPayload` when a required key is missing. Required keys: `symbol` for REST; `marketId` and `publishTime` for WebSocket, because the stale guard depends on the timestamp. Every other field defaults to null when absent.
- **Considered**: returning null and letting callers check.
- **Why**: callers decide what a bad payload means for them. The listener catches, logs and skips one message. `SyncMarkets` lets it bubble so a broken REST response fails the whole sync and keeps the existing rows. Null returns would spread the same check across every caller and lose the reason.

## 4. Numeric column types

- **Decision**: prices and volumes are `decimal(36, 18)` with `decimal:18` casts, as the architecture states. `percentage_change_24h` is `decimal(12, 6)` with a `decimal:6` cast. `trade_count_24h` and `price_precision` are unsigned integers with `int` casts.
- **Considered**: a `float` column for the percentage; storing the trade count as received.
- **Why**: every numeric value stays exact and arrives in PHP as a string or int, never a float. A ratio does not need 18 decimals. The provider sends `tradeCount24h` as an int on UAT but documents it as a string, so the `int` cast coerces either on write and the payload does not care.

## 5. Live columns are nullable, price timestamp has microsecond precision

- **Decision**: identity columns (`symbol`, `display_name`, `base_asset`, `quote_asset`, `market_type`, `status`, `price_precision`) are required. Every live column is nullable, including `last_price`. `price_updated_at` is a nullable timestamp with microsecond precision, written only by the listener from `publishTime`; the provider's nanoseconds are dropped.
- **Considered**: only `best_bid`, `best_ask` and `price_updated_at` nullable, everything else defaulting to 0.
- **Why**: zero is a wrong price, null is an honest "no data yet" that the UI can show as a dash. The payload already defaults absent fields to null, so the schema matches it. The provider's OpenAPI spec marks only `bestBid`, `bestAsk` and `percentageChange24h` as nullable and never sends a null `lastTradedPrice`, but the application schema allows it anyway so the model is not stricter than the payload. Each nullable live column carries a database comment saying so; SQLite ignores column comments, PostgreSQL and MySQL keep them. Microseconds are enough for the stale guard; two updates for one market within a microsecond do not happen on this feed.

## 6. Market type is stored as a plain string and not used

- **Decision**: `market_type` is a string column holding the provider value as received (`ATS`, `CONNECT`, `CRYPTO`, `FUND` on UAT). No enum, no cast, no scope, no UI. Nothing reads it.
- **Considered**: a backed `MarketType` enum.
- **Why**: simplicity. No code branches on the value, the set belongs to the provider and may grow, and an enum would throw on a fifth value for no benefit. It is stored so the data is there if a feature ever filters by type.

## 7. REST base URL stays on the service path

- **Decision**: `services.figure_markets.rest_url` keeps `https://www.figuremarkets.dev/service-hft-exchange/api/v1` (production: the same path on `www.figuremarkets.com`). The documented public gateway `https://api.figuremarkets.dev/public` is noted in the API doc but not used.
- **Considered**: switching to the documented gateway, which serves identical data under `/v1/markets`.
- **Why**: the service path is the agreed integration target for this work and the UAT host is the agreed default. Both bases return the same payload, so switching later is a one-line config change with no code impact.

## 8. Untraded markets keep the provider's zero price

- **Decision**: a market that has never traded arrives with `lastTradedPrice` `"0"`, `volume24h` `"0"` and `tradeCount24h` `0`. The payload stores these as received; `last_price` is `0`, not null. How a zero price is displayed is a UI decision for the market view.
- **Considered**: normalizing `"0"` to null so the UI's "no data" path handles it.
- **Why**: zero is what the provider says and is a real state (`USDC-USD` on UAT, `USDC-USDT` on production). Turning it into null would hide it and make the stored row differ from the provider. Null stays reserved for data the provider did not send, per decision 5.

## 9. Factory states and test placement for the market model

- **Decision**: `MarketFactory` defaults to an open market with random prices, bid and ask. States: `closed()`, `withoutOrderBook()` (null bid and ask), `untraded()` (zero last price, volume and trade count, as the provider sends it). Payload tests are unit tests fed by the captured fixtures through a `jsonFixture()` helper in `tests/Pest.php` (Pest's own `fixture()` resolves the path). Model tests are feature tests with the database. Variations the provider has not been observed to send are produced in the test by altering a real fixture record; no fabricated fixture files.
- **Considered**: fabricating fixture files for closed, halted and malformed payloads; skipping those cases.
- **Why**: fixtures stay a faithful capture of the provider, and the altered-record tests exist only to prove documented decisions (1 and 3). The string `tradeCount24h` case is not tested because the int cast is framework behaviour.

## 10. WebSocket payloads write live columns only

- **Decision**: `WebSocketMarketPayload` carries `symbol`, the live fields and `publishedAt`. Its `toAttributes()` returns the live columns and `price_updated_at`, never an identity column. `status` and `pricePrecision` on a WebSocket message are ignored; `RestMarketPayload` owns every identity column.
- **Considered**: also writing `status` and `price_precision` from WebSocket messages, with both as required keys that throw when missing.
- **Why**: clean ownership and no null risk. Decision 3 defaults any non-required key to null, and a null written into a required identity column would fail the upsert. Status changes between syncs are out of scope, since the statement of work excludes scheduled re-sync; a halted market still shows its last price, which is correct. The payload classes and the model are documented together in `docs/data-model.md` at the end of phase 2.

## 11. Market model stores dates with microseconds

- **Decision**: `Market` sets `#[Table(dateFormat: 'Y-m-d H:i:s.u')]` so `price_updated_at` keeps microseconds when written. The same format applies to `created_at` and `updated_at`.
- **Considered**: leaving Eloquent's default `Y-m-d H:i:s`, which silently truncated the timestamp to whole seconds in the first model test.
- **Why**: decision 5 requires microsecond precision for the stale guard, and the column is `timestamp(6)`; without the model format the precision existed only in the schema. One attribute on the model is the smallest change that makes writes match the column.
