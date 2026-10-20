<?php

declare(strict_types=1);

use App\Enums\Role;

it('lists the application roles', function (): void {
    expect(array_column(Role::cases(), 'value'))->toBe(['user', 'admin', 'super_admin']);
});

it('labels each role from its humanized value', function (): void {
    expect(Role::SUPER_ADMIN->getLabel())->toBe('Super admin');
});
