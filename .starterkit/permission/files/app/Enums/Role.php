<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasAutoLabel;

enum Role: string {
    use HasAutoLabel;

    case USER = 'user';
    case ADMIN = 'admin';
    case SUPER_ADMIN = 'super_admin';
}
