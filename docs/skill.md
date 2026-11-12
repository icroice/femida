# Writing the agent skill

In v1 Femida ships **no ready-made per-project skill** — but it ships an
**onboarding template**: the MCP resource `femida://onboarding` (read it via
`resources/list` + `resources/read` with the same Bearer key). It contains
adaptable skeletons of the two skills described here (`femida-write-cases`,
`femida-run-session`), the client and browser-driver scripts, a `femida.yaml`
reference and pitfalls from real runs. Copy the skeletons into your
repository, replace the `<PLACEHOLDER>` parts and work through the adaptation
checklist. This document is the contract such a skill must follow.

The skill runs **locally at the developer's machine**. The server is a hub: it
never sees your repositories — the skill checks out git worktrees, boots the app,
drives the browser (e.g. Playwright MCP) and uploads evidence.

## Connecting to Femida

The skill looks for:

- `FEMIDA_URL` / `FEMIDA_KEY` environment variables, or
- `.qa/service.toml` in the tested repository.

If neither is found, the skill asks the developer and offers to save the answer.

Both transports use the same **project API key** (issued in project settings,
shown once) as a Bearer token:

- **MCP** (recommended for chat agents): `{FEMIDA_URL}/mcp`, streamable HTTP.
- **REST**: `{FEMIDA_URL}/api/v1/…` — for scripts and other clients.

## MCP tools

| Tool | Purpose |
|---|---|
| `get_project` | Project the key belongs to |
| `list_cases` / `get_case` | Browse the case library (active cases by default, folder/status filters); `with_steps: true` returns step bodies too |
| `create_case` / `update_case` | Maintain the library from chat |
| `create_session` | Open a session: base/head commits, external ref, `only_after` flag |
| `get_session` | Session state: aggregated step pairs, suggestion completeness (`suggestions: {total, set, missing}`), per-case `coverage` (`expected/filled/passed` per side) and top-level `pending_case_ids` — the cases a re-run should pick up (empty or not fully passed) |
| `add_cases_to_session` | Attach the selected cases |
| `start_run` / `finish_run` | Run lifecycle per side (`before`/`after`), error text on failure; `finish_run` warns about cases with no suggestion. `start_run` is idempotent per commit: same commit returns the existing run (gap-filling), a different commit is rejected — create a new session for a new commit. `finish_run` is terminal: repeating it with the same status is idempotent (safe to retry), changing the status of a finished run returns 422 |
| `submit_step` | Step result: status (`passed/failed/skipped/error`), note (**required for `skipped`/`error`** — say why), `elapsed_ms` (step duration), screenshot (base64/data-URI). Fills an empty step or overwrites `failed`/`error`/`skipped` (flaky retry); a **`passed` step is frozen** — resubmitting it returns 422. Steps are accepted only while the run is running: after `finish_run` the evidence is final (clear the case/session to re-check) |
| `submit_session_metrics` | Agent token usage for the whole session (`tokens_in`/`tokens_out`) — sent once, at the very end |
| `set_case_suggestion` | Agent's suggested verdict + free-form notes per case |
| `set_verdict` | Human verdict — **not used by the agent**; verdicts are given in the UI |

Id arguments are uniform across tools: `case_id`, `session_id`, `run_id`
(get_case takes `case_id`, not `id`). A missing required field returns an
explicit `Missing required field '…'` error. `finish_run` accepts only
`done` or `failed`.

REST equivalents live under `/api/v1`: `me`, `cases` (CRUD; the list defaults
to active cases — `status=all` for the full library, same as `list_cases`),
`sessions` (list/create/patch — patch takes the `tokens_in`/`tokens_out`
metrics; the session status itself is computed, never set by hand; `case_ids`
accept active cases only), `sessions/{id}/runs`, `runs/{id}` (patch — finish,
`done`/`failed` only, terminal like `finish_run`), `runs/{id}/steps`
(screenshot as multipart field `screenshot`, optional `elapsed_ms`; same
freeze/overwrite/note rules as the MCP tool),
`sessions/{id}/cases/{id}` (suggestion/verdict). Rate limit:
240 requests/min per key.

Session status has exactly two stored values: `passed` / `not_passed`. A session
is born `not_passed` and flips to `passed` automatically when every expected
run has finished, every case is fully covered by `passed` steps on every
expected side and no human reject verdict exists. Clearing a case, a reject
verdict or attaching a new case flips it back to `not_passed`.

## The session flow

1. **Collect inputs:** task commits (`base`, `head`), the case list (`list_cases`),
   external ref (tracker issue). Warn the developer if the working tree is dirty:
   worktrees won't see uncommitted changes.
2. **Create the session** (`create_session`) and attach cases
   (`add_cases_to_session`). `base_commit = null` + `only_after = true` for the
   "after only" mode.
3. **Run sides, `after` first** (default; `femida.yaml` may set `run_order:
   before_first`). Rationale: head is what's in front of the developer now; if
   base fails to boot, the session is still useful.
4. **Per side:**
   - `git worktree` on the commit; read `femida.yaml` **from that worktree**;
   - pick a free port (search from 8123), start the app, wait for `ready`
     (60 s timeout; on timeout the run is `failed` with the error — the other
     side still runs);
   - run the seed command if configured (both sides get identical data);
   - `start_run(side)`, walk each case step by step in the browser and
     `submit_step` after every step: status, note, **full-page screenshot**
     (viewport and locale from `femida.yaml`);
   - `finish_run` when the side is done. Once every expected run has finished,
     the session is auto-marked `passed` if all cases are fully covered by
     `passed` steps and no human reject verdict exists (a failed run keeps it
     `not_passed`).
5. **After both sides:** `set_case_suggestion` per case — a short suggested
     verdict and notes about how the run went (`agent_notes`). Then
     `submit_session_metrics` once: the agent's token usage for the session
     (`tokens_in`/`tokens_out`). Step timings travel with each `submit_step`
     (`elapsed_ms`); the session screen sums them per case and side.
     `finish_run` and `get_session` both report cases still missing a
     suggestion — clear them before wrapping up.
6. **Report back to the developer:** a short summary; the full markdown summary
     is copied from the session screen in the UI.

## Error handling

- A step doesn't reproduce → `submit_step` with `status=error` + note; the case
  continues if possible.
- Flaky step → retry **once**, mark the note `flaky` (a `failed`/`error`/
  `skipped` step can be overwritten; a `passed` step is frozen).
- App didn't start / `ready` timed out → the run is `finished` as `failed` with
  the error text; the session keeps the other side; report to the chat.
- Screenshots: PNG/JPEG/WebP, ≤ 5 MB (the API validates magic bytes). A rejected
  upload never destroys the previous evidence — validation runs before anything
  is replaced.
- A tester can send any case back for a re-check from the UI (its steps,
  screenshots and verdict are cleared); on the next run pick it up via
  `pending_case_ids` in `get_session` and fill only the gaps.

## Rules of thumb

- Screenshots after **every** step, not only failures — the diff of a passing
  step is often the evidence.
- Keep `agent_notes` short: what booted, what was skipped, what flaked.
- Never call `set_verdict`: the verdict belongs to a human.
