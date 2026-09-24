#!/usr/bin/env bash
# Start this Coder workspace's services: trust the mkcert CA, run the Claude
# self-hosted runner loop in tmux, and start the d11 DDEV project.
# Safe to call repeatedly: it only does work once per workspace boot.
set -u

marker=/tmp/.d11-startup-done   # /tmp is wiped when the workspace restarts
[ -e "$marker" ] && exit 0
touch "$marker"

log=/tmp/d11-startup.log
exec >>"$log" 2>&1
echo "=== d11 startup $(date)"

# The system trust store is on the ephemeral root fs; the CA in ~/.local persists.
mkcert -install

# --health-port 0: the runner's /healthz defaults to 8080, which the Coder d11 app
# needs for DDEV's router.
secret=/home/coder/.claude-d11-selfhosted-secret.txt
if [ -f "$secret" ] && ! pgrep -f '^claude self-hosted-runner' >/dev/null; then
  tmux new-session -d -s runner "while true; do claude self-hosted-runner \
    --environment-secret-file $secret --base-dir /home/coder/workspace --capacity 1 \
    --use-anthropic-git-proxy --release-idle-session-min 30 --kill-session-after-min 480 \
    --health-port 0; sleep 5; done"
  echo "runner started in tmux session 'runner'"
fi

ddev start d11 -y
