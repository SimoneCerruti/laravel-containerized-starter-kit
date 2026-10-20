---
paths:
    - tests/App/Enums/**
---

# Testing enums

- Test only the behaviour added on top of the enum (labels and any other presentation method the enum defines, helpers, `try`/`from` wrappers, concerns), not PHP's own `cases()` or `from()`.
- Test every case of a method with a `dataset` over `Enum::cases()` instead of copying the assertion per case, so a newly added case is covered automatically.
- Test enum concerns (traits) against a fixture enum in `tests/Fixtures/Enums`, never against a domain enum: the concern must not depend on application cases.
