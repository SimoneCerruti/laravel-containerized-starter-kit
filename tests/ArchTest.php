<?php

declare(strict_types=1);

use App\Http\Controllers\Controller;
use App\Macros\Contracts\Macro;
use App\Macros\Str\EscapeControlCharactersMacro;
use App\Models\Concerns\HasOwner;
use App\Models\Concerns\LogsAllDirtyChanges;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

use function Safe\glob;

arch('php')->preset()->php();

arch('security')->preset()->security();

arch('laravel')
    ->preset()->laravel()
    ->ignoring([
        'App\Console\Commands\StarterKit',
        // @kit:arch-laravel-ignores
    ]);

arch('strict')
    ->preset()->strict()
    ->ignoring([
        'App\\Macros',
        'App\\Models',
        Controller::class,
        // @kit:arch-strict-ignores
    ]);

arch('control characters are escaped through the Str macro')
    ->expect('addcslashes')
    ->not->toBeUsedIn('App')
    ->ignoring(EscapeControlCharactersMacro::class);

arch('app uses strict types')
    ->expect('App')
    ->toUseStrictTypes();

arch('models are final')
    ->expect('App\Models')
    ->classes()
    ->toBeFinal();

arch('policies are final')
    ->expect('App\Policies')
    ->classes()
    ->toBeFinal();

arch('models log every change')
    ->expect('App\Models')
    ->classes()
    ->toUseTrait(LogsAllDirtyChanges::class);

arch('models use uuid primary keys')
    ->expect('App\Models')
    ->classes()
    ->toUseTrait(HasUuids::class);

arch('models have a factory')
    ->expect('App\Models')
    ->classes()
    ->toUseTrait(HasFactory::class);

test('Actions live directly in app/Actions, without subfolders', function (): void {
    $subfolders = collect(glob(app_path('Actions/*'), GLOB_ONLYDIR) ?: [])
        ->map(fn (string $directory): string => basename($directory))
        ->all();

    expect($subfolders)->toBeEmpty();
});

test('every Action in app/Actions is final and exposes exactly one public handle method', function (): void {
    $violations = collect(glob(app_path('Actions/*.php')))
        ->map(fn (string $file): string => 'App\\Actions\\'.pathinfo($file, PATHINFO_FILENAME))
        ->filter(fn (string $class): bool => class_exists($class))
        ->flatMap(function (string $action_class): array {
            $reflection = new ReflectionClass($action_class);

            $trait_method_names = collect(class_uses_recursive($action_class))
                ->flatMap(fn (string $trait): array => array_map(
                    fn (ReflectionMethod $method): string => $method->getName(),
                    (new ReflectionClass($trait))->getMethods(ReflectionMethod::IS_PUBLIC),
                ))
                ->unique()
                ->values()
                ->all();

            $own_public_method_names = collect($reflection->getMethods(ReflectionMethod::IS_PUBLIC))
                ->map(fn (ReflectionMethod $method): string => $method->getName())
                ->reject(fn (string $name): bool => in_array($name, $trait_method_names, true))
                ->reject(fn (string $name): bool => $name === '__construct')
                ->values()
                ->all();

            return array_filter([
                $reflection->isFinal() ? null : "{$action_class} must be final.",
                $own_public_method_names === ['handle'] ? null : "{$action_class} must expose exactly one public handle() method, found: ".implode(', ', $own_public_method_names),
            ]);
        })
        ->values()
        ->all();

    expect($violations)->toBeEmpty();
});

test('every model with a user_id column uses HasOwner', function (): void {
    $violations = collect(glob(app_path('Models/*.php')))
        ->map(fn (string $file): string => 'App\\Models\\'.pathinfo($file, PATHINFO_FILENAME))
        ->filter(fn (string $class): bool => is_subclass_of($class, Model::class))
        ->filter(fn (string $class): bool => Schema::hasColumn(new $class()->getTable(), 'user_id'))
        ->reject(fn (string $class): bool => in_array(HasOwner::class, class_uses_recursive($class), true))
        ->values()
        ->all();

    expect($violations)->toBeEmpty();
});

