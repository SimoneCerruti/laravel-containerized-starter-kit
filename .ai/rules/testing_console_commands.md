---
paths:
    - tests/App/Console/**
---

# Testing console commands

- Run the command with the Pest Laravel helper `artisan('domain:verb-object', [...])`, chained with `expectsOutputToContain()` / `expectsQuestion()` / `expectsConfirmation()` and always closed by `assertExitCode()` (or `assertSuccessful()` / `assertFailed()`).
- Cover every argument and option, and every prompt answer that changes the behaviour.
- Keep the command thin: when it delegates to an Action, test the logic in the Action's test and here only the I/O (arguments, output, exit code).
- Do not call external processes for real: fake them with `Process::fake()` and assert the commands that were run.
- Commands that write files work on a temporary directory, never on the project tree.
