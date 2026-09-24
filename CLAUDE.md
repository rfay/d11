# CLAUDE.md

Drupal 11 site run with DDEV (`.ddev/config.yaml`: PHP 8.5, nginx-fpm, MariaDB 11.8,
docroot `web`). The post-start hook runs `composer install`.

## Claude Code cloud environment (custom setup)

Claude Code on the web sessions run in a container with a custom environment:

- **Network access:** Full: any host, but only on ports 80 and 443 (see
  "Network and TLS").
- **Session user:** root. DDEV refuses to run as root, so DDEV runs as the
  `ubuntu` user (uid 1000) instead.

### Setup script

The environment's setup script (environment settings → Edit → Setup script)
is below. It logs every step to `/tmp/setup-script.log`. If a required step
fails, the script exits 1 with an `ERROR: required setup step failed: ...`
line naming the step, so session startup reports the real problem. Optional
steps (mkcert, global config, ngrok, image pre-pull) only print a warning.

```bash
#!/bin/bash
# Claude Code cloud environment setup for the d11 DDEV project.
#
# Every step is logged to /tmp/setup-script.log. Required steps make the script
# exit non-zero with a summary naming the failed step, so session startup
# reports the real problem. Optional steps only print a warning.

exec > >(tee -a /tmp/setup-script.log) 2>&1
set -uo pipefail

PROJECT=/workspace/d11
REQUIRED_FAILED=()
OPTIONAL_FAILED=()

# run_step required|optional "name" function
run_step() {
  local kind=$1 name=$2 fn=$3 rc
  echo "==> [$kind] $name"
  "$fn"
  rc=$?
  if [ "$rc" -eq 0 ]; then
    echo "<== ok: $name"
  else
    echo "!!! FAILED (exit $rc): $name"
    if [ "$kind" = required ]; then
      REQUIRED_FAILED+=("$name (exit $rc)")
    else
      OPTIONAL_FAILED+=("$name (exit $rc)")
    fi
  fi
}

# `sudo -n` never prompts for a password: it fails instead of hanging.
as_ubuntu() { sudo -n -u ubuntu -H "$@"; }

start_docker() {
  nohup dockerd >/tmp/dockerd.log 2>&1 &
  local i
  for i in $(seq 60); do
    docker info >/dev/null 2>&1 && return 0
    sleep 1
  done
  echo "dockerd did not answer within 60s; see /tmp/dockerd.log"
  return 1
}

install_ddev() {
  install -m 0755 -d /etc/apt/keyrings &&
  curl -fsSL https://packages.ddev.com/public/gpg.key -o /etc/apt/keyrings/ddev.asc &&
  printf "Types: deb\nURIs: https://packages.ddev.com/public/deb/ubuntu\nSuites: stable\nComponents: main\nSigned-By: /etc/apt/keyrings/ddev.asc\n" \
    > /etc/apt/sources.list.d/ddev.sources &&
  apt-get update &&
  DEBIAN_FRONTEND=noninteractive apt-get install -y ddev
}

setup_ubuntu_user() {
  # Let ubuntu reach the Docker socket; stop git's "dubious ownership" refusal
  usermod -aG docker ubuntu &&
  git config --system --add safe.directory "$PROJECT" &&
  if [ -d "$PROJECT" ]; then chown -R ubuntu:ubuntu "$PROJECT"; fi
}

install_ddev_wrapper() {
  # Running ddev as root re-runs it as ubuntu in the current directory
  cat > /usr/local/bin/ddev <<'EOF'
#!/bin/bash
if [ "$(id -u)" = 0 ]; then
  exec sudo -n -u ubuntu -H --preserve-env=PATH bash -c 'cd "$1" && shift && exec /usr/bin/ddev "$@"' _ "$PWD" "$@"
fi
exec /usr/bin/ddev "$@"
EOF
  chmod +x /usr/local/bin/ddev
}

install_container_ca() {
  # Make DDEV's containers trust the sandbox's TLS-inspecting egress CAs.
  # Use the copies baked into the image: /root/.ccr/ca-bundle.crt is only
  # written after this script has finished, so it can't be read here.
  local certs=(/usr/local/share/ca-certificates/egress-gateway-ca-*.crt
               /usr/local/share/ca-certificates/swp-ca-*.crt)
  local c d
  for c in "${certs[@]}"; do
    [ -s "$c" ] || { echo "missing egress CA file: $c"; return 1; }
  done
  for d in web-build db-build; do
    mkdir -p /home/ubuntu/.ddev/$d &&
    # awk 1, not cat: swp-ca-production.crt has no trailing newline, and cat
    # glues two certs together, which breaks the container's whole CA bundle.
    awk 1 "${certs[@]}" > /home/ubuntu/.ddev/$d/ccr-ca.crt &&
    printf 'COPY ccr-ca.crt /usr/local/share/ca-certificates/ccr-ca.crt\nRUN update-ca-certificates\n' \
      > /home/ubuntu/.ddev/$d/pre.Dockerfile.ccr-ca || return 1
  done
  chown -R ubuntu:ubuntu /home/ubuntu/.ddev
}

setup_mkcert() {
  # Create DDEV's local CA. TRUST_STORES=nss skips the system trust store,
  # which needs sudo as ubuntu (a password) and made plain `mkcert -install` fail.
  as_ubuntu env TRUST_STORES=nss mkcert -install
}

ddev_global_config() {
  as_ubuntu /usr/bin/ddev config global --instrumentation-opt-in=false
}

install_ngrok() {
  # ngrok for `ddev share` (cloudflared can't work here). NGROK_AUTHTOKEN is
  # set in the environment settings; store it in ubuntu's ngrok config because
  # the ddev wrapper passes only PATH through to ubuntu.
  local tgz=/tmp/ngrok.tgz
  curl -fsSL https://bin.equinox.io/c/bNyj1mQVY4c/ngrok-v3-stable-linux-amd64.tgz -o "$tgz" &&
  tar -xzf "$tgz" -C /usr/local/bin &&
  rm -f "$tgz" || return 1
  if [ -n "${NGROK_AUTHTOKEN:-}" ]; then
    as_ubuntu ngrok config add-authtoken "$NGROK_AUTHTOKEN"
  else
    echo "NGROK_AUTHTOKEN not set; ddev share won't work until it is"
  fi
}

download_images() {
  # Pull DDEV's images ahead of time so the first `ddev start` is faster
  [ -d "$PROJECT" ] || return 0
  (cd "$PROJECT" && timeout 600 /usr/local/bin/ddev utility download-images)
}

run_step required "start dockerd"          start_docker
run_step required "install ddev"           install_ddev
run_step required "set up ubuntu user"     setup_ubuntu_user
run_step required "install ddev wrapper"   install_ddev_wrapper
run_step required "install egress CA for DDEV containers" install_container_ca
run_step optional "mkcert local CA"        setup_mkcert
run_step optional "ddev global config"     ddev_global_config
run_step optional "install ngrok"          install_ngrok
run_step optional "pre-pull DDEV images"   download_images

echo
if [ ${#OPTIONAL_FAILED[@]} -gt 0 ]; then
  printf 'WARNING: optional setup step failed: %s\n' "${OPTIONAL_FAILED[@]}" >&2
fi
if [ ${#REQUIRED_FAILED[@]} -gt 0 ]; then
  printf 'ERROR: required setup step failed: %s\n' "${REQUIRED_FAILED[@]}" >&2
  echo "Full log: /tmp/setup-script.log" >&2
  exit 1
fi
echo "Setup complete. Log: /tmp/setup-script.log"
```

