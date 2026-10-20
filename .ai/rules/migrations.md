---
paths:
    - "database/migrations/**"
---

# Migrations

- **Forward-only: do not write `down()` methods.** Migrations are never rolled back, fix forward with a new migration.
- **Alpha stage: edit an already-run migration directly instead of adding a new one.** Once the project leaves alpha, switch to adding a new migration instead.
- Inspect the live schema (`database-schema`) before writing a migration instead of assuming columns, defaults, nullability, or FK names.
- Run each migration-creating command separately (`make:migration`, `make:model -m`), waiting for it to finish before the next — chaining with `&&`/`;` risks identical timestamps.
- Prefer `$table->foreignUuid(...)` for foreign keys; try to respect convention naming and constrain whenever possible.
- Use `$table->uuid('id')->primary();` for models primary keys.
- Handle delete behavior in the CRUD code, meticulously tested, rather than as a migration-level delete constraint.
- Provide defaults from the CRUD code that performs the write, rather than as a column default in the migration.
