---
paths:
    - app/Http/Controllers/**
---

# HTTP Controllers

- Keep controllers **slim**: push larger logic into fat models or Actions.
- Single-method controllers use `__invoke()`. Multi-method RESTful controllers use `Route::resource()->only([...])`.
- Use `Route::view()` with the Blade file directly instead of a controller whose only method returns `view()`.
