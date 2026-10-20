---
name: browser-smoke-testing
description: Use when a change needs visual/browser confirmation beyond Pest tests (UI behavior, CSS/Tailwind, row styling, etc.), the worktree isn't served by the shared dev server, and an agent (not a human) should drive the browser — in an interactive foreground Claude Code session with Chrome integration connected.
---

# Browser Smoke-Testing (Claude in Chrome)

**Prerequisite:** Claude in Chrome must be connected — check with `/chrome`; if not, ask the user
to connect it.

## Setup

1. Run the `worktree-local-server-setup` skill with no argument, to provision — it reports back
   the port and database name.
2. Navigate to `http://localhost:<port>/app/login`.
3. Log in as `test@example.com` / `password` (the base seed's dev user), or seed a
   feature-specific one if the check needs it.
4. Perform the asked checks.

## Context hygiene (mandatory)

Dispatch the browser-driving loop to a subagent. It returns only pass/fail per check,
console/network errors, and at most one screenshot when visual evidence is strictly required.

## Cleanup when done

1. Close the tab(s) Claude opened for this task **first** — even if the check errored out or was
   abandoned partway through, otherwise a tab stays open against a server about to be killed.
2. Run the `worktree-local-server-setup` skill with `cleanup`.
