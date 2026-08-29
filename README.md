# Hookscope

Self-hosted webhook inspector and replayer. Unlike webhook.site, RequestBin, Beeceptor, or Hookdeck, **you keep the data** — ops-shaped capture, replay, and retention on your own box.

This is also a reference Laravel 13 application (Inertia 3, Vue 3, Pest, Larastan level 7).

> v1.0 README, screenshot, and GIF land in a later phase. This placeholder exists so the repo is safe to open.

## Quickstart

```bash
cp .env.example .env
docker compose -f compose.yaml up -d --wait
```

App: http://localhost  ·  health: http://localhost/up

`compose.override.yaml` is for local development (bind mount + Vite HMR). CI and the clone-and-run path pass `-f compose.yaml` so the override is never applied.

## Status

- [![tests](https://github.com/32thePharaoh/hookscope/actions/workflows/tests.yml/badge.svg)](https://github.com/32thePharaoh/hookscope/actions/workflows/tests.yml)
- License: MIT
- Static analysis: Larastan **level 7**
