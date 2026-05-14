# deploy/

One-shot scripts for the Hetzner CPX22 deployment.

| File | Purpose |
|---|---|
| `setup.sh` | First-time bootstrap on a fresh Ubuntu 24.04 VPS. Idempotent. Installs Docker, configures UFW (only if inactive), clones repo, generates secrets, brings the stack up. |
| `redeploy.sh` | Wipe + re-provision. **Destroys all course content and user data.** Use during the demo phase; never on a live tenant. |
| `Caddyfile` | Snippet to add to the reverse-proxy that fronts the box. See file for the two routing options. |

## Co-hosting on the existing CRM box

Moodle binds to `127.0.0.1:8090` only — Caddy on the host (or in CRM's compose) reverse-proxies `https://lms.infinitynexatech.com` to it. This keeps the LMS off the public internet except through TLS-terminated Caddy.

DNS:
```
lms.infinitynexatech.com  A  <CRM_VPS_IPv4>  TTL 600
```

## Migration plan (when the demo outgrows shared hosting)

Move to a dedicated node before:

- Adding **BigBlueButton** (BBB needs ≥ 4 vCPU, ≥ 8 GB RAM, public IP, UDP 16384–32768).
- Exceeding **~50 concurrent learners** (CPX22 starts thrashing on the CRM + LMS combo).
- Adding **real Stripe/Razorpay payments** (PCI scope considerations argue for isolation).

Migration is a `pg_dump`-equivalent: `mysqldump bitnami_moodle` + `tar moodledata/` + scp to the new box + restore.