test('every class in app/Data is final and every one of its own properties is readonly', function (): void {
    $violations = collect(glob(app_path('Data/*.php')))
        ->map(fn (string $file): string => 'App\\Data\\'.pathinfo($file, PATHINFO_FILENAME))
        ->merge(
            collect(glob(app_path('Services/*/Data/*.php')))
                ->map(function (string $file): string {
                    $service = basename(\dirname($file, 2));
                    $class_name = pathinfo($file, PATHINFO_FILENAME);

                    return "App\\Services\\{$service}\\Data\\{$class_name}";
                }),
        )
        ->filter(fn (string $class): bool => class_exists($class))
        ->flatMap(function (string $data_class): array {
            $reflection = new ReflectionClass($data_class);

            $writable_properties = collect($reflection->getProperties())
                ->filter(fn (ReflectionProperty $property): bool => $property->getDeclaringClass()->getName() === $data_class)
                ->reject(fn (ReflectionProperty $property): bool => $property->isReadOnly())
                ->map(fn (ReflectionProperty $property): string => "{$data_class}::\${$property->getName()} must be readonly.")
                ->values()
                ->all();

            return $reflection->isFinal() ? $writable_properties : ["{$data_class} must be final.", ...$writable_properties];
        })
        ->values()
        ->all();

    expect($violations)->toBeEmpty();
});

arch('actions do not depend on the HTTP layer or the authenticated user')
    ->expect('App\Actions')
    ->not->toUse(['App\Http', 'Illuminate\Http', Auth::class, 'request', 'auth', 'session']);

arch('models do not depend on the HTTP, console or Filament layers')
    ->expect('App\Models')
    ->not->toUse(['App\Http', 'App\Console', 'App\Filament']);

arch('macros extend the Macro contract')
    ->expect('App\Macros')
    ->classes()
    ->toExtend(Macro::class)
    ->ignoring('App\Macros\Contracts');

arch('dates are immutable')
    ->expect('App')
    ->not->toUse([Carbon::class, Illuminate\Support\Carbon::class]);

test('Concerns folders hold only traits and Contracts folders only interfaces or abstract classes', function (): void {
    $violations = collect(new RecursiveIteratorIterator(new RecursiveDirectoryIterator(app_path(), FilesystemIterator::SKIP_DOTS)))
        ->filter(fn (SplFileInfo $file): bool => $file->getExtension() === 'php')
        ->filter(fn (SplFileInfo $file): bool => in_array(basename($file->getPath()), ['Concerns', 'Contracts'], true))
        ->map(fn (SplFileInfo $file): string => 'App\\'.str_replace('/', '\\', mb_substr($file->getPathname(), mb_strlen(app_path()) + 1, -4)))
        ->reject(function (string $class): bool {
            if (str_contains($class, '\\Concerns\\')) {
                return trait_exists($class);
            }

            return interface_exists($class) || (class_exists($class) && new ReflectionClass($class)->isAbstract());
        })
        ->values()
        ->all();

    expect($violations)->toBeEmpty();
});

test('models never opt out of mass-assignment protection', function (): void {
    $violations = collect(new RecursiveIteratorIterator(new RecursiveDirectoryIterator(app_path(), FilesystemIterator::SKIP_DOTS)))
        ->filter(fn (SplFileInfo $file): bool => $file->getExtension() === 'php')
        ->filter(fn (SplFileInfo $file): bool => preg_match('/\bunguard(ed)?\s*\(|\$guarded\b/', (string) file_get_contents($file->getPathname())) === 1)
        ->map(fn (SplFileInfo $file): string => $file->getPathname())
        ->values()
        ->all();

    expect($violations)->toBeEmpty();
});

