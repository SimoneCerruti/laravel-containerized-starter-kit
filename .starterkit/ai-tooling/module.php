<?php

declare(strict_types=1);

/*
 * AI tooling: Boost guidelines and skills, MCP servers, Claude config. Tokens go to `.env` only
 * (never `.env.example`). When the module is not selected, `when_disabled` strips everything it owns:
 * files, packages, JSON entries and the `@kit:begin ai-tooling` regions of the shared files.
 */
return [
    'env_local' => [
        'GH_TOKEN' => '__KIT_GH_TOKEN__',
        'CONTEXT7_API_KEY' => '__KIT_CONTEXT7_API_KEY__',
    ],
    'artisan' => [
        'php artisan ai:link-hooks',
    ],
    'when_disabled' => [
        'remove_files' => [
            '.claude',
            '.ai',
            'boost.json',
            '.mcp.json',
            'CLAUDE.md',
            'AGENTS.md',
            'config/boost.php',
            'app/Console/Commands/LinkAiHooksCommand.php',
            'tests/App/Console/Commands/LinkAiHooksCommandTest.php',
        ],
        'json' => [
            'composer.json' => [
                'scripts.ai:update' => null,
                'scripts.post-update-cmd' => ['@composer ai:update'],
            ],
        ],
        'remove_packages' => [
            'laravel/boost',
        ],
    ],
];
