<?php

declare(strict_types=1);

use App\Support\MediaPathGenerator;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\Support\PathGenerator\PathGeneratorFactory;
use Tests\Fixtures\Models\FixtureMediaOwner;

beforeEach(function (): void {
    Schema::create('fixture_media_owners', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->timestamps();
    });

    Config::set('media-library.disk_name', 'local');
});

afterEach(function (): void {
    Schema::dropIfExists('fixture_media_owners');
});

function attachFixtureCover(FixtureMediaOwner $owner, int $width = 600, int $height = 900): void {
    $owner->addMedia(UploadedFile::fake()->image('cover.jpg', $width, $height))
        ->toMediaCollection(FixtureMediaOwner::COVER_COLLECTION);
}

describe('paths', function (): void {
    test('groups the files of a media by owner model, collection and media uuid', function (): void {
        $owner = FixtureMediaOwner::query()->create();
        attachFixtureCover($owner);
        $media = $owner->fresh()?->getFirstMedia(FixtureMediaOwner::COVER_COLLECTION);
        $base = "fixture_media_owners/{$owner->id}/fixture_cover/{$media?->uuid}";
        $generator = new MediaPathGenerator;

        expect($media)->not->toBeNull()
            ->and($generator->getPath($media))->toBe("{$base}/")
            ->and($generator->getPathForConversions($media))->toBe("{$base}/conversions/")
            ->and($generator->getPathForResponsiveImages($media))->toBe("{$base}/responsive-images/");
    });

    test('gives the media of two owners different paths', function (): void {
        $first_owner = FixtureMediaOwner::query()->create();
        $second_owner = FixtureMediaOwner::query()->create();
        attachFixtureCover($first_owner);
        attachFixtureCover($second_owner);
        $generator = new MediaPathGenerator;

        $first = $first_owner->fresh()?->getFirstMedia(FixtureMediaOwner::COVER_COLLECTION);
        $second = $second_owner->fresh()?->getFirstMedia(FixtureMediaOwner::COVER_COLLECTION);

        expect($first)->not->toBeNull()
            ->and($second)->not->toBeNull()
            ->and($generator->getPath($first))->not->toBe($generator->getPath($second));
    });
});

describe('configuration', function (): void {
    test('is the media library path generator', function (): void {
        $owner = FixtureMediaOwner::query()->create();
        attachFixtureCover($owner);
        $media = $owner->fresh()?->getFirstMedia(FixtureMediaOwner::COVER_COLLECTION);

        expect(Config::string('media-library.path_generator'))->toBe(MediaPathGenerator::class)
            ->and(PathGeneratorFactory::create($media))->toBeInstanceOf(MediaPathGenerator::class);
    });
});

describe('storage', function (): void {
    test('stores a file and its conversions under the generated path', function (): void {
        $owner = FixtureMediaOwner::query()->create();

        attachFixtureCover($owner);

        $media = $owner->fresh()?->getFirstMedia(FixtureMediaOwner::COVER_COLLECTION);
        $base = "fixture_media_owners/{$owner->id}/fixture_cover/{$media?->uuid}";

        Storage::disk('local')->assertExists("{$base}/cover.jpg");
        Storage::disk('local')->assertExists("{$base}/conversions/cover-thumbnail.webp");
    });

    test('keeps the new files when a file is replaced by one with the same name', function (): void {
        $owner = FixtureMediaOwner::query()->create();

        attachFixtureCover($owner, 600, 900);
        $old_media = $owner->fresh()?->getFirstMedia(FixtureMediaOwner::COVER_COLLECTION);

        attachFixtureCover($owner, 500, 700);
        $new_media = $owner->fresh()?->getFirstMedia(FixtureMediaOwner::COVER_COLLECTION);

        expect($new_media?->is($old_media))->toBeFalse();

        Storage::disk('local')->assertMissing($old_media?->getPathRelativeToRoot() ?? '');
        Storage::disk('local')->assertExists($new_media?->getPathRelativeToRoot() ?? '');
        Storage::disk('local')->assertExists($new_media?->getPathRelativeToRoot('thumbnail') ?? '');
    });
});
