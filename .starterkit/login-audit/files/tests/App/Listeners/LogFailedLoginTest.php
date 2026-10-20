<?php

declare(strict_types=1);

use App\Enums\ActivityEvent;
use App\Listeners\LogFailedLogin;
use Illuminate\Auth\Events\Failed;
use Spatie\Activitylog\Models\Activity;

it('logs a failed login with the attempted email', function (): void {
    new LogFailedLogin()->handle(new Failed('web', null, ['email' => 'nobody@example.com']));

    expect(Activity::query()->where('event', ActivityEvent::LOGIN_FAILED->value)->first()->properties['email'])->toBe('nobody@example.com');
});
