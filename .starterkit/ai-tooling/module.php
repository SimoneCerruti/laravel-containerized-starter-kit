<?php

declare(strict_types=1);

/*
 * AI tooling: Boost guidelines and skills, MCP servers, Claude config. Tokens go to `.env` only
 * (never `.env.example`). When the module is not selected, `when_disabled` removes its files and
 * the Boost package; the compose variables and the composer `ai:update` script are added here.
 */
return [
    'env_local' => [
        'GH_TOKEN' => '__KIT_GH_TOKEN__',
        'CONTEXT7_API_KEY' => '__KIT_CONTEXT7_API_KEY__',
    ],
    'artisan' => [
        'php artisan ai:link-hooks',
    ],
    'fragments' => [
        'docker-compose.local.yml' => [
            'compose-environment' => <<<'YAML'
- GH_TOKEN=${GH_TOKEN:-}
- GITHUB_PERSONAL_ACCESS_TOKEN=${GH_TOKEN:-}
- CONTEXT7_API_KEY=${CONTEXT7_API_KEY:-}
YAML,
        ],
    ],
    'json' => [
        'composer' => [
            'scripts.ai:update' => ['@php artisan boost:update --ansi', '@php artisan ai:link-hooks --ansi'],
            'scripts.post-update-cmd' => ['@composer ai:update'],
        ],
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
        'remove_packages' => [
            'laravel/boost',
        ],
    ],
];
