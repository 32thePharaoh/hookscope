# Hookscope

Self-hosted webhook inspector and replayer. Unlike webhook.site, RequestBin, Beeceptor, or Hookdeck, **you keep the data** — ops-shaped capture, replay, and retention on your own box.

This is also a reference Laravel 13 application (Inertia 3, Vue 3, Pest, Larastan level 7).

> v1.0 README, screenshot, and GIF land in a later phase. This placeholder exists so the repo is safe to open.

## Quickstart

```bash
git clone git@github.com:32thePharaoh/hookscope.git
cd hookscope
docker compose up -d --wait
```

App: http://localhost · health: http://localhost/up

Demo login: `demo@hookscope.test` / `password`

```bash
docker compose exec app php artisan hookscope:demo-traffic
```

That command must run inside the app container so requests go through nginx
(`http://nginx`), not php-fpm. No `.env` step: `compose.yaml` builds everything
it needs, generates `APP_KEY` on first boot, and seeds the demo user.

### Local development

```bash
docker compose -f compose.yaml -f compose.dev.yaml up -d --wait
```

Bind-mounts your checkout and runs Vite with HMR, so it expects `composer install`
and `npm install` on the host first.

## Status

- [![tests](https://github.com/32thePharaoh/hookscope/actions/workflows/tests.yml/badge.svg)](https://github.com/32thePharaoh/hookscope/actions/workflows/tests.yml)
- License: MIT
- Static analysis: Larastan **level 7**
- Clone-and-run path smoke-tested on every PR: `docker-smoke.yml` boots `compose.yaml`
  with no `.env`, seeds, and drives real traffic through nginx
