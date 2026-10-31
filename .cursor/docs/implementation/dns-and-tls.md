# DNS, TLS, and reverse-proxy notes (not applied)

These are requirements for a future production deployment. **No DNS, certificate, or proxy settings were changed in this increment.**

## Wildcard platform host

- Apex: `orbit.com` (and `www.orbit.com`) → central application.
- Wildcard: `*.orbit.com` → same application (tenant subdomains).
- Local equivalent: `orbit.test` and `*.orbit.test` via Laravel Herd.

## TLS

- Cover `orbit.com`, `www.orbit.com`, and `*.orbit.com` (a wildcard certificate does not cover the apex; issue both).
- Custom organisation domains (`attendance.example.com`) need their own certificate after DNS verification. Terminate TLS at the reverse proxy.

## Reverse proxy and trusted hosts

- Forward `Host` and `X-Forwarded-Proto` from the proxy.
- Configure Laravel trusted proxies for the proxy CIDR.
- `TrustHostList` already allows the platform base, `*.{base}`, configured central hosts, and **verified** custom domains (cached 5 minutes).

## Custom-domain verification

1. Organisation adds `attendance.example.com` as type `custom_domain`.
2. They create TXT `_orbit-verify.attendance.example.com` = the token shown in the UI.
3. ORBIT checks the TXT record, then marks the row verified and active.
4. They point the hostname (CNAME or A/AAAA) at the ORBIT edge.

Unverified custom domains never activate a tenant.

## Environment

```
APP_URL=https://orbit.com
ORBIT_TENANT_BASE_DOMAIN=orbit.com
ORBIT_CENTRAL_HOSTS=orbit.com,www.orbit.com
SESSION_DOMAIN=null
SESSION_SECURE_COOKIE=true
```

Do not share session cookies across `*.orbit.com` unless a dedicated SSO design (new ADR) requires it.
