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
- **Why**: callers decide what a bad payload means for them. The listener catches, logs and skips one message. `SyncMarketsAction` lets it bubble so a broken REST response fails the whole sync and keeps the existing rows. Null returns would spread the same check across every caller and lose the reason.

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

## 12. The REST client requests one page and returns raw records

- **Decision**: `RestClient::markets()` sends one `GET /markets?size=50` and returns the `data` array as received. `SyncMarketsAction` maps each record through `RestMarketPayload::fromRecord()`. The client knows only the response envelope; the payload class stays the only place that knows market field names.
- **Considered**: walking `pagination.totalPages` with a `LazyCollection`; returning `RestMarketPayload` instances from the client.
- **Why**: simplicity. UAT has 16 visible markets and production 17, both under the page cap of 50. Page walking is code without a case to exercise it, listed under later improvements in the architecture and the README. Returning payloads from the client would spread provider field knowledge across two classes.

## 13. Plain retry, no `Retry-After`

- **Decision**: `RestClient` uses `Http::baseUrl()->timeout(10)->retry(3, 500)`. Timeout and retry values are constants on the class. `retry()` throws `RequestException` itself on the last failed attempt, so no `throw()` call. A 429 fails the sync like any other error once the retries are spent; existing rows stay and the caller logs. Laravel's `ConnectionException` and `RequestException` bubble unwrapped.
- **Considered**: a sleep closure on `retry()` that reads `Retry-After` on 429; a domain `ProviderUnavailable` exception wrapping both.
- **Why**: the statement of work asks (R9) that a REST failure keeps existing data and is logged, nothing about rate-limit headers. Sync runs once at listener start and on demand, and the listener's backoff already spaces retries on an empty table. A wrapper exception would have no caller branching on it.

## 14. Sync normalizes everything first, then writes each market through the model

- **Decision**: `SyncMarketsAction::handle(): int` maps all records to payloads before the first write, so a `MalformedMarketPayload` fails the sync with the table untouched (decision 3). Each payload is written with `Market::updateOrCreate(['symbol' => ...], $attributes)`, then one `update` sets `status = closed` on open markets whose symbol is absent from the response. Both run inside one `DB::transaction()`, so a database constraint failure on a record that has a `symbol` but lacks another required column rolls back every write. Returns the number of records written. No logging inside the action; the command and the listener decide what a failure means. A 200 with zero records closes every market.
- **Considered**: one `Market::upsert()` by `symbol`; catching `MalformedMarketPayload` per record, logging and skipping it; a result object with synced and closed counts; a guard that treats zero records as a failure.
- **Why**: `updateOrCreate` fires model events and writes through the model, which a bulk upsert skips; sixteen rows do not need one statement. Skipping a malformed record would silently close that market in the next step, so failing the whole sync is the honest outcome. The command prints only the synced count, so an int is enough. The provider has never returned an empty list; guarding it is handling an edge case without evidence. Both `laravel-simplifier` passes, on the implementation and on the tests, returned no changes: the map, write, close order is load-bearing and the tests already share the fixture helper.

## 15. `market:sync` catches, reports and exits non-zero

- **Decision**: the command calls the action, prints `Synced N markets.` and returns success. On any `Throwable` it calls `report()`, prints one error line and returns failure.
- **Considered**: letting the exception propagate to Laravel's handler, which also logs and exits 1.
- **Why**: decision 3 puts the failure decision with the caller. Catching in the command mirrors what the listener does in phase 4 (catch, log, back off), and gives a one-line message instead of a stack trace.

## 16. Every accepted WebSocket update writes the row and broadcasts

- **Decision**: the listener writes and dispatches `MarketUpdated` for every message that passes the stale guard, with no comparison of the live columns against the stored row. Each message carries a new `publishTime`, so `price_updated_at` always changes and the row is always dirty.
- **Considered**: skipping the write and the broadcast when only the timestamp changed.
- **Why**: the provider sends a message only when market state changes, so duplicates are rare, and the timestamp is itself the "last updated" value the UI shows. A change detector would save one re-render at the cost of a column-by-column comparison.

## 17. Message handling is an action, not an observer

