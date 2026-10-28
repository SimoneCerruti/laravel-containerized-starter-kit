# Deploy

This project is deployed to a shared VPS (Traefik already running, Docker Compose) through GitHub Actions. Every app born from the starter kit is deployed the same way.

- The code is built **once**, in CI, and pushed to Docker Hub tagged with the commit SHA.
- The deploy uses the image **digest** (`repo@sha256:…`), never a tag: what runs on the server is exactly what CI produced.
- Production **reuses the staging image**: a new build happens only for hotfixes.
- On the server, CI can do one thing only: run `deploy/gateway.sh` with a fixed verb, over SSH.

## How it works

```
push to staging ─► image build (tag = SHA) ─► deploy staging
release published ─► reuse the staging image (tag = vX.Y.Z) ─► deploy production
```

The deploy (`.github/workflows/_deploy.yml`) is a sequence of gateway verbs, one CI step each:

| Step | Verb | What it does |
| --- | --- | --- |
| Upload | `put-compose`, `put-env` | Uploads `deploy/compose.yml` and the `.env` (`DOTENV_*` secret + `IMAGE_REF` + `APP_VERSION`). Not active yet. |
| Activate | `activate` | Validates compose and `.env`, applies the security policy and makes the **normalized** compose the active one. |
| Pull | `pull` | Pulls the images with the read-only Docker Hub token, passed over stdin. |
| Start services | `up-services` | Starts everything but `app` (the database) and waits until it is healthy. |
| Migrate | `migrate` | Throwaway container: `php artisan migrate --force`, with no traffic. |
| Roll out | `rollout` | Replaces `app` with no downtime (docker-rollout). |
| Check | — | CI calls `APP_URL/up` from outside, up to 30 times. |
| Prune | `prune` | Removes old images, keeping the current and the previous one. |
| Status | `status` | Always, even when something failed: containers, version, latest logs. |

Migrations run **before** the rollout and never in the entrypoint: if a migration fails, the old container keeps serving and the deploy stops there.

## Decisions and reasoning

| Topic | Decision | Why |
| --- | --- | --- |
| Image | Built only in CI, tag = SHA, deployed by digest. Production reuses the staging image. | Production runs exactly what was tested. A tag can be moved, a digest cannot. |
| Orchestration | Sequence of verbs in `_deploy.yml`; what must happen all at once lives in a single verb (`rollout`). | Every step is visible in the CI logs and the server holds no deploy logic. |
| Server access | `deploy` user with a *forced command*, one key per app + environment. | `deploy` is in the `docker` group, i.e. root on the host: the gateway is the real security boundary. |
| Validation | `activate` runs `docker compose config` and applies a `jq` policy; the **normalized** file becomes the active one. | What runs is what was validated, not what was uploaded. Includes, extends and variables are already resolved. |
| Per-app configuration | `/var/www/<app>/<env>/deploy.conf`, owned by root, with `ALLOWED_HOSTS`. | It sits next to the app, but neither `deploy` nor the containers can change it. |
| Container | A single one (supervisord: nginx, php-fpm, worker, cron). | Project choice: no separate services for workers and scheduler. |
| Workers during rollout | Stop workers and cron in the old container → `docker rollout` → if it fails, restart them in the old one. | docker-rollout keeps two containers side by side: without the stop every job and cron would run twice. 30–60 s without workers is the better trade. |
| `optimize` | In the entrypoint, only when `APP_ENV` is not `local`. | `config:cache` depends on the `.env`, so it cannot run at build time (the same image runs in several environments). After a crash or a reboot the container repairs itself. |
| User | supervisord runs as root, the app processes as `www-data`. The code is owned by root and read-only for `www-data`. | A compromised app cannot modify `vendor/` to get itself executed as root at the next restart. Only `storage/`, `bootstrap/cache/` and `/var/www/.cache` are writable. |
| Cron and logs | Cron stays (to add custom crons) and so does logrotate (14 days, gzip). | One configurable scheduler; plain `daily` covered only `laravel.log`. |
| Healthcheck | `healthcheck.sh` (supervisor processes + `curl /up`) plus the listener that makes `/up` fail when the DB does not answer. `STOPPED` workers and cron are tolerated. | Without that tolerance Traefik would drop the old container during the rollout. `/up` already covers nginx → php-fpm → Laravel, so no separate FPM check is needed. |
| TLS | Terminated by Traefik. The certresolver is chosen per app (`TRAEFIK_CERTRESOLVER`). | Port 443 of a container behind Traefik is not needed. Default `cloudflare` (DNS challenge); apps with a TLS challenge use another resolver. |
| Concurrency | `cancel-in-progress: false`, group `deploy-<env>`, staging included. | A deploy cancelled between `migrate` and `rollout` would leave the new schema with the old code. Of the queued ones only the newest is kept. |
| Secrets | Repository secrets with `_STAGING` / `_PRODUCTION` suffixes, passed explicitly. No GitHub Environments. | Environments are not available on private repos of free plans (org or user). Anyone with write access to the repo is considered trusted. |
| Registry | Docker Hub, one free account per client (1 private repo). `DOCKERHUB_TOKEN` read-write for CI, `DOCKERHUB_PULL_TOKEN` read-only for the server. | The free plan allows a single private repo. The server can never publish. |
| Database | Postgres in the compose of each app + environment, on an internal network with no internet access. | Isolation and independent versions. |
| Migrations | `compose run --rm` before the rollout, following the *expand/contract* rule (see below). | During the rollout the old code runs on the new schema, and a code rollback does not undo migrations. |
| Prune | The current and the previous image are kept; no `image prune -af`. | Fast rollback without pulling again, without touching other apps' images. |

