<?php

declare(strict_types=1);

/*
 * Outbound HTTP integrations through Saloon, always wrapped by a Service (see .ai/rules/services.md).
 * Tests must not leave a global mock client behind.
 */
return [
    'require' => [
        'saloonphp/saloon' => '^4.5',
        'saloonphp/laravel-plugin' => '^5.0',
    ],
    'fragments' => [
        'tests/Pest.php' => [
            'pest-helpers' => 'fragments/pest-helpers.php',
        ],
    ],
    'when_disabled' => [
        'remove_files' => [
            '.ai/rules/services.md',
            '.ai/rules/testing_services.md',
        ],
    ],
];
