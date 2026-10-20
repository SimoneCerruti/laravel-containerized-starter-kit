# Environment

## Local Env

- IDE: VS Code.
- Host: Docker container from a custom image (`docker/Dockerfile`). The same image as production, plus dev-only customizations inside a dedicated `if` block.
- Runtime: Supervisor-managed processes: nginx, php-fpm, cron, queues, and (dev only) vite watch build.

## Logs

All logs live in `storage/logs`:

- `nginx.log`
- `nginx_access.log`
- `nginx_error.log`
- `php-fpm.log`
- `laravel.log`
- `cron.log`
- `vite-watch.log`
- `supervisord.log`
- `worker.log`

### Reading logs

- Read Laravel logs via Boost MCP: `read-log-entries` for recent entries, `last-error` for the most recent exception — prefer these over `cat`/`grep`-ing `laravel.log`.
- Tail live logs with `php artisan pail`.
- Browser / JS runtime errors: the `browser-logs` Boost MCP tool.