### What the gateway policy blocks

`activate` rejects the compose file if it contains anything outside the allowed list. The keys of a service are an *allow-list*: whatever is not listed is forbidden (`privileged`, `cap_add`, `devices`, `ports`, `network_mode`, `pid`, `ipc`, `build`, `container_name`, …). In addition:

- `security_opt` only `no-new-privileges`;
- bind mounts only under `…/state/`, without `..` (no `/`, no `docker.sock`);
- named volumes without `driver` or `driver_opts` (with `driver_opts` a volume could mount any host directory), not external, with a name starting with `<app>-<env>_` (otherwise an app could use another app's volume);
- networks: the only external one allowed is `traefik`; the others must be named `<app>-<env>_…`, without `driver_opts`;
- services without aliases or static addresses on networks;
- Traefik labels: routers, services and middlewares must be named `<app>-<env>` or start with `<app>-<env>-`; the rule must start with `Host(…)` and use only hosts listed in `ALLOWED_HOSTS`; no `||`, `HostRegexp` or static server addresses;
- `include` and `extends` are rejected (compose resolves them before the policy can see them).

There is no image allow-list: whoever can write to the repo can already make the image they build run any code.

## Server setup (once)

Requirements: Docker with the Compose plugin (tested with Compose 5.x; `config --no-env-resolution` and `up --wait-timeout` are needed), `jq`, `flock`, and Traefik with the external network `traefik`.

1. **docker-rollout** as a system plugin (pinned version):

   ```bash
   sudo mkdir -p /usr/local/lib/docker/cli-plugins
   sudo curl -fsSL https://raw.githubusercontent.com/wowu/docker-rollout/v0.14/docker-rollout \
     -o /usr/local/lib/docker/cli-plugins/docker-rollout
   sudo chmod +x /usr/local/lib/docker/cli-plugins/docker-rollout
   docker rollout --version
   ```

2. **`deploy` user**, in the `docker` group, without a password:

   ```bash
   sudo adduser --disabled-password --gecos "" deploy
   sudo usermod -aG docker deploy
   ```

3. **Gateway** (reinstall by copying the file again when it changes):

   ```bash
   sudo install -m 755 -o root -g root deploy/gateway.sh /usr/local/bin/deploy-gateway
   ```

4. **Directories and configuration** of the app, for each environment (an app with production only uses `production` too):

   ```bash
   sudo mkdir -p /var/www/<app>/<env>/state
   sudo chown deploy:deploy /var/www/<app>/<env>/state
   sudo chmod 700 /var/www/<app>/<env>/state
   echo 'ALLOWED_HOSTS="app.example.com"' | sudo tee /var/www/<app>/<env>/deploy.conf
   sudo chown root:root /var/www/<app>/<env>/deploy.conf && sudo chmod 644 /var/www/<app>/<env>/deploy.conf
   ```

   `/var/www/<app>/<env>` stays owned by root (755): `deploy` can write only in `state/`. `ALLOWED_HOSTS` must list every host the router may serve (including `www.` if you add it).

5. **SSH key**, one per app + environment. Generate it locally and put the public half in `~deploy/.ssh/authorized_keys`:

   ```bash
   ssh-keygen -t ed25519 -N "" -C "<app>-<env>" -f ./deploy_key
   ```

   ```
   restrict,command="/usr/local/bin/deploy-gateway <app> <env>" ssh-ed25519 AAAA… <app>-<env>
   ```

   `restrict` disables forwarding, pty and environment variables; `command` ignores whatever the client asks for, which reaches the gateway only as `SSH_ORIGINAL_COMMAND` and must match one of the verbs.

6. **Known hosts**: `ssh-keyscan -t ed25519 <host>` (check the fingerprint over a trusted channel). The line goes into the `DEPLOY_KNOWN_HOSTS` secret.

7. **Traefik**: entrypoint `websecure`, certresolver `cloudflare` (DNS challenge) and the external network `traefik`. For an app that cannot use Cloudflare DNS, define another resolver (TLS challenge) and set it with `TRAEFIK_CERTRESOLVER` in the `.env`.

## GitHub setup

**Variables** (repository):

| Name | Value |
| --- | --- |
| `DEPLOY_HOST` | Hostname or IP of the VPS |
| `DOCKERHUB_USERNAME` | The client's Docker Hub user |
| `DOCKERHUB_NAMESPACE` | Docker Hub namespace (usually the same as the user) |
| `RELEASE_APP_ID` | ID of the GitHub App used by release and hotfix |

**Secrets** (repository):

| Name | Content |
| --- | --- |
| `DEPLOY_SSH_KEY_STAGING`, `DEPLOY_SSH_KEY_PRODUCTION` | Private key of the environment |
| `DEPLOY_KNOWN_HOSTS` | The `ssh-keyscan` line |
| `DOTENV_STAGING`, `DOTENV_PRODUCTION` | Content of the `.env` of each environment |
| `DOCKERHUB_TOKEN` | Docker Hub read-write token (CI only) |
| `DOCKERHUB_PULL_TOKEN` | Docker Hub **read-only** token (goes to the server, only during the pull) |
| `RELEASE_APP_PRIVATE_KEY` | Private key of the GitHub App |

The Docker Hub repository (`<namespace>/<app>`) must be created as private before the first deploy.

The `.env` of each environment must contain at least: `APP_NAME`, `APP_ENV`, `APP_KEY`, `APP_URL` (with `https://`, CI calls `APP_URL/up`), `APP_HOST` (the host only, no scheme), `ACTUAL_TIMEZONE`, `DB_CONNECTION=pgsql`, `DB_HOST=db`, `DB_PORT=5432`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`. Also set `APP_DEBUG=false`, `LOG_LEVEL` (e.g. `warning`) and the mail settings (`MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS`): the default `MAIL_MAILER=log` writes emails (email verification, password reset, notifications) to the log instead of sending them. The pipeline appends `IMAGE_REF` and `APP_VERSION` at the end, so they win over any duplicate. Optional: `TRAEFIK_ENTRYPOINT`, `TRAEFIK_CERTRESOLVER`, `APP_MEMORY_LIMIT` (default `1g`), `APP_WORKERS_AMOUNT`, `PHP_FPM_*` and `PHP_*` (smaller profiles for staging).

The `.env` is never executed as shell: the entrypoint reads only the keys it needs (`APP_ENV`, `APP_URL`, `FASTCGI_HTTPS`, `APP_WORKERS_*`, `PHP_*`) and expands nothing.

Recommended protections: protected `main`, `staging` and `production` branches, with the `pr-checks.yml` checks required.

## First start

A new environment has an empty database and no seeder runs there, so nobody can sign in. After the first successful deploy create the first user (email already verified) on the server, as the `deploy` user:

```bash
cd /var/www/<app>/<env>/state
COMPOSE_PROJECT_NAME=<app>-<env> docker compose exec app php artisan users:create
```

The command asks for first name, last name, email and password (at least 12 characters), or takes `--first-name`, `--last-name` and `--email`. With the roles and permissions module the user is created as `super_admin`. It is a manual step on purpose: the gateway has no verb that runs arbitrary commands in the container.

## Monitoring

- **Uptime**: the pipeline checks `APP_URL/up` only while deploying. For continuous monitoring use an external service that calls it on a schedule and alerts on failure, for example [cron-job.org](https://cron-job.org/en/) (free): create a cron job on `https://<APP_HOST>/up`, every 1–5 minutes, and enable the notification on failure. `/up` answers 200 only when the application and the database respond, so it covers more than "nginx is up". Any other uptime service works the same way.
- **Errors and performance** (optional `nightwatch` module): the agent runs in the same container, under supervisor. In the `.env` of the environments that must report, set `NIGHTWATCH_ENABLED=true` and `NIGHTWATCH_TOKEN` (one token per environment, from the Nightwatch dashboard). With `NIGHTWATCH_ENABLED=false` the entrypoint does not start the agent. The agent log is `storage/logs/nightwatch-agent.log`.
- **Backups** (optional `backup` module): with `BACKUP_DISKS=local` the archives stay on the same server volume as the database, so they do not survive losing the server. Use the Google Drive variant (or another remote disk) for an offsite copy, and set `BACKUP_NOTIFICATION_EMAIL` so a failed or stale backup is reported.

## Operations

- **Staging deploy**: every merge into `staging`. Manual rerun: *Deploy staging* → *Run workflow*.
- **Release**: the *Release* workflow; it publishes the Release and the production deploy starts with the image already tested in staging.
- **Hotfix**: the *Hotfix* workflow; the only case where production builds its own image.
- **Rollback**: *Deploy production* → *Run workflow* with the previous tag (`vX.Y.Z`). The code goes back, **migrations do not** (see below).
- **Status**: the last step of every run shows `status`. On the server, as the `deploy` user:
  `SSH_ORIGINAL_COMMAND=status /usr/local/bin/deploy-gateway <app> <env>`.
- **The rollout fails**: the new container is discarded, the old one stays in service and workers and cron are restarted. The new container's logs are in the output of the *Roll out* step.
- **The gateway does not start**: check that `deploy.conf` is owned by root and not writable by others, and that `state/` exists and belongs to `deploy`.

### Migrations: expand / contract

During the rollout the old code runs for a few seconds on the already migrated schema, and a code rollback leaves the schema as it is. So every migration must be compatible with the previous version of the code:

1. **Expand**: add columns and tables (nullable or with a default), do not remove or rename anything. Deploy.
2. The new code starts using the new schema. Deploy.
3. **Contract**: only in a later release, when no running version uses the old part anymore, remove columns and tables.

### Adding the `www` redirect

The compose file does not include it. To add it, put the `www.` host in `ALLOWED_HOSTS` and add these labels to `app` (the router and middleware names must start with `${COMPOSE_PROJECT_NAME}`):

```yaml
- traefik.http.routers.${COMPOSE_PROJECT_NAME}-www.rule=Host(`www.${APP_HOST}`)
- traefik.http.routers.${COMPOSE_PROJECT_NAME}-www.entrypoints=${TRAEFIK_ENTRYPOINT:-websecure}
- traefik.http.routers.${COMPOSE_PROJECT_NAME}-www.tls=true
- traefik.http.routers.${COMPOSE_PROJECT_NAME}-www.tls.certresolver=${TRAEFIK_CERTRESOLVER:-cloudflare}
- traefik.http.routers.${COMPOSE_PROJECT_NAME}-www.middlewares=${COMPOSE_PROJECT_NAME}-www
- traefik.http.middlewares.${COMPOSE_PROJECT_NAME}-www.redirectregex.regex=^https://www\.(.*)
- traefik.http.middlewares.${COMPOSE_PROJECT_NAME}-www.redirectregex.replacement=https://$${1}
- traefik.http.middlewares.${COMPOSE_PROJECT_NAME}-www.redirectregex.permanent=true
```

## Known limits and possible improvements

- **Shared `traefik` network.** The `app` containers of different clients sit on the same network and could reach each other. The database cannot be reached: it lives on an internal per-app network. A possible improvement: one proxy network per app + environment, with an idempotent `docker network connect` in the gateway, `default-address-pools` of /24 to avoid running out of subnets, and the network declared in Traefik's compose. It is not implemented, to keep the gateway simple.
- **Secrets are readable by anyone who can write to the repo**: a modified workflow can read them. That is why everyone with write access is assumed to be trusted (no Environments on the free plan).
- **The `.env` on the server is readable by `www-data` inside the container** (Laravel needs it) and, on the host, only by `deploy` and root, because `state/` is `700`.
- **Docker Hub free**: a single private repo per account, hence one account per client. The read-only token on the server is used only during `pull`, in a temporary Docker config deleted right after.
- **Queued deploys**: with `cancel-in-progress: false` GitHub keeps only one pending run per group; a queued deploy can be replaced by a newer one (a queued rollback included).
- **A minute without workers** during the rollout is the price of not running jobs and crons twice.
