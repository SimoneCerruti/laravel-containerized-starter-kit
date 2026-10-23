# Containerized Starter Kit

A containerized, modular starter kit for Laravel. It starts from a clean Laravel install and, during setup, asks a few questions to decide which dependencies to install and which files to add or modify.

## How it works

1. Install Laravel normally from this kit.
2. `composer setup` runs `php artisan starter-kit:install`.
3. The command asks for the name, slug, vendor, license, port, timezone, the GitHub repository and the author (both optional), then the application type and the optional features.
4. Based on the answers it:
   - installs the required Composer packages (`require` and `require-dev`);
   - copies the files of the selected modules;
   - injects code fragments into the shared files (e.g. `User`, `AppServiceProvider`);
   - replaces the `__KIT_*__` placeholders;
   - writes the `LICENSE` file for the chosen license and sets the license, repository links and author in `composer.json` and `package.json`;
   - updates `.env`, `.env.example`, `composer.json` and `package.json`.
5. When the installation is done the kit removes itself, and this README is replaced by one that contains only the project name.

## Structure

Each module lives in `.starterkit/<module>/` and contains:

- `files/`: files copied to the project root, keeping the same path (placeholders are replaced in file names too);
- `SETUP.md` (optional, plus `SETUP.<variant>.md`): the steps to do outside the app (accounts, tokens, DNS, server). If the user accepts the last install question they are composed into `SETUP.md` in the project root, which outlives the kit;
- `module.php`: the manifest. Keys: `require`, `require-dev`, `fragments` (file => marker => code, or a path to a file in the module), `replace`, `env`, `env_local` (`.env` only, for secrets), `artisan`, `json`, `variants` (e.g. the `filament` plugin packages or the Socialite providers) and `when_disabled` (files and packages to remove when the module is not selected). Prefer adding things from the module (files, fragments on a `@kit:` marker, e.g. `# @kit:gitignore`) over shipping them in the base and removing them with `when_disabled`; `replace` is for edits of existing text. The base files carry only the markers: a module adds its own lines there, including the entries of shared JSON files (`json.files`: `file => dotted.path => value`, a list is appended to the list already there).

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
| `__KIT_GH_REPOSITORY__` | GitHub repository (`owner/repo`); also sets `homepage`, `support` and the npm `repository` |
| `__KIT_YEAR__`, `__KIT_LICENSE_HOLDER__` | Install year and copyright holder (author name, or the vendor) used in the generated `LICENSE` |
| `__KIT_GH_TOKEN__`, `__KIT_CONTEXT7_API_KEY__` | Tokens for the AI tooling, in `.env` only |
| `__KIT_PANEL_ID__`, `__KIT_PANEL_CLASS__` | Id and class prefix of the Filament panel |
| `__KIT_DEFAULT_LOCALE__`, `__KIT_LOCALE_CASES__` | Default language and the `Locale` enum cases |
| `__KIT_ALLOWED_EMAIL_DOMAINS__`, `__KIT_MFA_REQUIRED__` | Panel email domains and whether 2FA is mandatory |

`composer.json` and `package.json` contain no placeholders, because the kit must stay publishable: they are edited with `composer config` and `npm pkg` during the installation.

## What it includes

- Docker environment for development and production (nginx, php-fpm, supervisor, cron) and a devcontainer.
- GitHub workflows for tests, code style, dependency audit, secret scan and image scan, plus a weekly (non-blocking) mutation testing run. Pull requests only re-run the tests affected by their changes (Pest TIA), using a baseline graph recorded on `main` by `tia-baseline.yml`.
- Optional `deploy` module: release flow (changelog, versioning, hotfixes), image build to Docker Hub and zero-downtime deploys to a shared VPS behind Traefik, through GitHub Actions and a single SSH gateway script on the server. The generated project documents the design, the decisions and the server and GitHub setup in `deploy/README.md`.
- Quality tooling: Pest (arch, mutation, type coverage), PHPStan/Larastan, Rector, Pint.
- Application base: actions, activity log, security headers, query logger, model concerns (`HasBlameable`, `LogsAllDirtyChanges`) and macros.

## Developing the kit

The kit's own tests run inside the container:

```bash
composer test
```
