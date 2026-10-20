<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;

trait HasOwner {
    /**
     * Scope a query to the rows owned by one user.
     *
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function whereOwnedBy(Builder $query, User $user): void {
        $query->where($query->qualifyColumn('user_id'), $user->id);
    }
}
