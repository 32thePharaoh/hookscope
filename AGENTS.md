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

- Capture URL stays `/in/{token}` on a dedicated `routes/capture.php` stack (no CSRF, no session). Not `routes/api.php`. A capture response must set zero cookies; moving the route into `web.php` with a CSRF exception would still start a session per webhook. Record GET/POST/PUT/PATCH/DELETE; skip HEAD and OPTIONS without inserting a row.
- Persist the capture first; return 200 even if the queue is down.
- Multipart bodies on `/in/` must hit nginx `enable_post_data_reading=off` so `getContent()` is the real bytes. Do not set that ini globally.
- Queue and cache use Redis. Broadcast uses Reverb over the compose network (`reverb:8080`). nginx proxies `/app/` same-origin; `/apps` stays internal (no `ports:` on the reverb service). Shared Inertia props carry only `reverbKey`, never the secret. Client Echo derives host/port/TLS from `window.location`.
- The entrypoint generates `APP_KEY` for command services (worker, scheduler, reverb), not only FPM.
- Encode at every JSON boundary (headers, Inertia props, broadcasts, replay snippets). Capture bodies stay `longblob` + `body_encoding`. Headers are stored as `array<string, list<string>>`. Replay `response_snippet` is always base64 at that boundary — no `response_snippet_encoding` column.
- Replay goes through the PHP SSRF guard. Do not reimplement replay in another language.
- Clone-and-run seeds a demo user on first boot (`demo@hookscope.test` / `password`) and a `Demo` endpoint. Guard on `User::query()->exists()`.
- `hookscope:demo-traffic` posts through `http://nginx` (not `APP_URL` / localhost). Reuse the Demo endpoint, clear its limiter, keep the burst under 120/min. An oversized 413 is a passing guard check.
- Dashboard capture URLs are `window.location.origin + '/in/' + token`, never `config('app.url')`. Clipboard write has an execCommand fallback for non-secure contexts (http://LAN).
- Capture list queries must not select `body` (or `headers`). Encode the body server-side keyed off `body_encoding` before it becomes an Inertia prop. Scope endpoints through `$request->user()->endpoints()`, not implicit route-model binding.
- `CaptureDropCounter::count()` returns 0 if the cache is down — same fail-open posture as capture.
- Live capture inserts sort by `(received_at, id)` descending, matching the list query. Channel names use the endpoint id, never the capture token. `broadcastWith()` is list metadata only. Channel auth is its own HTTP test (`POST /broadcasting/auth`); Phase 6 scoping does not cover websockets.
- Replay goes through `$user->endpoints()` then the captured request. `response_snippet` is stored already base64 (no encoding column). The SSRF validator is a class: injectable resolver, `ForbiddenIp` as pure functions, pin with `CURLOPT_RESOLVE` as `host:port:ip` using the URL's port. Connect timeout plus total timeout; cap the body by reporting the full chunk as consumed and discarding past the cap (returning 0 from the write callback is `CURLE_WRITE_ERROR` and loses `status_code`). Content-Type (and any forwarded header) comes from the headers JSON, not the lossy `content_type` column. Auth-bearing headers are off unless opted in. `HOOKSCOPE_ALLOW_PRIVATE_TARGETS` is the local demo hatch; Pest uses `Http::fake()`; smoke must not call a real external host. A 301 is a recorded status, not a bug. `error` stays ASCII — never interpolate response bytes into it.
- `hookscope:prune` deletes on `received_at` (the `(endpoint_id, received_at)` index), never `created_at`. Use `chunkById()`, not `chunk()` — offset pagination skips rows as deletes shift the window. Keep the chunk small; cascade deletes replays in the same statement. Exact cutoff (`received_at === now - retention_days`) is kept (`<`, not `<=`). If the Redis lock cannot be acquired or throws, skip the run (fail-open: a missed prune is harmless). Do not put `withoutOverlapping()` on the Schedule event.
- Queue healthcheck is `grep -q queue: /proc/1/cmdline`. Scheduler healthcheck is `grep -q schedule: /proc/1/cmdline`. Do not copy either onto the other service. The scheduler must pass `command: ['php', 'artisan', 'schedule:work']` — the entrypoint's no-command path migrates, seeds, and execs php-fpm. Do not wrap that command in `sh -c`; PID 1 would be `sh` and the healthcheck would never match.
- Larastan level 7 includes `tests/`. Do not drop that path. Do not add a coverage threshold gate.

## Reviews

Load-bearing phases before merge: 2 (done), 4, 7, 8, 10. Skip a full design review for schema/docs nits.
