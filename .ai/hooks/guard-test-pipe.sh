#!/usr/bin/env bash
#
# PreToolUse hook (Bash): blocks a test run whose output is piped, or that runs in background.
#
# Why: pest-plugin-browser starts a Playwright daemon through Symfony Process. The daemon inherits
# the write end of any open pipe and outlives artisan, so the reader (`tail`, `grep`, `tee`) never
# sees EOF and the command hangs forever. Running in background exposes the agent to the same race.
#
# Safe pattern: redirect to a file, then read the file.
#   composer test:agentic > "$CLAUDE_JOB_DIR/tmp/pest.log" 2>&1
#   tail -n 20 "$CLAUDE_JOB_DIR/tmp/pest.log"
#
set -euo pipefail
. "$(dirname "${BASH_SOURCE[0]}")/_lib.sh"
hook_read_input

cmd="$(hook_field tool_input.command)"
[ -z "$cmd" ] && exit 0

runs_tests='(artisan[[:space:]]+test|vendor/bin/pest|composer[[:space:]]+(test|coverage|mutate|type-coverage|qa)(:agentic)?([[:space:]]|$))'
unsafe_msg=""

# Line by line, so a commit message that mentions "pest" and a later "| tail" cannot match.
while IFS= read -r line; do
  grep -qE "$runs_tests" <<<"$line" || continue
  if grep -qE '\|[[:space:]]' <<<"$line"; then
    unsafe_msg="a test command is piped into another command."
  fi
done <<<"$cmd"

if [ -z "$unsafe_msg" ] && grep -qE "$runs_tests" <<<"$cmd" && [ "$(hook_field tool_input.run_in_background)" = "1" ]; then
  unsafe_msg="a test command runs with run_in_background."
fi

[ -z "$unsafe_msg" ] && exit 0

hook_block "$unsafe_msg" \
  "A leftover Playwright daemon keeps the pipe open, so the reader never gets EOF and hangs." \
  "Redirect to a file, then read it:" \
  '  composer test:agentic > "$CLAUDE_JOB_DIR/tmp/pest.log" 2>&1' \
  '  tail -n 20 "$CLAUDE_JOB_DIR/tmp/pest.log"'
