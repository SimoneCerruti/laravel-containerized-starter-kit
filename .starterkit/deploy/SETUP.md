## Deploy

The code is built in CI and rolled out to the shared VPS (Traefik + Docker Compose) through GitHub Actions. The full guide is `deploy/README.md`; the steps outside the app are:

1. **Server (once per server)**: install the deploy gateway from the [laravel-deploy-gateway](https://github.com/SimoneCerruti/laravel-deploy-gateway) repository at the version in `deploy/gateway.version` (`git checkout v<version>`, check that `sha256sum gateway.sh` equals `deploy/gateway.sha256`, then `sudo ./install.sh`). Skip it if another app already installed that version.
2. **Server (per app and environment)**: generate a deploy key and register it with `sudo ./add-app.sh __KIT_APP_SLUG__ <env> "<APP_HOST>" <deploy_key.pub> ALLOWED_IMAGE_REPOS="docker.io/<DOCKERHUB_NAMESPACE>/__KIT_APP_SLUG__ postgres"` (the allow-list is required: the app can run only those repositories). See *Server setup* in `deploy/README.md`.
3. **GitHub**: add the repository secrets with the `_STAGING` / `_PRODUCTION` suffixes (`DOTENV_*`, `DEPLOY_*`, Docker Hub tokens, `DEPLOY_KNOWN_HOSTS`), and `GATEWAY_REPO_TOKEN` if the gateway repository is private. See *GitHub setup* in `deploy/README.md`.
4. **DNS**: point `APP_HOST` of each environment to the server.
5. **`.env` of each environment**: set `APP_ENV=production`, `APP_DEBUG=false`, `LOG_LEVEL`, `MAIL_*` and the database credentials.
6. **First start**: create the first user with `docker compose exec app php artisan users:create` (see *First start*).
7. **Uptime**: monitor `https://<APP_HOST>/up` from outside, for example with [cron-job.org](https://cron-job.org/en/).
