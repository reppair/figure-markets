# 03 Market sync

The REST client, the `SyncMarketsAction` action and the `market:sync` command. Design: [architecture.md](../architecture.md) provider integration section, [design decisions](../design-decisions.md) 3 and 12 to 15. Provider facts: [docs/figure-markets-api.md](../../docs/figure-markets-api.md).

## Outcome

- `RestClient::markets()` returns the raw records of one `GET /markets?size=50` request.
- `SyncMarketsAction::handle()` normalizes every record, writes each market through the model, closes open markets absent from the response, returns the count written.
- `php artisan market:sync` prints the count and exits 0, or reports the exception and exits 1.
- A failed request or a malformed record leaves the table untouched.

## Flow

```
market:sync ─┐
             ├─▶ SyncMarketsAction::handle(): int
market:listen┘        │
                      ▼
            RestClient::markets()        GET /markets?size=50, timeout 10s, 3 retries
                      │                  error → ConnectionException / RequestException bubble
                      ▼
            records → RestMarketPayload::fromRecord() for all, before any write
                      │                  missing symbol → MalformedMarketPayload bubbles
                      ▼
            Market::updateOrCreate(['symbol'], toAttributes()) per payload
                      │
                      ▼
            Market::open()->whereNotIn('symbol', ...)->update(['status' => closed])
                      │
                      ▼
            return count of payloads written
```

## Classes

| Class | Responsibility |
|-------|----------------|
| `App\Services\FigureMarkets\RestClient` | `markets(): array<int, array<string, mixed>>`. `Http::baseUrl(config('services.figure_markets.rest_url'))->timeout(self::TIMEOUT_SECONDS)->retry(self::RETRIES, self::RETRY_DELAY_MILLISECONDS)->get('/markets', ['size' => 50])`, returns `json('data')`. `retry()` throws on the last failed attempt, so no `throw()`. Knows only the envelope (decision 12, 13). |
| `App\Actions\SyncMarketsAction` | Constructor injects `RestClient`. `handle(): int` as in the flow above, writes inside `DB::transaction()`. No logging, no catching (decision 14). Never writes `price_updated_at`, which `RestMarketPayload::toAttributes()` does not contain. |
| `App\Console\Commands\SyncMarketsCommand` | Signature `market:sync`, description "Fetch the market list from Figure Markets and update the markets table". Calls the action, `info("Synced {$count} markets.")`, returns `self::SUCCESS`. Catches `Throwable`: `report($e)`, `error('Market sync failed: '.$e->getMessage())`, returns `self::FAILURE` (decision 15). |

## Tests

| File | Covers |
|------|--------|
| `tests/Feature/Services/FigureMarkets/RestClientTest.php` | `Http::fake` with `markets.json`: returns the 16 records; request goes to the configured base URL with `size=50`; a 500 on every attempt throws `RequestException` after 3 attempts (`Http::assertSentCount`, `Sleep::fake()`); a connection failure throws `ConnectionException` |
| `tests/Feature/Actions/SyncMarketsActionTest.php` | `$this->mock(RestClient::class)` returning fixture records: creates 16 rows with the first record's attributes and returns 16; an existing row is updated and keeps its `price_updated_at`; an open market absent from the response becomes closed; a closed market present in the response as `OPEN` becomes open; a record without `symbol` throws and no row is written or closed; a client exception bubbles and rows stay; an empty response closes every market; a record failing a NOT NULL constraint rolls back every write |
| `tests/Feature/Console/SyncMarketsCommandTest.php` | Mocked action returning 16: output contains `Synced 16 markets.`, exit 0; mocked action throwing: output contains `Market sync failed`, exit 1 |

Fixture variations (closed market, missing symbol) are made by altering a `markets.json` record in the test (decision 9). Mocking follows the rule to mock the service class rather than HTTP where the client is not the unit under test.

## Steps

| # | Step | Verify | Status |
|---|------|--------|--------|
| 1 | `php artisan make:class Services/FigureMarkets/RestClient`; write client with constants | `RestClientTest` passes | done |
| 2 | `php artisan make:class Actions/SyncMarketsAction`; write action | `SyncMarketsActionTest` passes | done |
| 3 | `php artisan make:command SyncMarketsCommand`; write command | `SyncMarketsCommandTest` passes; `php artisan market:sync` against UAT prints `Synced 16 markets.` | done |
| 4 | `laravel-simplifier` pass on implementation, then on tests | No findings left unapplied or logged in the decisions file | done |
| 5 | `composer test` | Pint, PHPStan, Pest green | done |
| 6 | `update-docs`: add `docs/market-sync.md` with a Mermaid version of the flow, what a sync does and does not touch, failure behaviour, exit codes, the pagination and `Retry-After` gaps; link from README | Doc present, linked | done |

## Out of scope

- Calling the sync from the listener and the empty-table retry loop (phase 4).
- Scheduled re-sync, pagination walking, `Retry-After` handling (README improvements).
