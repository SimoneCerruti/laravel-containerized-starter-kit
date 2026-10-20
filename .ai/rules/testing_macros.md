---
paths:
    - tests/App/Macros/**
---

# Testing macros

- Call the macro through the macroable class (`Str::escapeControlCharacters(...)`), proving the registration works, and not through `Macro::handle()` directly.
- Assert that the macro is registered (`Str::hasMacro('name')`) in a dedicated test.
- Use a `dataset` of input/output pairs and include the edge cases: empty string, `null` where allowed, multibyte characters, values at the boundaries.
