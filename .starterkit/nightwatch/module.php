<?php

declare(strict_types=1);

/*
 * Laravel Nightwatch monitoring. The agent runs in the same container, under supervisor, next to
 * nginx, php-fpm, the workers and cron. `NIGHTWATCH_ENABLED` is `false` in `.env.example`: turn it on
 * and set `NIGHTWATCH_TOKEN` in the `.env` of the environments that must report (the token is per
 * environment). The entrypoint drops the agent program when the flag is false, so a disabled
 * environment runs no agent. The package is also off in phpunit.xml.
 */
return [
    'require' => [
        'laravel/nightwatch' => '^1.30',
    ],
    'env' => [
        'NIGHTWATCH_ENABLED' => 'false',
        'NIGHTWATCH_TOKEN' => '',
    ],
    'fragments' => [
        'docker/Dockerfile' => [
            'web-common' => "# Setup nightwatch agent supervisor (dropped by the entrypoint when NIGHTWATCH_ENABLED=false)\nCOPY ./docker/configs/nightwatch-agent-supervisor.conf /etc/supervisor/conf.d/nightwatch-agent.conf",
        ],
        'docker/scripts/entrypoint.sh' => [
            'startup' => <<<'SH'
# The agent needs a token and only runs where Nightwatch is on. NIGHTWATCH_ENABLED is read from the
# .env above; an unset value counts as enabled, like in the package.
if [ "${NIGHTWATCH_ENABLED:-true}" = "false" ]; then
    rm -f /etc/supervisor/conf.d/nightwatch-agent.conf
fi
SH,
        ],
    ],
    'replace' => [
        'docker/scripts/entrypoint.sh' => [
            '|PHP_[A-Z_]+)$ ]]' => '|PHP_[A-Z_]+|NIGHTWATCH_ENABLED)$ ]]',
        ],
    ],
];
