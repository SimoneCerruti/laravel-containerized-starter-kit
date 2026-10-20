<?php

declare(strict_types=1);

use Rector\CodingStyle\Rector\PostInc\PostIncDecToPreIncDecRector;
use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector\ClassMethod\RemoveUnusedPublicMethodParameterRector;
use Rector\Php80\Rector\Switch_\ChangeSwitchToMatchRector;
use Rector\Php81\Rector\Property\ReadOnlyPropertyRector;
use Rector\Php83\Rector\ClassConst\AddTypeToConstRector;
use RectorLaravel\Rector\FuncCall\ConfigToTypedConfigMethodCallRector;
use RectorLaravel\Set\LaravelSetList;

return RectorConfig::configure()
    ->withPaths([
        __DIR__.'/config',
        __DIR__.'/app',
        __DIR__.'/database',
        __DIR__.'/routes',
    ])
    ->withSets([
        LaravelSetList::LARAVEL_CODE_QUALITY,
        LaravelSetList::LARAVEL_COLLECTION,
        LaravelSetList::LARAVEL_130,
        LaravelSetList::LARAVEL_IF_HELPERS,
    ])
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        typeDeclarations: true,
        privatization: true,
        instanceOf: true,
        earlyReturn: true,
        rectorPreset: true,
    )
    ->withRules([
        ChangeSwitchToMatchRector::class,
        ReadOnlyPropertyRector::class,
        AddTypeToConstRector::class,
        ConfigToTypedConfigMethodCallRector::class,
    ])
    ->withSkip([
        PostIncDecToPreIncDecRector::class,
        RemoveUnusedPublicMethodParameterRector::class => [
            __DIR__.'/app/Policies',
        ],
    ]);
