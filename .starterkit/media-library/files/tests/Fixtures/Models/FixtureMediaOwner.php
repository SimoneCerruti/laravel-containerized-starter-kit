<?php

declare(strict_types=1);

namespace Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Minimal media owner for the media library tests; its table is created by the test itself.
 *
 * @property string $id
 */
final class FixtureMediaOwner extends Model implements HasMedia {
    use HasUuids;
    use InteractsWithMedia;

    public const string COVER_COLLECTION = 'fixture_cover';

    protected $table = 'fixture_media_owners';

    public function registerMediaCollections(): void {
        $this->addMediaCollection(self::COVER_COLLECTION)->singleFile();
    }

    public function registerMediaConversions(?Media $media = null): void {
        $this->addMediaConversion('thumbnail')
            ->performOnCollections(self::COVER_COLLECTION)
            ->width(100)
            ->format('webp')
            ->nonQueued();
    }
}
