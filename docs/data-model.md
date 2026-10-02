# Data model

What the `markets` table and the payload classes mean, beyond what the migration, model and factory show. Reasoning lives in the [design decisions](../specs/design-decisions.md); provider facts in [figure-markets-api.md](figure-markets-api.md).

## Ownership

One row per market. Identity columns (`symbol` through `price_precision`) describe the market and are written by the REST sync only. Live columns (`last_price` through `price_updated_at`) are written by the REST sync and by every WebSocket update. `price_updated_at` comes from WebSocket `publishTime` alone and is never set from REST; it drives the stale guard.

## Column semantics

| Column | Provider field | Meaning |
|--------|----------------|---------|
| `symbol` | `symbol` (REST), `marketId` (WebSocket) | Identifies the market across both feeds |
| `base_asset`, `quote_asset` | `denom`, `quoteDenom` | |
| `market_type` | `marketType` | Stored as received, not used ([decision 6](../specs/design-decisions.md)) |
| `status` | `status` | `open` or `closed`; the provider's nine other statuses all map to `closed` ([decision 1](../specs/design-decisions.md)) |
| `last_price` | `lastTradedPrice` | `0` for a market that never traded ([decision 8](../specs/design-decisions.md)) |
| `best_bid`, `best_ask` | `bestBid`, `bestAsk` | Null when the market has no order book |
| `percentage_change_24h` | `percentageChange24h` | A ratio, not a percent: `-0.05` is minus five percent |
| `volume_24h` | `volume24h` | In the quote asset |

Null in any live column means the provider sent nothing ([decision 5](../specs/design-decisions.md)). The database comments on those columns say the same; SQLite drops comments, PostgreSQL and MySQL keep them.

Decimal casts return strings with 18 places, for example `0.020000000000000000`. Dates keep microseconds ([decision 11](../specs/design-decisions.md)).

## Payloads

`RestMarketPayload` and `WebSocketMarketPayload` are independent on purpose, sharing only the value-coercion helpers in the `ReadsProviderValues` trait ([decision 2](../specs/design-decisions.md)). Each turns one decoded provider array into the columns it owns:

| | REST | WebSocket |
|---|---|---|
| Required keys, else `MalformedMarketPayload` | `symbol` | `marketId`, `publishTime` |
| Writes | identity and live columns, not `price_updated_at` | live columns and `price_updated_at` |
| Ignores | fields with no column | `status`, `pricePrecision` ([decision 10](../specs/design-decisions.md)) |

Every other absent key becomes null ([decision 3](../specs/design-decisions.md)). Numbers pass through as the provider's strings; the float `percentageChange24h` is converted to a string so no float reaches the database.
