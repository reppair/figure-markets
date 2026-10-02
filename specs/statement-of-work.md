# Statement of Work

## Purpose

A small web application that shows real-time cryptocurrency market information from the public Figure Markets API. A signed-in user selects a market and sees its current price, which updates live as trades happen.

## Scope and effort

- Backend-focused. The frontend only needs to be functional; visual design is not a goal.
- Pragmatic implementation within roughly four hours of effort. Not production-complete.
- Assumptions, gaps and improvements that would be made with more time are documented in the README.
- The market view sits behind the existing login. A demo user is seeded for local use so the application can be used right after setup; the README states the credentials and marks them local-only.
- Built and run locally on the existing stack (Laravel 13, Livewire 4, Flux UI, Tailwind CSS 4, Pest 5). Deployment is not a deliverable.

## Functional requirements

| ID | Requirement |
|----|-------------|
| R1 | The backend retrieves the list of available markets from the Figure Markets REST API (`GET /v1/markets`). The browser obtains this list from the application, never from Figure Markets directly. |
| R2 | The backend establishes a WebSocket connection to Figure Markets and subscribes to market updates on the `MARKET` channel. |
| R3 | For the selected market the UI shows at minimum the last traded price. Best bid, best ask, 24h volume, 24h high and 24h low may be shown as well. |
| R4 | The backend normalizes external payloads into the application's own shape. The frontend never consumes the provider's raw format. |
| R5 | When new market information arrives, connected clients receive it in real time through the application's own WebSocket. |
| R6 | The frontend must not poll the backend for market updates. |
| R7 | The browser must not connect to the Figure Markets WebSocket. |
| R8 | The UI lets a user select a market, see its current price, and see price changes without reloading the page. |

## Non-functional requirements

| ID | Requirement |
|----|-------------|
| R9 | External REST calls and WebSocket connections can fail or disconnect. The application handles these cases in a way appropriate to its scope: it keeps working with the data it has, recovers automatically where possible, and logs what happened. |
| R10 | Automated tests cover the parts of the solution that matter most. Exhaustive coverage is not expected. |

## Data flow

```
Figure Markets REST ──▶ Backend ──▶ Frontend

Figure Markets WebSocket ──▶ Backend ──▶ Application WebSocket ──▶ Frontend
```

## External interface

Public, unauthenticated and rate-limited. Use in line with the provider's terms of service. Details below come from the provider's public API documentation (`https://www.figuremarkets.dev/api-docs/public-api/`). The documentation gives REST paths relative to an unstated base URL; the base URLs listed here were verified by calling them.

### REST

| Environment | Base URL |
|-------------|----------|
| UAT | `https://www.figuremarkets.dev/service-hft-exchange/api/v1` |
| Production | `https://www.figuremarkets.com/service-hft-exchange/api/v1` |

`GET /markets` returns `{ "data": Market[], "pagination": { "page", "size", "totalPages", "totalCount" } }`. Relevant market fields: `symbol`, `displayName`, `denom`, `quoteDenom`, `marketType`, `status`, `pricePrecision`, `lastTradedPrice`, `bestBid`, `bestAsk`, `priceChange24h`, `percentageChange24h`, `high24h`, `low24h`, `volume24h`, `tradeCount24h`.

### WebSocket

| Environment | URL |
|-------------|-----|
| UAT | `wss://www.figuremarkets.dev/service-hft-exchange-websocket/ws/v1` |
| Production | `wss://www.figuremarkets.com/service-hft-exchange-websocket/ws/v1` |

Subscribe:

```json
{ "action": "SUBSCRIBE", "channel": "MARKET", "channelUuid": "<uuid>", "symbol": "ETH-USD" }
```

Unsubscribe:

```json
{ "action": "UNSUBSCRIBE", "channelUuid": "<uuid>" }
```

Update message fields: `channelUuid`, `marketId`, `lastTradedPrice`, `bestBid`, `bestAsk`, `priceChange24h`, `percentageChange24h`, `high24h`, `low24h`, `volume24h`, `tradeCount24h`, `pricePrecision`, `publishTime`.

Connection rules:

- A snapshot is sent immediately after subscribing, then updates only when market state changes.
- The client must send a `PING` at least every 30 seconds.
- Sessions are closed by the server after 30 minutes.
- At most 50 channel subscriptions per connection.

## Deliverables

1. Source code in this repository.
2. README with instructions for running the solution, assumptions made, anything left incomplete, and what would be changed or improved with more time.

## Out of scope

- Provider data beyond `GET /markets` and the `MARKET` WebSocket channel (order book, candles, trades).
- Authentication beyond the existing registration and login.
- Historical price storage or charts.
- Scheduled re-sync of the market list; the list is synced when the backend listener starts.
- Deployment, beyond notes in the README.

## Acceptance criteria

| ID | Verified by |
|----|-------------|
| R1 | Market list in the UI comes from the application's database, populated by a backend sync from the REST API. Tests fake the HTTP layer. |
| R2 | A backend process connects to the WebSocket and subscribes to every open market. |
| R3 | Selected market view shows last traded price, bid, ask, 24h high, low and volume. |
| R4 | A single normalizer maps provider fields to application attributes; broadcast payloads contain the market id and application attributes only. |
| R5 | A broadcast event per update reaches the browser over the application's WebSocket server. |
| R6 | No `wire:poll`, timers or repeated fetches in the frontend. |
| R7 | No provider WebSocket URL appears in frontend code or configuration exposed to the browser. |
| R8 | Changing the selected market shows that market; price changes appear without reload. |
| R9 | WebSocket drop triggers reconnect and resubscribe; REST failure keeps existing data; errors are logged. |
| R10 | Tests cover normalization, sync, update handling, broadcast event and the Livewire component. |
