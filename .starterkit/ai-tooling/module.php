<?php

declare(strict_types=1);

/*
 * AI tooling: Boost guidelines and skills, MCP servers, Claude config. Tokens go to `.env` only
 * (never `.env.example`). When the module is not selected, `when_disabled` strips everything it owns.
 */
return [
    'env_local' => [
        'GH_TOKEN' => '__KIT_GH_TOKEN__',
        'CONTEXT7_API_KEY' => '__KIT_CONTEXT7_API_KEY__',
    ],
    'artisan' => [
        'php artisan ai:link-hooks',
    ],
    'replace' => [
        '.mcp.json' => [
            "            \"command\": \"wsl.exe\",\n            \"args\": [\n                \"/usr/bin/php8.4\",\n                \"/home/simone/projects/laravel-containerized-starter-kit/artisan\",\n                \"boost:mcp\"\n            ]" => "            \"command\": \"php\",\n            \"args\": [\n                \"artisan\",\n                \"boost:mcp\"\n            ]",
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
        'replace' => [
            'composer.json' => [
                ",\n            \"@composer ai:update\"" => '',
                "        \"ai:update\": [\n            \"@php artisan boost:update --ansi\",\n            \"@php artisan ai:link-hooks --ansi\"\n        ],\n" => '',
            ],
            'docker-compose.local.yml' => [
                "      - GH_TOKEN=\${GH_TOKEN:-}\n      - GITHUB_PERSONAL_ACCESS_TOKEN=\${GH_TOKEN:-}\n      - CONTEXT7_API_KEY=\${CONTEXT7_API_KEY:-}\n" => '',
            ],
        ],
        'remove_packages' => [
            'laravel/boost',
        ],
    ],
];
