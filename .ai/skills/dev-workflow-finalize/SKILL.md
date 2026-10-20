---
name: dev-workflow-finalize
description: Use when a task's work is done and needs to be closed out — removing the worktree/branch/test DB.
---

# Dev Workflow — Finalize

Run from the main checkout, not from inside the worktree being removed — `git worktree remove`
fails on the worktree that is the current directory.

```bash
git worktree remove <worktree_path>
git branch -d <branch_name>

# WITH (FORCE) terminates leftover sessions that would block the drop.
PGPASSWORD=db_passw psql -h db -U db_user -d postgres -c 'DROP DATABASE IF EXISTS <db_name> WITH (FORCE);'
```

`<db_name>` is the name dev-workflow-setup reserved for this worktree's test DB — see
[reserve-db-name.md](../dev-workflow-setup/reserve-db-name.md), purpose `test`.

Done when: `git worktree list` no longer shows `<worktree_path>`, the branch is gone, and the
database no longer appears in `psql -h db -U db_user -d postgres -l`.
