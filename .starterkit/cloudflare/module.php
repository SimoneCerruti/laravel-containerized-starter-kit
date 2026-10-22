<?php

declare(strict_types=1);

/*
 * Cloudflare real client IPs: nginx reads CF-Connecting-IP from Cloudflare ranges refreshed
 * at container start and weekly; Laravel trusts the proxy chain.
 * The permissive Traefik-oriented defaults in `nginx.conf` sit in a `!cloudflare` region of the base file,
 * dropped when this module is selected.
 */
return [
    'fragments' => [
        'docker/Dockerfile' => [
            'web-production' => <<<'DOCKER'
COPY ./docker/scripts/cloudflare-ips.sh /usr/local/bin/cloudflare-ips.sh
RUN chmod +x /usr/local/bin/cloudflare-ips.sh && \
    printf '%s\n' \
    "# Refresh Cloudflare IP ranges weekly (Sunday 4am)" \
    "0 4 * * 0 root /usr/local/bin/cloudflare-ips.sh >> /var/www/html/storage/logs/cloudflare-ips.log 2>&1" \
    >> /etc/crontab
DOCKER,
        ],
        'docker/scripts/entrypoint.sh' => [
            'startup' => <<<'SH'
# Refresh Cloudflare IP ranges (best effort)
/usr/local/bin/cloudflare-ips.sh || echo "[WARN] Could not refresh Cloudflare IPs at startup"
SH,
        ],
        'bootstrap/app.php' => [
            'middleware' => '$middleware->trustProxies(at: \'*\');',
        ],
    ],
];
