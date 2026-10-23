## Deploy

The code is built in CI and rolled out to the shared VPS (Traefik + Docker Compose) through GitHub Actions. The full guide is `deploy/README.md`; the steps outside the app are:

1. **Server (once)**: create the `deploy` user with the forced command `deploy/gateway.sh`, one SSH key per app and environment, and the directory `/var/www/__KIT_APP_SLUG__/<env>`. See *Server setup* in `deploy/README.md`.
2. **GitHub**: add the repository secrets with the `_STAGING` / `_PRODUCTION` suffixes (`DOTENV_*`, `DEPLOY_*`, Docker Hub tokens, `DEPLOY_KNOWN_HOSTS`). See *GitHub setup* in `deploy/README.md`.
3. **DNS**: point `APP_HOST` of each environment to the server.
4. **`.env` of each environment**: set `APP_ENV=production`, `APP_DEBUG=false`, `LOG_LEVEL`, `MAIL_*` and the database credentials.
5. **First start**: create the first user with `docker compose exec app php artisan users:create` (see *First start*).
6. **Uptime**: monitor `https://<APP_HOST>/up` from outside, for example with [cron-job.org](https://cron-job.org/en/).
