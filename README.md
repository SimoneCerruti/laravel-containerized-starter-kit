# Containerized Starter Kit

A containerized, modular starter kit for Laravel. It starts from a clean Laravel install and, during setup, asks a few questions to decide which dependencies to install and which files to add or modify.

## How it works

1. Install Laravel normally from this kit.
2. `composer setup` runs `php artisan starter-kit:install`.
3. The command asks for the name, slug, vendor, license, port and timezone, then the application type and the optional features.
4. Based on the answers it:
   - installs the required Composer packages (`require` and `require-dev`);
   - copies the files of the selected modules;
   - injects code fragments into the shared files (e.g. `User`, `AppServiceProvider`);
   - replaces the `__KIT_*__` placeholders;
   - updates `.env`, `.env.example`, `composer.json` and `package.json`.
5. When the installation is done the kit removes itself, and this README is replaced by one that contains only the project name.

## Structure

Each module lives in `.starterkit/<module>/` and contains:

- `files/`: files copied to the project root, keeping the same path (placeholders are replaced in file names too);
- `module.php`: the manifest. Keys: `require`, `require-dev`, `fragments` (file => marker => code, or a path to a file in the module), `replace`, `env`, `env_local` (`.env` only, for secrets), `artisan`, `json`, `variants` (e.g. the `filament` plugin packages or the Socialite providers) and `when_disabled` (files and packages to remove when the module is not selected).

`core` is always installed. The others (Filament, 2FA, multilingual, roles and permissions, money, media library, Cloudflare, ...) depend on the answers.

The shared files contain `@kit:<name>` markers (e.g. `// @kit:user-traits`): that is where the modules inject their code. Unused markers are removed at the end of the installation.

## Placeholders

| Placeholder | Meaning |
| --- | --- |
| `__KIT_APP_NAME__` | Application name |
| `__KIT_APP_SLUG__` | Slug (Docker images, database, network) |
| `__KIT_VENDOR__` | Vendor (Docker image) |
| `__KIT_APP_LICENSE__` | License |
| `__KIT_APP_PORT__` | Local port |
| `__KIT_APP_ACTUAL_TIMEZONE__` | The users' real timezone |
| `__KIT_GH_REPOSITORY__` | GitHub repository (`owner/repo`) |
| `__KIT_GH_TOKEN__`, `__KIT_CONTEXT7_API_KEY__` | Tokens for the AI tooling, in `.env` only |
| `__KIT_PANEL_ID__`, `__KIT_PANEL_CLASS__` | Id and class prefix of the Filament panel |
| `__KIT_DEFAULT_LOCALE__`, `__KIT_LOCALE_CASES__` | Default language and the `Locale` enum cases |
| `__KIT_ALLOWED_EMAIL_DOMAINS__`, `__KIT_MFA_REQUIRED__` | Panel email domains and whether 2FA is mandatory |

`composer.json` and `package.json` contain no placeholders, because the kit must stay publishable: they are edited with `composer config` and `npm pkg` during the installation.

## What it includes

- Docker environment for development and production (nginx, php-fpm, supervisor, cron) and a devcontainer.
- GitHub workflows for tests, code style, dependency audit, secret scan and image scan, plus a weekly (non-blocking) mutation testing run. Pull requests only re-run the tests affected by their changes (Pest TIA), using a baseline graph recorded on `main` by `tia-baseline.yml`.
- Quality tooling: Pest (arch, mutation, type coverage), PHPStan/Larastan, Rector, Pint.
- Application base: actions, activity log, security headers, query logger, model concerns (`HasBlameable`, `LogsAllDirtyChanges`) and macros.

## Developing the kit

The kit's own tests run inside the container:

```bash
composer test
```
