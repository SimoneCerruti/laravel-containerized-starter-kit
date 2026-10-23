#!/bin/bash
set -e

# Load runtime config so templates below see current values.
# Laravel reads .env on its own at runtime; this is only for entrypoint/template substitution.
# The file is parsed, never sourced: it can come from a CI secret and this script runs as root,
# so a value like $(...) must not be executed. Only the keys the templates below use are read.
if [ -f /var/www/html/.env ]; then
    allowed_keys=(
        'APP_ENV'
        'APP_URL'
        'FASTCGI_HTTPS'
        'APP_WORKERS_[A-Z_]+'
        'PHP_[A-Z_]+'
        # @kit:env-allowlist
    )
    double_quoted='^"(.*)"$'
    single_quoted="^'(.*)'\$"

    while IFS= read -r line || [ -n "$line" ]; do
        line=${line#export }
        key=${line%%=*}
        value=${line#*=}

        [[ "$line" == *=* ]] || continue

        is_allowed=false

        for allowed_key in "${allowed_keys[@]}"; do
            if [[ "$key" =~ ^${allowed_key}$ ]]; then
                is_allowed=true
                break
            fi
        done

        [ "$is_allowed" = true ] || continue

        value=${value%"${value##*[![:space:]]}"}

        if [[ "$value" =~ $double_quoted || "$value" =~ $single_quoted ]]; then
            value=${BASH_REMATCH[1]}
        else
            value=${value%%[[:space:]]\#*}
        fi

        export "$key=$value"
    done < /var/www/html/.env
fi

# @kit:env-defaults
export REAL_IP_HEADER=${REAL_IP_HEADER:-X-Forwarded-For}
export APP_WORKERS_AMOUNT=${APP_WORKERS_AMOUNT:-2}
export APP_WORKERS_MEMORY_LIMIT=${APP_WORKERS_MEMORY_LIMIT:-256M}
export FASTCGI_HTTPS=${FASTCGI_HTTPS:-$([ "$APP_ENV" = "local" ] && echo off || echo on)}
export PHP_MEMORY_LIMIT=${PHP_MEMORY_LIMIT:-128M}
export PHP_MAX_EXECUTION_TIME=${PHP_MAX_EXECUTION_TIME:-30}
export PHP_POST_MAX_SIZE=${PHP_POST_MAX_SIZE:-16M}
export PHP_UPLOAD_MAX_FILESIZE=${PHP_UPLOAD_MAX_FILESIZE:-16M}
export PHP_OPCACHE_PRELOAD=${PHP_OPCACHE_PRELOAD:-}
export PHP_FPM_PM=${PHP_FPM_PM:-dynamic}
export PHP_FPM_MAX_CHILDREN=${PHP_FPM_MAX_CHILDREN:-20}
export PHP_FPM_START_SERVERS=${PHP_FPM_START_SERVERS:-4}
export PHP_FPM_MIN_SPARE_SERVERS=${PHP_FPM_MIN_SPARE_SERVERS:-2}
export PHP_FPM_MAX_SPARE_SERVERS=${PHP_FPM_MAX_SPARE_SERVERS:-6}
export PHP_FPM_MAX_REQUESTS=${PHP_FPM_MAX_REQUESTS:-500}

# Explicit var list required: php.ini.template contains ${foo} in comments
envsubst '${PHP_MEMORY_LIMIT} ${PHP_MAX_EXECUTION_TIME} ${PHP_POST_MAX_SIZE} ${PHP_UPLOAD_MAX_FILESIZE}' \
    < /usr/local/etc/php/php.ini.template \
    > /usr/local/etc/php/php.ini

envsubst '${PHP_FPM_PM} ${PHP_FPM_MAX_CHILDREN} ${PHP_FPM_START_SERVERS} ${PHP_FPM_MIN_SPARE_SERVERS} ${PHP_FPM_MAX_SPARE_SERVERS} ${PHP_FPM_MAX_REQUESTS}' \
    < /usr/local/etc/php-fpm.d/www.conf.template \
    > /usr/local/etc/php-fpm.d/www.conf

envsubst < /usr/local/etc/php/conf.d/opcache.ini.template \
         > /usr/local/etc/php/conf.d/opcache.ini

envsubst < /etc/supervisor/conf.d/laravel-worker.conf.template \
         > /etc/supervisor/conf.d/laravel-worker.conf

ssl_host=$(printf '%s' "${APP_URL:-http://localhost}" | sed -E 's#^[a-z]+://##; s#[:/].*##')
SSL_DIR=/etc/nginx/ssl
LETSENCRYPT_LIVE_DIR="/etc/letsencrypt/live/$ssl_host"

# A host-managed Let's Encrypt cert wins when mounted; otherwise a self-signed one is generated
# at every start, so the private key never lands in an image layer. Root-only permissions are
# enough: nginx's root master process reads the key before forking the unprivileged workers.
if [ -f "$LETSENCRYPT_LIVE_DIR/fullchain.pem" ] && [ -f "$LETSENCRYPT_LIVE_DIR/privkey.pem" ]; then
    export SSL_CERTIFICATE="$LETSENCRYPT_LIVE_DIR/fullchain.pem"
    export SSL_CERTIFICATE_KEY="$LETSENCRYPT_LIVE_DIR/privkey.pem"
else
    export SSL_CERTIFICATE="$SSL_DIR/self-signed.crt"
    export SSL_CERTIFICATE_KEY="$SSL_DIR/self-signed.key"
fi

envsubst '${FASTCGI_HTTPS} ${SSL_CERTIFICATE} ${SSL_CERTIFICATE_KEY} ${REAL_IP_HEADER}' \
    < /etc/nginx/sites-available/default.template \
    > /etc/nginx/sites-available/default

if [ "$SSL_CERTIFICATE" = "$SSL_DIR/self-signed.crt" ] && { [ ! -f "$SSL_CERTIFICATE" ] || [ ! -f "$SSL_CERTIFICATE_KEY" ]; }; then
    mkdir -p "$SSL_DIR"
    if printf '%s' "$ssl_host" | grep -qE '^[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+$'; then
        ssl_san="IP:$ssl_host,DNS:localhost,IP:127.0.0.1"
    else
        ssl_san="DNS:$ssl_host,DNS:localhost,IP:127.0.0.1"
    fi
    openssl req -x509 -nodes -days 825 -newkey rsa:2048 \
        -keyout "$SSL_DIR/self-signed.key" -out "$SSL_DIR/self-signed.crt" \
        -subj "/CN=$ssl_host" \
        -addext "subjectAltName=$ssl_san"
    chmod 600 "$SSL_DIR/self-signed.key"
fi

if [ -f /var/www/html/artisan ] && [ ! -L /var/www/html/public/storage ]; then
    su www-data -s /bin/bash -c 'HOME=/var/www php /var/www/html/artisan storage:link' || echo "[WARN] storage:link failed (non-blocking)"
fi

# Reconcile the chromium binary used by the Pest browser tests with whatever `playwright`
# version is pinned in the bind-mounted node_modules. No-op when already up to date. Dev only
# (devDependency, absent in production); skipped when node_modules hasn't been installed yet.
if [ -x /var/www/html/node_modules/.bin/playwright ]; then
    PLAYWRIGHT_BROWSERS_PATH=/var/www/.cache/ms-playwright \
        /var/www/html/node_modules/.bin/playwright install chromium \
        || echo "[WARN] playwright install failed (non-blocking)"
    chown -R www-data:www-data /var/www/.cache/ms-playwright 2>/dev/null || true
fi

if [ -n "$PHP_OPCACHE_PRELOAD" ] && [ ! -f "$PHP_OPCACHE_PRELOAD" ]; then
    echo "[WARN] $PHP_OPCACHE_PRELOAD missing - opcache preload disabled"
fi

# Keep the Claude Code CLI current on every container start (dev only). It's installed via
# apt in the Dockerfile, so it won't self-update on its own; non-blocking so a network hiccup
# doesn't stall boot.
if [ "$APP_ENV" = "local" ] && command -v claude >/dev/null 2>&1; then
    apt-get update && apt-get install --only-upgrade -y claude-code \
        || echo "[WARN] claude-code update failed (non-blocking)"
fi

# /var/www/.claude is a named volume (dev only) so login, history and the statusline
# survive image rebuilds. Its numeric uid can go stale if WWWUID/WWWGID change across
# rebuilds, locking www-data out; reconcile it on every boot rather than only once.
if [ "$APP_ENV" = "local" ]; then
    chown -R www-data:www-data /var/www/.claude 2>/dev/null || true
fi

# @kit:startup

# opcache.file_cache holds compiled bytecode PHP loads back, so it must never be
# world-writable. Runs last, after the root-owned artisan calls above, so any entry they
# created is handed back to www-data instead of locking the tree.
mkdir -p /var/www/.cache/opcache
chown -R www-data:www-data /var/www/.cache/opcache
chmod 2770 /var/www/.cache/opcache

# Outside the local environment the caches (config, routes, views, events) are built at every
# start, from the runtime .env: the container turns healthy only once they exist, and a restart
# after a crash or a reboot repairs itself. Locally the code is bind-mounted and changes constantly.
if [ "${APP_ENV:-production}" != "local" ]; then
    /rebuild-caches.sh
fi

exec "$@"