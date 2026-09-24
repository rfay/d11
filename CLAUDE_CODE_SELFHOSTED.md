# Running Claude Code sessions for this project on a self-hosted runner

This guide proposes running Claude Code cloud sessions for this DDEV project on
a **self-hosted environment**: a runner you host yourself, for example in a
[coder.ddev.com](https://ddev.com/blog/coder-ddev-com-announcement/) workspace.
Sessions still start from claude.ai/code, the Claude app, or `claude --cloud`,
but they execute on your machine, as your user, with your network.

> **Status: untested.** This is based on the current Claude Code
> documentation, not on a working runner. Commands and flags may need
> adjusting once someone tries it. Update this file with what actually works.

For the Anthropic-hosted sandbox this repository currently uses, and its
limits, see [`CLAUDE_CLOUD_SETUP.md`](CLAUDE_CLOUD_SETUP.md).

## Why self-host

The Anthropic-hosted sandbox works, but it's restrictive:

| Anthropic-hosted sandbox | Self-hosted runner in a Coder workspace |
| --- | --- |
| Session runs as root; DDEV needs a wrapper that re-runs it as `ubuntu` | Session runs as the workspace user (`coder`, with passwordless sudo); DDEV runs natively |
| TLS-inspecting egress gateway; its CA has to be injected into DDEV's images | Normal outbound TLS; no CA injection |
| Outbound traffic only on ports 80 and 443 | Whatever the host's network allows |
| `ddev share` fails (cloudflared needs port 7844; ngrok is rejected by the gateway) | `ddev share` should work (ngrok just needs normal TLS; cloudflared needs outbound 7844) |
| Only curl and Playwright inside the container can see the site | You can also open the site yourself through Coder port forwarding |
| Fresh container each session; DDEV database starts empty | Persistent workspace; the DDEV project and database survive between sessions |

## Requirements

- **Plan:** self-hosted environments are a **public beta on Team and
  Enterprise plans only**, off by default. An organization **Owner** turns on
  **Allow self-hosted environments** at
  [Admin settings → Cloud environments](https://claude.ai/admin-settings/cloud-environments).
  Cloud sessions must be enabled for the organization too.
- **Claude Code v2.1.224 or later** on the runner host (the runner is the
  `claude self-hosted-runner` subcommand of the normal `claude` binary).
- **Git 2.32 or later** (needed for `--use-anthropic-git-proxy`).
- A Linux or macOS host with outbound HTTPS to `api.anthropic.com` and
  `claude.ai`, and a clock synced to real time (authentication fails when it's
  more than five minutes off).
- Docker and DDEV on the host. A coder.ddev.com workspace already has both
  (Docker runs under Sysbox).
- The Claude GitHub App installed on `rfay/d11`, as for Anthropic-hosted
  sessions.

Nothing connects **into** the host: the runner polls `api.anthropic.com` and
streams session events over outbound HTTPS.

## Setup

### 1. Create the environment (claude.ai)

1. Go to [Admin settings → Cloud environments](https://claude.ai/admin-settings/cloud-environments).
2. Under **Self-hosted environments**, select **New**, name it (for example
   `d11-coder`), and select **Create**.
3. Select **Copy environment key**. It's shown once and expires after 365
   days. If you lose it, create a new one on the environment's
   **Configuration** tab and revoke the old one.

Alternatively, `claude self-hosted-runner setup` on a machine signed in as an
Owner walks through these steps interactively.

### 2. Prepare the workspace

Use a coder.ddev.com workspace from the **freeform** template (or any Linux
host with Docker and DDEV), as the normal non-root user:

```bash
# Install Claude Code, then confirm the runner subcommand exists
curl -fsSL https://claude.ai/install.sh | bash
claude self-hosted-runner --help     # should list --environment-secret-file

# Store the environment key, readable only by you
mkdir -p ~/.claude-runner ~/runner
(umask 077 && cat > ~/.claude-runner/environment-secret)   # paste, Enter, Ctrl-D
```

### 3. Start the runner in a restart loop

The runner exits by design when its sessions finish, so it needs something to
restart it. Save this as `~/.claude-runner/run.sh`:

```bash
#!/bin/bash
# Keep a Claude Code self-hosted runner running for the d11 project.
while true; do
  claude self-hosted-runner \
    --environment-secret-file "$HOME/.claude-runner/environment-secret" \
    --base-dir "$HOME/runner" \
    --capacity 1 \
    --use-anthropic-git-proxy \
    --release-idle-session-min 30 \
    --kill-session-after-min 480
  echo "runner exited ($?); restarting in 5s"
  sleep 5
done
```

Then run it where it survives your terminal closing:

```bash
chmod +x ~/.claude-runner/run.sh
tmux new -d -s claude-runner ~/.claude-runner/run.sh
tmux attach -t claude-runner      # watch it; detach with Ctrl-b d
```

For something sturdier, start it from a Coder startup script (a
`coder_script` resource in the workspace template) so it comes back whenever
the workspace starts.

What the flags do:

- `--capacity 1`: one session at a time.
  - `--use-anthropic-git-proxy` requires it.
  - At capacity 1 the runner keeps one reusable checkout at
    `~/runner/rfay/d11` and resets it to the requested branch for each
    session, instead of cloning again. So the DDEV project is always the same
    `d11` project, and its database and volumes carry over between sessions.
  - With a higher capacity, parallel checkouts would all be DDEV projects
    named `d11` and collide.
- `--use-anthropic-git-proxy`: clone and push through Anthropic's git proxy,
  using your existing Claude GitHub connection. The workspace needs no git
  tokens or SSH keys.
- `--release-idle-session-min 30`: frees the slot when a conversation goes
  idle; the session resumes (on this or another runner) when you send the
  next message.
- `--kill-session-after-min 480`: hard backstop, because a session with a
  never-ending background task (a `ddev share` tunnel, for example) never
  counts as idle.

### 4. Check it and start a session

1. On the **Cloud environments** page, the environment should go from **No
   runners deployed** to **Healthy** within a few seconds.
2. Start a session at [claude.ai/code](https://claude.ai/code), pick
   `rfay/d11`, and choose the self-hosted environment in the environment
   picker.
3. The runner logs `Picked up session <session-id>`.

You can also send follow-ups from any machine where you're signed in:
`claude -p "your message" --cloud <session-id>`.

## Working with DDEV in a runner session

None of the sandbox workarounds in `CLAUDE.md` apply: no wrapper, no CA
files, no setup script. In a session:

```bash
cd ~/runner/rfay/d11
ddev start
ddev drush si -y demo_umami --account-pass=admin   # first time only; the database persists
```

Seeing the site:

- **curl and Playwright** work as on any DDEV host: no `--noproxy` and no proxy
  settings for Chromium. You still need the mkcert CA (or
  `ignoreHTTPSErrors: true`) unless `mkcert -install` has been run.
- **You**, through Coder: forward the web container's port (`ddev describe`
  shows it) or use the workspace's port-forwarding apps.
- **Someone else:** `ddev share` (ngrok by default; needs `ngrok config
  add-authtoken`). The shared URL is public, so change the `admin` password
  first or use `ddev share --provider-args "--basic-auth user:pass"`.

`CLAUDE.md` currently describes the Anthropic-hosted sandbox. If runner
sessions become the main way to work, add a short section there saying which
environment you're in (for example, check `id -un`: `root` in the sandbox,
`coder` on the runner), so Claude skips the sandbox workarounds on the runner.

## Caveats

- **Anyone in your organization can dispatch sessions to the environment.**
  Those sessions run model-directed code in your workspace as `coder`, with
  sudo and access to whatever else is in the workspace. Use a dedicated
  workspace with no unrelated credentials or projects in it.
- **One person at a time per runner.** A runner locks to the owner of the
  first session it picks up. Each person working at the same time needs their
  own runner (or workspace).
- **Workspace auto-stop.** If Coder stops the workspace mid-session, the
  runner dies and the session is requeued. Either turn off auto-stop for this
  workspace, or pass `--retire-at <epoch-seconds>` (a few minutes before the
  stop) so the runner releases sessions cleanly.
- **Shared persistent disk.** Anthropic's hardening guidance is a fresh
  container per session. A persistent workspace trades that isolation for
  speed and a DDEV database that survives between sessions. That's
  reasonable for one developer's demo site, not for untrusted users.
- **coder.ddev.com is experimental,** with no uptime or retention guarantees,
  and an always-on runner is a steady load on it.
- **Session content still goes to Anthropic** for model inference; only the
  checkout, build output, and DDEV data stay on your host.

## Possible next step

Add a "Claude runner" option to the
[ddev/coder-ddev](https://github.com/ddev/coder-ddev) template: a parameter for
the environment key and a `coder_script` that installs Claude Code and runs the
restart loop above. Any workspace could then serve as a runner.

## References

- [Self-hosted environments](https://code.claude.com/docs/en/self-hosted-environments)
- [Self-hosted environments quickstart](https://code.claude.com/docs/en/self-hosted-environments-quickstart)
- [Deploy self-hosted environments to production](https://code.claude.com/docs/en/self-hosted-environments-deploy)
  (hardening, git options, pre-warmed checkouts, known issues)
- [Self-hosted runner reference](https://code.claude.com/docs/en/self-hosted-environments-reference)
  (all flags)
- [Use Claude Code in the cloud](https://code.claude.com/docs/en/claude-code-on-the-web)
- [Introducing coder.ddev.com](https://ddev.com/blog/coder-ddev-com-announcement/)
- [ddev/coder-ddev](https://github.com/ddev/coder-ddev)
