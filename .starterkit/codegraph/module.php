<?php

declare(strict_types=1);

/*
 * CodeGraph: a local code index and MCP server for the agents, part of the AI tooling. Everything
 * it owns is added here: the Dockerfile and entrypoint blocks, the agent guideline, the git ignore
 * and the entries in the MCP and Claude configuration.
 */
return [
    'fragments' => [
        'docker/Dockerfile' => [
            'dev-tools' => 'fragments/dev-tools.Dockerfile',
        ],
        'docker/scripts/entrypoint.sh' => [
            'startup' => 'fragments/startup.sh',
        ],
        '.gitignore' => [
            'gitignore' => '/.codegraph',
        ],
        '.ai/guidelines/agents_orchestration.md' => [
            'agents-guidelines' => 'fragments/agents-guidelines.md',
        ],
    ],
    'json' => [
        'files' => [
            '.mcp.json' => [
                'mcpServers.codegraph' => ['type' => 'stdio', 'command' => 'codegraph', 'args' => ['serve', '--mcp']],
            ],
            '.claude/settings.json' => [
                'permissions.allow' => ['mcp__codegraph__*'],
                'enabledMcpjsonServers' => ['codegraph'],
                'hooks.UserPromptSubmit' => [['hooks' => [['type' => 'command', 'command' => 'codegraph prompt-hook']]]],
            ],
        ],
    ],
];
