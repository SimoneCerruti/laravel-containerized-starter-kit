# Build the CodeGraph index on first boot only — it lives in the bind-mounted repo, so it
# survives rebuilds and must not be re-indexed every start. Runs as www-data: the entrypoint is
# root, and a root-owned .codegraph/ would lock out the daemon that later writes to it.
if [ "$APP_ENV" = "local" ] && [ ! -d /var/www/html/.codegraph ] \
    && command -v codegraph >/dev/null 2>&1; then
    su www-data -s /bin/bash -c 'HOME=/var/www codegraph init /var/www/html' \
        || echo "[WARN] codegraph init failed (non-blocking)"
fi
