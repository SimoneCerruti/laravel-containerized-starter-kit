# Code Style

- Use **`snake_case`** for variables. Eg: the following is WRONG `$customerData`, while the following is CORRECT `$customer_data`.  
  Exceptions are possible when otherwise the code or the static analysis would break (eg: `saloonphp` needs camelCase variables).
- Use descriptive names for variables and methods. Eg: `isRegisteredForDiscounts()`, not `discount()`.
- Start a method name with a verb that names the full action, so the call site alone tells a command from a query, without opening the definition. Eg: `exportData()` is ambiguous — it may run the export or return the rows to be exported; `getExportData()` reads as a query, `exportDataToCsv()` as a command.
- Boolean variables or methods that return bool must always start with `is` or `has`.
- Use PHP attributes, and prefer attribute-based syntax, whenever possible.
  Eg: `#[\Illuminate\Database\Eloquent\Attributes\Scope]` on a `whereActive()` method instead of `scopeWhereActive()`.
- Prefer Facades over inline helpers when method chaining is not necessary. Eg `Auth::id()` wins over `auth()->id()`.  
  Prefer helpers when method chaining degrades readability. Eg: `str('Some String')->lower()->replace('_', ' ')->ucfirst()->toString()` wins over `Str::ucfirst(Str::replace('_', ' ', Str::lower('Some String')))`.  
  If `use` statements are NOT available (eg in blade views), thus forcing Facades to be used from FQN, prefer helpers.
- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- NO comments. Most lines, functions, and even unusual-looking logic should ship with zero comments, trust the code and naming to speak for themselves.
Add a comment only when omitting it would likely cause a future reader to "fix" correct behavior into a bug.
Hard cap: 1-3 lines per comment. No section headers, no bullet lists, no multi-paragraph comments.
Never include: references to external docs/ADRs/tickets/issues, restatement of what the code visibly does, historical/debug/design rationale ("we chose X over Y because..."), UI labels or naming trivia.
Pattern: "<what's non-obvious> — <why, in one clause>."
- No class-explanation comments above class declaration.
- Prefer PHPDoc blocks over inline comments.
- No docblocks on simple variable declarations, except type hints like `/** @var \App\Models\User $current_user */`.
- Add PHPDoc on a method only when it is `public`, or when it needs annotations more precise than
  native PHP allows (array shapes, generics, …).
- Use array shape type definitions in PHPDoc blocks.
