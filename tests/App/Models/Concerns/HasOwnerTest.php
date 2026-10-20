<?php

declare(strict_types=1);

use App\Models\Concerns\HasOwner;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    Schema::create('owned_test_models', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->foreignUuid('user_id')->constrained();
        $table->timestamps();
    });

    $this->owned_model = new class extends Model {
        use HasOwner, HasUuids;

        protected $table = 'owned_test_models';

        /** @var list<string> */
        protected $fillable = ['user_id'];
    };
});

afterEach(function (): void {
    Schema::dropIfExists('owned_test_models');
});

describe('scopes', function (): void {
    describe('whereOwnedBy', function (): void {
        test('returns only the rows owned by the given user', function (): void {
            [$owner, $other] = User::factory()->count(2)->create()->all();
            $owned = $this->owned_model::query()->create(['user_id' => $owner->id]);
            $this->owned_model::query()->create(['user_id' => $other->id]);

            $ids = $this->owned_model::query()->whereOwnedBy($owner)->pluck('id')->all();

            expect($ids)->toBe([$owned->id]);
        });

        test('returns nothing for a user that owns no rows', function (): void {
            $this->owned_model::query()->create(['user_id' => User::factory()->create()->id]);

            expect($this->owned_model::query()->whereOwnedBy(User::factory()->create())->exists())->toBeFalse();
        });

        test('qualifies the column so it survives a join', function (): void {
            $owner = User::factory()->create();
            $this->owned_model::query()->create(['user_id' => $owner->id]);

            $count = $this->owned_model::query()
                ->join('users', 'users.id', '=', 'owned_test_models.user_id')
                ->whereOwnedBy($owner)
                ->count();

            expect($count)->toBe(1);
        });
    });
});
