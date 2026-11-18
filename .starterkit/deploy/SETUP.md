## Deploy

The code is built in CI and rolled out to the shared VPS (Traefik + Docker Compose) through GitHub Actions. The server side is the **deploy gateway** ([laravel-deploy-gateway](https://github.com/SimoneCerruti/laravel-deploy-gateway)), pinned by this project in `deploy/gateway.version` + `deploy/gateway.sha256`. This is the short version; every step, with the commands to get each secret, is in `deploy/README.md`.

### Steps, in order

| # | Step | Command / action | Details in `deploy/README.md` |
| --- | --- | --- | --- |
| 1 | Gateway 2.0.0 on the server (once per server, skip if already installed) | `sudo ./install.sh --expect-sha256 <deploy/gateway.sha256>` from the release archive | *Server setup*, step 1 |
| 2 | Deploy key, one per app + environment | `ssh-keygen -t ed25519 -N "" -C "__KIT_APP_SLUG__-<env>" -f ./deploy_key` | *Server setup*, step 2 |
| 3 | Register app + environment | `sudo deploy-gateway-add-app <app> <env> "<APP_HOST>" <deploy_key.pub> ALLOWED_IMAGE_REPOS="docker.io/<DOCKERHUB_NAMESPACE>/__KIT_APP_SLUG__ postgres"` | *Server setup*, step 3 |
| 4 | Traefik: `websecure` entrypoint, `cloudflare` certresolver, container named `traefik` | after recreating it: `sudo deploy-gateway-connect-traefik` | *Server setup*, step 5 |
| 5 | Known hosts | `ssh-keyscan -H -t ed25519 <host>`, fingerprint checked on the server | *`DEPLOY_KNOWN_HOSTS`* |
| 6 | Docker Hub | private repo `<DOCKERHUB_NAMESPACE>/__KIT_APP_SLUG__`, one read-write and one read-only token | *`DOCKERHUB_*`* |
| 7 | GitHub App for release and hotfix | create, install on the repo, allow it to bypass the branch protection | *`RELEASE_APP_ID`, `RELEASE_APP_PRIVATE_KEY`* |
| 8 | GitHub secrets | the table below | *How to get each secret* |
| 9 | DNS | point `APP_HOST` of each environment to the server | — |
| 10 | `.env` of each environment | `APP_ENV=production`, `APP_DEBUG=false`, `LOG_LEVEL`, `MAIL_*`, DB credentials; one `KEY=value` per line | *`DOTENV_*`* |
| 11 | Branch protection | protect `main`, `staging`, `production`; require the `pr-checks.yml` checks (incl. `compose-policy`); enable auto-merge | *GitHub setup* |
| 12 | First start | `docker compose exec app php artisan users:create` on the server | *First start* |
| 13 | Uptime | monitor `https://<APP_HOST>/up` from outside (e.g. [cron-job.org](https://cron-job.org/en/)) | *Monitoring* |

Before step 1, check the host settings listed in the gateway README (limits of the `deploy` user, Docker log rotation and `default-address-pools`, Traefik listeners, firewall, sshd): `install.sh` warns about the ones it can check.

### GitHub secrets

Everything is a repository secret (no variables), so nothing shows in the UI or the logs.

| Secret | Where it comes from |
| --- | --- |
| `DEPLOY_HOST` | Hostname or IP of the VPS |
| `DEPLOY_SSH_KEY_STAGING`, `DEPLOY_SSH_KEY_PRODUCTION` | Private half of the key of step 2 (the public half goes to the server) |
| `DEPLOY_KNOWN_HOSTS` | Output of `ssh-keyscan` (step 5) |
| `DOCKERHUB_USERNAME`, `DOCKERHUB_NAMESPACE` | Docker Hub account: login and repository namespace |
| `DOCKERHUB_TOKEN` | Docker Hub access token, **read & write** (CI only) |
| `DOCKERHUB_PULL_TOKEN` | Docker Hub access token, **read-only** (goes to the server) |
| `RELEASE_APP_ID`, `RELEASE_APP_PRIVATE_KEY` | GitHub App: App ID and generated `.pem` |
| `DOTENV_STAGING`, `DOTENV_PRODUCTION` | The `.env` of each environment |
| `GATEWAY_REPOSITORY` | Optional: only if the gateway repository is not `SimoneCerruti/laravel-deploy-gateway` |
| `GATEWAY_REPO_TOKEN` | Optional: read access to the gateway repository, only if it is private (without it `compose-policy` cannot clone it) |

### Registering the app: what to know

- `<app>` is `__KIT_APP_SLUG__` by default, or a domain name (`example.com`): lowercase letters and digits, segments separated by a single `.` or `-`, at most 40 characters. Docker and Traefik use the **id** (`<app>` with dots as hyphens): `COMPOSE_PROJECT_NAME=<id>-<env>`, network `traefik-<id>-<env>`; `deploy-gateway --names <app> <env>` prints them.
- A name that extends another app's name with a hyphenated label is refused (`shop.example.com` next to `example.com`): use `example-shop`. A host already assigned to another app + environment is refused.
- `ALLOWED_IMAGE_REPOS` is **required**: the app image and every image of the compose file. Add a repository whenever you add a service with another image.
- Optional settings, passed as `KEY=value`: `ALLOWED_REGISTRIES` (images not on Docker Hub), `ALLOWED_CERTRESOLVERS`, `ALLOWED_ENTRYPOINTS`, `MIGRATE_COMMAND`, and the budget `MAX_SERVICES` (8), `MAX_APP_MEMORY_MB` (4096), `MAX_APP_CPUS` (4), `MAX_APP_PIDS` (4096), `MAX_NETWORKS` (4), `MAX_SERVICE_*`, `MIGRATE_TIMEOUT` (1800 s). Raise them together with `APP_MEMORY_LIMIT`, `DB_MEMORY_LIMIT`, `APP_CPUS` or when adding services (`app` counts 3 times).
- Running the command again with the same app + environment updates configuration and key.

### Rules for `deploy/compose.yml`

The gateway enforces them; the `compose-policy` check of pull requests tells you before the deploy does.

- Every `image:` pinned by digest (`name:tag@sha256:…`), its repository in `ALLOWED_IMAGE_REPOS`; the app image carries no `traefik.*` or `com.docker.compose.*` labels.
- Every service: `mem_limit`, `cpus`, `pids_limit` within the ceilings, `security_opt: [no-new-privileges:true]`, `cap_drop` with `NET_RAW`, `restart: unless-stopped`; no `ulimits`. Budget of the app within `MAX_APP_*`.
- Every router: `rule` (``Host(`host`)``, backticks, optional `&&` + `Path`, `PathPrefix`, `Header`, `Method`, …; no `||`, `!`, parentheses), `entrypoints` and its own `service` (`traefik.http.routers.${COMPOSE_PROJECT_NAME}.service=${COMPOSE_PROJECT_NAME}`); no `buffering` or `retry` middlewares.
- The proxy network is `traefik-${COMPOSE_PROJECT_NAME}` and `traefik.docker.network` points to it.
- No `include`, `extends` or YAML tags (`!!binary`, `!reset`, `!override`).
- A new `${VAR:?}` needs a dummy value in the `compose-policy` job of `pr-checks.yml`.

### Servers already in use, and upgrades

- On a server already in use: install the new gateway, run `deploy-gateway-add-app` again for every app + environment, then deploy every app (*Servers already in use* in `deploy/README.md`).
- Projects generated with gateway 1.x need three changes for 2.0.0: the `…routers.${COMPOSE_PROJECT_NAME}.service=${COMPOSE_PROJECT_NAME}` label in `deploy/compose.yml`, the new `deploy/gateway.version` and `deploy/gateway.sha256` together, and a `.env` with one variable per line.
- To upgrade the gateway: install the new release on the server (same procedure as step 1) and change `deploy/gateway.version` and `deploy/gateway.sha256` together in a reviewed PR. A deploy stops before touching anything if the server does not run the pinned script.
