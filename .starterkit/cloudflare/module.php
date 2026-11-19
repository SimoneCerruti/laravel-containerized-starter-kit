<?php

declare(strict_types=1);

/*
 * Cloudflare real client IPs: nginx reads CF-Connecting-IP from Cloudflare ranges refreshed
 * at container start and weekly; Laravel trusts the proxy chain.
 * The default of `REAL_IP_HEADER` (X-Forwarded-For, for Traefik) becomes CF-Connecting-IP; the
 * env var still overrides it. Laravel trusts only the Docker ranges of `nginx-real-ip.conf`:
 * nginx has already rewritten the client IP from the Cloudflare header by the time PHP sees it.
 */
return [
    'replace' => [
        'docker/scripts/entrypoint.sh' => [
            '${REAL_IP_HEADER:-X-Forwarded-For}' => '${REAL_IP_HEADER:-CF-Connecting-IP}',
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
# Refresh Cloudflare IP ranges (best effort; the script is only installed in the production image)
if [ -x /usr/local/bin/cloudflare-ips.sh ]; then
    /usr/local/bin/cloudflare-ips.sh || echo "[WARN] Could not refresh Cloudflare IPs at startup"
fi
SH,
        ],
        'bootstrap/app.php' => [
            'middleware' => '$middleware->trustProxies(at: [\'172.16.0.0/12\', \'10.0.0.0/8\']);',
        ],
    ],
];
