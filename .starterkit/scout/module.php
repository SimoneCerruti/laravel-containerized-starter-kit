<?php

declare(strict_types=1);

/*
 * Full-text search with Laravel Scout and a Meilisearch container.
 */
return [
    'require' => [
        'laravel/scout' => '^11.9',
        'meilisearch/meilisearch-php' => '^1.0',
        'http-interop/http-factory-guzzle' => '^1.2',
    ],
    'env' => [
        'SCOUT_DRIVER' => 'meilisearch',
        'MEILISEARCH_HOST' => 'http://meilisearch:7700',
        'MEILISEARCH_KEY' => 'masterKey',
    ],
    'fragments' => [
        'docker-compose.local.yml' => [
            'compose-services' => 'fragments/compose-services.yml',
            'compose-volumes' => 'meilisearch_data:',
        ],
    ],
    'artisan' => [
        'php artisan vendor:publish --provider="Laravel\Scout\ScoutServiceProvider" --no-interaction',
    ],
];
