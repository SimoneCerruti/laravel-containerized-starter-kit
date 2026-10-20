# Architecture

- Sequential explicit logic invocation is preferred over event-driven design.
- Actions pattern wins over Observers.
- When creating or refactoring a behavior, prefer an **Action** (`lorisleiva/laravel-actions`) over a Support class, an helper method or similar.
- Prefer macros instead of Support classes or helpers. Eg: prefer creating macros on the `Cache` facade instead of creating a `Support\CacheHelper` class.
- One Service Provider per macro family, registering every macro on that same macroable class: all `Config` macros in `ConfigMacroServiceProvider`, all `Str` macros in `StrMacroServiceProvider`, etc.
- Use Services **only for external integrations**.
- If a Service is used in **one** controller method, inject it into that method via type-hint.
  If used in **multiple** methods, resolve it in the constructor.
- Classes are **`final` by default**.
- **Always use an enum (its case or `->value`) instead of a raw string wherever the enum exists**:
  routes, middleware, migrations, seeds, configs, UI defaults, Blade, and test data.
- Do **not** group classes by domain. Actions go in `app/Actions`, not `app/Actions/Analytics`. Same for Support, Service, etc. Exception may be allowed by explicit declaration of the user.
- Enforce architectural testing via Pest's `arch` testing.
