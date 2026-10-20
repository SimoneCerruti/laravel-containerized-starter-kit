<?php

declare(strict_types=1);

/*
 * UI language per user. The `Locale` enum is generated from the languages picked at install time,
 * and `ApplyUserLocale` sets the app locale from `User::$locale` on every authenticated request.
 * With Filament the middleware joins the panel's auth middleware, otherwise the web group.
 */
return [
    'require-dev' => [
        'laravel-lang/common' => '^6.7',
    ],
    'fragments' => [
        'database/migrations/0001_01_01_000000_create_users_table.php' => [
            'users-columns' => '$table->string(\'locale\')->default(config(\'app.locale\'));',
        ],
        'app/Models/User.php' => [
            'user-imports' => "use App\\Enums\\Locale;\nuse Illuminate\\Contracts\\Translation\\HasLocalePreference;",
            'user-phpdoc' => '@property Locale $locale',
            'user-implements' => 'HasLocalePreference,',
            'user-casts' => '\'locale\' => Locale::class,',
            'user-attribute-defaults' => "'locale' => Locale::__KIT_DEFAULT_LOCALE_CASE__->value,",
            'user-methods' => "public function preferredLocale(): string {\n    return \$this->locale->value;\n}",
        ],
        'database/factories/UserFactory.php' => [
            'factory-definition' => '\'locale\' => config(\'app.locale\'),',
        ],
    ],
];
