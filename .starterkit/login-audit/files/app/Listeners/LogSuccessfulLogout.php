<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Enums\ActivityEvent;
use App\Models\User;
use Illuminate\Auth\Events\Logout;

final class LogSuccessfulLogout {
    public function handle(Logout $event): void {
        activity('auth')
            ->causedBy($event->user instanceof User ? $event->user : null)
            ->event(ActivityEvent::LOGOUT->value)
            ->log('User logged out');
    }
}
