<?php

declare(strict_types=1);

namespace App\Support;

use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\Support\PathGenerator\DefaultPathGenerator;

final class MediaPathGenerator extends DefaultPathGenerator
{
    protected function getBasePath(Media $media): string
    {
        $owner_directory = str($media->model_type)->classBasename()->snake()->plural()->toString();

        return "{$owner_directory}/{$media->model_id}/{$media->collection_name}/{$media->uuid}";
    }
}
