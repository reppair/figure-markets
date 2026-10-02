# Figure Markets API

What the application uses from the public Figure Markets API, how it is configured, and what was verified against the UAT environment on 2026-10-02. Requirement IDs refer to the [statement of work](../specs/statement-of-work.md). Provider documentation: https://www.figuremarkets.dev/api-docs/public-api/

## Configuration

| Config key | Environment variable | Default |
|------------|----------------------|---------|
| `services.figure_markets.rest_url` | `FIGURE_MARKETS_REST_URL` | `https://www.figuremarkets.dev/service-hft-exchange/api/v1` |
| `services.figure_markets.ws_url` | `FIGURE_MARKETS_WS_URL` | `wss://www.figuremarkets.dev/service-hft-exchange-websocket/ws/v1` |

Defaults point at UAT and live in `config/services.php`, so nothing is needed in `.env` to run locally. Production uses the same paths on `www.figuremarkets.com`. The API is public, unauthenticated and rate-limited.

## REST: `GET /markets` (R1)

- Response: `{ "data": Market[], "pagination": { "page", "size", "totalPages", "totalCount" } }`.
- UAT has 16 markets, all `OPEN`, returned on one page (`size` 16, `totalPages` 1).
- `id` equals `symbol`. Symbols may contain dots, for example `figure.forge.plmjuygukj-USD`.
- Fields the application reads: `symbol`, `displayName`, `denom`, `quoteDenom`, `marketType`, `status`, `pricePrecision`, `lastTradedPrice`, `bestBid`, `bestAsk`, `priceChange24h`, `percentageChange24h`, `high24h`, `low24h`, `volume24h`, `tradeCount24h`.
- `bestBid` and `bestAsk` exist only for markets with an order book (4 of 16 on UAT). Treat them as nullable.
- Records carry no timestamp. REST data never sets `price_updated_at`.
- Prices and volumes are decimal strings with up to 18 fractional digits.

## WebSocket (R2)

Connect to the `ws_url`, then send one text message per market:

```json
{ "action": "SUBSCRIBE", "channel": "MARKET", "channelUuid": "<uuid>", "symbol": "HASH-USD" }
```

```json
{ "action": "UNSUBSCRIBE", "channelUuid": "<uuid>" }
```

Rules, each verified against UAT:

| Rule | Detail |
|------|--------|
| `channelUuid` | Must be a UUID. Any other unique string is rejected. |
| Keepalive | A WebSocket ping frame (opcode 9) at least every 30 seconds; the server answers with a pong frame. Text messages such as `PING` or `{"action": "PING"}` are rejected. |
| Session cap | The server closes the connection after 30 minutes. |
| Subscriptions | At most 50 channels per connection. |
| Rejection | Rejected messages receive `{"message": "Invalid request", "code": 1}` and the connection stays open. |
| Delivery | A snapshot is sent right after subscribing, then an update whenever market state changes. `HASH-USD` updates about once per second; most UAT markets are quiet. |

Update message:

- Fields the application reads: `channelUuid`, `marketId`, `lastTradedPrice`, `bestBid`, `bestAsk`, `priceChange24h`, `percentageChange24h`, `high24h`, `low24h`, `volume24h`, `tradeCount24h`, `pricePrecision`, `publishTime`.
- `marketId` equals the REST `symbol`.
- `bestBid` and `bestAsk` are absent for markets without an order book.
- `publishTime` is an RFC 3339 timestamp with nanoseconds, for example `2026-10-02T10:37:54.427234574Z`. It drives the listener's stale-update guard.
- Also present and ignored: `midMarketPrice`, `indexPrice`, `exchangePrice`, `inRegularTradingHours`, `status`, `channel`.

With `ratchet/pawl`, a ping frame is `$connection->send(new Frame('', true, Frame::OP_PING))` and the pong arrives as a `pong` event.

## Fixtures

Captured from UAT into `tests/Fixtures/figure-markets/` with a throwaway Pawl script that is not part of the repository.

| File | Source | Use |
|------|--------|-----|
| `markets.json` | `GET /markets`, full page of 16 markets | `MarketPayload` REST normalization, `SyncMarkets` with `Http::fake`, pagination shape |
| `market-snapshot.json` | First `MARKET` message for `HASH-USD`, with `bestBid` and `bestAsk` | `MarketPayload` WebSocket normalization, update handling |
| `market-update.json` | Second `MARKET` message for `HASH-USD`, later `publishTime` | Stale guard and broadcast tests, paired with the snapshot |
| `market-snapshot-no-book.json` | First `MARKET` message for `FIGR_HELOC-USD`, no `bestBid` or `bestAsk` | Nullable bid and ask handling |

The `channelUuid` values in the fixtures are the ones used during capture and have no meaning beyond matching a subscription.
