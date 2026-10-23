# ========================
# 1. BASE
# ========================
FROM php:8.5-fpm AS base

RUN apt-get update && \
    apt-get install -y --no-install-recommends \
    curl \
    nano \
    nginx \
    openssl \
    supervisor \
    cron \
    libpq-dev \
    libicu-dev \
    libzip-dev \
    unzip \
    bash \
    logrotate \
    gettext-base \
    gnupg \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libpng-dev \
    libwebp-dev \
    jpegoptim \
    optipng \
    pngquant \
    gifsicle \
    webp && \
    apt-get clean && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

# pg_dump (backups, schema dumps) refuses to dump a server newer than itself, and the Debian repo ships
# an older client than the database: take it from the PostgreSQL repo and keep the major in step with
# the `postgres` image of docker-compose.local.yml, deploy/compose.yml and the CI workflows.
RUN install -d -m 0755 /etc/apt/keyrings && \
    curl -fsSL https://www.postgresql.org/media/keys/ACCC4CF8.asc | gpg --dearmor -o /etc/apt/keyrings/postgresql.gpg && \
    echo "deb [signed-by=/etc/apt/keyrings/postgresql.gpg] https://apt.postgresql.org/pub/repos/apt $(. /etc/os-release && echo "$VERSION_CODENAME")-pgdg main" \
        > /etc/apt/sources.list.d/pgdg.list && \
    apt-get update && \
    apt-get install -y --no-install-recommends postgresql-client-18 && \
    apt-get clean && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

RUN docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp && \
    docker-php-ext-install pdo_pgsql intl zip exif gd bcmath sockets

COPY --from=composer:2.10 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# ========================
# 2. VENDOR
# ========================
FROM base AS vendor
COPY composer.json composer.lock ./
RUN composer install --prefer-dist --no-progress --no-scripts --no-dev --optimize-autoloader

# ========================
# 3. ASSETS
# ========================
FROM node:24 AS assets

WORKDIR /var/www/html

COPY package.json package-lock.json ./
RUN npm ci --ignore-scripts --no-audit --no-fund

COPY . .
COPY --from=vendor /var/www/html/vendor ./vendor

RUN npm run build

# ========================
# 4. WEB COMMON
# ========================
FROM base AS web-common

# Nginx config
COPY ./docker/configs/nginx.conf /etc/nginx/sites-available/default.template
COPY ./docker/configs/nginx-real-ip.conf /etc/nginx/conf.d/real-ip.conf

# Setup PHP-FPM pool config
COPY ./docker/configs/php-fpm-www.conf /usr/local/etc/php-fpm.d/www.conf.template

# Setup php.ini
COPY ./docker/configs/php.ini $PHP_INI_DIR/php.ini.template

# Setup supervisord configs
COPY ./docker/configs/supervisord.conf /etc/supervisor/supervisord.conf

# Setup cron supervisor (for Laravel scheduler)
COPY ./docker/configs/crontab /etc/crontab
RUN chmod 0644 /etc/crontab && crontab /etc/crontab
COPY ./docker/configs/laravel-cron-supervisor.conf /etc/supervisor/conf.d/laravel-cron.conf

# Setup worker supervisor (for Laravel queues)
COPY ./docker/configs/laravel-worker-supervisor.conf /etc/supervisor/conf.d/laravel-worker.conf.template

# Setup nginx supervisor
COPY ./docker/configs/nginx-supervisor.conf /etc/supervisor/conf.d/nginx.conf

# Setup php-fpm supervisor
COPY ./docker/configs/php-fpm-supervisor.conf /etc/supervisor/conf.d/php-fpm.conf

# @kit:web-common

# Setup logrotate
COPY ./docker/configs/logrotate /etc/logrotate.d/laravel

# Setup cache rebuild script
COPY ./docker/scripts/rebuild-caches.sh /rebuild-caches.sh
RUN chmod +x /rebuild-caches.sh

# Setup healthcheck script
COPY ./docker/scripts/healthcheck.sh /healthcheck.sh
RUN chmod +x /healthcheck.sh

# Setup opcache
COPY ./docker/configs/opcache.ini /usr/local/etc/php/conf.d/opcache.ini.template

# Setup entrypoint
COPY ./docker/scripts/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh


# Set the working directory
WORKDIR /var/www/html

# preparing cache folders so its sure they exists and that they will be owned by www-data
RUN mkdir -p /var/www/.cache /var/www/.npm /var/www/.config/psysh && \
    chown -R www-data:www-data /var/www/.cache /var/www/.npm /var/www/.config/psysh

COPY --from=vendor /var/www/html/vendor ./vendor
COPY --from=assets /var/www/html/public/build ./public/build
# Copy app code
COPY . .
# Remove the docker folder as its not needed in non-deploy envs
RUN rm -rf ./docker

# bash as default with colors and aliases
RUN usermod -s /bin/bash www-data && \
    echo "export TERM=xterm-256color" >> /etc/bash.bashrc && \
    echo "alias ls='ls --color=auto'" >> /etc/bash.bashrc && \
    echo "alias grep='grep --color=auto'" >> /etc/bash.bashrc && \
    echo "alias fgrep='fgrep --color=auto'" >> /etc/bash.bashrc && \
    echo "alias egrep='egrep --color=auto'" >> /etc/bash.bashrc && \
    echo "alias artisan='php artisan'" >> /etc/bash.bashrc && \
    echo "alias tinker='php artisan tinker'" >> /etc/bash.bashrc && \
    echo "PS1='\\[\\033[01;32m\\]\\u@\\h\\[\\033[00m\\]:\\[\\033[01;34m\\]\\w\\[\\033[00m\\]\\$ '" >> /etc/bash.bashrc

