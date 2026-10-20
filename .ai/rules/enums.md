---
paths:
    - app/Enums/**
---

# Enums

- Use `UPPERCASE_SNAKE_CASE` for enum case names.
- An enum that backs model fields and is shown to users exposes `getLabel(): string`, returning a translated label. When every label is just the humanized backing value, use the `App\Enums\Concerns\HasAutoLabel` trait instead of writing the method.
- Narrow return types to the single concrete type returned (`string`, not `string|null|Htmlable`).
- Shared behaviour goes in a trait under `app/Enums/Concerns`, tested against a fixture enum in `tests/Fixtures/Enums`.
