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

No `.env` step and no extra flags: `compose.yaml` builds everything it needs and
the container generates its own `APP_KEY` on first boot.

### Local development

```bash
docker compose -f compose.yaml -f compose.dev.yaml up -d --wait
```

The dev overlay bind-mounts your checkout and runs Vite with HMR, so it needs
`composer install` and `npm install` to have been run on the host first. It is
deliberately **not** named `compose.override.yaml`: auto-loading it would make the
bare `docker compose up` above bind an empty `vendor/` over the image on a fresh
clone and break the quickstart.

## Status

- [![tests](https://github.com/32thePharaoh/hookscope/actions/workflows/tests.yml/badge.svg)](https://github.com/32thePharaoh/hookscope/actions/workflows/tests.yml)
- License: MIT
- Static analysis: Larastan **level 7**
