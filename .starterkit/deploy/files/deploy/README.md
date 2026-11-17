# Deploy

This project is deployed to a shared VPS (Traefik already running, Docker Compose) through GitHub Actions. Every app born from the starter kit is deployed the same way.

- The code is built **once**, in CI, and pushed to Docker Hub tagged with the commit SHA.
- The deploy uses the image **digest** (`repo@sha256:…`), never a tag: what runs on the server is exactly what CI produced.
- Production **reuses the staging image**: a new build happens only for hotfixes.
- On the server, CI can do one thing only: run the **deploy gateway** with a fixed verb, over SSH. The gateway is not part of this project: it lives in the [laravel-deploy-gateway](https://github.com/SimoneCerruti/laravel-deploy-gateway) repository, is installed once per server, and `deploy/gateway.version` says which version this project expects: **2.0.0**. The gateway has to be 2.x (same major, version equal or newer): 2.0 refuses compose files and `.env` files that 1.x accepted (see *What the gateway policy blocks* and the `.env` format below).

## How it works

```
push to staging ─► image build (tag = SHA) ─► deploy staging
release published ─► reuse the staging image (tag = vX.Y.Z) ─► deploy production
```

The deploy (`.github/workflows/_deploy.yml`) is a sequence of gateway verbs, one CI step each:

| Step | Verb | What it does |
| --- | --- | --- |
| Gateway version | `version` | Stops the deploy, before touching anything, if the gateway on the server is older than `deploy/gateway.version`, has another major version, or its script does not have the SHA-256 in `deploy/gateway.sha256`. |
| Upload | `put-compose`, `put-env` | Uploads `deploy/compose.yml` and the `.env` (`DOTENV_*` secret + `IMAGE_REF` + `APP_VERSION`). Not active yet. The *Build .env* step checks the format of the `.env` before this, so a bad line fails the run early. |
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
| Validation | `activate` runs `docker compose config` and applies a `jq` policy; the **normalized** file becomes the active one. | What runs is what was validated, not what was uploaded. Variables are already resolved; `include` and `extends` are refused. |
| Gateway | A separate repository, installed on the server once and pinned per project by `deploy/gateway.version`. | The gateway is server infrastructure and the security boundary: one copy, with its own tests and releases, instead of one drifting copy per app. |
| Policy in CI | `pr-checks.yml` runs the gateway's offline `--check-compose` on `deploy/compose.yml`, at the pinned version, after checking that the cloned `gateway.sh` has the SHA-256 in `deploy/gateway.sha256` (a release tag can be moved, the hash cannot). When you upgrade the gateway, change `deploy/gateway.version` and `deploy/gateway.sha256` together, in a reviewed PR. | A compose file the server would refuse fails the pull request, not the deploy. |
| Per-app configuration | `/var/www/<app>/<env>/deploy.conf`, owned by root: `ALLOWED_HOSTS`, `ALLOWED_ENTRYPOINTS`, `ALLOWED_CERTRESOLVERS`, … | It sits next to the app, but neither `deploy` nor the containers can change it. |
| Container | A single one (supervisord: nginx, php-fpm, worker, cron). | Project choice: no separate services for workers and scheduler. |
| Workers during rollout | Stop workers and cron in the old container → `docker rollout` → if it fails, restart them in the old one. | docker-rollout keeps two containers side by side: without the stop every job and cron would run twice. 30–60 s without workers is the better trade. |
| `optimize` | In the entrypoint, only when `APP_ENV` is not `local`. | `config:cache` depends on the `.env`, so it cannot run at build time (the same image runs in several environments). After a crash or a reboot the container repairs itself. |
| User | supervisord runs as root, the app processes as `www-data`. The code is owned by root and read-only for `www-data`. | A compromised app cannot modify `vendor/` to get itself executed as root at the next restart. Only `storage/`, `bootstrap/cache/` and `/var/www/.cache` are writable. |
| Cron and logs | Cron stays (to add custom crons) and so does logrotate (14 days, gzip), the only rotation: rotated copies go to `storage/logs/old/` and Supervisor does not rotate. | One configurable scheduler; plain `daily` covered only `laravel.log`. |
| Healthcheck | `healthcheck.sh` (supervisor processes + `curl /up`) plus the listener that makes `/up` fail when the DB does not answer. `STOPPED` workers and cron are tolerated. | Without that tolerance Traefik would drop the old container during the rollout. `/up` already covers nginx → php-fpm → Laravel, so no separate FPM check is needed. |
| TLS | Terminated by Traefik. The certresolver is chosen per app (`TRAEFIK_CERTRESOLVER`). | Port 443 of a container behind Traefik is not needed. Default `cloudflare` (DNS challenge); apps with a TLS challenge use another resolver. |
| Concurrency | `cancel-in-progress: false`, group `deploy-<env>`, staging included. | A deploy cancelled between `migrate` and `rollout` would leave the new schema with the old code. Of the queued ones only the newest is kept. |
| Secrets | Repository secrets with `_STAGING` / `_PRODUCTION` suffixes, passed explicitly. No GitHub Environments. | Environments are not available on private repos of free plans (org or user). Anyone with write access to the repo is considered trusted. |
| Registry | Docker Hub, one free account per client (1 private repo). `DOCKERHUB_TOKEN` read-write for CI, `DOCKERHUB_PULL_TOKEN` read-only for the server. | The free plan allows a single private repo. The server can never publish. |
| Database | Postgres in the compose of each app + environment, on an internal network with no internet access. | Isolation and independent versions. |
| Migrations | `compose run --rm` before the rollout, following the *expand/contract* rule (see below). | During the rollout the old code runs on the new schema, and a code rollback does not undo migrations. |
| Prune | The current and the previous image are kept; no `image prune -af`. | Fast rollback without pulling again, without touching other apps' images. |

### What the gateway policy blocks

`activate` rejects the compose file if it breaks the policy; the full text is in the README of the gateway repository ("The compose policy"). The server hosts apps of other customers, so the gateway assumes that any one app's pipeline may be hostile. In short:

- **Parsing**: the file is parsed by a pinned `yq` and compose only reads the result. `include` and `extends` (in any spelling) and YAML tags such as `!!binary`, `!reset` or `!override` are rejected, and so is a multi-document file.
- **Services**: the keys are an *allow-list* (no `privileged`, `cap_add`, `ports`, `network_mode`, `build`, `container_name`, `ulimits`, …). Every service needs `security_opt: [no-new-privileges:true]`, `cap_drop` including `NET_RAW`, and `mem_limit`, `cpus` and `pids_limit` within the ceilings of `deploy.conf` (`MAX_SERVICE_MEMORY_MB`, `MAX_SERVICE_CPUS`, `MAX_SERVICE_PIDS`); `memswap_limit`, if set, between `mem_limit` and twice that. `restart` is `unless-stopped`, healthcheck intervals are at least 1 s and `stop_grace_period` at most 15 minutes.
- **App budget**: at most `MAX_SERVICES` services and `MAX_NETWORKS` private networks (the implicit `default` counts, the proxy network does not), and the sums of `mem_limit`, `cpus` and `pids_limit` within `MAX_APP_MEMORY_MB`, `MAX_APP_CPUS` and `MAX_APP_PIDS`, with `app` counted **three times** (the rollout runs the old and the new container side by side, `migrate` a one-off copy). The starter kit's compose file is exactly at the default: `app` 1 GB × 3 + `db` 1 GB = 4096 MB, `cpus` (1 × 3 + 1) = 4, `pids_limit` 512 × 3 + 256 = 1792, two private networks.
- **Images**: every `image:` and the repository of `IMAGE_REF` must be in `ALLOWED_IMAGE_REPOS`, and every `image:` is **pinned by digest** (`name@sha256:…`, or `name:tag@sha256:…`: Renovate keeps tag and digest together). The image must not carry `traefik.*` or `com.docker.compose.*` labels: the server inspects it after the pull, and `_image.yml` checks it right after the build. A digest inside an allowed repository is whatever the pipeline pushed there, so keep push rights to the registry in CI only.
- **Mounts, volumes**: the **only** bind mount is `./.env`, read-only (a wider rule would let a container plant a symlink in the state directory and have the next deploy mount any host path); volumes are named `<id>-<env>_…` and cannot use `driver` or `driver_opts`.
- **Networks**: the only external network is the app's own proxy network, `traefik-<id>-<env>` (`traefik-${COMPOSE_PROJECT_NAME}` in the compose file), which `deploy-gateway-add-app` creates; `traefik.docker.network` must be that one. The others must be `<id>-<env>_…`.
- **Traefik**: routers, services and middlewares are named `<id>-<env>` or start with `<id>-<env>--`. Every router sets `rule`, `entrypoints` **and** `service`, the last one naming a service of its own (`<id>-<env>` or `<id>-<env>--…`): without it Traefik makes up a name (`<compose service>-<project>`) that can be the service of another app. The rule is ``Host(`host`)`` with a host of `ALLOWED_HOSTS` (backticks, no double quotes), optionally followed by `&&` and `Path`, `PathPrefix`, `PathRegexp`, `Header`, `HeaderRegexp`, `Method`, `Query`, `QueryRegexp` or `ClientIP`; no `||`, `!` or parentheses. Entrypoints and certresolvers must be in the allow-lists of `deploy.conf`. The `buffering`, `retry`, `forwardauth` and `errors` middlewares are not allowed.

The same policy runs on every pull request (`pr-checks.yml`, job `compose-policy`), at the version in `deploy/gateway.version`, with the pinned `yq`. If you add variables with `${VAR:?}` to `deploy/compose.yml`, add a dummy value for them in that job.

The budget has a consequence for the variables of the `.env`: `APP_MEMORY_LIMIT`, `DB_MEMORY_LIMIT` and `APP_CPUS` are limited by `MAX_APP_MEMORY_MB` and `MAX_APP_CPUS` of the environment's `deploy.conf`. Raising them without raising the matching `MAX_APP_*` on the server makes `activate` refuse the compose file: see *Gateway settings* in step 3.

## Server setup

The server needs Docker with the Compose plugin, `jq`, `flock`, `logger`, `curl`, `timeout` and Traefik. Besides the steps below, the host needs the settings of *Limits for the `deploy` user*, *Docker daemon* (log rotation and `default-address-pools`), *Traefik* (no listener besides the public entrypoints), *Firewall* and *sshd* in the README of the gateway repository: they are what makes the isolation between apps hold, and `install.sh` warns about the ones it can check.

1. **Gateway (once per server)**: the server has no Git: install it from an archive of the [laravel-deploy-gateway](https://github.com/SimoneCerruti/laravel-deploy-gateway) release in `deploy/gateway.version`. On your machine, from a clone of the gateway repository (or download the ZIP of the release from GitHub):

   ```bash
   git archive --format=tar.gz --prefix=laravel-deploy-gateway-<version>/ \
     -o laravel-deploy-gateway-<version>.tar.gz v<version>
   scp laravel-deploy-gateway-<version>.tar.gz <user>@<server>:/tmp/
   ```

   On the server:

   ```bash
   cd /tmp && tar xzf laravel-deploy-gateway-<version>.tar.gz && cd laravel-deploy-gateway-<version>
   sudo ./install.sh --expect-sha256 <contents of deploy/gateway.sha256>
   ```

   `install.sh` computes the SHA-256 of `gateway.sh` and stops if it differs from the one you pass. The hash must come from this project (`deploy/gateway.sha256`), **not** from the archive: a checksum shipped in the same archive proves nothing. It installs docker-rollout and `yq` (each pinned by version and checksum), creates the `deploy` user (in the `docker` group, which is root on the host: that is why the gateway is the security boundary), installs `/usr/local/bin/deploy-gateway` and the admin tools `/usr/local/sbin/deploy-gateway-add-app` and `/usr/local/sbin/deploy-gateway-connect-traefik`. To upgrade, repeat the procedure with the newer archive; then change `deploy/gateway.version` and `deploy/gateway.sha256` together, in a reviewed PR, in the projects that should require it. The details are in the section "Install and upgrade (no Git on the server)" of the gateway README.

2. **SSH key**, one per app + environment. Generate it locally:

   ```bash
   ssh-keygen -t ed25519 -N "" -C "<app>-<env>" -f ./deploy_key
   ```

3. **Register the app + environment** on the server (an app with production only uses `production` too):

   ```bash
   sudo deploy-gateway-add-app <app> <env> "app.example.com" ./deploy_key.pub ALLOWED_IMAGE_REPOS="docker.io/<DOCKERHUB_NAMESPACE>/__KIT_APP_SLUG__ postgres"
   ```

   It creates `/var/www/<app>/<env>/deploy.conf` (root-owned), `state/`, the proxy network `traefik-<id>-<env>` (with the Traefik container attached) and the `authorized_keys` line with the forced command. `<env>` has no hyphens. If `/var/www/<app>` already exists it must be owned by root and not writable by group or others: `deploy-gateway-add-app` checks it and does not change it.

   **App names.** `<app>` is the name the gateway knows the app by. It can be the project slug (`__KIT_APP_SLUG__`) or a domain name such as `example.com`, if you keep `/var/www` organized by domain: lowercase letters and digits, segments separated by a single `.` or `-`, at most 40 characters. It names the directory `/var/www/<app>/<env>` and the forced command, and it does **not** have to match the slug used for the image. Docker and Traefik do not accept dots in their names, so they use the **id**: `<app>` with the dots turned into hyphens. The compose project is `<id>-<env>` (`COMPOSE_PROJECT_NAME`, which the gateway sets) and the proxy network `traefik-<id>-<env>`; for `example.com` and `staging` that is `example-com-staging`. The compose file of the project uses `${COMPOSE_PROJECT_NAME}` and needs no change. `deploy-gateway --names <app> <env>` prints the project and network names of a pair. Two names with the same id (`a.b` and `a-b`) are refused, because they would share containers and volumes. A name that extends the name of another registered app with a hyphenated label in front is refused too (`shop.example.com` next to `example.com`: Traefik could merge the two apps' services); name them `example.com` and `example-shop` instead. A host already assigned to another app + environment is refused. The hosts must list every host the router may serve (including `www.` if you add it). `ALLOWED_IMAGE_REPOS` is required and lists every image repository the app may run: the app image and `postgres`, plus any service you add to the compose file. 

   **Gateway settings.** Besides `ALLOWED_*`, `deploy.conf` bounds the compose file; the defaults fit the starter kit and are raised with the same `KEY=value` form (`deploy-gateway-add-app … MAX_APP_MEMORY_MB=6144`, then deploy again):

   | Setting | Default | Raise it when |
   | --- | --- | --- |
   | `MAX_SERVICES` | 8 | you add services to the compose file |
   | `MAX_APP_MEMORY_MB` | 4096 | you raise `APP_MEMORY_LIMIT` or `DB_MEMORY_LIMIT`, or add a service with `mem_limit` (`app` counts 3 times) |
   | `MAX_APP_CPUS` | 4 | you raise `APP_CPUS` or `DB_CPUS`, or add a service |
   | `MAX_APP_PIDS` | 4096 | you raise a `pids_limit` or add a service |
   | `MAX_NETWORKS` | 4 | you add private networks (each takes a subnet of the Docker daemon) |
   | `MIGRATE_TIMEOUT` | 1800 | a migration needs more than 30 minutes (it holds the lock of every later deploy) |
   | `MAX_SERVICE_MEMORY_MB`, `MAX_SERVICE_CPUS`, `MAX_SERVICE_PIDS` | 4096, 2, 2048 | one service needs more than that |

   For another certresolver, entrypoint, or a different migrate command, pass `ALLOWED_CERTRESOLVERS=…`, `ALLOWED_ENTRYPOINTS=…`, `MIGRATE_COMMAND=…` (see the gateway README).

4. **Known hosts**: `ssh-keyscan -H -t ed25519 <host>` (`-H` hashes the host so the secret does not reveal it; check the fingerprint over a trusted channel). The line goes into the `DEPLOY_KNOWN_HOSTS` secret.

5. **Traefik**: entrypoint `websecure` and certresolver `cloudflare` (DNS challenge). The Traefik container must be named `traefik` (otherwise set `TRAEFIK_CONTAINER=<name>` for `deploy-gateway-add-app` and `deploy-gateway-connect-traefik`), with `providers.docker.exposedByDefault=false`, no custom `defaultRule` and no `providers.docker.network` that apps can join. There is no shared `traefik` network anymore: each app + environment has its own, created by `deploy-gateway-add-app`. Recreating the Traefik container (a new version, a changed compose) detaches it from those networks: run `sudo deploy-gateway-connect-traefik` afterwards. The DNS token of the certresolver should cover only the zones of the apps on this server. For an app that cannot use Cloudflare DNS, define another resolver (TLS challenge), list it in `ALLOWED_CERTRESOLVERS` of the app's `deploy.conf` and set it with `TRAEFIK_CERTRESOLVER` in the `.env`.

### Servers already in use

A server that ran the previous version of this flow (the gateway inside the project, one shared `traefik` network) moves in this order:

1. Install the new gateway release (step 1 above): `install.sh` from the archive, which also installs `yq` and the admin tools.
2. Run `deploy-gateway-add-app` again for **every** app + environment, with `ALLOWED_IMAGE_REPOS` and the same hosts and key as before: it creates the `traefik-<id>-<env>` networks, attaches Traefik to them and refuses a host that is assigned twice.
3. Deploy every app with its new `deploy/compose.yml` (digests, `cpus`, `pids_limit`, `NET_RAW`, per-app network). Until an app is deployed again it keeps running on the old network, and the old compose is refused by `activate`.
4. Set up the host settings the gateway README lists (Docker log rotation, firewall, sshd) if they are not there yet.

Until the first new deploy of an environment, `prune` removes nothing: it keeps a history per environment, and the new gateway starts from an empty one.

## GitHub setup

**Secrets** (repository). Everything is a secret, no variables: nothing shows in the GitHub UI or in the logs.

| Name | Content |
| --- | --- |
| `DEPLOY_HOST` | Hostname or IP of the VPS |
| `DOCKERHUB_USERNAME` | The client's Docker Hub user |
| `DOCKERHUB_NAMESPACE` | Docker Hub namespace (usually the same as the user) |
| `RELEASE_APP_ID` | ID of the GitHub App used by release and hotfix |
| `GATEWAY_REPOSITORY` | Optional: the gateway repository, if it is not `SimoneCerruti/laravel-deploy-gateway` |
| `DEPLOY_SSH_KEY_STAGING`, `DEPLOY_SSH_KEY_PRODUCTION` | Private key of the environment |
| `DEPLOY_KNOWN_HOSTS` | The `ssh-keyscan` line |
| `DOTENV_STAGING`, `DOTENV_PRODUCTION` | Content of the `.env` of each environment |
| `DOCKERHUB_TOKEN` | Docker Hub read-write token (CI only) |
| `DOCKERHUB_PULL_TOKEN` | Docker Hub **read-only** token (goes to the server, only during the pull) |
| `RELEASE_APP_PRIVATE_KEY` | Private key of the GitHub App |
| `GATEWAY_REPO_TOKEN` | Only if the gateway repository is private: a token with read access to it, used by the `compose-policy` check |

The Docker Hub repository (`<namespace>/__KIT_APP_SLUG__`) must be created as private before the first deploy.

The `.env` of each environment must contain at least: `APP_NAME`, `APP_ENV`, `APP_KEY`, `APP_URL` (with `https://`, CI calls `APP_URL/up`), `APP_HOST` (the host only, no scheme), `ACTUAL_TIMEZONE`, `DB_CONNECTION=pgsql`, `DB_HOST=db`, `DB_PORT=5432`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`. Also set `APP_DEBUG=false`, `LOG_LEVEL` (e.g. `warning`) and the mail settings (`MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS`): the default `MAIL_MAILER=log` writes emails (email verification, password reset, notifications) to the log instead of sending them. The pipeline appends `IMAGE_REF` and `APP_VERSION` at the end, so they win over any duplicate.

**Format.** One variable per line, as the gateway 2.0 requires: `KEY=value`; `KEY="…"` or `KEY='…'` closed on the same line, optionally followed by `# comment`; an optional `export ` prefix; `KEY` alone, comments and blank lines (a trailing CR is tolerated). `KEY` is `[A-Za-z_][A-Za-z0-9_.-]*`. `KEY: value` and values over several lines (a PEM key, for example) are refused: put such a value on one line with `\n` inside double quotes, or ship it another way. The *Build .env* step of `_deploy.yml` applies the same grammar and fails with the line numbers (never the content, the file is a secret) before anything reaches the server. Optional: `TRAEFIK_ENTRYPOINT`, `TRAEFIK_CERTRESOLVER`, `APP_MEMORY_LIMIT` (default `1g`), `APP_WORKERS_AMOUNT`, `PHP_FPM_*` and `PHP_*` (smaller profiles for staging).

The `.env` is never executed as shell: the entrypoint reads only the keys it needs (`APP_ENV`, `APP_URL`, `FASTCGI_HTTPS`, `APP_WORKERS_*`, `PHP_*`) and expands nothing.

Recommended protections: protected `main`, `staging` and `production` branches, with the `pr-checks.yml` checks required.

## First start

A new environment has an empty database and no seeder runs there, so nobody can sign in. After the first successful deploy create the first user (email already verified) on the server, as the `deploy` user:

```bash
cd /var/www/<app>/<env>/state
COMPOSE_PROJECT_NAME=<id>-<env> docker compose exec app php artisan users:create
```

`<id>` is `<app>` with the dots turned into hyphens (see *App names* above): for `example.com` and `staging`, `COMPOSE_PROJECT_NAME=example-com-staging`.

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
  `SSH_ORIGINAL_COMMAND=status /usr/local/bin/deploy-gateway <app> <env>`. The application logs are not printed by default, because they end up in the CI log: set `STATUS_LOGS=yes` in the `deploy.conf` of the environment to include the last 40 lines.
- **The rollout fails**: the new container is discarded, the old one stays in service and workers and cron are restarted. The new container's logs are in the output of the *Roll out* step.
- **The gateway does not start**: check that `deploy.conf` is owned by root and not writable by others, and that `state/` exists and belongs to `deploy`.

### Migrations: expand / contract

During the rollout the old code runs for a few seconds on the already migrated schema, and a code rollback leaves the schema as it is. So every migration must be compatible with the previous version of the code:

1. **Expand**: add columns and tables (nullable or with a default), do not remove or rename anything. Deploy.
2. The new code starts using the new schema. Deploy.
3. **Contract**: only in a later release, when no running version uses the old part anymore, remove columns and tables.

### Adding the `www` redirect

The compose file does not include it. To add it, put the `www.` host in `ALLOWED_HOSTS` and add these labels to `app` (the router and middleware names must be `${COMPOSE_PROJECT_NAME}` or start with `${COMPOSE_PROJECT_NAME}--`):

```yaml
- traefik.http.routers.${COMPOSE_PROJECT_NAME}--www.rule=Host(`www.${APP_HOST}`)
- traefik.http.routers.${COMPOSE_PROJECT_NAME}--www.entrypoints=${TRAEFIK_ENTRYPOINT:-websecure}
- traefik.http.routers.${COMPOSE_PROJECT_NAME}--www.tls=true
- traefik.http.routers.${COMPOSE_PROJECT_NAME}--www.tls.certresolver=${TRAEFIK_CERTRESOLVER:-cloudflare}
- traefik.http.routers.${COMPOSE_PROJECT_NAME}--www.service=${COMPOSE_PROJECT_NAME}
- traefik.http.routers.${COMPOSE_PROJECT_NAME}--www.middlewares=${COMPOSE_PROJECT_NAME}--www
- traefik.http.middlewares.${COMPOSE_PROJECT_NAME}--www.redirectregex.regex=^https://www\.(.*)
- traefik.http.middlewares.${COMPOSE_PROJECT_NAME}--www.redirectregex.replacement=https://$${1}
- traefik.http.middlewares.${COMPOSE_PROJECT_NAME}--www.redirectregex.permanent=true
```

## Known limits and possible improvements

- **`docker` group is root.** A bug in the gateway or its policy is root on the host. The policy, its tests and the host settings above are the defence; rootless Docker does not fit a shared Traefik (see the gateway README, "Known limits").
- **Proxy networks.** Each app + environment has its own `traefik-<id>-<env>` network, so the `app` containers of different clients cannot reach each other; only Traefik is on all of them. Every network takes a subnet (each app may use `MAX_NETWORKS` private ones besides this one): on a server with many apps set `default-address-pools` in `daemon.json` (gateway README, "Docker daemon").
- **Secrets are readable by anyone who can write to the repo**: a modified workflow can read them. That is why everyone with write access is assumed to be trusted (no Environments on the free plan).
- **The `.env` on the server is readable by `www-data` inside the container** (Laravel needs it) and, on the host, only by `deploy` and root, because `state/` is `700`.
- **Docker Hub free**: a single private repo per account, hence one account per client. The read-only token on the server is used only during `pull`, in a temporary Docker config deleted right after.
- **Queued deploys**: with `cancel-in-progress: false` GitHub keeps only one pending run per group; a queued deploy can be replaced by a newer one (a queued rollback included).
- **A minute without workers** during the rollout is the price of not running jobs and crons twice.
