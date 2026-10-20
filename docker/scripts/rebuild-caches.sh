#!/bin/bash

# Rebuilds Laravel/Filament caches. Run inside the app container before it starts
# serving traffic (e.g. as part of the deploy script that pulls a new image).

set -euo pipefail
IFS=$'\n\t'

LOG_FILE="/var/www/html/storage/logs/rebuild-caches-$(date -u +"%F-%H_%M_%S").log"
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

log "==> Start rebuild caches"
cd /var/www/html || { log "[FATAL] cd /var/www/html failed"; exit 1; }

run "Optimizing app"            php artisan optimize
run "Optimizing filament"       php artisan filament:optimize
run "Fixing cache ownership"    chown -R www-data:www-data storage bootstrap/cache

log "==> Rebuild caches completed successfully"
