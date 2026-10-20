---
paths:
    - tests/App/Models/**
---

# Testing Models

Group Model tests in `describe` blocks following this structure:
- `describe('attributes')`
    - `describe('<attribute name>')`: attribute-specific tests such as casts, hidden, hashed, PK testing, nullable, required, etc.
- `describe('accessors')`
    - `describe('<accessor name>')`: accessor-specific tests
- `describe('scopes')`
    - `describe('<scope name>')`: scope-specific tests
- `describe('mass-assignment')`: mass-assignment and fillable definition tests
- `describe('activity logging')`: activity logging tests
- `describe('factory')`: factory-related tests

