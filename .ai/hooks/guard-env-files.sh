#!/usr/bin/env bash
#
# PreToolUse hook (Bash|Read|Edit|Write|MultiEdit): keeps the agent away from real secrets files.
# `.env.example` and `.env.testing` stay readable. The permission deny list covers the common
# `cat`/`grep` spellings, this catches the rest (sed, head, less, cp, redirections, the Read tool).
#
set -euo pipefail
. "$(dirname "${BASH_SOURCE[0]}")/_lib.sh"
hook_read_input

secret_re='(^|[[:space:]/="'"'"'])(\.env(\.local|\.production|\.staging|\.backup)?|auth\.json|storage/[^[:space:]]*\.key)([[:space:]"'"'"';|&]|$)'

file="$(hook_field tool_input.file_path)"
if [ -n "$file" ]; then
  if grep -qE "$secret_re" <<<" $file "; then
    hook_block "$file may hold secrets." "Ask the user for the value or read .env.example instead."
  fi
  exit 0
fi

cmd="$(hook_field tool_input.command)"
[ -z "$cmd" ] && exit 0

if grep -qE "$secret_re" <<<"$cmd"; then
  hook_block "the command touches a secrets file (.env, auth.json, storage key)." \
    "Use .env.example for the available keys, or ask the user for the value." \
    "Reading config through the app is fine: php artisan config:show <key>."
fi

exit 0
