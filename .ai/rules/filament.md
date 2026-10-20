---
paths:
    - app/Filament/**
---

# Filament

- When generating a Resource, also generate Filament smoke tests verifying it works. When changing a Resource run those tests (create them if missing) and make resource + tests pass. See `.ai/rules/testing_filament.md` for test conventions and snippets.
- Generate a View page or Infolist only when explicitly asked.
- Use `->authorize('ability')` whenever applicable. Eg: Actions needing authorization use `->authorize('ability')` (handles enforcement + visibility), not manual `Gate::authorize()` / `Gate::allows()`.
- `unique()` validation defaults to `ignoreRecord: true` — leave it unspecified.
- Use Filament v5's current APIs; avoid deprecated v3/v4 methods or concepts.
- Scaffold Filament files with `php artisan make:filament-*` (`--no-interaction`).
- If a field configuration appears multiple times, create a component in `app/Filament/Form/Components`.  
  Leave field modifiers such as `->required()` off the component in that case — the decision to apply any modifier lives in the client code.  
   Eg:

    ```php
    <?php

    declare(strict_types=1);

    namespace App\Filament\Form\Components;

    use Filament\Forms\Components\TextInput;

    final class PercentageNumberInput {
        public static function make(string $name): TextInput {
            return TextInput::make($name)
                ->numeric()
                ->integer()
                ->minValue(1)
                ->maxValue(100);
        }
    }
    ```
    
## Patterns

Always use static `make()` methods to initialize components. Most configuration methods accept a `Closure` for dynamic values.

Use `Get $get` to read other form field values for conditional logic:

**Conditional form field visibility**
```php
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;

Select::make('type')
    ->options(CompanyType::class)
    ->required()
    ->live(),

TextInput::make('company_name')
    ->required()
    ->visible(fn (Get $get): bool => $get('type') === 'business'),
```

Use `Set $set` inside `->afterStateUpdated()` on a `->live()` field to mutate another field reactively. Prefer `->live(onBlur: true)` on text inputs to avoid per-keystroke updates:

**Reactive field update**
```php
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\Str;

TextInput::make('title')
    ->required()
    ->live(onBlur: true)
    ->afterStateUpdated(fn (Set $set, ?string $state) => $set(
        'slug',
        Str::slug($state ?? ''),
    )),

TextInput::make('slug')
    ->required(),
```

Compose layout by nesting `Section` and `Grid`. Children need explicit `->columnSpan()` or `->columnSpanFull()`:

**Section and Grid layout**
```php
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;

Section::make('Details')
    ->schema([
        Grid::make(2)->schema([
            TextInput::make('first_name')
                ->columnSpan(1),
            TextInput::make('last_name')
                ->columnSpan(1),
            TextInput::make('bio')
                ->columnSpanFull(),
        ]),
    ]),
```

Use `Repeater` for inline `HasMany` management. `->relationship()` with no args binds to the relationship matching the field name:

**Repeater for HasMany**
```php
use Filament\Forms\Components\Repeater;

Repeater::make('qualifications')
    ->relationship()
    ->schema([
        TextInput::make('institution')
            ->required(),
        TextInput::make('qualification')
            ->required(),
    ])
    ->columns(2),
```

Use `state()` with a `Closure` to compute derived column values:

**Computed table column value**
```php
use Filament\Tables\Columns\TextColumn;

TextColumn::make('full_name')
    ->state(fn (User $record): string => "{$record->first_name} {$record->last_name}"),
```

Use `SelectFilter` for enum or relationship filters, and `Filter` with a `->query()` closure for custom logic:

**Table filters**
```php
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;

SelectFilter::make('status')
    ->options(UserStatus::class),

SelectFilter::make('author')
    ->relationship('author', 'name'),

Filter::make('verified')
    ->query(fn (Builder $query) => $query->whereNotNull('email_verified_at')),
```

Actions are buttons that encapsulate optional modal forms and behavior:

**Action with modal form**
```php
use Filament\Actions\Action;

Action::make('updateEmail')
    ->schema([
        TextInput::make('email')
            ->email()
            ->required(),
    ])
    ->action(fn (array $data, User $record) => $record->update($data)),
```

## Correct Namespaces

- Form fields (`TextInput`, `Select`, `Repeater`, etc.): `Filament\Forms\Components\`
- Infolist entries (`TextEntry`, `IconEntry`, etc.): `Filament\Infolists\Components\`
- Layout components (`Grid`, `Section`, `Fieldset`, `Tabs`, `Wizard`, etc.): `Filament\Schemas\Components\`
- Schema utilities (`Get`, `Set`, etc.): `Filament\Schemas\Components\Utilities\`
- Table columns (`TextColumn`, `IconColumn`, etc.): `Filament\Tables\Columns\`
- Table filters (`SelectFilter`, `Filter`, etc.): `Filament\Tables\Filters\`
- Actions (`DeleteAction`, `CreateAction`, etc.): `Filament\Actions\`. Never use `Filament\Tables\Actions\`, `Filament\Forms\Actions\`, or any other sub-namespace for actions.
- Icons: `Filament\Support\Icons\Heroicon` enum (e.g., `Heroicon::PencilSquare`)

## Common Mistakes

- **File visibility is `private` by default.** Use `->visibility('public')` explicitly when public access is needed.
- **`Grid`, `Section`, `Fieldset`, and `Repeater` do not span all columns by default.**
- **Use `Select::make('author_id')->relationship('author', 'name')` for BelongsTo fields.** `BelongsToSelect` does not exist in v5.
- **`Repeater` uses `->schema()`, not `->fields()`.**
- **`->dehydrated(false)` strips a field's value from form state before `->action()` or the save handler runs.** Reserve it for helper/UI-only fields — never on a field that needs to be saved.
- **Use correct property types when overriding `Page`, `Resource`, and `Widget` properties.** These properties have union types or changed modifiers that must be preserved:
  - `$navigationIcon`: `protected static string | BackedEnum | null` (not `?string`)
  - `$navigationGroup`: `protected static string | UnitEnum | null` (not `?string`)
  - `$view`: `protected string` (not `protected static string`) on `Page` and `Widget` classes
