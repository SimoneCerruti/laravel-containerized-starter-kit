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

# Like run, for steps that are an optimisation only: a failure is logged and the start goes on.
try() {
    local description="$1"
    shift

    log "${description}..."
    if "$@" >>"$LOG_FILE" 2>&1; then
        log "${description}: OK"
    else
        log "[WARN] ${description} failed - skipped (non-blocking)"
    fi
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
    # "$*" joins with the first character of IFS, which is a newline here: it would split the command
    local IFS=' '
    if [ "$(id -u)" = "0" ]; then
        su www-data -s /bin/bash -c "HOME=/var/www php artisan $*"
    else
        php artisan "$@"
    fi
}

log "==> Start rebuild caches"
cd /var/www/html || { log "[FATAL] cd /var/www/html failed"; exit 1; }

# This runs at every start, before the migrations (the deploy runs them once the container is up), so on
# a first deploy, a recreated volume or a wiped database there are no tables yet. Steps of `optimize`
# that write to the application cache (CACHE_STORE=database) fail there with "relation cache does not
# exist": laravel-data's is the known one, so it is left out here and run on its own below.
# CACHE_STORE must not be overridden for this command instead: config:cache would bake the override in.
run "Optimizing app"            artisan optimize --except=laravel-data
# Pre-warms the structure cache of laravel-data. It needs the cache table: it works on a normal restart and
# is skipped on an empty database, where the structures are cached on first use after the migrations.
try "Caching data structures"   artisan data:cache-structures
# @kit:rebuild-caches
run "Fixing cache ownership"    chown -R www-data:www-data storage bootstrap/cache

log "==> Rebuild caches completed successfully"
