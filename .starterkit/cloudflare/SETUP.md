## Cloudflare

1. Proxy the DNS record of the app (orange cloud) and set SSL/TLS to *Full (strict)*.
2. The container trusts the proxy chain and reads `CF-Connecting-IP` from Cloudflare ranges, refreshed at start and weekly: the server must be reachable only through Cloudflare (firewall on the origin) or client IPs can be spoofed.
3. If Traefik uses the `cloudflare` cert resolver (DNS challenge), the Cloudflare API token with `Zone:DNS:Edit` goes in the Traefik configuration of the server, not in this app.
