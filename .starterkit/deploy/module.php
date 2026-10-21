<?php

declare(strict_types=1);

/*
 * Default deploy to the shared VPS (Traefik + Docker Compose), driven by GitHub Actions.
 * The release flow (release, hotfix, PR checks, changelog), the image build and the deploy workflows
 * come from files/.github; deploy/ holds the compose file and the gateway script installed on the
 * server. Everything is documented in deploy/README.md of the generated project.
 * The env entries document the keys the deploy compose file reads from the .env of each environment.
 */
return [
    'env' => [
        'APP_HOST' => '__KIT_APP_SLUG__.test',
        'TRAEFIK_ENTRYPOINT' => 'websecure',
        'TRAEFIK_CERTRESOLVER' => 'cloudflare',
    ],
    'replace' => [
        '.dockerignore' => [
            "/docs\n" => "/docs\n/deploy\n/CHANGELOG.md\n",
        ],
    ],
];
