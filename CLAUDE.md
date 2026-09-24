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
  exec sudo -iu ubuntu bash -c 'cd "$1" && shift && exec /usr/bin/ddev "$@"' _ "$PWD" "$@"
fi
exec /usr/bin/ddev "$@"
EOF
chmod +x /usr/local/bin/ddev
```

### Running DDEV

- Run `ddev` normally from `/workspace/d11` (`ddev start`, `ddev drush ...`,
  `ddev composer ...`). `/usr/local/bin/ddev` comes first in `PATH` and re-runs
  the command as `ubuntu` in the current directory.
- If the wrapper is missing (`which ddev` shows `/usr/bin/ddev`), run commands as
  `ubuntu` yourself:
  `sudo -iu ubuntu bash -c 'cd /workspace/d11 && ddev <command>'`.
  Don't use `sudo -s ubuntu`: `-s` runs a shell, so that tries to run a command
  named `ubuntu` as root.
- Each Bash tool call is a new root shell, so a bare `su - ubuntu` doesn't
  carry over to later commands. The user has to be switched on every command,
  which is why the wrapper exists.

### Troubleshooting

- `DDEV is not designed to be run with root privileges`: the wrapper isn't in
  place. See above.
- `permission denied ... /var/run/docker.sock` as ubuntu: `usermod -aG docker ubuntu`
  didn't run. `id ubuntu` should list `docker`.
- Files under the repo are owned by root: the checkout didn't exist yet when the
  setup script ran, so it wasn't chowned. Run
  `chown -R ubuntu:ubuntu /workspace/d11` (it needs root and approval).
- Docker not responding: see `/tmp/dockerd.log`.
- Git as root will complain about "dubious ownership" unless
  `safe.directory` is set (the setup script sets it system-wide).
