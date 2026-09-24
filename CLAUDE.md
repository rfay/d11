# CLAUDE.md

Drupal 11 site run with DDEV (`.ddev/config.yaml`: PHP 8.5, nginx-fpm, MariaDB 11.8,
docroot `web`). The post-start hook runs `composer install`.

## Claude Code cloud environment (custom setup)

Claude Code on the web sessions run in a container with a custom environment:

- **Network access:** full.
- **Session user:** root. DDEV refuses to run as root, so DDEV runs as the
  `ubuntu` user (uid 1000) instead.

### Setup script

The environment's setup script (environment settings → Edit → Setup script)
does the following:

```bash
nohup dockerd >/tmp/dockerd.log 2>&1 &
# Install DDEV from packages.ddev.com
install -m 0755 -d /etc/apt/keyrings
curl -fsSL https://packages.ddev.com/public/gpg.key -o /etc/apt/keyrings/ddev.asc
printf "Types: deb\nURIs: https://packages.ddev.com/public/deb/ubuntu\nSuites: stable\nComponents: main\nSigned-By: /etc/apt/keyrings/ddev.asc\n" > /etc/apt/sources.list.d/ddev.sources
apt-get update && apt-get install -y ddev

# Let the ubuntu user reach the Docker socket
usermod -aG docker ubuntu

# ubuntu has to be able to write to the project (.ddev, vendor/, web/, ...)
if [ -d /workspace/d11 ]; then
  chown -R ubuntu:ubuntu /workspace/d11
fi

# The session still runs as root; this stops git from refusing
# ("dubious ownership") once the repo belongs to ubuntu
git config --system --add safe.directory /workspace/d11

# Optional: set up DDEV's local CA and global config ahead of time
sudo -u ubuntu -H mkcert -install || true
sudo -u ubuntu -H ddev config global --instrumentation-opt-in=false || true

# Wrap ddev so that running it as root re-runs it as ubuntu
cat > /usr/local/bin/ddev <<'EOF'
#!/bin/bash
# Re-run as ubuntu when invoked as root, keeping the current directory
if [ "$(id -u)" = 0 ]; then
  exec sudo -u ubuntu -H --preserve-env=PATH bash -c 'cd "$1" && shift && exec /usr/bin/ddev "$@"' _ "$PWD" "$@"
fi
exec /usr/bin/ddev "$@"
EOF
chmod +x /usr/local/bin/ddev

# Make DDEV's containers trust the sandbox's TLS-inspecting egress CAs
# (see "Network and TLS" below)
for d in web-build db-build; do
  mkdir -p /home/ubuntu/.ddev/$d
  python3 - "$d" <<'PY'
import re, subprocess, sys
pems = re.findall(r'-----BEGIN CERTIFICATE-----.*?-----END CERTIFICATE-----',
                  open('/root/.ccr/ca-bundle.crt').read(), re.S)
keep = [p for p in pems if 'Anthropic' in subprocess.run(
    ['openssl', 'x509', '-noout', '-subject'], input=p,
    capture_output=True, text=True).stdout]
open(f'/home/ubuntu/.ddev/{sys.argv[1]}/ccr-ca.crt', 'w').write('\n'.join(keep) + '\n')
PY
  printf 'COPY ccr-ca.crt /usr/local/share/ca-certificates/ccr-ca.crt\nRUN update-ca-certificates\n' \
    > /home/ubuntu/.ddev/$d/pre.Dockerfile.ccr-ca
  chown -R ubuntu:ubuntu /home/ubuntu/.ddev/$d
done

# Optional: pull DDEV's images ahead of time so the first `ddev start` is faster
if [ -d /workspace/d11 ]; then
  (cd /workspace/d11 && ddev utility download-images) || true
fi
```

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
- `/root/.ccr/agent-proxy-ca.crt` contains only the agent-proxy CAs, not the
  egress gateway CA, so the setup script takes every `O=Anthropic` cert from
  `ca-bundle.crt` instead.
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

### Troubleshooting

- `DDEV is not designed to be run with root privileges`: the wrapper isn't in
  place. See above.
- `permission denied ... /var/run/docker.sock` as ubuntu: `usermod -aG docker ubuntu`
  didn't run. `id ubuntu` should list `docker`.
- Files under the repo are owned by root: the checkout didn't exist yet when the
  setup script ran, so it wasn't chowned. Run
  `chown -R ubuntu:ubuntu /workspace/d11` (it needs root and approval).
- Docker not responding: see `/tmp/dockerd.log`.
- `SSL certificate problem: self-signed certificate in certificate chain`
  inside a container, or a web image build that sits in `composer self-update`
  for minutes: the egress CA isn't installed. Check
  `~/.ddev/web-build/pre.Dockerfile.ccr-ca` exists, then `ddev utility rebuild`.
- Git as root will complain about "dubious ownership" unless
  `safe.directory` is set (the setup script sets it system-wide).