Things the setup script can't rely on:

- `/root/.ccr/ca-bundle.crt` doesn't exist yet: the environment writes it
  (and the agent-proxy CAs) only after the setup script finishes. The script
  uses the egress CAs baked into the image under
  `/usr/local/share/ca-certificates/` instead.
- ubuntu has no passwordless sudo, so plain `mkcert -install` as ubuntu fails
  while adding its CA to the system trust store. `TRUST_STORES=nss` skips that
  store. Every `sudo` uses `-n` so it fails instead of waiting for a password.
- `dockerd` isn't ready the moment it's started, so the script waits for
  `docker info` to answer.

The wrapper uses `sudo -u ubuntu -H`, not `sudo -iu ubuntu`: with `-i`, sudo
re-quotes the command for a login shell, the directory argument is lost, and
DDEV runs in `/home/ubuntu` ("could not find a project").

### Network and TLS

- The session's own tools use the agent proxy (`HTTPS_PROXY=127.0.0.1:44467`)
  and trust `/root/.ccr/ca-bundle.crt`. Containers can't reach that proxy and
  don't need it: their traffic goes out directly through the sandbox egress
  gateway, which re-signs TLS with `O=Anthropic, CN=sandbox-egress-gateway-*
  Egress Gateway CA`. So no Docker/DDEV proxy settings are needed; only the
  CA has to be trusted.
- The containers need the egress gateway and TLS inspection CAs
  (`/usr/local/share/ca-certificates/egress-gateway-ca-*.crt` and
  `swp-ca-*.crt`, part of the image). `/root/.ccr/agent-proxy-ca.crt` holds
  only the agent-proxy CAs, and all of `/root/.ccr/` is written after the setup
  script has run.
- "Full" network access means any host, but only on ports 80 and 443.
  Everything else times out: tested 7844 (two Cloudflare edge IPs and
  portquiz.net), 8080, and 22 (github.com). So SSH-based git remotes and
  anything else on a non-web port won't work from the session or the containers.
