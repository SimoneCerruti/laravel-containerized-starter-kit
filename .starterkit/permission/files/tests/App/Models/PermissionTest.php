<?php

declare(strict_types=1);

use App\Models\Permission;

test('uses a UUID primary key', function (): void {
    $permission = Permission::query()->create(['name' => 'posts.view', 'guard_name' => 'web']);

    expect($permission->id)->toBeUuid();
});
