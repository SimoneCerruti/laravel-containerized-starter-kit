# Reserve a worktree-scoped database name

Shared by dev-workflow-setup (the Pest test DB) and worktree-local-server-setup (the local
browsing DB) — both need a name that's unique per worktree, and the two must not collide with
each other, since a single worktree can end up provisioning both at once.

1. Slugify the current branch (`git branch --show-current`) — lowercase, `[^a-z0-9]` → `_`, capped
   at 40 chars.
2. Prefix with `wt_<purpose>_`, where `<purpose>` is `test` or `local` depending on which database
   you're reserving — this is what keeps the two databases from landing on the same name.
3. Confirm it's free:
   ```
   PGPASSWORD=db_passw psql -h db -U db_user -d postgres -tAc "SELECT 1 FROM pg_database WHERE datname='<db_name>'"
   ```
   A hit means another agent already reserved this database name — stop and report instead of
   reusing it.

Done when: `<db_name>` is confirmed free.
