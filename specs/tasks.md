# Tasks

Build order for the design in [architecture.md](architecture.md). One feature spec per phase under `specs/features/`, written just before the phase starts. Status: `todo`, `in progress`, `done`. Phases 2 to 5 each end with a document under `docs/`; phase 6 is the README itself.

| # | Phase | Spec | Status |
|---|-------|------|--------|
| 1 | Preparation: broadcasting, provider client, config, fixtures | [features/01-preparation.md](features/01-preparation.md) | done |
| 2 | Market model, table, factory, REST and WebSocket payloads | [features/02-market-model.md](features/02-market-model.md) | done |
| 3 | `RestClient`, `SyncMarketsAction`, `market:sync` | [features/03-market-sync.md](features/03-market-sync.md) | done |
| 4 | `market:listen` provider WebSocket listener, `MarketUpdated` event | [features/04-market-listen.md](features/04-market-listen.md) | done |
| 5 | Channel auth, `MarketWatch` component, demo user | features/05-market-watch.md | todo |
| 6 | README: run, assumptions, gaps, improvements | features/06-readme.md | todo |
