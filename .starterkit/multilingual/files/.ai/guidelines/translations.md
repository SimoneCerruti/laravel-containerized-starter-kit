# Translations

- A translation key is always the English phrase.
- Provide the Italian translation in `lang/it.json`.
- Prefer `Lang::string()` to `__()` and `trans()->string()`. It better support static analysis.
- In Blade use `{{ trans()->string('phrase') }}`, never `@lang()`.
- Use named placeholders (`:name`) — never `sprintf`.
- Use the `App\Enums\Concerns\HasAutoLabel` trait in enums to derive their translation key from the case's value at runtime, instead of a literal `__('...')` call. When adding a new case to one of these enums, manually add the corresponding translation entries.