ENTRYPOINT ["/entrypoint.sh"]
CMD ["supervisord", "-c", "/etc/supervisor/supervisord.conf", "-n"]

# ========================
# 5a. WEB PRODUCTION
# ========================
FROM web-common AS web-production

# @kit:web-production

# The code stays owned by root: a compromised app (www-data) must not be able to rewrite what the
# root entrypoint executes at the next start. www-data owns only the writable paths.
RUN chown -R www-data:www-data storage bootstrap/cache /var/www/.cache /var/www/.npm /var/www/.config && \
    ln -s ../storage/app/public public/storage

# ========================
# 5b. WEB LOCAL (dev)
# ========================
FROM web-common AS web-local

ARG WWWUID=1000
ARG WWWGID=1000

# Dev system deps + pcntl
RUN apt-get update && \
    apt-get install -y --no-install-recommends git ssh xclip ca-certificates curl gnupg jq fd-find && \
    docker-php-ext-install pcntl && \
    ln -sf "$(command -v fdfind)" /usr/local/bin/fd && \
    apt-get clean && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

# Coverage + debugging drivers (dev only): PCOV drives Pest/PHPUnit coverage (fast),
# Xdebug stays off by default (zero overhead) and is enabled on demand via XDEBUG_MODE.
RUN pecl install xdebug-3.5.3 pcov-1.0.12 && \
    docker-php-ext-enable xdebug pcov && \
    rm -rf /tmp/pear
COPY ./docker/configs/xdebug.ini /usr/local/etc/php/conf.d/zz-xdebug.ini
COPY ./docker/configs/pcov.ini /usr/local/etc/php/conf.d/zz-pcov.ini

# Node (for running vite dev server inside the container)
RUN curl -fsSL https://deb.nodesource.com/setup_24.x | bash - && \
    apt-get install -y nodejs && \
    npm install -g npm@12.1.0 && \
    apt-get clean && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

# Claude CLI
RUN install -d -m 0755 /etc/apt/keyrings && \
    curl -fsSL https://downloads.claude.ai/keys/claude-code.asc -o /etc/apt/keyrings/claude-code.asc && \
    echo "deb [signed-by=/etc/apt/keyrings/claude-code.asc] https://downloads.claude.ai/claude-code/apt/latest latest main" \
        > /etc/apt/sources.list.d/claude-code.list && \
    apt-get update && \
    apt-get install -y claude-code && \
    apt-get clean && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/* && \
    install -d -o www-data -g www-data /var/www/.claude

# GitHub CLI
RUN install -d -m 0755 /etc/apt/keyrings && \
    curl -fsSL https://cli.github.com/packages/githubcli-archive-keyring.gpg -o /etc/apt/keyrings/githubcli-archive-keyring.gpg && \
    chmod go+r /etc/apt/keyrings/githubcli-archive-keyring.gpg && \
    echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/githubcli-archive-keyring.gpg] https://cli.github.com/packages stable main" \
        > /etc/apt/sources.list.d/github-cli.list && \
    apt-get update && \
    apt-get install -y gh && \
    apt-get clean && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

# Playwright system libs for the Pest browser tests (dev only). The chromium binary itself is
# installed at container start by entrypoint.sh, from the `playwright` version pinned in
# package.json, so the deps installed here stay in lockstep with it.
RUN npx --yes playwright@1.64.0 install-deps chromium && \
    npm cache clean --force && \
    apt-get clean && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

# ast-grep (structural code search/refactor on the PHP/Blade tree). Its global bin is named
# "sg", which collides with util-linux's /usr/bin/sg (switch-group); unused in this container.
RUN rm -f /usr/bin/sg && \
    npm install -g @ast-grep/cli@0.45.3 && npm cache clean --force

# @kit:dev-tools

# Reap orphaned `playwright run-server` processes left by Pest browser tests (dev only —
# pest-plugin-browser never terminates the process it spawns; see
# docker/scripts/reap-playwright-orphans.sh).
COPY ./docker/scripts/reap-playwright-orphans.sh /reap-playwright-orphans.sh
RUN chmod +x /reap-playwright-orphans.sh
COPY ./docker/configs/reap-playwright-orphans-supervisor.conf /etc/supervisor/conf.d/reap-playwright-orphans.conf

# Vite watch supervisor (dev only)
COPY ./docker/configs/vite-watch.conf /etc/supervisor/conf.d/vite-watch.conf

# Align www-data uid/gid to host user, then fix ownership
RUN if [ "$(id -g www-data)" != "$WWWGID" ]; then \
        groupmod -o -g "$WWWGID" www-data ; \
    fi && \
    if [ "$(id -u www-data)" != "$WWWUID" ]; then \
        usermod -o -u "$WWWUID" www-data ; \
    fi && \
    chown -R www-data:www-data /var/www