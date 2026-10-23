# Filament

- Reference Filament routes via `Resource::getUrl('index')`, not Laravel `route()`. Name the exact Resource, not `getResource()`.
- Always use Filament enums (eg: `Filament\Support\Icons\Heroicon`, `Filament\Support\Enums\Operation`, ...) instead of raw strings.
- Filament-specific behavior must be wrapped in a Filament Action. Whenever, write a generic Action and reference it from a Filament Action. Treat Filament action as adapter/wrapper over generic actions.
- Filament `->authorize('ability')` references the model policies / gates.
