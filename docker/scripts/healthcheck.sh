#!/bin/bash

set -euo pipefail
IFS=$'\n\t'

# Checks that every process of a supervisor target is in one of the allowed states.
# A target supervisor does not know (e.g. no workers configured) has nothing to check.
check_processes() {
    local target=$1 allowed=$2 output

    output=$(supervisorctl status "$target" 2>&1 || true)

    if grep -q 'no such' <<<"$output"; then
        return 0
    fi

    if [ -z "$output" ] || awk -v allowed="$allowed" '$2 !~ "^(" allowed ")$" { bad = 1 } END { exit !bad }' <<<"$output"; then
        echo "[HEALTHCHECK] ❌ $target is not ${allowed//|/ or }: $output"
        return 1
    fi
}

check_processes "nginx" "RUNNING" || exit 1
check_processes "php-fpm" "RUNNING" || exit 1

# STOPPED is deliberate: the deploy stops workers and cron on the old container before the rollout,
# and an unhealthy old container would be dropped by Traefik before the new one is ready.
check_processes "laravel-worker:*" "RUNNING|STOPPED" || exit 1
check_processes "laravel-cron" "RUNNING|STOPPED" || exit 1

if ! curl --silent --fail --max-time 3 http://127.0.0.1/up >/dev/null; then
    echo "[HEALTHCHECK] ❌ HTTP /up failed"
    exit 1
fi

echo "[HEALTHCHECK] ✅ All checks passed"
exit 0
