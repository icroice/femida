# Contributing

## Commits

Commit messages follow [Conventional Commits](https://www.conventionalcommits.org/)
in English:

```
<type>(<optional scope>): <short imperative summary>

<optional body>
<optional footer>
```

- Types: `feat`, `fix`, `docs`, `refactor`, `perf`, `test`, `build`, `ci`,
  `chore`, `revert`.
- Scope is optional but encouraged for area-focused changes (`feat(api)`,
  `fix(ui)`, `docs(skill)`).
- Breaking changes are marked with `!` and/or a `BREAKING CHANGE:` footer:
  `feat(api)!: drop the legacy verdict endpoint`.
- One logical change per commit; keep the summary line under ~72 characters.

## Development

```bash
make up      # build and start (http://localhost:8080)
make test    # PHPUnit
make lint    # phpstan + pint (check)
make fmt     # fix code style
```

Tests and lint must pass before a change is considered done.
