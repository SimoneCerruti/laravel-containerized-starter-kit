#!/usr/bin/env bash
#
# Checks the built production image before it can be deployed. Usage: check-image.sh <image-ref> [compose-file]
#
# 1. Permissions: the code is root-owned and read-only for www-data; only storage/ and bootstrap/cache/ are
#    writable. The base php image makes /var/www/html 1777 and a careless chown can reopen it.
# 2. Content: nothing that is only needed to build the image or to develop (AI tooling, frontend sources,
#    docs, nginx's placeholder page) is in it, and what the app needs at runtime (views, compiled assets) is.
# 3. First start: the entrypoint builds the caches before any migration runs, so on a new or wiped database
#    (no `cache` table) that step must still succeed and must not bake a different cache store in the config.
#
# Runs on a CI runner with Docker, from the root of the repository.

set -euo pipefail

image="${1:?usage: check-image.sh <image-ref> [compose-file]}"
compose="${2:-deploy/compose.yml}"
failed=0

fail() {
    echo "::error::$*"
    failed=1
}

in_image() {
    docker run --rm --entrypoint bash "$image" -c "$1"
}

echo "==> Permissions"

mode=$(in_image 'stat -c %a /var/www/html')
owner=$(in_image 'stat -c %U:%G /var/www/html')
[[ "$mode" == 755 ]] || fail "/var/www/html has mode $mode, expected 755"
[[ "$owner" == root:root ]] || fail "/var/www/html belongs to $owner, expected root:root"

# Anything writable by www-data (owner, group or others) outside the two writable paths. Symlinks are skipped:
# their own mode is always 777 and what counts is the target (public/storage points into storage/).
writable=$(in_image '
    find /var/www/html \
        \( -path /var/www/html/storage -o -path /var/www/html/bootstrap/cache \) -prune -o \
        ! -type l \( -user www-data -perm -u+w -o -group www-data -perm -g+w -o -perm -o+w \) -print
')
if [[ -n "$writable" ]]; then
    fail "writable by www-data outside storage/ and bootstrap/cache/:"$'\n'"$writable"
fi

echo "==> Content"

unwanted=(
    .agents AGENTS.md SETUP.md renovate.json index.nginx-debian.html
    package.json package-lock.json vite.config.js resources/js resources/css
    docker tests
)
for path in "${unwanted[@]}"; do
    if in_image "test -e /var/www/html/$path"; then
        fail "$path must not be in the image"
    fi
done

required=(resources/views public/build/manifest.json artisan vendor/autoload.php)
[[ -d lang ]] && required+=(lang)
for path in "${required[@]}"; do
    in_image "test -e /var/www/html/$path" || fail "$path is missing from the image"
done

echo "==> First start on an empty database"

# The same postgres image (and digest) as the deploy compose file, so Renovate bumps it in one place
postgres=$(grep -oE 'postgres:[0-9]+@sha256:[0-9a-f]{64}' "$compose" | head -n 1)
if [[ -z "$postgres" ]]; then
    fail "no pinned postgres image found in $compose"
    exit 1
fi

network="image-check-$$"
database="image-check-db-$$"
cleanup() {
    docker rm -f "$database" > /dev/null 2>&1 || true
    docker network rm "$network" > /dev/null 2>&1 || true
}
trap cleanup EXIT

docker network create "$network" > /dev/null
docker run -d --name "$database" --network "$network" --network-alias db \
    -e POSTGRES_DB=app -e POSTGRES_USER=app -e POSTGRES_PASSWORD=secret "$postgres" > /dev/null

for _ in {1..30}; do
    docker exec "$database" pg_isready -q -d app -U app && break
    sleep 2
done
docker exec "$database" pg_isready -q -d app -U app || { fail "the test database did not start"; exit 1; }

# No migrations: the database has no tables, as on a first deploy, a recreated volume or after db:wipe.
# CACHE_STORE=database is the setting that made the bootstrap depend on a table.
# The entrypoint's own work (nginx, supervisord) is not needed: only the cache rebuild it runs.
first_start='
    /rebuild-caches.sh
    php -r "\$config = require \"/var/www/html/bootstrap/cache/config.php\"; echo \$config[\"cache\"][\"default\"], PHP_EOL;"
'
if ! cache_store=$(docker run --rm --network "$network" --entrypoint bash \
    -e APP_ENV=production -e APP_DEBUG=false -e APP_URL=https://app.example.com \
    -e "APP_KEY=base64:$(head -c 32 /dev/urandom | base64)" -e ACTUAL_TIMEZONE=UTC \
    -e DB_CONNECTION=pgsql -e DB_HOST=db -e DB_PORT=5432 \
    -e DB_DATABASE=app -e DB_USERNAME=app -e DB_PASSWORD=secret \
    -e CACHE_STORE=database -e SESSION_DRIVER=database -e QUEUE_CONNECTION=database \
    "$image" -c "$first_start"); then
    echo "$cache_store"
    fail "the cache rebuild failed on an empty database"
else
    echo "$cache_store"
    # the last line is the store of the cached config: it must be the configured one, not a bootstrap override
    [[ "$(tail -n 1 <<< "$cache_store")" == database ]] || fail "the cached config does not use CACHE_STORE=database"
fi

exit "$failed"
