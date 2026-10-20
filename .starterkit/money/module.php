<?php

declare(strict_types=1);

/*
 * Money as integer minor units. `AsMoney` relies on brick/math's BigDecimal; the Number and
 * Config macros are registered by their own service providers.
 */
return [
    'require' => [
        'brick/math' => '*',
        'brick/money' => '*',
    ],
    'fragments' => [
        'bootstrap/providers.php' => [
            'providers-imports' => "use App\\Providers\\ConfigMacroServiceProvider;\nuse App\\Providers\\NumberMacroServiceProvider;",
            'providers' => "NumberMacroServiceProvider::class,\nConfigMacroServiceProvider::class,",
        ],
    ],
];
