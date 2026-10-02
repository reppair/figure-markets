# 01 Preparation

Install and verify everything the feature phases depend on. No application code yet.

## Outcome

- Reverb is the application WebSocket server, Echo connects from the dashboard.
- `ratchet/pawl` is installed as the provider WebSocket client.
- Provider URLs are configurable and default to UAT.
- Real provider payloads are captured as test fixtures.
- Quality checks pass.

## Steps

| # | Step | Verify | Status |
|---|------|--------|--------|
| 1 | Install broadcasting with Reverb: `php artisan install:broadcasting --reverb`. Run in a real terminal; the Node step needs a TTY. | `config/broadcasting.php`, `routes/channels.php`, `resources/js/echo.js` exist; Reverb env vars in `.env` and `.env.example`. | done |
| 2 | `composer require ratchet/pawl` | Resolves alongside Reverb on PHP 8.4. | done |
| 3 | `config/services.php`: `figure_markets.rest_url`, `figure_markets.ws_url` default to the UAT URLs, overridable by `FIGURE_MARKETS_REST_URL`, `FIGURE_MARKETS_WS_URL`. Nothing in `.env.example`. | `php artisan config:show services.figure_markets` | done |
| 4 | `composer run dev` | `reverb:start` runs; dashboard loads; Echo connects (browser console, Reverb output). | done |
| 5 | Capture fixtures into `tests/Fixtures/figure-markets/`: `markets.json` (one `GET /markets` page), `market-snapshot.json` and `market-update.json` (HASH-USD, with bid and ask), `market-snapshot-no-book.json` (a market without an order book). Capture script is throwaway, not committed. | Files present, valid JSON, field names match [statement-of-work.md](../statement-of-work.md). | done |
| 6 | `composer test` and `npm run build` | Green. | done |
| 7 | README "Development": list the processes `composer run dev` starts. | README updated. | done |

## Notes

- Dependency changes are approved per step by the user.
- Reverb 1.x needs `guzzlehttp/psr7` 2.x. The installer updates with all dependencies, which downgrades Guzzle 8 to 7. Laravel 13 accepts both.
- `reverb:install` writes Reverb variables to `.env` only; `.env.example` is updated by hand with the same local-only values.
