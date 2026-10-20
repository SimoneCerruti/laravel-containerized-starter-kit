<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\LogsAllDirtyChanges;
use Database\Factories\PermissionFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\Permission\Models\Permission as SpatiePermission;

final class Permission extends SpatiePermission {
    /** @use HasFactory<PermissionFactory> */
    use HasFactory;

    use HasUuids;
    use LogsAllDirtyChanges;
}
