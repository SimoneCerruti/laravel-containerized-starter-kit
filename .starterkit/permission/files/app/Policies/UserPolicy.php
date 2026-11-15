<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

final class UserPolicy {
    public function viewAny(User $user): bool {
        return $user->can('ViewAny:User');
    }

    public function view(User $user, User $record): bool {
        return $user->can('View:User');
    }

    public function create(User $user): bool {
        return $user->can('Create:User');
    }

    public function update(User $user, User $record): bool {
        return $user->can('Update:User');
    }

    public function delete(User $user, User $record): bool {
        return $user->can('Delete:User');
    }
}
