# Architecture

- Sequential explicit logic invocation is preferred over event-driven design.
- Actions pattern wins over Observers.
- When creating or refactoring a behavior, prefer an **Action** (`lorisleiva/laravel-actions`) over a Support class, an helper method or similar.
- Prefer macros instead of Support classes or helpers. Eg: prefer creating macros on the `Cache` facade instead of creating a `Support\CacheHelper` class.
- One Service Provider per macro family, registering every macro on that same macroable class: all `Config` macros in `ConfigMacroServiceProvider`, all `Str` macros in `StrMacroServiceProvider`, etc.
- Use Services **only for external integrations**.
- If a Service is used in **one** controller method, inject it into that method via type-hint.
  If used in **multiple** methods, resolve it in the constructor.
- Classes are **`final` by default**. Exceptions are the one exception: see `.ai/rules/exceptions.md`.
- **Always use an enum (its case or `->value`) instead of a raw string wherever the enum exists**:
  routes, middleware, migrations, seeds, configs, UI defaults, Blade, and test data.
- Do **not** group classes by domain. Actions go in `app/Actions`, not `app/Actions/Analytics`. Same for Support, Service, etc. Exception may be allowed by explicit declaration of the user.
- Enforce architectural testing via Pest's `arch` testing.
- Give a job that blocks its worker for long (polling an external report, a backfill) or that must never run in parallel (a rate-limited API key shared with other projects) its **own queue and its own worker program**. A single long job on the default pool starves every other queued job.
  - Declare the worker in `docker/configs/laravel-worker-supervisor.conf`, with `--queue=<name>`, its own `--timeout` and `--max-time`, and `numprocs=1` when reads must not run in parallel.
  - Set `stopwaitsecs` above the worker `--timeout`, or supervisor kills the worker before it can exit cleanly.
  - Take timeout, tries and memory limit from environment variables, never from literals in the supervisor file.
  - Set `retry_after` of the queue connection above the job timeout, so a running job is never handed to a second worker.
