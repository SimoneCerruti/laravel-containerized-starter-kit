<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Enums\ActivityEvent;
use App\Models\User;
use Illuminate\Auth\Events\Failed;

final class LogFailedLogin {
    public function handle(Failed $event): void {
        activity('auth')
            ->causedBy($event->user instanceof User ? $event->user : null)
            ->withProperties(['email' => $event->credentials['email'] ?? null])
            ->event(ActivityEvent::LOGIN_FAILED->value)
            ->log('Login attempt failed');
    }
}
