---
paths:
    - "resources/views/**"
    - "**/*.blade.php"
    - "resources/js/**"
    - "resources/css/**"
---

# Frontend (HTML / CSS / Tailwind)

- Use modern, semantic HTML tags.
- Prefer Tailwind over custom CSS; avoid arbitrary Tailwind values where possible.
- When writing or fixing Tailwind utility classes, use the `tailwindcss-development` skill.

## Blade

- Use the `@session()` directive for flash messages, not `@if(session())`.
- Use `@selected()` / `@checked()`, not raw `selected` / `checked` attributes.
  Good: `@selected(old('status') === App\Enums\ProjectStatus::Pending->value)`.
- Translations via `{{ __('phrase') }}`.
