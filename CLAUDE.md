# d11

Drupal 11 test project run with DDEV (PHP 8.5, nginx-fpm, MariaDB 11.8, docroot `web`).

## Setup

Run these in order from the project root:

```bash
mkcert -install        # create/trust the local CA so https://d11.ddev.site is trusted
ddev coder-setup       # Coder workspaces only: writes .ddev/config.coder.yaml + routing hook
ddev start             # post-start hook runs `composer install`
ddev drush si -y --existing-config --account-pass=admin   # only if the database is empty
```

- Run `mkcert -install` before the first `ddev start`, so the router is issued
  certificates from a trusted CA. If you run it later, use `ddev restart`.
- `ddev coder-setup` must run before `ddev start` in a Coder workspace. It writes
  `.ddev/config.coder.yaml` and `.ddev/docker-compose.coder-describe.yaml` (both
  are added to the global git ignore and must not be committed), and adds a
  post-start hook that publishes Traefik routes for the Coder URLs.
- The site config lives in `config/sync`. The front page is `/admin/welcome`,
  so anonymous visitors get a 403 on `/`. Log in with `ddev drush uli`.

## URLs

- `https://d11.ddev.site` (see `ddev describe`)
- Coder: `https://d11--<workspace>--<owner>.coder.ddev.com`. This is routed to
  DDEV's `router_http_port` (8080 in the global config). If that port is busy,
  DDEV falls back to another port (e.g. 33000) and the Coder URL returns 404.
