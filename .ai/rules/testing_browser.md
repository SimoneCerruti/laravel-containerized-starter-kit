---
paths:
    - tests/Browser/**
---

# Browser tests

- Use Pest's browser plugin (`visit()`) only for flows that need a real browser: JavaScript behaviour, navigation across several pages, visual smoke checks. Everything else is a feature test.
- Chain `assertNoSmoke()` (no JavaScript errors or console logs) on every page visited.
- Select elements by visible text, label or role, not by CSS classes.
- Create the data through factories before visiting; never depend on seeded data.
- Use the `/browser-smoke-testing` skill for the project's smoke-test workflow.
