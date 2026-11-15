#!/usr/bin/env bash
#
# PreToolUse hook (Bash|Read|Edit|Write|MultiEdit): keeps the agent away from real secrets files.
# `.env.example` and `.env.testing` stay readable. The permission deny list covers the common
# `cat`/`grep` spellings, this catches the rest (sed, head, less, cp, redirections, the Read tool).
#
# Exception: inside `.claude/worktrees/<name>/` the `.env*` files are fair game. A worktree's env
# file is copied from main's `.env.example`, so it holds no real secrets and may be edited freely.
# `auth.json` and key files stay blocked everywhere.
#
set -euo pipefail
. "$(dirname "${BASH_SOURCE[0]}")/_lib.sh"
hook_read_input

secret_re='(^|[[:space:]/="'"'"'])(\.env(\.local|\.production|\.staging|\.backup)?|auth\.json|storage/[^[:space:]]*\.key)([[:space:]"'"'"';|&]|$)'

worktree_env_re='/\.claude/worktrees/[^/]+/\.env(\.[A-Za-z0-9_-]+)?$'

file="$(hook_field tool_input.file_path)"
if [ -n "$file" ]; then
  if grep -qE "$worktree_env_re" <<<"$file" && [[ "$file" != *..* ]]; then
    exit 0
  fi
  if grep -qE "$secret_re" <<<" $file "; then
    hook_block "$file may hold secrets." "Ask the user for the value or read .env.example instead."
  fi
  exit 0
fi

cmd="$(hook_field tool_input.command)"
[ -z "$cmd" ] && exit 0

# Bash inside a worktree: ignore plain relative `.env*` tokens, then check the rest as usual.
# Commands that wander out of the worktree (`..`, `~`, `cd`) get no exemption.
cwd="$(hook_field cwd)"
if grep -qE '/\.claude/worktrees/[^/]+(/|$)' <<<"$cwd" && ! grep -qE '\.\.|~|(^|[[:space:];&|])cd([[:space:]]|$)' <<<"$cmd"; then
  cmd="$(sed -E "s#(^|[[:space:]\"'=])(\./)?\.env(\.[A-Za-z0-9_-]+)?([[:space:]\"';|&]|\$)#\1 \4#g" <<<"$cmd")"
fi

if grep -qE "$secret_re" <<<"$cmd"; then
  hook_block "the command touches a secrets file (.env, auth.json, storage key)." \
    "Use .env.example for the available keys, or ask the user for the value." \
    "Reading config through the app is fine: php artisan config:show <key>."
fi

exit 0
