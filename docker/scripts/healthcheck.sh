#!/bin/bash

set -euo pipefail
IFS=$'\n\t'

declare -a services=(
    "laravel-worker:laravel-worker_00"
    "laravel-cron"
    "nginx"
    "php-fpm"
)

for service in "${services[@]}"; do
    if ! supervisorctl status "$service" | grep -q "RUNNING"; then
        echo "[HEALTHCHECK] ❌ $service not running"
        exit 1
    fi
done

if ! curl --silent --fail --max-time 3 http://127.0.0.1/up >/dev/null; then
    echo "[HEALTHCHECK] ❌ HTTP /up failed"
    exit 1
fi

echo "[HEALTHCHECK] ✅ All checks passed"
exit 0
