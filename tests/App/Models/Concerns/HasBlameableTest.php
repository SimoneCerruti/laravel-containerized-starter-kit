<?php

declare(strict_types=1);

use App\Models\Concerns\HasBlameable;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    Schema::create('blameable_test_models', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->string('name')->nullable();
        $table->foreignUuid('created_by')->nullable()->constrained('users');
        $table->foreignUuid('last_updated_by')->nullable()->constrained('users');
        $table->timestamps();
    });

    $this->blameable_model = new class extends Model {
        use HasBlameable, HasUuids;

        protected $table = 'blameable_test_models';

        /** @var list<string> */
        protected $fillable = ['name', 'created_by'];
    };
});

afterEach(function (): void {
    Schema::dropIfExists('blameable_test_models');
});

describe('creating', function (): void {
    test('stamps both columns with the authenticated user', function (): void {
        $actor = User::factory()->create();

        $model = $this->actingAs($actor)->blameable_model::query()->create(['name' => 'a']);

        expect($model->created_by)->toBe($actor->id)
            ->and($model->last_updated_by)->toBe($actor->id);
    });

    test('leaves both columns null outside an authenticated context', function (): void {
        $model = $this->blameable_model::query()->create(['name' => 'a']);

        expect($model->created_by)->toBeNull()
            ->and($model->last_updated_by)->toBeNull();
    });

    test('keeps an explicitly provided creator', function (): void {
        [$actor, $creator] = User::factory()->count(2)->create()->all();

        $model = $this->actingAs($actor)->blameable_model::query()->create(['name' => 'a', 'created_by' => $creator->id]);

        expect($model->created_by)->toBe($creator->id)
            ->and($model->last_updated_by)->toBe($actor->id);
    });
});

describe('updating', function (): void {
    test('changes only the editor and keeps the creator', function (): void {
        [$creator, $editor] = User::factory()->count(2)->create()->all();
        $model = $this->actingAs($creator)->blameable_model::query()->create(['name' => 'a']);

        $this->actingAs($editor);
        $model->update(['name' => 'b']);

        expect($model->created_by)->toBe($creator->id)
            ->and($model->last_updated_by)->toBe($editor->id);
    });
});

describe('relations', function (): void {
    test('exposes the creator and the editor', function (): void {
        [$creator, $editor] = User::factory()->count(2)->create()->all();
        $model = $this->actingAs($creator)->blameable_model::query()->create(['name' => 'a']);
        $this->actingAs($editor);
        $model->update(['name' => 'b']);

        expect($model->creator->is($creator))->toBeTrue()
            ->and($model->editor->is($editor))->toBeTrue();
    });
});
