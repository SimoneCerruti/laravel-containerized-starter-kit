<?php

declare(strict_types=1);

/*
 * Default deploy to the shared VPS (Traefik + Docker Compose), driven by GitHub Actions.
 * The release flow (release, hotfix, PR checks, changelog), the image build and the deploy workflows
 * come from files/.github; deploy/ holds the compose file and the version of the deploy gateway the
 * project expects on the server. The gateway itself lives in its own repository
 * (laravel-deploy-gateway) and is installed on the server once. Everything is documented in
 * deploy/README.md of the generated project.
 * The env entries document the keys the deploy compose file reads from the .env of each environment.
 */
return [
    // The deploy flow has no `main`: `staging` is the default branch, so the core workflows that run on
    // push to the default branch follow it.
    'replace' => [
        '.github/workflows/tia-baseline.yml' => ['branches: [main]' => 'branches: [staging]'],
        '.github/workflows/secret-scan.yml' => ['branches: [main]' => 'branches: [staging]'],
        '.github/workflows/dependency-audit.yml' => ['branches: [main]' => 'branches: [staging]'],
        '.github/workflows/container-scan.yml' => ['branches: [main]' => 'branches: [staging]'],
        '.github/workflows/tests.yml' => ['every push to main' => 'every push to staging'],
    ],
    'env' => [
        'APP_HOST' => '__KIT_APP_SLUG__.test',
        'TRAEFIK_ENTRYPOINT' => 'websecure',
        'TRAEFIK_CERTRESOLVER' => 'cloudflare',
    ],
    'fragments' => [
        '.dockerignore' => [
            'dockerignore' => "/deploy\n/CHANGELOG.md",
        ],
    ],
];
