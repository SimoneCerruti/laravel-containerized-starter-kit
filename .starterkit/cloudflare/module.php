<?php

declare(strict_types=1);

/*
 * Cloudflare real client IPs: nginx reads CF-Connecting-IP from Cloudflare ranges refreshed
 * at container start and weekly; Laravel trusts the proxy chain.
 * `replace` entries swap the permissive Traefik-oriented defaults in the base files.
 */
return [
    'replace' => [
        'docker/configs/nginx.conf' => [
            "    # needed for Traefik reverse proxy\n    real_ip_header X-Forwarded-For;\n    set_real_ip_from 0.0.0.0/0;\n" => '',
        ],
    ],
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
