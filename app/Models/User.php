<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasBlameable;
use App\Models\Concerns\LogsAllDirtyChanges;
use App\Models\Contracts\HasDisplayTitle;
use Carbon\CarbonImmutable;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

// @kit:user-imports

/**
 * @property string $id
 * @property string $first_name
 * @property string $last_name
 * @property string $email
 * @property CarbonImmutable|null $email_verified_at
 * @property string $password
 * @property string|null $remember_token
 * @property string|null $created_by
 * @property string|null $last_updated_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read non-falsy-string $full_name
 *
 * @kit:user-phpdoc
 */
#[Fillable(['first_name', 'last_name', 'email', 'password'])]
#[Hidden([
    'password',
    'remember_token',
    // @kit:user-hidden
])]
final class User extends Authenticatable implements
    // @kit:user-implements
    HasDisplayTitle, MustVerifyEmail {
    use HasBlameable;

    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use HasUuids;
    use LogsAllDirtyChanges;
    use Notifiable;
    // @kit:user-traits

    /** @var list<string> */
    private array $non_loggable_attributes = [
        'password',
        'remember_token',
        // @kit:user-non-loggable
    ];

    /** @var array<string, mixed> */
    protected $attributes = [
        // @kit:user-attribute-defaults
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array {
        return [
            'email_verified_at' => 'immutable_datetime',
            'password' => 'hashed',
            // @kit:user-casts
        ];
    }

    /** @return Attribute<non-falsy-string, never> */
    protected function fullName(): Attribute {
        return Attribute::get(fn (): string => "{$this->first_name} {$this->last_name}");
    }

    public function getDisplayTitle(): string {
        return $this->full_name;
    }

    // @kit:user-methods
}
