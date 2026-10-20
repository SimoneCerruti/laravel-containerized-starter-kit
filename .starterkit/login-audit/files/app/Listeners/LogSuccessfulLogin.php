<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Enums\ActivityEvent;
use App\Models\User;
use Illuminate\Auth\Events\Login;

final class LogSuccessfulLogin {
    public function handle(Login $event): void {
        activity('auth')
            ->causedBy($event->user instanceof User ? $event->user : null)
            ->event(ActivityEvent::LOGIN->value)
            ->log('User logged in');
    }
}
