#!/usr/bin/env bash
#
# PreToolUse hook (Bash): blocks the git invocations that skip checks or rewrite shared history:
#   - --no-verify / --no-gpg-sign on commit and push
#   - force pushes (--force, -f), except --force-with-lease on a non-default branch
#   - pushes straight to main/master
#   - reset --hard, clean -f, checkout/restore of the whole tree (`.`)
# Conscious override: AGENT_ALLOW_GIT_RISK=1 <command>.
#
set -euo pipefail
. "$(dirname "${BASH_SOURCE[0]}")/_lib.sh"
hook_read_input

[ "${AGENT_ALLOW_GIT_RISK:-0}" = "1" ] && exit 0

cmd="$(hook_field tool_input.command)"
[ -z "$cmd" ] && exit 0
grep -qE '(^|[;&|[:space:]])git[[:space:]]' <<<"$cmd" || exit 0

if grep -qE 'git[[:space:]].*(--no-verify|--no-gpg-sign)' <<<"$cmd"; then
  hook_block "git hooks and signing must not be skipped." "Fix what the hook reports instead."
fi

if grep -qE 'git[[:space:]]+push' <<<"$cmd"; then
  if grep -qE '(--force([[:space:]]|$)|[[:space:]]-f([[:space:]]|$)|\+[A-Za-z0-9_/.-]+)' <<<"$cmd"; then
    hook_block "force push." "Use --force-with-lease on your own feature branch, and only if the user asked."
  fi
  if grep -qE 'git[[:space:]]+push([[:space:]]+[^;&|]*)?[[:space:]](origin[[:space:]]+)?(HEAD:)?(main|master)([[:space:]]|$)' <<<"$cmd"; then
    hook_block "push to the default branch." "Push a feature branch and open a pull request."
  fi
fi

if grep -qE 'git[[:space:]]+(reset[[:space:]]+--hard|clean[[:space:]]+-[a-z]*f|checkout[[:space:]]+(--[[:space:]]+)?\.([[:space:]]|$)|restore[[:space:]]+(--[a-z-]+[[:space:]]+)*\.([[:space:]]|$))' <<<"$cmd"; then
  hook_block "this discards uncommitted work." "Look at 'git status' and 'git diff' first, and ask the user."
fi

exit 0
