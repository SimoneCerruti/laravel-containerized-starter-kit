<?php

declare(strict_types=1);

namespace App\Enums;

enum ActivityEvent: string {
    case LOGIN = 'login';
    case LOGIN_FAILED = 'login_failed';
    case LOGOUT = 'logout';
}
