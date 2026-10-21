#!/bin/bash

# Rebuilds Laravel/Filament caches. Run by the entrypoint outside the local environment, before the
# container starts serving traffic: the caches depend on the runtime .env, so they cannot be baked
# into the image (the same image runs in staging and production with different .env files).

set -euo pipefail
IFS=$'\n\t'

LOG_FILE="/var/www/html/storage/logs/rebuild-caches.log"
mkdir -p "$(dirname "$LOG_FILE")"

log() {
    printf '[%s] %s\n' "$(date -u +'%F %T')" "$*" | tee -a "$LOG_FILE"
}

run() {
    local description="$1"
    shift

    log "${description}..."
    if "$@" >>"$LOG_FILE" 2>&1; then
        log "${description}: OK"
        return 0
    fi

    log "[FATAL] ${description} failed - aborting"
    exit 1
}

# Artisan always runs as www-data, so the files it creates are writable by the app.
artisan() {
    if [ "$(id -u)" = "0" ]; then
        su www-data -s /bin/bash -c "HOME=/var/www php artisan $*"
    else
        php artisan "$@"
    fi
}

log "==> Start rebuild caches"
cd /var/www/html || { log "[FATAL] cd /var/www/html failed"; exit 1; }

run "Optimizing app"            artisan optimize
# @kit:rebuild-caches
run "Fixing cache ownership"    chown -R www-data:www-data storage bootstrap/cache

log "==> Rebuild caches completed successfully"
