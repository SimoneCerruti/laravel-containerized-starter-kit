#!/usr/bin/env bash
#
# PreToolUse hook (Bash): blocks the artisan commands that wipe or roll back a database, unless
# the command is explicitly aimed at the testing environment. Migrations here are forward-only,
# so a reset of the development database cannot be undone.
#
# Allowed: `php artisan migrate:fresh --env=testing`, `APP_ENV=testing php artisan db:wipe`.
# Conscious override: AGENT_ALLOW_DB_RESET=1 <command>.
#
set -euo pipefail
. "$(dirname "${BASH_SOURCE[0]}")/_lib.sh"
hook_read_input

[ "${AGENT_ALLOW_DB_RESET:-0}" = "1" ] && exit 0

cmd="$(hook_field tool_input.command)"
[ -z "$cmd" ] && exit 0

grep -qE 'artisan[[:space:]]+(migrate:(fresh|refresh|reset|rollback)|db:wipe)' <<<"$cmd" || exit 0
grep -qE '(--env[= ]testing|APP_ENV=testing|AGENT_ALLOW_DB_RESET=1)' <<<"$cmd" && exit 0

hook_block "this command wipes or rolls back the database." \
  "Migrations are forward-only: fix forward with a new migration." \
  "To reset the TESTING database add --env=testing; any other reset needs the user's go-ahead."
