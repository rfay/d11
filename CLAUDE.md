# d11

Drupal 11 test project run with DDEV (PHP 8.5, nginx-fpm, MariaDB 11.8, docroot `web`),
in the `claude-d11-selfhosted` Coder workspace (template: ddev/coder-ddev).

## Starting the workspace

`.coder/startup.sh` does everything needed after a workspace (re)start:

1. `mkcert -install`. The CA lives in `~/.local/share/mkcert` (persistent), but
   the system trust store is on the ephemeral root filesystem, so this has to run
   on every boot.
2. Starts the Claude self-hosted runner loop in a detached tmux session named
   `runner`, unless a runner is already running.
3. `ddev start d11`.

It runs at most once per boot (marker `/tmp/.d11-startup-done`) and logs to
`/tmp/d11-startup.log`. It is called from `~/.bashrc`, so it runs when the first
terminal opens:

```bash
[ -x ~/workspace/rfay/d11/.coder/startup.sh ] && (~/workspace/rfay/d11/.coder/startup.sh &)
```

For it to run with no terminal open, the Coder template's startup script would
need to call it (e.g. run `~/.coder-startup.sh` if present). The ddev/coder-ddev
template has no such hook today.

To start the runner by hand instead:

```bash
while true; do claude self-hosted-runner \
    --environment-secret-file /home/coder/.claude-d11-selfhosted-secret.txt \
    --base-dir /home/coder/workspace --capacity 1 --use-anthropic-git-proxy \
    --release-idle-session-min 30 --kill-session-after-min 480 \
    --health-port 0; sleep 5; done
```

`--health-port 0` is required. The runner's `/healthz` listener defaults to
port 8080, which the Coder `d11` app forwards to (see URLs below).

Attach to the runner with `tmux attach -t runner`. Restarting the runner ends any
session it is running.

## First-time project setup

Run these from the project root:

```bash
mkcert -install        # before the first ddev start, so router certs come from a trusted CA
ddev coder-setup       # before ddev start: writes .ddev/config.coder.yaml + routing hook
ddev start             # post-start hook runs `composer install`
ddev drush si -y demo_umami --account-pass=admin          # only if the database is empty
```

- The project name must be `d11`, the only name registered in the workspace
  (`CODER_PROJECT_NAMES`). It comes from the directory name.
- `ddev coder-setup` writes `.ddev/config.coder.yaml` and
  `.ddev/docker-compose.coder-describe.yaml`. Both are in the global git ignore
  and must not be committed. It also adds a post-start hook that publishes
  Traefik routes for the Coder URLs.
- If `mkcert -install` runs after the first start, run `ddev restart`.
- If the checkout moves to a new path, run `ddev stop --unlist d11` and then
  `ddev start` from the new path. The database volume does not survive this, so
  reinstall afterwards.
- The install profile is `demo_umami`, which has a `hook_install()`, so
  `drush si --existing-config` fails. `config/sync` also doesn't import cleanly
  onto a fresh Umami install: it deletes Umami's demo content and enables
  `memcache`, which isn't in composer. The seed DB snapshot is no longer in git.
- Log in with `ddev drush uli`.

## URLs

- `https://d11.ddev.site` (see `ddev describe`)
- Coder: `https://d11--claude-d11-selfhosted--rfay.coder.ddev.com` (Mailpit:
  `https://mailpit-d11--...`). The Coder `d11` app forwards to `localhost:8080`,
  which must be DDEV's `router_http_port` (set to 8080 in `~/.ddev/global_config.yaml`).
  If 8080 is busy, DDEV falls back to another port (e.g. 33000) and the Coder URL
  returns "not found".
