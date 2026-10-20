<?php

declare(strict_types=1);

use App\Enums\ActivityEvent;
use App\Listeners\LogSuccessfulLogin;
use App\Models\User;
use Illuminate\Auth\Events\Login;
use Spatie\Activitylog\Models\Activity;

it('logs a successful login', function (): void {
    $user = User::factory()->create();

    new LogSuccessfulLogin()->handle(new Login('web', $user, false));

    expect(Activity::query()->where('event', ActivityEvent::LOGIN->value)->where('causer_id', $user->id)->exists())->toBeTrue();
});
