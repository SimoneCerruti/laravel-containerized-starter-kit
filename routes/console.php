<?php

declare(strict_types=1);

// @kit:console-imports

Schedule::timezone(config()->string('app.actual_timezone'))
    ->withoutOverlapping()
    ->group(function (): void {
        // @kit:schedule
    });

foreach (Schedule::events() as $event) {
    $event->appendOutputTo(storage_path('logs/cron.log'));
}
