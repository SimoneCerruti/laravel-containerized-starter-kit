---
paths:
    - app/Actions/**
---

# Actions

- Take explicit, typed parameters instead of relying on a `Request` — Actions may run from a CLI (or other) context where none is available.
- Keep every Action directly in `app/Actions`, with no subfolders; group by name (`ResolveUserFromSocialite`), not by directory.
- Actions can be called within other actions.
- Wrap an Action's body in a DB transaction whenever it performs database operations.
- An Action exposes exactly one public method, `handle`.
- When implementing, refactoring, or troubleshooting an Action, use the `/laravel-actions` skill.
