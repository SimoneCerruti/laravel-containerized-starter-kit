#!/bin/bash
set -euo pipefail

# Writes the nginx `set_real_ip_from` list for Cloudflare so the real client IP is read from
# CF-Connecting-IP. Runs at container start (best effort) and weekly from cron.

OUTPUT=/etc/nginx/conf.d/cloudflare-ips.conf
TMP=$(mktemp)

v4=$(curl -fsS --max-time 10 https://www.cloudflare.com/ips-v4 || true)
v6=$(curl -fsS --max-time 10 https://www.cloudflare.com/ips-v6 || true)

if { [ -z "$v4" ] || [ -z "$v6" ]; } && [ -s "$OUTPUT" ]; then
  echo "Cloudflare ranges incomplete; keeping existing $OUTPUT untouched"
  rm -f "$TMP"
  exit 0
fi

{
  echo "# Auto-generated $(date -Iseconds) - DO NOT EDIT"
  if [ -n "$v4" ]; then echo "$v4" | awk '{print "set_real_ip_from " $1 ";"}'; fi
  if [ -n "$v6" ]; then echo "$v6" | awk '{print "set_real_ip_from " $1 ";"}'; fi
  echo "# Docker internal networks"
  echo "set_real_ip_from 172.16.0.0/12;"
  echo "set_real_ip_from 10.0.0.0/8;"
  echo "real_ip_header CF-Connecting-IP;"
  echo "real_ip_recursive on;"
} > "$TMP"

chmod 644 "$TMP"
mv "$TMP" "$OUTPUT"

# Refuse to leave a broken config in place.
if ! nginx -t 2>/dev/null; then
  echo "ERROR: nginx config test failed after update" >&2
  exit 1
fi

# Reload only if nginx is already running; at container start the file loads on nginx's first start.
if nginx -s reload 2>/dev/null; then
  echo "Cloudflare IPs updated and nginx reloaded"
else
  echo "Cloudflare IPs written (nginx not running yet; will load on start)"
fi
