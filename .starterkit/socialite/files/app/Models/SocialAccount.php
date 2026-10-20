<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasOwner;
use App\Models\Concerns\LogsAllDirtyChanges;
use Carbon\CarbonImmutable;
use Database\Factories\SocialAccountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $user_id
 * @property string $provider
 * @property string $provider_user_id
 * @property string|null $token
 * @property string|null $refresh_token
 * @property CarbonImmutable|null $expires_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read User $user
 */
#[Fillable(['user_id', 'provider', 'provider_user_id', 'token', 'refresh_token', 'expires_at'])]
#[Hidden(['token', 'refresh_token'])]
final class SocialAccount extends Model {
    /** @use HasFactory<SocialAccountFactory> */
    use HasFactory;

    use HasOwner;
    use HasUuids;
    use LogsAllDirtyChanges;

    /** @var list<string> */
    private array $non_loggable_attributes = [
        'token',
        'refresh_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array {
        return [
            'token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'expires_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo {
        return $this->belongsTo(User::class);
    }
}