test('migrations are forward-only and use UUID keys', function (): void {
    $infrastructure_tables = ['create_jobs_table', 'create_activity_log_table'];
    $vendor_tables = [
        // @kit:arch-vendor-migrations
    ];

    $violations = collect(glob(database_path('migrations/*.php')))
        ->reject(fn (string $file): bool => collect($vendor_tables)->contains(fn (string $table): bool => str_contains($file, $table)))
        ->flatMap(function (string $file) use ($infrastructure_tables): array {
            $contents = file_get_contents($file);
            $is_infrastructure = collect($infrastructure_tables)->contains(fn (string $table): bool => str_contains($file, $table));

            return array_filter([
                preg_match('/function\s+down\s*\(/', $contents) === 1 ? basename($file).' must not define down().' : null,
                preg_match('/->foreignId\s*\(/', $contents) === 1 ? basename($file).' must use foreignUuid() instead of foreignId().' : null,
                ! $is_infrastructure && preg_match('/\$table->id\s*\(/', $contents) === 1 ? basename($file).' must use uuid(\'id\')->primary() instead of id().' : null,
            ]);
        })
        ->values()
        ->all();

    expect($violations)->toBeEmpty();
});

test('every test file declares strict types', function (): void {
    $violations = collect(new RecursiveIteratorIterator(new RecursiveDirectoryIterator(base_path('tests'), FilesystemIterator::SKIP_DOTS)))
        ->filter(fn (SplFileInfo $file): bool => $file->getExtension() === 'php')
        ->filter(fn (SplFileInfo $file): bool => ! str_contains((string) file_get_contents($file->getPathname()), 'declare(strict_types=1);'))
        ->map(fn (SplFileInfo $file): string => mb_substr($file->getPathname(), mb_strlen(base_path()) + 1))
        ->values()
        ->all();

    expect($violations)->toBeEmpty();
});

test('every concrete class, trait and enum in app has a test file', function (): void {
    $exempt = [
        'app/Providers/',
        'app/Http/Controllers/Controller.php',
        // @kit:arch-untested-exemptions
    ];

    $violations = collect(new RecursiveIteratorIterator(new RecursiveDirectoryIterator(app_path(), FilesystemIterator::SKIP_DOTS)))
        ->filter(fn (SplFileInfo $file): bool => $file->getExtension() === 'php')
        ->map(fn (SplFileInfo $file): string => mb_substr($file->getPathname(), mb_strlen(base_path()) + 1))
        ->reject(fn (string $path): bool => collect($exempt)->contains(fn (string $prefix): bool => str_starts_with($path, $prefix)))
        ->reject(fn (string $path): bool => str_contains($path, '/Contracts/'))
        ->filter(function (string $path): bool {
            $class = 'App\\'.str_replace('/', '\\', mb_substr($path, 4, -4));

            if (trait_exists($class) || enum_exists($class)) {
                return true;
            }

            return class_exists($class) && ! new ReflectionClass($class)->isAbstract();
        })
        ->reject(fn (string $path): bool => is_file(base_path('tests/App/'.mb_substr($path, 4, -4).'Test.php')))
        ->map(fn (string $path): string => "{$path} has no tests/App/".mb_substr($path, 4, -4).'Test.php.')
        ->values()
        ->all();

    expect($violations)->toBeEmpty();
});

test('variables are snake_case', function (): void {
    $allowed = [
        'loggableAttributes',
        'nonLoggableAttributes',
        // @kit:arch-camel-case-variables
    ];

    $violations = collect(['app', 'bootstrap', 'config', 'database', 'routes', 'tests'])
        ->flatMap(fn (string $folder): array => iterator_to_array(new RecursiveIteratorIterator(new RecursiveDirectoryIterator(base_path($folder), FilesystemIterator::SKIP_DOTS)), false))
        ->filter(fn (SplFileInfo $file): bool => $file->getExtension() === 'php' && ! str_ends_with($file->getFilename(), '.blade.php'))
        ->flatMap(function (SplFileInfo $file) use ($allowed): array {
            $relative_path = mb_substr($file->getPathname(), mb_strlen(base_path()) + 1);

            return collect(PhpToken::tokenize((string) file_get_contents($file->getPathname())))
                ->filter(fn (PhpToken $token): bool => $token->is(T_VARIABLE))
                ->filter(fn (PhpToken $token): bool => preg_match('/[a-z0-9][A-Z]/', $token->text) === 1)
                ->reject(fn (PhpToken $token): bool => in_array(ltrim($token->text, '$'), $allowed, true))
                ->map(fn (PhpToken $token): string => "{$relative_path}:{$token->line} {$token->text}")
                ->all();
        })
        ->values()
        ->all();

    expect($violations)->toBeEmpty();
});

// @kit:arch-rules
