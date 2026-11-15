test('every model except Role and Permission has a Policy', function (): void {
    $without_policy = collect(glob(app_path('Models/*.php')))
        ->map(fn (string $file): string => 'App\\Models\\'.pathinfo($file, PATHINFO_FILENAME))
        ->filter(fn (string $class): bool => is_subclass_of($class, Model::class))
        ->reject(fn (string $class): bool => in_array($class, [\App\Models\Permission::class, \App\Models\Role::class], true))
        ->reject(fn (string $class): bool => \Illuminate\Support\Facades\Gate::getPolicyFor($class) !== null)
        ->values()
        ->all();

    expect($without_policy)->toBeEmpty();
});
