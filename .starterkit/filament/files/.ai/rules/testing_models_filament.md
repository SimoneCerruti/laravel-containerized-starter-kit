---
paths:
    - tests/App/Models/**
---

# Testing Models (Filament)

Applies on top of `.ai/rules/testing_models.md`.

- Add a `describe('filament')` block after `describe('mass-assignment')` for Filament-specific model behaviour: panel access (`canAccessPanel`), the Filament name, and any other Filament contract the model implements.
