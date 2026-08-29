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

- Capture URL stays `/in/{token}` on a dedicated `routes/capture.php` stack (no CSRF, no session). Not `routes/api.php`.
- Persist the capture first; return 200 even if the queue is down.
- Encode at every JSON boundary (headers, Inertia props, broadcasts, replay snippets). Capture bodies stay `longblob` + `body_encoding`. Replay `response_snippet` is always base64 at that boundary — no `response_snippet_encoding` column.
- Replay goes through the PHP SSRF guard. Do not reimplement replay in another language.
- Pest and Larastan **level 7** run against MySQL. The `ALTER TABLE ... LONGBLOB` migration is MySQL-only; do not add a SQLite test path.

## Reviews

Load-bearing phases before merge: 2 (done), 4, 7, 8, 10. Skip a full design review for schema/docs nits.
