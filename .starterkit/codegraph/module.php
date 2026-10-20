<?php

declare(strict_types=1);

/*
 * CodeGraph: a local code index and MCP server for the agents, part of the AI tooling. The base
 * files ship with it wired in; when it is not selected `when_disabled.replace` takes it out.
 */
return [
    'when_disabled' => [
        'replace' => [
            '.mcp.json' => [
                ',
        "codegraph": {
            "type": "stdio",
            "command": "codegraph",
            "args": [
                "serve",
                "--mcp"
            ]
        }' => '',
            ],
            '.claude/settings.json' => [
                '            "mcp__codegraph__*",
' => '',
                ',
        "codegraph"' => '',
                ',
        "UserPromptSubmit": [
            {
                "hooks": [
                    {
                        "type": "command",
                        "command": "codegraph prompt-hook"
                    }
                ]
            }
        ]' => '',
            ],
            'docker/Dockerfile' => [
                '# CodeGraph (local code-intelligence graph + MCP server for the agents). The npm package ships
# the native binary as an optionalDependency, so the build needs no runtime download. Telemetry
# defaults to ON upstream and the home dir isn\'t a persisted volume, so it\'s disabled via env.
ENV CODEGRAPH_TELEMETRY=0
RUN npm install -g @colbymchenry/codegraph@1.6.0 && npm cache clean --force
' => '',
            ],
            'docker/scripts/entrypoint.sh' => [
                '# Build the CodeGraph index on first boot only — it lives in the bind-mounted repo, so it
# survives rebuilds and must not be re-indexed every start. Runs as www-data: the entrypoint is
# root, and a root-owned .codegraph/ would lock out the daemon that later writes to it.
if [ "$APP_ENV" = "local" ] && [ ! -d /var/www/html/.codegraph ] \\
    && command -v codegraph >/dev/null 2>&1; then
    su www-data -s /bin/bash -c \'HOME=/var/www codegraph init /var/www/html\' \\
        || echo "[WARN] codegraph init failed (non-blocking)"
fi

' => '',
            ],
            '.ai/guidelines/agents_orchestration.md' => [
                '- Reach for CodeGraph BEFORE grep/find or reading files when you need to understand or locate code
  — this repo is indexed by it (a `.codegraph/` directory exists at the repo root):
  - **MCP tool** (when available): `codegraph_explore` answers most code questions in one call — the
    relevant symbols\' verbatim source plus the call paths between them, including dynamic-dispatch
    hops grep can\'t follow. Name a file or symbol in the query to read its current line-numbered
    source. If it\'s listed but deferred, load it by name via tool search.
  - **Shell** (always works): `codegraph explore "<symbol names or question>"` prints the same
    output.
' => '',
            ],
            '.gitignore' => [
                "/.codegraph\n" => '',
            ],
        ],
    ],
];
