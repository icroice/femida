# `femida.yaml` — boot config for the tested repository

`femida.yaml` lives in the root of the repository under test and is versioned with
the code. The agent skill reads it **from the worktree of the commit being
tested** — a "before" run uses the config of its own era.

## Reference

```yaml
version: 1

app:
  start: "php artisan serve --port=%PORT%"       # required; %PORT% is substituted by the skill
  ready: "curl -sf http://127.0.0.1:%PORT%/up"   # required; 60s timeout
  base_url: "http://127.0.0.1:%PORT%"            # required
  env: { APP_ENV: testing }                      # optional
  stop: null                                     # optional; default: kill the process group

seed: "php artisan db:seed --class=QaSeed"       # optional
auth: { login: qa@example.test, password: secret }  # optional; used in cases as ${auth.login}
browser: { viewport: [1280, 800], locale: ru }
run_order: after_first
```

## Fields

| Field | Required | Notes |
|---|---|---|
| `version` | yes | Currently `1` |
| `app.start` | yes | Command that boots the app. `%PORT%` is replaced with a free port picked automatically (search starts at 8123) |
| `app.ready` | yes | Shell check polled until it succeeds or 60s pass. On timeout the run is marked `failed` with the error text |
| `app.base_url` | yes | Where the browser points |
| `app.env` | no | Extra environment variables for `start` |
| `app.stop` | no | Custom stop command; by default the skill kills the whole process group |
| `seed` | no | Data seeding command; runs after each side boots, so "before" and "after" work on identical data. If the seed is incompatible with old code — that is an honest `failed` before-run |
| `auth` | no | Credentials referenced from case steps as `${auth.login}` / `${auth.password}` |
| `browser.viewport` | no | `[width, height]`, default `[1280, 800]` |
| `browser.locale` | no | Browser locale, default `en` |
| `run_order` | no | `after_first` (default) or `before_first` — see [skill.md](skill.md) |

## Tips

- `ready` should be the cheapest possible proof the app serves traffic (a health
  route, a static 200 page) — it is polled repeatedly.
- The seed must be **idempotent** or reset the data first: it runs before every
  run, not once per session.
- Keep `femida.yaml` working on old commits too: the "before" side executes the
  config of its own commit.