- **Decision**: `App\Actions\HandleMarketUpdateAction::handle(array $message): ?Market` normalizes the message with `WebSocketMarketPayload`, loads the row by symbol, applies the stale guard, calls `$market->update()`, then dispatches `MarketUpdated` and returns the market. It catches `MalformedMarketPayload`, logs a warning with the raw message and returns null; a stale message or a symbol not in the table is logged and returns null the same way. The command prints one line per written update and `Skipped a message, see the log for details.` for null, so the `artisan dev` listener tab shows the live feed while the reason for a skip stays in the log. Provider rejections (`{"message": "Invalid request", "code": 1}`) and any other unexpected shape take this path, since they lack `marketId`. The dispatch is wrapped in a `Throwable` catch that reports and continues, so a failed broadcast never costs the connection: the row is already current and the next page load is right (architecture failure table).
- **Considered**: dispatching from a model `updated` observer; a dedicated branch in the command for the provider's rejection shape.
- **Why**: the REST sync also updates rows and must never broadcast, and the architecture requires the row to be written before the event is dispatched. One action is the unit the socket loop hands each message to and the unit the tests drive with `Event::fake`. A rejection branch would guard against a message our own subscribe format never triggers; the malformed path already logs the provider's text.

## 18. Pawl stays inline in the command, the lifecycle is verified by hand

- **Decision**: `ListenToMarketsCommand` holds the connect, subscribe, ping timer, close and error handling directly against `ratchet/pawl`. The reconnect delay is a blocking `Sleep::for()` between iterations, since the connection is closed at that point. The backoff resets on the first delivered message rather than at the handshake, so a provider that accepts and immediately closes the connection is still backed off. A stop signal that arrives while the connect is pending closes the connection as soon as it resolves. Messages that are not a JSON object are logged and skipped in the command, before the action. The backoff arithmetic lives in `App\Services\FigureMarkets\Backoff` (`next()`, `reset()`), the only part of the lifecycle with a unit test. The socket lifecycle itself has no automated test; `composer run dev` is the check.
- **Considered**: a `MarketFeed` interface (`listen(symbols, onMessage)`, `stop()`) with a Pawl implementation, so the command's reconnect, resubscribe, backoff and SIGTERM handling could be tested against a mock.
- **Why**: simplicity. The interface would add two files and a container binding to test the loop, while the risky part, the Pawl calls, stays untested either way. The command is shaped so the extraction is one method later. The interface is the named improvement in the architecture and the README. Both `laravel-simplifier` passes returned no changes on the implementation; on the tests they collapsed paired range assertions into `toBeBetween()` and reused one `broadcastOn()` call.

## 19. The listener syncs on every connect

- **Decision**: each iteration of the listener loop runs `SyncMarketsAction` first, logs and continues on failure, then loads open markets. With no open markets it logs, sleeps the backoff and loops. Otherwise it connects and subscribes. The sync runs through `$this->call('market:sync')` rather than the action directly, so the success line and the failure handling exist once (decision 15). After a server close the next iteration re-syncs before reconnecting.
- **Considered**: syncing once at process start only; syncing on every loop iteration including failed connect attempts.
- **Why**: the 30-minute reconnect refreshes identity data for free, two REST calls an hour. Syncing on every attempt was the first implementation and would have made two REST calls a minute during a WebSocket outage at the 30-second backoff cap; one boolean on the command removes that.

## 20. `MarketUpdated` is built in phase 4 with its broadcast shape

- **Decision**: `App\Events\MarketUpdated` is created with the listener: `ShouldBroadcastNow`, `PrivateChannel('markets')`, `broadcastAs()` `market.updated`, `broadcastWith()` is `$market->toArray()`, which includes `id`. `broadcasting.connections.reverb.client_options` gets the 2-second `connect_timeout` and `timeout`. Phase 5 adds channel authorization, the component and the demo user.
- **Considered**: a plain event in phase 4, broadcasting added in phase 5.
- **Why**: the listener dispatches the event and its tests assert the dispatch, so the class belongs to this phase. Completing it here lets the listener be smoke-tested end to end against Reverb at the end of phase 4 instead of a phase later.
