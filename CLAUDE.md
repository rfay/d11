# d11

Drupal 11 test project run with DDEV (PHP 8.5, nginx-fpm, MariaDB 11.8, docroot `web`).

## Setup

Run these in order from the project root:

```bash
mkcert -install        # create/trust the local CA so https://d11.ddev.site is trusted
ddev coder-setup       # Coder workspaces only: writes .ddev/config.coder.yaml + routing hook
ddev start             # post-start hook runs `composer install`
ddev drush si -y demo_umami --account-pass=admin          # only if the database is empty
```

- Run `mkcert -install` before the first `ddev start`, so the router is issued
  certificates from a trusted CA. If you run it later, use `ddev restart`.
- `ddev coder-setup` must run before `ddev start` in a Coder workspace. It writes
  `.ddev/config.coder.yaml` and `.ddev/docker-compose.coder-describe.yaml` (both
  are added to the global git ignore and must not be committed), and adds a
  post-start hook that publishes Traefik routes for the Coder URLs.
- The install profile is `demo_umami`, which has a `hook_install()`, so
  `drush si --existing-config` fails. `config/sync` also doesn't import cleanly
  onto a fresh Umami install: it deletes Umami's demo content and enables
  `memcache`, which isn't in composer. The seed DB snapshot is no longer in git.
- Log in with `ddev drush uli`.

## URLs

- `https://d11.ddev.site` (see `ddev describe`)
- Coder: `https://d11--<workspace>--<owner>.coder.ddev.com`. The Coder `d11` app
  forwards to `localhost:8080`, which must be DDEV's `router_http_port`. If 8080
  is busy, DDEV falls back to another port (e.g. 33000) and the Coder URL returns
  "not found". The Claude self-hosted runner's `/healthz` listener defaults to
  8080, so start the runner with `--health-port 0` (or another port).