- The CA goes in global `~/.ddev/{web,db}-build/pre.Dockerfile.ccr-ca` (see
  the [DDEV networking docs](https://docs.ddev.com/en/stable/users/usage/networking/)),
  so nothing sandbox-specific is committed to the project.
- Without it, every download in the containers fails with `self-signed
  certificate in certificate chain`: the web image build spends ~3 minutes
  timing out in `composer self-update`, and the post-start `composer install`
  hangs.

### Running DDEV

- Run `ddev` normally from `/workspace/d11` (`ddev start`, `ddev drush ...`,
  `ddev composer ...`). `/usr/local/bin/ddev` comes first in `PATH` and re-runs
  the command as `ubuntu` in the current directory.
- If the wrapper is missing (`which ddev` shows `/usr/bin/ddev`), run commands as
  `ubuntu` yourself:
  `sudo -u ubuntu -H bash -c 'cd /workspace/d11 && /usr/bin/ddev <command>'`.
  Don't use `sudo -s ubuntu`: `-s` runs a shell, so that tries to run a command
  named `ubuntu` as root.
- Each Bash tool call is a new root shell, so a bare `su - ubuntu` doesn't
  carry over to later commands. The user has to be switched on every command,
  which is why the wrapper exists.

- `ddev start`, `ddev restart` and `ddev utility rebuild` can take minutes.
  Run them in the background with output going to a log, and watch the log,
  rather than piping through `tail` (which shows nothing until the end).
  `docker buildx history ls` / `docker buildx history logs <id>` show which
  image build step is slow.

### Installing Drupal

After `ddev start`, the site redirects to `/core/install.php` until Drupal is
installed. Install the Umami demo with:

```bash
ddev drush si -y demo_umami --account-pass=admin
```

### Reaching the site

Only from inside this sandbox; nothing can connect in from outside.

- From the session: `curl --noproxy '*' --cacert "$(sudo -u ubuntu -H mkcert -CAROOT)/rootCA.pem" https://d11.ddev.site`.
  Without `--noproxy`, the request goes to the agent proxy, which rejects
  `d11.ddev.site` (502). Root's `CURL_CA_BUNDLE` doesn't include DDEV's mkcert
  root, so pass it with `--cacert`.
- From inside the web container: `ddev exec curl -sS http://localhost/`.
- Chromium/Playwright is preinstalled and can load the site for screenshots.
  Launch it with `proxy: { server: process.env.HTTPS_PROXY, bypass: '*.ddev.site' }`
  and `ignoreHTTPSErrors: true` on the page; `--no-proxy-server` isn't enough.
- `ddev drush uli --uri=https://d11.ddev.site --no-browser` gives a one-time
  admin login link for scripted browser sessions.

### Sharing the site

Nothing can connect in to the sandbox, so `ddev share` (an outbound tunnel) is
the only way for someone outside to see the site.

- `ddev share --provider=cloudflared` doesn't work here. It gets a
  `*.trycloudflare.com` URL (an ordinary 443 request), but the tunnel has to
  connect to Cloudflare's edge on port 7844, which is blocked:
  `dial tcp 198.41.200.13:7844: i/o timeout`, and the URL answers 530.
- ngrok (the default provider) doesn't work here either (tested ngrok 3.39.11).
  It connects to `connect.ngrok-agent.com:443` directly through the egress
  gateway, which re-signs TLS, so ngrok's pinned CA fails first:
  `x509: certificate signed by unknown authority`. With
  `connect_cas: host` under `agent:` in `~/.config/ngrok/ngrok.yml`, TLS
  verifies, but the gateway then drops the tunnel protocol:
  `failed to send authentication request: session closed`. The sandbox's
  `/root/.ccr/README.md` lists ngrok among the clients the proxy doesn't
  support ("report, do not work around").
- `NGROK_AUTHTOKEN` from the environment settings is visible in the session
  but was not set while the setup script ran, so the script's
  `ngrok config add-authtoken` step was skipped.
- Both providers point the tunnel at the web container's direct host port
  (`DDEV_LOCAL_URL`, e.g. `http://127.0.0.1:32781`), not at the router.
- A shared site is public. The Umami demo install uses `--account-pass=admin`,
  so change the password or use `ddev share --provider-args "--basic-auth user:pass"`.

### Troubleshooting

- `DDEV is not designed to be run with root privileges`: the wrapper isn't in
  place. See above.
- `permission denied ... /var/run/docker.sock` as ubuntu: `usermod -aG docker ubuntu`
  didn't run. `id ubuntu` should list `docker`.
- Files under the repo are owned by root: the checkout didn't exist yet when the
  setup script ran, so it wasn't chowned. Run
  `chown -R ubuntu:ubuntu /workspace/d11` (it needs root and approval).
- Something from the setup script is missing (wrapper, `~/.ddev/*-build`,
  ngrok): see `/tmp/setup-script.log` for the step that failed.
- Docker not responding: see `/tmp/dockerd.log`.
- `SSL certificate problem: self-signed certificate in certificate chain`
  inside a container, or a web image build that sits in `composer self-update`
  for minutes: the egress CA isn't installed. Check
  `~/.ddev/web-build/pre.Dockerfile.ccr-ca` exists, then `ddev utility rebuild`.
- `curl error 77 ... error setting certificate file: /etc/ssl/certs/ca-certificates.crt`
  in the container (post-start `composer install` exits 100): `ccr-ca.crt`
  has two certs on one line (`-----END CERTIFICATE----------BEGIN
  CERTIFICATE-----`). Rebuild it with `awk 1` as in the setup script, then
  `ddev restart`.
- Git as root will complain about "dubious ownership" unless
  `safe.directory` is set (the setup script sets it system-wide).
