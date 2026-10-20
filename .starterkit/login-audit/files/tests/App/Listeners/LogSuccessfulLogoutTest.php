<?php

declare(strict_types=1);

use App\Enums\ActivityEvent;
use App\Listeners\LogSuccessfulLogout;
use App\Models\User;
use Illuminate\Auth\Events\Logout;
use Spatie\Activitylog\Models\Activity;

it('logs a logout', function (): void {
    $user = User::factory()->create();

    new LogSuccessfulLogout()->handle(new Logout('web', $user));

    expect(Activity::query()->where('event', ActivityEvent::LOGOUT->value)->exists())->toBeTrue();
});
