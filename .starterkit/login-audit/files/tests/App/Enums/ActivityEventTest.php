<?php

declare(strict_types=1);

use App\Enums\ActivityEvent;

it('names the authentication events', function (): void {
    expect(array_column(ActivityEvent::cases(), 'value'))->toBe(['login', 'login_failed', 'logout']);
});
