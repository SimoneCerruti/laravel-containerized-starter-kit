<?php

declare(strict_types=1);

/*
 * CodeGraph: a local code index and MCP server for the agents, part of the AI tooling. The base
 * files ship with it wired in; when it is not selected `when_disabled` takes it out: the JSON
 * entries below and the `@kit:begin codegraph` regions of the Dockerfile, entrypoint, .gitignore
 * and agent guidelines.
 */
return [
    'when_disabled' => [
        'json' => [
            '.mcp.json' => [
                'mcpServers.codegraph' => null,
            ],
            '.claude/settings.json' => [
                'permissions.allow' => ['mcp__codegraph__*'],
                'enabledMcpjsonServers' => ['codegraph'],
                'hooks.UserPromptSubmit' => null,
            ],
        ],
    ],
];
