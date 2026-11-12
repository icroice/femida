# Femida onboarding: build your project's QA skills

You are the agent of a project that has just received a Femida API key. Femida
is an external QA evidence service: it stores before/after screenshot pairs
for tasks, and a human reviews the pairs and records verdicts in its web UI.
Femida never runs your application — you do, and submit the evidence over the
API.

This resource is a template. Your job is to end up with two working skills in
this repository's `.claude/skills/`, adapted to this project. Everything in
`<ANGLE_BRACKETS>` is a placeholder — replace it. Femida ships this template
with the server, so re-reading `femida://onboarding` later always gives you
the current version; refresh your local copies when it changes.

## 1. Study your project first

The Femida server cannot inspect your repository — do this survey yourself
before writing any skill:

1. **`femida.yaml`** at the repo root — the boot config for QA runs (reference in
   section 5). Missing → draft it with the owner: `app.start`, `app.ready`,
   `app.base_url`, `seed`.
2. **How the app boots**: build steps, containers, ports, required env vars.
   Everything `app.start` needs must work from a fresh git worktree (no
   untracked files, no `.env`).
3. **Playwright**: `scripts/drive.mjs` (section 4) needs it. Check
   `node -e "require('playwright')"` from the repo, or a global install, or
   set `PLAYWRIGHT_PATH`.
4. **Deterministic data**: a seed command that resets the data to a known
   state (`femida.yaml: seed`). Cases may only reference data the seed
   guarantees. A scenario needs data the seed lacks → extend the seed first,
   then write the case.
5. **Runtime env vars**: a worktree has no `.env`. Any variable the app needs
   at runtime (external API keys, feature flags) must be exported inside
   `app.start` — e.g. from the main checkout's `.env` or the CI environment.
   A missed key shows up as a feature silently missing on screenshots while
   local runs work fine.

## 2. Skill: femida-write-cases

Save as `.claude/skills/femida-write-cases/SKILL.md`:

````markdown
---
name: femida-write-cases
description: <Maintain PROJECT_NAME's test-case library in Femida — survey the app, create and edit cases via the API. No runs, sessions or app boots. Use when asked to "create/update test cases in Femida", "fill the case library", "review cases after a feature", or "do a first QA survey of the project".>
---

# Femida cases — test-case library for <PROJECT_NAME>

The skill's job is to keep Femida's case library current: survey the project,
decide what is worth checking, and record it as concrete cases. Runs are NOT
here — once the cases are ready, the `femida-run-session` skill takes over.

## Connection

`FEMIDA_URL` (e.g. `http://localhost:8080`) and `FEMIDA_KEY` | `FEMIDA_API_KEY`
from `.env` (export before scripts: `set -a; . ./.env; set +a`) or
`.qa/service.toml`. The client script is shared with the sibling skill:

```
node .claude/skills/femida-run-session/scripts/femida.mjs list_cases '{}'
node .claude/skills/femida-run-session/scripts/femida.mjs create_case '<json>'
node .claude/skills/femida-run-session/scripts/femida.mjs update_case '<json>'
```

Contract quirk: `get_case`/`get_session` take the argument `id`; all other
tools take `case_id`/`session_id`.

## Where the material comes from

1. **What Femida already has**: `list_cases` — do not duplicate; edit existing
   cases via `update_case` (case id + the changed fields in full; steps are
   replaced as a whole array).
