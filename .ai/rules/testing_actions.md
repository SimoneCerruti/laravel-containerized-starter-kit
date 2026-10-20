---
paths:
    - tests/App/Actions/**
---

# Testing Actions

- Call the Action through its static entry point (`MyAction::run(...)`), the way application code does, not through `new MyAction()->handle()`.
- Test the Action in isolation: pass explicit arguments and never rely on a request or an authenticated user unless the Action takes one as a parameter.
- Assert the observable outcome (database rows, dispatched jobs, sent notifications, return value), not that internal methods were called.
- When an Action performs several writes, test the rollback: make a later step fail and assert that nothing was persisted.
- When testing a class that calls an Action, replace the Action with `MyAction::mock()->shouldReceive('handle')` instead of exercising its real behaviour a second time.
