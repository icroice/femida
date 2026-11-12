# Deployment

Femida is a single Docker container: FrankenPHP serves the app, SQLite keeps the
data, screenshots live on the local disk. No external services required.

## Install

```bash
git clone https://github.com/<you>/femida.git && cd femida
docker compose up -d --build
```

Open `http://localhost:8080` — the first run shows the **setup wizard**
(environment checks → admin account → done). After the first user is created the
wizard closes forever.

Registration and password reset are intentionally absent from the UI: the admin
creates users and resets passwords in the admin section (a new password is shown
once).

## Configuration

Everything is optional; defaults fit a local or single-server install.

| Variable | Default | Purpose |
|---|---|---|
| `FEMIDA_PORT` | `8080` | Host port for the web UI |
| `APP_URL` | `http://localhost:8080` | Public URL (links, API base for agents) |
| `FEMIDA_APP_KEY` | *(generated)* | Fixed `APP_KEY` to keep sessions/remember-cookies across container recreates. Set it once (e.g. `base64:…` from `php artisan key:generate --show`) in `.env` next to `docker-compose.yml` — without it a recreated container generates a new key and everyone is logged out. |

When running behind a reverse proxy, set `APP_URL` to the public origin agents
will use.

## Single node only

Femida is designed as a **single app container per instance**:

- SQLite on a local volume — multiple writers from several containers will
  contend (`database is locked` under load, WAL only mitigates it);
- the entrypoint runs `php artisan migrate --force` on every start — with two
  containers booting simultaneously the migrations race.

Do not scale the `app` service (`--scale app=2`) or point two instances at the
same volume. Vertical scaling and `restart: unless-stopped` cover the intended
use. The image ships a `HEALTHCHECK` against `/up`, so an unhealthy container
is visible via `docker compose ps`.

## TLS / running behind a reverse proxy

Two supported modes:

- **Local / plain HTTP** (default, `http://localhost:8080`): nothing to do —
  keep `SESSION_SECURE_COOKIE=false` (the `.env.example` default), otherwise
  the browser will not send session cookies and login breaks.
- **Behind an HTTPS reverse proxy** (nginx/Caddy/traefik terminating TLS in
  front of the container): set `SESSION_SECURE_COOKIE=true` in the container
  environment so session/remember cookies are only sent over HTTPS, and enable
  `Strict-Transport-Security` handling by trusting the proxy (`X-Forwarded-Proto`;
  see Laravel's `TrustProxies` — not preconfigured, fork and adjust if your
  proxy setup needs it).

Keep it simple: the project targets personal/small-team self-hosting, both
plain-HTTP LAN and HTTPS-behind-proxy. Exotic setups are a fork away.

## Where the data lives

The named volume `femida-data` is mounted at `/app/storage/app`:

```
storage/app/database.sqlite     single SQLite database (WAL mode)
storage/app/screenshots/        screenshot files
```

Nothing is ever deleted automatically — Femida keeps evidence forever. Disk
growth is the instance owner's concern; deleting a project in the UI removes its
cases, sessions and screenshots in one go.

**Backup** = the volume: `database.sqlite` plus `screenshots/` (stop the
container or use `sqlite3 .backup` for a consistent copy).

## Updates

```bash
git pull
docker compose up -d --build
```

The container entrypoint runs `php artisan migrate --force` on start, so schema
updates apply automatically. Restore the volume from backup if you need to roll
back — no downgrade migrations are shipped.

## Operation notes

- Logs: `docker compose logs -f app`
- The container is `restart: unless-stopped`
- Data persists across rebuilds (volume), but **not** across `docker compose down -v`
