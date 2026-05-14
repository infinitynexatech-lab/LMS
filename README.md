# Infinity Learn

White-labeled, self-hosted LMS built on Moodle 4.5 LTS. Powers corporate, customer, partner, and academic training in one demo deployment at **lms.infinitynexatech.com**.

This is a config-overlay fork: upstream Moodle stays untouched inside the image, and our customizations live in `theme/infinity/`, `local/infinity/`, `deploy/`, and `seed/`.

## Quick start (local)

```bash
cp .env.example .env       # fill in DB_PASSWORD, ADMIN_PASSWORD
docker compose up -d
```

Open http://localhost:8080 — log in with `admin / AdminPass!2026`.

## Full rebuild guide

See [`AS_BUILT.md`](AS_BUILT.md) — the canonical, end-to-end walkthrough mirroring the Infinity CRM playbook.
