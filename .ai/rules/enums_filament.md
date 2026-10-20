---
paths:
    - app/Enums/**
---

# Enums (Filament)

Applies on top of `.ai/rules/enums.md`.

- When creating an enum that backs model fields implement Filament's `HasLabel`, `HasColor`, `HasIcon`.
  Narrow each method's return type to the single concrete type it actually returns — all covariant, valid PHP — instead of the wide interface unions (`string|BackedEnum|Htmlable|null`):
    - `getLabel(): string`
    - `getColor(): string`
    - `getIcon(): Heroicon`
- For icons use `Filament\Support\Icons\Heroicon` cases, not strings.
