<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

// @kit:pest-imports

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in(__DIR__)
    ->beforeEach(function (): void {
        Mail::fake();
        Notification::fake();
        Sleep::fake();
        Storage::fake('local');
        App::setLocale(config()->string('app.locale'));
        Http::preventStrayRequests();
        Process::fake([]);
        Process::preventStrayProcesses();
        $this->freezeTime();
        // @kit:pest-before-each
    })
    ->afterEach(function (): void {
        Str::createUuidsNormally();
    });

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->intercept('toBe', Model::class, function (Model $expected): void {
    expect($expected->is($this->value))->toBeTrue(
        'Failed asserting that two models are the same record (same table, connection and key).'
    );
});

expect()->intercept('toEqual', Model::class, function (Model $expected): void {
    expect($expected->is($this->value))->toBeTrue(
        'Failed asserting that two models are the same record (same table, connection and key).'
    );
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * @return Collection<int, Activity>
 */
function getActivityLogsFor(Model $subject, ?string $event = null): EloquentCollection {
    return Activity::query()
        ->forSubject($subject)
        ->when($event !== null, fn (Builder $query): Builder => $query->forEvent($event))
        ->orderBy('id')
        ->get();
}

function getLastActivityLogsFor(Model $subject, ?string $event = null): ?Activity {
    return getActivityLogsFor($subject, $event)->last();
}

function assertNoActivityLogsFor(Model $subject, ?string $event = null): void {
    expect(getActivityLogsFor($subject, $event))->toBeEmpty();
}

function assertCountActivityLogsFor(Model $subject, int $count, ?string $event = null): void {
    expect(getActivityLogsFor($subject, $event))->toHaveCount($count);
}

function assertActivityLogHasChanges(?Activity $activity): void {
    expect($activity)->not->toBeNull()
        ->and($activity?->attribute_changes)->not->toBeNull();
}

/**
 * Makes every UUID generated afterwards (HasUuids, Str::uuid()) the same value until the test ends.
 */
function freezeUuids(): string {
    $uuid = Str::freezeUuids();

    return (string) $uuid;
}

// @kit:pest-helpers
