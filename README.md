# Infinity Learn

White-labeled, self-hosted LMS built on Moodle 4.5 LTS. Powers corporate, customer, partner, and academic training in one demo deployment at **lms.infinitynexatech.com**.

This is a config-overlay fork: upstream Moodle stays untouched inside the image, and our customizations live in `theme/infinity/`, `local/infinity/`, `deploy/`, and `seed/`.

## Quick start (local)

```bash
docker compose up -d                  # builds the image on first run (~5 min)
docker compose logs -f moodle         # wait for install.php to finish (~2 min)
```

Open http://localhost:8081 — log in with `admin / AdminPass!2026`.

`.env` is only required for production (`docker-compose.prod.yml`). See `.env.example`.

## Full rebuild guide

See [`AS_BUILT.md`](AS_BUILT.md) — the canonical, end-to-end walkthrough mirroring the Infinity CRM playbook.
