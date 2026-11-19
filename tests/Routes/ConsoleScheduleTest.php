<?php

declare(strict_types=1);

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;

use function Safe\file_get_contents;

/**
 * @return list<Event>
 */
function scheduledEventsRunning(string $artisan_command): array {
    return collect(resolve(Schedule::class)->events())
        ->filter(fn (Event $event): bool => str_contains((string) $event->command, $artisan_command))
        ->values()
        ->all();
}

describe('activity log cleanup', function (): void {
    test('runs daily without overlapping, in the application timezone', function (): void {
        $events = scheduledEventsRunning('activitylog:clean');

        expect($events)->toHaveCount(1)
            ->and($events[0]->expression)->toBe('0 2 * * *')
            ->and($events[0]->withoutOverlapping)->toBeTrue()
            ->and($events[0]->timezone)->toBe(config('app.actual_timezone'));
    });

    test('deletes records older than a year unless the retention is overridden', function (): void {
        expect(config('activitylog.clean_after_days'))->toBe(365);
    });
});

test('the console routes import the Schedule facade instead of relying on the global alias', function (): void {
    expect(file_get_contents(base_path('routes/console.php')))->toContain('use Illuminate\Support\Facades\Schedule;');
});
