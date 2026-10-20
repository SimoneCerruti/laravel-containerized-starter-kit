<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Fills `created_by` / `last_updated_by` from the authenticated user (left `null` outside an authenticated context).
 * The table needs both as nullable foreign UUIDs on `users`:
 *
 * ```php
 * $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
 * $table->foreignUuid('last_updated_by')->nullable()->constrained('users')->nullOnDelete();
 * ```
 *
 * @property-read User|null $creator
 * @property-read User|null $editor
 */
trait HasBlameable {
    public static function bootHasBlameable(): void {
        static::creating(fn (self $model) => self::stampBlameable($model, is_creating: true));
        static::updating(fn (self $model) => self::stampBlameable($model, is_creating: false));
    }

    private static function stampBlameable(self $model, bool $is_creating): void {
        $user_id = auth()->id();

        if ($user_id === null) {
            return;
        }

        // Preserve an explicitly provided creator so it is never reassigned to the actor.
        if ($is_creating && blank($model->getAttribute('created_by'))) {
            $model->setAttribute('created_by', $user_id);
        }

        $model->setAttribute('last_updated_by', $user_id);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function editor(): BelongsTo {
        return $this->belongsTo(User::class, 'last_updated_by');
    }
}
