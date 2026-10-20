#!/bin/bash
# Dev-only: pestphp/pest-plugin-browser spawns a `playwright run-server` Node process on every
# browser-plugin bootstrap but never terminates it, even on a clean test run — the process is
# reparented to init (PPID 1) the moment the PHP test process exits and lingers indefinitely,
# each instance holding on the order of 100-200MB. This loop reaps orphaned instances older than
# REAP_MIN_AGE_SECONDS, every REAP_INTERVAL_SECONDS, for as long as supervisor keeps it running.

set -uo pipefail

interval_seconds=${REAP_INTERVAL_SECONDS:-600}
min_age_seconds=${REAP_MIN_AGE_SECONDS:-600}

while true; do
    for pid in $(pgrep -f "playwright run-server"); do
        read -r ppid etimes < <(ps -o ppid=,etimes= -p "$pid" 2>/dev/null)

        if [ -n "$ppid" ] && [ "$ppid" -eq 1 ] && [ "$etimes" -ge "$min_age_seconds" ]; then
            echo "$(date '+%Y-%m-%d %H:%M:%S') reaping orphaned playwright run-server (pid=$pid, etimes=${etimes}s)"
            kill "$pid" 2>/dev/null || true
        fi
    done

    sleep "$interval_seconds"
done
