---
paths:
    - tests/App/Http/**
---

# Testing controllers and middleware

- Test through HTTP (`get()`, `post()`, `actingAs()`...) with the Pest Laravel helpers, asserting the status, the redirect or the JSON structure.
- Always act as a specific kind of user (guest, default user, privileged user) and cover both the allowed and the forbidden one.
- Test middleware through a route that uses it, not by instantiating it. Register a throw-away route in the test only if no real route applies.
- Assert the validation failures (`assertSessionHasErrors` / `assertInvalid`) for each rule of a form request, together with a valid payload.
