---
paths:
    - app/Macros/**
---

# Macros

- Every macro extends `App\Macros\Contracts\Macro` and registers through it — never `Config::macro()`/`Str::macro()`/etc. called directly.
- Implement the macro logic as the class's own public static `handle()` method, never as a closure built inside another method: Larastan only propagates a `@template` generic through a real, reflectable method.
- One folder per macroable class, named after the class `macroable()` returns — e.g. `app/Macros/Config/` for `Illuminate\Support\Facades\Config`, `app/Macros/Str/` for `Illuminate\Support\Str`. Never name it after the underlying implementation class: `Illuminate\Cache\Repository` and `Illuminate\Config\Repository` share the same basename and would collide under a generic `app/Macros/Repository/`. A macro never sits directly under `app/Macros/`.
