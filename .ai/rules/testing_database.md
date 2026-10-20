---
paths:
    - tests/Database/**
---

# Testing database (migrations, seeders)

- Test the resulting schema (`Schema::hasColumn()`, `Schema::getColumnType()`, nullable/default via `Schema::getColumns()`), not the migration's code.
- Seeders are tested by running them and asserting the rows; they must be idempotent, so run each one twice and assert the counts do not change.
- Environment-restricted seeders (`database.seeders_allowed_environments`) are tested for both an allowed and a forbidden environment.
