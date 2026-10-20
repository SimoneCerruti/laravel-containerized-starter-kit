---
name: worktree-local-server-setup
description: Provision, or tear down, a throwaway local Postgres database and PHP dev server for the current git worktree, so its in-progress work can be opened in a browser.
argument-hint: "leave empty to provision, or pass 'cleanup' to tear down"
disable-model-invocation: true
---

# Worktree local server setup

Spins up a disposable database and `php artisan serve` instance scoped to the calling agent's worktree, or tears one down.

**Precondition, checked before anything else:** the calling agent must already be working inside a linked git worktree, not the main checkout. Verify with:

```
git rev-parse --git-dir
git rev-parse --git-common-dir
```

Equal output → this is the main checkout; refuse and stop, this skill must never touch its database. Different output → linked worktree, proceed.

## Slot

A **slot** is the (database name, port) pair reserved for this worktree. It lives in `.worktree-slot` at the worktree root — plain `KEY=value` lines, not `.env`. Nothing in it is secret, unlike `.env`, so cleanup can read it back without running into the project's `.env` read restrictions (`cat`/`grep`/`tail`/`Read` on `.env*` are denied by this repo's permission settings). Provision writes the file, cleanup reads it and deletes it.

## Provision

1. **Real vendor install.** If `vendor` is a symlink, remove it. Then run `composer install` (idempotent, safe to re-run) so this worktree has its own install matching its own `composer.lock`, not the main worktree's. Done when: `vendor` is a real directory and the install exits 0.
2. **`.env` exists.** If missing: `cp .env.example .env`. Done when: `.env` exists.
3. **Reserve a slot:**
   - Database name: see [reserve-db-name.md](../dev-workflow-setup/reserve-db-name.md), purpose `local` — this worktree's Pest test DB (from dev-workflow-setup) reserves the same branch slug under purpose `test`, so the two never collide.
   - Port: starting at 8100, first port where `ss -tuln | grep -q ":<port> "` finds nothing.
   - Record both:
     ```
     printf 'DB_NAME=%s\nPORT=%s\n' '<db_name>' '<port>' > .worktree-slot
     ```
   Done when: `.worktree-slot` holds a free database name and a free port.
4. **Write the slot into `.env`:**
   ```
   sed -i \
     -e 's/^APP_ENV=.*/APP_ENV=local/' \
     -e "s/^DB_DATABASE=.*/DB_DATABASE=<db_name>/" \
     -e 's#^APP_URL=.*#APP_URL=http://localhost:<port>#' \
     .env
   ```
   Done when: those three lines in `.env` reflect the slot's values.
5. **Create the database:**
   ```
   PGPASSWORD=db_passw psql -h db -U db_user -d postgres -c 'CREATE DATABASE <db_name>;'
   ```
   Done when: the command returns without error.
6. **Migrate and base-seed:** `php artisan migrate --force --seed`. The base seed creates a dev
   user at `test@example.com` / `password`. Done when: it exits 0.
7. **Feature seed.** Seed whatever data is needed to exercise the feature this worktree implements — infer it from the branch/issue, then create it via a Tinker one-liner or a dedicated seeder call. Skip if step 6's base seed already covers it. Done when: the data the feature needs to be clicked through exists, or you've confirmed none is needed beyond the base seed.
8. **Build assets:** `npm run build`. Done when: it exits 0.
9. **Start the server:**
   ```
   nohup php artisan serve --host=0.0.0.0 --port=<port> > /tmp/serve-<port>.log 2>&1 & disown
   ```
   The log filename is scoped to the port so concurrent worktree servers don't clobber each other's log. Done when: the process is running, detached from the shell.
10. **Smoke check:**
    ```
    sleep 2 && curl -s -o /dev/null -w "%{http_code}\n" http://localhost:<port>/app/login
    ```
    Done when: a status code came back. Report it to whoever asked, together with `http://localhost:<port>` and the database name — a redirect is expected for an unauthenticated visit, so report the code rather than assuming failure on a non-200.

## Cleanup

Run only when explicitly asked to tear down this worktree's server.

1. Read the slot: `DB_NAME` and `PORT` from `.worktree-slot`.
2. Stop the server scoped to this worktree's port only — never a bare `pkill -f "artisan serve"`, which would also kill other worktrees' running servers:
   ```
   pkill -f "artisan serve --host=0.0.0.0 --port=<port>"
   ```
3. Drop the database:
   ```
   PGPASSWORD=db_passw psql -h db -U db_user -d postgres -c 'DROP DATABASE IF EXISTS <db_name> WITH (FORCE);'
   ```
4. Remove local state: `rm -f .env .worktree-slot`.

Done when: no `artisan serve` process remains for this port, the database no longer appears in `psql -h db -U db_user -d postgres -l`, and both `.env` and `.worktree-slot` are gone.
