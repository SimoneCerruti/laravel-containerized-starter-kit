#!/usr/bin/env bash
#
# PostToolUse hook (Edit|Write|MultiEdit): formats the PHP file the agent just wrote with Pint, so
# the style rule ("run pint before finishing") cannot be forgotten. Never blocks: a formatting
# failure must not stop the agent, and the result of pint is only reported when it changed something.
#
set -uo pipefail
. "$(dirname "${BASH_SOURCE[0]}")/_lib.sh"
hook_read_input

file="$(hook_field tool_input.file_path)"

case "$file" in
  *.php) ;;
  *) exit 0 ;;
esac

[ -f "$file" ] && [ -x vendor/bin/pint ] || exit 0

vendor/bin/pint --quiet "$file" >/dev/null 2>&1 || true

exit 0