2. **App pages**: <the router file, e.g. routes/web.php> — public routes and
   pages; views/templates when you need real selectors
   (<the project's CSS selector convention, e.g. block__element classes>).
3. **Product scenarios**: <the product doc, e.g. docs/PRODUCT.md or README> —
   what counts as the product's main paths; write cases for those first.
4. **Run data**: <the QA seed, e.g. database/seeders/QaSeed.php> — cases lean
   on its dataset (<list 2–3 anchor records, e.g. a known city, a known item
   and its URL>). If a scenario needs data the seed lacks — extend the seed
   first, then the case.

## Case standard

- `title` — "what is being checked", no verbs ("City item list", not "Check
  the city item list").
- `folder` — up to two levels via `/`. Project canon: <the project's folder
  list, e.g. "Home", "Catalog", "Catalog/Item", "Forms", "Account">. A new
  case goes into an existing folder; create a new folder only with the
  owner's consent.
- `status`: `active` — runnable; `draft` — recorded, but data or conditions
  for a run are missing yet (say exactly what is missing in
  `preconditions`).
- `preconditions` — what must be in the data (seeded records, rights, state).
- Step `action` — a concrete action with a URL or a selector; `expected` — an
  observable outcome: a text, an element count, a navigation, an element's
  absence. Not "it works", but e.g. `the string "2 items" matches the number
  of cards`. Write expectations against the actual UI, not against the seed
  file (the UI may present data differently than it is stored).
- `tags` — area labels: <e.g. catalog, search, form, account, admin>.
  `priority`: P0 main paths → P3 cosmetics.

## Workflow

1. Scope: what was asked (one feature / one area / the whole project) → the
   list of pages and scenarios it covers.
2. `list_cases` — what already exists; plan: create / update / keep.
3. Agree the plan with the owner if the scope is large or existing cases
   change.
4. Create/update the cases; afterwards `get_case` each new one and check the
   steps landed as intended.
5. Short report: how many created/updated, what was left out and why.

## Don'ts

- No sessions, runs, containers or worktrees — that is `femida-run-session`
  territory.
- Never delete or silently rewrite other people's cases: send the change
  list to the owner first.
- Don't invent expectations: unsure how the UI actually behaves → tell the
  owner the step needs a probe run to pin down.
````

## 3. Skill: femida-run-session

Save as `.claude/skills/femida-run-session/SKILL.md` (and `scripts/` next to it,
section 4):

````markdown
---
name: femida-run-session
description: <Run QA sessions for PROJECT_NAME with before/after evidence in Femida: boot both commits of a task in git worktrees, walk case steps with Playwright, upload screenshots, suggest verdicts. Use when asked to "run femida", "record a QA session", "take before/after for a task", "test commits via femida".>
---

# Femida sessions — QA runs for <PROJECT_NAME>

Femida is an external QA evidence service (API). We are the client: we boot
the app ourselves, upload evidence over the API, and a human records verdicts
in Femida's UI. Never fix Femida-side problems — report them to the owner
verbatim.

## Connection

- `FEMIDA_URL` (e.g. `http://localhost:8080`) and the key: `FEMIDA_KEY` or
  `FEMIDA_API_KEY` — in the project's `.env` (export before scripts:
  `set -a; . ./.env; set +a`) or `.qa/service.toml`.
- MCP client: `node scripts/femida.mjs <tool> '<json-args>'` — does
  initialize + tools/call itself. Screenshots are convenient to pass as
  `"screenshot_file": "path.png"` — the script base64-encodes them.
- Fallback — REST `curl $FEMIDA_URL/api/v1/...` with the same Bearer key
  (cases, sessions, runs, multipart steps). Use it if MCP is down (report
  the error to the owner).

## Run directory

Everything temporary lives outside the repository:
`../qa-runs/<date>-<topic>/` (intermediate screenshots, notes). Worktrees:
`git worktree add ../<PROJECT_NAME>-qa-<side> <commit>` — also outside the
repo; remove them after the run.

## Session flow

1. **Inputs.** Task → a commit pair: base ("before") and head ("after") —
   usually two adjacent commits of the task branch. Uncommitted changes in
   the tree → warn the owner: worktrees won't see them.
2. **Cases.** `femida.mjs get_project '{}'` (key check), `list_cases` — pick
   cases for the task by title/tags; steps — `get_case` (argument `id`, not
   `case_id` — that's how `get_case`/`get_session` work; other tools take
   `case_id`/`session_id`). No fitting cases → offer the `femida-write-cases`
   skill and stop.
3. **Session.** `create_session`: `external_ref`, `title`, `base_commit`,
   `head_commit`, `case_ids`, `agent: "<agent name> + Playwright"`. Base
   unknown → ask; the `only_after` mode (fast run without "before") — only
   with the owner's explicit permission.
4. **Sides, "after" first** (per `femida.yaml` `run_order`). For each side:
   - `git worktree add ../<PROJECT_NAME>-qa-<side> <commit>`;
   - read `femida.yaml` from the worktree and execute it: `%PORT%` — a free
     port (search from 8123); `start`, wait for `ready` up to 60 s, then
     `seed`;
   - `start_run {session_id, side, commit}`;
   - per case: every step — `node scripts/drive.mjs <base_url><path> ...`
     with the selectors from the case, then
     `submit_step {run_id, case_id, step_index, status, note, elapsed_ms,
     screenshot_file}`. A screenshot is mandatory, full page. Texts to
     verify — `--text`; console errors arrive in the driver report;
   - `finish_run {run_id, status: done}`; `stop` from `femida.yaml`. Did not
     boot — `finish_run` with `status: failed` and the error text; the
     other side still runs.
5. **After both sides.** Cross-check via `get_session {id}`: both runs
   `done`, every case has steps from both sides. Then
   `set_case_suggestion` for every case (pass/fail/blocked + notes on how
   "after" differs from "before"; a step failing only on "before" is the
   expected diff of the new feature — the verdict is still about head), and
   once `submit_session_metrics {tokens_in, tokens_out}` — the session's
   token usage.
6. **Report to the owner.** Short: session #, cases, suggestions, a link to
   the UI (`$FEMIDA_URL` → project → session). Do NOT set verdicts
   (`set_verdict` — only if the owner explicitly dictates the decision).

## Rules

- The verdict semantically describes the head commit; "before" is the
  evidence base.
- HTTP 429 from Femida → wait the `Retry-After` seconds and continue the
  run; never retry immediately (the limit is per key, 240/min).
- A step does not reproduce → `submit_step` with `status: error` and a note;
  continue the case if it still makes sense.
- Data is deterministic via the `femida.yaml` seed: <list 2–3 anchor records
  cases rely on>.
- Cleanup after the run: `stop` from `femida.yaml` (or kill the leftovers),
  `git worktree remove --force ../<PROJECT_NAME>-qa-*`; keep the run
  directory (the owner decides about it).
````

## 4. Scripts

Both scripts go to `.claude/skills/femida-run-session/scripts/`. The
`femida-write-cases` skill references them from there (keep one copy, don't fork).

### `scripts/femida.mjs` — minimal MCP client

```javascript
#!/usr/bin/env node
// Minimal Femida MCP client over streamable HTTP: initialize + tools/call.
// Usage: node femida.mjs <tool> ['{"json":"args"}'] [--raw]
//   - "screenshot_file": "path.png" in args is replaced with screenshot_base64
//   - connection: FEMIDA_URL and FEMIDA_KEY | FEMIDA_API_KEY from the environment
// Prints the result's text content; with --raw the whole JSON. Error → exit 1.
import fs from 'node:fs';

const base = process.env.FEMIDA_URL;
const key = process.env.FEMIDA_KEY || process.env.FEMIDA_API_KEY;
if (!base || !key) {
  console.error('FEMIDA_URL and FEMIDA_KEY (or FEMIDA_API_KEY) are required in the environment');
  process.exit(1);
}

const [, , tool, argsJson = '{}', ...flags] = process.argv;
if (!tool) {
  console.error('node femida.mjs <tool> [json-args] [--raw]');
  process.exit(1);
}

const args = JSON.parse(argsJson);
if (args.screenshot_file) {
  args.screenshot_base64 = fs.readFileSync(args.screenshot_file).toString('base64');
  delete args.screenshot_file;
}

const endpoint = `${base.replace(/\/$/, '')}/mcp`;

const post = async (body) => {
  const res = await fetch(endpoint, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/json, text/event-stream',
      Authorization: `Bearer ${key}`,
    },
    body: JSON.stringify(body),
  });
  const text = await res.text();
  if (!res.ok) throw new Error(`HTTP ${res.status}: ${text.slice(0, 300)}`);
  // streamable HTTP may answer with SSE — take the first data: line with JSON-RPC
  if (text.startsWith('data:') || text.startsWith('event:')) {
    const line = text.split('\n').find((l) => l.startsWith('data:'));
    return JSON.parse(line.slice(5).trim());
  }
  return JSON.parse(text);
};

await post({
  jsonrpc: '2.0',
  id: 1,
  method: 'initialize',
  params: {
    protocolVersion: '2025-03-26',
    capabilities: {},
    clientInfo: { name: 'femida-skill-client', version: '1.0' },
  },
});

const resp = await post({
  jsonrpc: '2.0',
  id: 2,
  method: 'tools/call',
  params: { name: tool, arguments: args },
});

if (flags.includes('--raw')) {
  console.log(JSON.stringify(resp, null, 1));
} else if (resp.error) {
  console.error(JSON.stringify(resp.error));
  process.exit(1);
} else {
  const text = (resp.result?.content ?? [])
    .filter((c) => c.type === 'text')
    .map((c) => c.text)
    .join('\n');
  console.log(text || JSON.stringify(resp.result));
  if (resp.result?.isError) process.exit(1);
}
```

### `scripts/drive.mjs` — Playwright step driver

One call = one case step: navigate, act, screenshot, report.

```javascript
#!/usr/bin/env node
// Browser driver for step-by-step QA runs: one call = one case step.
// Usage: node drive.mjs <url> [options]
//   --out F.png     screenshot after the step (full page)
//   --click SEL     click (multiple --click/--fill run in order of appearance)
//   --fill SEL=TEXT fill a field
//   --press SEL=KEY press a key in a field
//   --check SEL     check a checkbox
//   --wait SEL      wait for a selector after the actions
//   --wait-url SUB  wait until the URL contains a substring (navigation)
//   --wait-ms N     extra pause (animations, lazy images)
//   --text SEL      extract the element's text into the report (repeatable)
//   --count SEL     count elements by selector (into counts)
//   --viewport WxH  default 1280x800 (take from femida.yaml)
//   --locale LL     default en (take from femida.yaml)
//   --timeout MS    default 15000
//   --state F.json  Playwright storageState: load before the step (if the file
//                   exists) and save after — login flows survive between calls
//   --pre-wait-ms N pause after load before the actions (scripts that bind
//                   handlers on load), default 300
// Prints JSON: {url, title, texts, counts, consoleErrors, elapsed_ms}.
// Write screenshots/logs to the run directory, not into the repository.
import fs from 'node:fs';
import { execSync } from 'node:child_process';
import { createRequire } from 'node:module';

const require = createRequire(import.meta.url);

// Playwright location: PLAYWRIGHT_PATH if set, then the global npm root,
// then a plain require() from the project's node_modules.
function loadChromium() {
  const paths = [];
  if (process.env.PLAYWRIGHT_PATH) paths.push(process.env.PLAYWRIGHT_PATH);
  try {
    paths.push(`${execSync('npm root -g', { encoding: 'utf8' }).trim()}/playwright`);
  } catch {}
  for (const p of paths) {
    try { return require(require.resolve('playwright', { paths: [p] })).chromium; } catch {}
  }
  return require('playwright').chromium;
}
const chromium = loadChromium();

const [, , url, ...rest] = process.argv;
if (!url) {
  console.error('node drive.mjs <url> [options]');
  process.exit(1);
}

const opts = { viewport: '1280x800', locale: 'en', out: null, wait: [], waitMs: 0, texts: [], counts: [], timeout: 15000, state: null, preWaitMs: 300 };
const actions = [];
for (let i = 0; i < rest.length; i++) {
  switch (rest[i]) {
    case '--out': opts.out = rest[++i]; break;
    case '--click': actions.push(['click', rest[++i]]); break;
    case '--click-force': actions.push(['click-force', rest[++i]]); break;
    // split on the LAST "=": selectors like a[href=x] contain "=" inside
    case '--fill': { const arg = rest[++i]; const eq = arg.lastIndexOf('='); actions.push(['fill', arg.slice(0, eq), arg.slice(eq + 1)]); break; }
    case '--press': { const arg = rest[++i]; const eq = arg.lastIndexOf('='); actions.push(['press', arg.slice(0, eq), arg.slice(eq + 1)]); break; }
    case '--check': actions.push(['check', rest[++i]]); break;
    case '--wait': opts.wait.push(rest[++i]); break;
    case '--wait-url': opts.waitUrl = rest[++i]; break;
    case '--wait-ms': opts.waitMs = Number(rest[++i]); break;
    case '--text': opts.texts.push(rest[++i]); break;
    case '--count': opts.counts.push(rest[++i]); break;
    case '--viewport': opts.viewport = rest[++i]; break;
    case '--locale': opts.locale = rest[++i]; break;
    case '--timeout': opts.timeout = Number(rest[++i]); break;
    case '--state': opts.state = rest[++i]; break;
    case '--pre-wait-ms': opts.preWaitMs = Number(rest[++i]); break;
    default: throw new Error(`Unknown option: ${rest[i]}`);
  }
}

const [width, height] = opts.viewport.split('x').map(Number);
const browser = await chromium.launch({ args: ['--no-sandbox', '--disable-dev-shm-usage'] });
try {
  const context = await browser.newContext({
    viewport: { width, height },
    locale: opts.locale,
    ...(opts.state && fs.existsSync(opts.state) ? { storageState: opts.state } : {}),
  });
  const page = await context.newPage();

  const consoleErrors = [];
  page.on('console', (m) => { if (m.type() === 'error') consoleErrors.push(m.text().slice(0, 200)); });
  page.on('pageerror', (e) => consoleErrors.push(String(e).slice(0, 200)));

  const t0 = Date.now();
  await page.goto(url, { waitUntil: 'domcontentloaded', timeout: opts.timeout });
  // scripts binding handlers on load: acting earlier clicks into emptiness
  await page.waitForLoadState('load', { timeout: opts.timeout }).catch(() => {});
  if (opts.preWaitMs) await page.waitForTimeout(opts.preWaitMs);

  for (const [kind, sel, value] of actions) {
    if (kind === 'click') await page.click(sel, { timeout: opts.timeout });
    if (kind === 'click-force') await page.click(sel, { timeout: opts.timeout, force: true });
    if (kind === 'fill') await page.fill(sel, value, { timeout: opts.timeout });
    if (kind === 'press') await page.press(sel, value, { timeout: opts.timeout });
    if (kind === 'check') await page.check(sel, { timeout: opts.timeout });
  }
  if (opts.waitUrl) await page.waitForURL((u) => u.href.includes(opts.waitUrl), { timeout: opts.timeout });
  for (const sel of opts.wait) await page.waitForSelector(sel, { timeout: opts.timeout });
  if (opts.waitMs) await page.waitForTimeout(opts.waitMs);
  await page.evaluate(() => document.fonts.ready).catch(() => {});

  if (opts.out) await page.screenshot({ path: opts.out, fullPage: true });

  const texts = {};
  for (const sel of opts.texts) {
    texts[sel] = (await page.locator(sel).first().textContent())?.trim() ?? null;
  }
  const counts = {};
  for (const sel of opts.counts) {
    counts[sel] = await page.locator(sel).count();
  }

  console.log(JSON.stringify({
    url: page.url(),
    title: await page.title(),
    texts,
    counts,
    consoleErrors,
    elapsed_ms: Date.now() - t0,
  }, null, 1));

  if (opts.state) await context.storageState({ path: opts.state });
} finally {
  await browser.close();
}
```

## 5. `femida.yaml` reference

`femida.yaml` lives in the repo root, versioned with the code. The sessions skill
reads it **from the worktree of the commit under test** — the "before" run
uses the config of its own era.

```yaml
version: 1

app:
  start: "php artisan serve --port=%PORT%"       # required; %PORT% is substituted by the skill
  ready: "curl -sf http://127.0.0.1:%PORT%/up"   # required; polled up to 60 s
  base_url: "http://127.0.0.1:%PORT%"            # required
  env: { APP_ENV: testing }                      # optional
  stop: null                                     # optional; default: kill the process group

seed: "php artisan db:seed --class=QaSeed"       # optional; runs after each side boots
auth: { login: qa@example.test, password: secret }  # optional; referenced in cases as ${auth.login}
browser: { viewport: [1280, 800], locale: en }
run_order: after_first
```

- `app.start` must boot the app from a fresh worktree; `%PORT%` gets a free
  port (search starts at 8123).
- `app.ready` — the cheapest possible proof the app serves traffic (a health
  route, a static 200 page). On the 60 s timeout the run is honestly `failed`.
- `seed` runs before every run, not once per session — make it idempotent or
  reset-first. If the seed is incompatible with old code, that is an honest
  failed before-run.
- `stop` — default kills the process group; containers need an explicit
  `docker rm -f <name>-%PORT%` here.
- `run_order`: `after_first` (default) or `before_first`.

## 6. Pitfalls collected from real runs

- **A `submit_step` for EVERY step of a case.** Collapsing a case into one
  step loses the evidence timeline — the reviewer sees one screenshot where
  the case promises five checkpoints.
- **A `set_case_suggestion` for EVERY case.** `finish_run` returns warnings
  and `get_session` a `suggestions: {missing}` summary — use them to close
  the gaps before reporting.
- **Env vars don't reach the run process.** A git worktree has no `.env`;
  an external service key missing from `app.start`'s environment silently
  blanks a feature on the screenshots. Symptom: works locally, missing in
  the run — check the env first, then report a skip with a note if the key
  is intentionally unavailable.
- **Port collisions.** Always take the port from the `%PORT%` search (from
  8123 up); hardcoded ports break the second side and parallel sessions.
- **Screenshots must be full-page** with viewport/locale from `femida.yaml`:
  the reviewer compares before/after pairs — a cropped or differently sized
  pair is useless as evidence.
- **Uncommitted changes are invisible to worktrees** — warn the owner before
  running with a dirty tree.
- **Rate limit: 240 requests per minute per project key.** Everything
  sharing one key (REST and MCP alike) draws from the same bucket. Give
  every agent its own key — a shared "service" key makes parallel runs
  starve each other. A normal run is nowhere near the limit (browser steps
  pace it at ~15–40 requests/min), but a retry storm is: on HTTP 429 wait
  the `Retry-After` seconds, never retry immediately — hammering pushes
  the block into the next minute too.
- **Never fix Femida-side problems** — report the error text to the owner
  verbatim and continue what you can (the REST fallback covers a dead MCP).

## 7. Adaptation checklist

Run through before declaring the skills ready:

- [ ] Both skills are in `.claude/skills/`, descriptions name your project
      and its triggers.
- [ ] No `<PLACEHOLDER>` left: `grep -n '<[A-Z]' .claude/skills/femida-*/SKILL.md`
      is empty (or only matches intentional examples).
- [ ] `femida.yaml` exists and `app.start`/`ready`/`base_url` were verified by
      hand on the current commit.
- [ ] `node .claude/skills/femida-run-session/scripts/drive.mjs <base_url>`
      prints JSON with `url`/`title` for the front page.
- [ ] `node .claude/skills/femida-run-session/scripts/femida.mjs get_project '{}'`
      answers with your project's name.
- [ ] A first trivial session (one case, one step, a real commit pair)
      passes end-to-end and shows up in Femida's UI.
