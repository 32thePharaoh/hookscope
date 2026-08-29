# Hookscope agent notes

Architecture lives in `~/.cursor/plans/hookscope_webhook_inspector_826071af.plan.md`. Do not re-derive it. Do not start Phase 12 (AI/MCP) until v1.0 is tagged.

## Git

- Remote is SSH: `git@github.com:32thePharaoh/hookscope.git`. Do not switch to HTTPS to unstick a push.
- `gh` is the GitHub API only (`gh pr create`, `gh repo` ). Do not run `gh auth login --insecure-storage` or `--skip-ssh-key`.
- `main` stays green. Phase work goes on `phase-N-*` branches and merges via PR. Do not force-push `main`.
- Never commit `.env`. `.env.example` must stay tracked (`!.env.example` in `.gitignore`).

## Docker

- Clone-and-run: `docker compose -f compose.yaml up -d --wait`. CI smoke uses the same explicit `-f`.
- Local HMR: `docker compose -f compose.yaml -f compose.dev.yaml up -d --wait`. There is **no** `compose.override.yaml` on purpose — Compose would auto-load it and CI would have to remember to exclude it. Do not add one.
- `public/hot` is in `.dockerignore`. Do not bake a Vite dev pointer into the production image.

## Product invariants (v1.0)

- Capture URL stays `/in/{token}` on a dedicated `routes/capture.php` stack (no CSRF, no session). Not `routes/api.php`. Record GET/POST/PUT/PATCH/DELETE; skip HEAD and OPTIONS without inserting a row.
- Persist the capture first; return 200 even if the queue is down.
- Multipart bodies on `/in/` must hit nginx `enable_post_data_reading=off` so `getContent()` is the real bytes. Do not set that ini globally.
- Queue and cache use Redis. Broadcast stays `log` until Phase 7. The entrypoint generates `APP_KEY` for command services (worker, scheduler), not only FPM.
- Encode at every JSON boundary (headers, Inertia props, broadcasts, replay snippets). Capture bodies stay `longblob` + `body_encoding`. Headers are stored as `array<string, list<string>>`. Replay `response_snippet` is always base64 at that boundary — no `response_snippet_encoding` column.
- Replay goes through the PHP SSRF guard. Do not reimplement replay in another language.
- Clone-and-run seeds a demo user on first boot (`demo@hookscope.test` / `password`) and a `Demo` endpoint. Guard on `User::query()->exists()`.
- `hookscope:demo-traffic` posts through `http://nginx` (not `APP_URL` / localhost). Reuse the Demo endpoint, clear its limiter, keep the burst under 120/min. An oversized 413 is a passing guard check.
- Dashboard capture URLs are `window.location.origin + '/in/' + token`, never `config('app.url')`. Clipboard write has an execCommand fallback for non-secure contexts (http://LAN).
- Capture list queries must not select `body` (or `headers`). Encode the body server-side keyed off `body_encoding` before it becomes an Inertia prop. Scope endpoints through `$request->user()->endpoints()`, not implicit route-model binding.
- `CaptureDropCounter::count()` returns 0 if the cache is down — same fail-open posture as capture.

## Reviews

Load-bearing phases before merge: 2 (done), 4, 7, 8, 10. Skip a full design review for schema/docs nits.
