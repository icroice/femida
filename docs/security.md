# Security model

## Authentication

- **Web UI:** authentication is always on. No self-registration, no password
  reset in the UI — the admin creates users and resets passwords (a new password
  is shown once). Blocked users cannot log in; blocking also kills their active
  sessions and remember-cookies immediately, and a password reset invalidates
  the target's sessions. Login attempts are rate-limited (5 per minute per
  email + IP).
- **Agents:** a **project API key** (Bearer token). The key is always owned by a
  user, so every agent action — runs, steps, suggestions — is attributed.

## API keys

- Issued personally in the user's profile; the token is shown **once** at
  creation. Project owners and instance admins see **all keys of their project**
  in project settings and can revoke any of them.
- Stored as a SHA-256 hash; only the short prefix (`fem_ab12…`) is displayed
  afterwards for identification.
- Can be revoked; `last_used_at` is updated at most once a minute.
- Keys are never written to logs.
- A key stays valid only while its owner remains a project member with the
  developer role (or above) and is not blocked: removal, demotion, blocking or
  deletion of the owner deactivates the key on the next request.
- Rate limit: 240 requests per minute per key.
- A key grants access **to its project only**; other projects' resources return
  404 (no existence leak).

## Roles

| | Instance admin | Project owner | Developer | Tester |
|---|---|---|---|---|
| Projects | create, edit, delete any | own: settings, team | — | — |
| Case library | full access | own: create, edit, deactivate | own: create, edit, deactivate | own: create, edit |
| Sessions & review | full access | own: create, verdicts, full reset | own: create, verdicts, full reset | own: verdicts, attach cases, send for re-check |
| API keys | every project | own: manage | own: manage | — |
| Users | create, appoint owners, reset passwords, block | own projects: add developer/tester users | — | — |

The project owner keeps every developer right (case library, sessions, keys)
and adds project settings and the team: an owner sees the "Users" section
limited to their own projects and can add users with the developer or tester
role. Appointing other owners, resetting passwords and blocking users stay
admin-only.

Implemented with Laravel policies; the admin passes all checks via `Gate::before`.

## Screenshot storage and privacy

- Screenshots are stored **on the instance disk** (`storage/app/screenshots/`),
  served only to project members through an authenticated route.
- They are never sent anywhere else, and **nothing is deleted automatically** —
  no retention, no cleanup jobs. Disk management is the owner's explicit
  decision; explicit deletions (removing a case from a session, deleting a
  project) remove the screenshot files together with the database records —
  no orphan files are left behind.
- Uploads are limited to PNG/JPEG/WebP, ≤ 5 MB, validated by magic bytes.

## Transport and deployment notes

- Run Femida behind TLS when agents talk to it over a network; the default
  compose setup serves plain HTTP on `localhost:8080`.
- CORS is closed for the API; the only supported auth for `/api/v1` and `/mcp`
  is the project key, for the UI — the session cookie.
