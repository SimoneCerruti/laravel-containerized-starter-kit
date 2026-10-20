---
paths:
    - database/factories/**
---

# Factories

- Use `fake()->randomElement(Enum::cases())` to pick a random case of an enum.
- For new factories, create an exhaustive set of states that may represent different model states.
- Use faker for every attribute value instead of hardcoding it, via the `faker()` helper (e.g. `faker()->word()`, `faker()->randomDigit()`).
- Reflect model column modifiers such as `unique` (via `->unique()`) and `nullable` (via `->optional()`).
