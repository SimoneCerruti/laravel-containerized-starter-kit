## Deploy

The code is built in CI and rolled out to the shared VPS (Traefik + Docker Compose) through GitHub Actions. The server side is the **deploy gateway**, which lives in its own repository ([laravel-deploy-gateway](https://github.com/SimoneCerruti/laravel-deploy-gateway)) and is pinned by this project in `deploy/gateway.version` + `deploy/gateway.sha256`. The full guide is `deploy/README.md`; the steps outside the app, in order:

1. **Gateway on the server (once per server)**: from a checkout of the gateway repository, `git checkout v<version>` (the one in `deploy/gateway.version`), check that `sha256sum gateway.sh` prints exactly the hash in `deploy/gateway.sha256`, then `sudo ./install.sh`. Skip it if another app already installed that same release. Needs Docker with Compose, `jq`, `flock`, `curl`.
2. **Deploy key**: one ed25519 key per app + environment (`ssh-keygen -t ed25519 -N "" -C "__KIT_APP_SLUG__-<env>" -f ./deploy_key`). The private half goes to GitHub, the public half to the server.
3. **Register the app + environment on the server**: `sudo ./add-app.sh __KIT_APP_SLUG__ <env> "<APP_HOST>" <deploy_key.pub> ALLOWED_IMAGE_REPOS="docker.io/<DOCKERHUB_NAMESPACE>/__KIT_APP_SLUG__ postgres"`.
   - `ALLOWED_IMAGE_REPOS` is **required**: every image the compose file uses, plus the app image (`IMAGE_REF`). Add a repository here whenever you add a service with another image.
   - Add `ALLOWED_REGISTRIES="<host>"` only if the images are not on Docker Hub, and `ALLOWED_CERTRESOLVERS`, `ALLOWED_ENTRYPOINTS`, `MIGRATE_COMMAND`, `MAX_SERVICE_MEMORY_MB` if you need values other than the defaults.
   - Running it again with the same app + environment updates the configuration and the key.
4. **Traefik**: entrypoint `websecure`, certresolver `cloudflare` and the external network `traefik` must exist on the server.
5. **Known hosts**: `ssh-keyscan -t ed25519 <host>`, checked over a trusted channel; the line is the `DEPLOY_KNOWN_HOSTS` secret.
6. **GitHub variables**: `DEPLOY_HOST`, `DOCKERHUB_USERNAME`, `DOCKERHUB_NAMESPACE`, `RELEASE_APP_ID`, and `GATEWAY_REPOSITORY` only if the gateway repository is not `SimoneCerruti/laravel-deploy-gateway`.
7. **GitHub secrets**: the ones with the `_STAGING` / `_PRODUCTION` suffixes (`DOTENV_*`, `DEPLOY_SSH_KEY_*`), `DEPLOY_KNOWN_HOSTS`, the Docker Hub tokens, and `GATEWAY_REPO_TOKEN` (read access to the gateway repository) if that repository is private. Without it the `compose-policy` check of pull requests cannot clone the gateway.
8. **Docker Hub**: create the private repository `<DOCKERHUB_NAMESPACE>/__KIT_APP_SLUG__`; the pull token on the server must be read-only.
9. **DNS**: point `APP_HOST` of each environment to the server.
10. **`.env` of each environment**: set `APP_ENV=production`, `APP_DEBUG=false`, `LOG_LEVEL`, `MAIL_*` and the database credentials.
11. **Branch protection**: protect `main`, `staging` and `production` and require the `pr-checks.yml` checks (including `compose-policy`).
12. **First start**: create the first user with `docker compose exec app php artisan users:create` (see *First start*).
13. **Uptime**: monitor `https://<APP_HOST>/up` from outside, for example with [cron-job.org](https://cron-job.org/en/).

When you edit `deploy/compose.yml`: every service needs `mem_limit` and `security_opt: [no-new-privileges:true]`, every image must be in `ALLOWED_IMAGE_REPOS` of each environment, and any new `${VAR:?}` needs a dummy value in the `compose-policy` job of `pr-checks.yml`. To upgrade the gateway, install the new release on the server and change `deploy/gateway.version` and `deploy/gateway.sha256` together in a reviewed PR; a deploy stops before touching anything if the server does not run the pinned script.
