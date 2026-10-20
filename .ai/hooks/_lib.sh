#!/usr/bin/env bash
#
# Shared helpers for the agent hooks (Claude Code and Codex use the same stdin JSON contract).
# Source it: `. "$(dirname "${BASH_SOURCE[0]}")/_lib.sh"`.
#
# Contract: the hook receives one JSON object on stdin; exit 0 lets the action through, exit 2
# blocks it and feeds stderr back to the agent.

# Reads the JSON payload once into $HOOK_INPUT.
hook_read_input() {
  HOOK_INPUT="$(cat)"
}

# hook_field <dot.path> — prints a field of the payload, e.g. `hook_field tool_input.command`.
hook_field() {
  php -r '
    $value = json_decode($argv[1], true);
    foreach (explode(".", $argv[2]) as $key) {
        $value = is_array($value) ? ($value[$key] ?? null) : null;
    }
    echo is_scalar($value) ? (string) $value : "";
  ' "$HOOK_INPUT" "$1"
}

# hook_block <message...> — blocks the action and tells the agent why.
hook_block() {
  {
    echo "BLOCKED: $1"
    shift
    for line in "$@"; do
      echo "$line"
    done
  } >&2
  exit 2
}
