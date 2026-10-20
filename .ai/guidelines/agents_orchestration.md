# Agents Orchestration

Set of rules, best practice and guidelines an AI agent must follow when working on any task:

## Session and guardrails

- Always use a worktree, not only a branch, even for a documentation-only task. When work needs to happen directly on main, the user will specify that.
- Only perform one task/issue per session. When a task is finalized, recommend the user start the next task in a fresh session (/clear) instead of continuing here.
- Create a new base folder only with the user's approval.
- Never disable PAO.
- NEVER edit CLAUDE.md: it is a file generated from laravel boost.
- NEVER edit rules, guidelines, skill definitions, nor any file inside `.ai` or `.claude`. The only exception is via EXPLICIT authorization given by the user. If instructed to touch those file, then run `php artisan boost:update`.

## Gathering context

- Never `cat`/`tail -N` a whole file into context.
- Prefer `Explore` over `grep` when a wide sweep is needed and/or a known symbol/specific signature is not provided. Prefer `Grep`/`Read` otherwise.
- An approved plan states the file paths, conventions, helper names, and test seams resolved during planning. Read them from there, do not re-explore or re-verify.
- To get packages documentation, consult `context7 mcp`.
- This project has domain-specific skills available in **/skills/**. You MUST activate the relevant skill whenever you work in that domain.
- Reach for CodeGraph BEFORE grep/find or reading files when you need to understand or locate code
  — this repo is indexed by it (a `.codegraph/` directory exists at the repo root):
  - **MCP tool** (when available): `codegraph_explore` answers most code questions in one call — the
    relevant symbols' verbatim source plus the call paths between them, including dynamic-dispatch
    hops grep can't follow. Name a file or symbol in the query to read its current line-numbered
    source. If it's listed but deferred, load it by name via tool search.
  - **Shell** (always works): `codegraph explore "<symbol names or question>"` prints the same
    output.

## Delegating to subagents

- Parallelize any task as much as possible, using dedicated subagents.
  Those subagents report back only what is strictly necessary and should avoid context pollution of the dispatching agent as much as possible.
  Subagent may dispatch their own subagents.
  The dispatching agent keeps decisions and verdicts, the bulk output stays in the subagent.
- Do not reuse the same subagent for a new task.
- Use less token-expensive models, such as `haiku` for exploration.
- Use more capable models, such as `opus` for planning.
- Use average capable models, such as `sonnet` for implementing a plan.

## Running commands

- Always use the :agentic script variants when available. Eg: `composer test:agentic` instead of `composer test`.
- Composer script extra flags go after `--`. Eg: `composer test:agentic -- --filtered --bail`. Composer forwards everything after `--` to the script's last command.
- Delegate formatting to `composer pint:agentic`.
- Delegate code form to `composer rector:agentic`.
- Delegate static analysis form to `composer stan:agentic`.
- Never run `composer qa` mid-task, only at the end, only if instructed.
- Generate scaffolding files with `php artisan make:*`. Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior. Use `php artisan make:test --pest {name}` to create tests with Pest.

## Testing

- Every change must be programmatically tested. Write a new test or update an existing test, then run the affected tests to make sure they pass.
- Creating, editing, or removing any code means that the relevant test suite MUST be created or adjusted.
- If no code changed since the last test run, do not re-run. Nothing can have changed.
- When verification of a single feature/fix/refactor or edit is needed, run the minimum amount of tests possible. Maybe the test file, or maybe a single test, never the full suite.
- To run only tests affected by a change use `composer test:agentic -- --filtered`.
- If a one-shot, non persisted test is needed (eg: for debugging), use Pest's `--agent` flag. Use the `/pest-plugin-agent`. Eg: `--agent='$user = \App\Models\User::factory()->create(); $this->actingAs($user)->get("/dashboard")->assertOk();'`. Note the single quotes: they avoid shell interpolation on `$user`.

## Talking to the user

- Never use these terms without immediately explaining them with a concrete example: fold, rollup, axis (and similar data-modeling jargon).
Pattern to follow: "<term>: <one plain-language sentence>. For example, <concrete instance from the user's own data/domain>."
Bad: "Add a rollup on the sales axis."
Good: "A rollup means combining rows automatically — for example, summing all sales rows by month instead of showing each one. I'd add this on your 'date' column."
Prioritize the example over brevity: a slightly longer message the user understands beats a short one they don't.
- Talk to the user in Italian, using translated ASD-STE100 Simplified Technical English.
- Code, comments, commit messages, skills, documentation and everything that is not direct and volatile communication with the user MUST be written in English.
