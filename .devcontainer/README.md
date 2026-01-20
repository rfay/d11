# D11 Devcontainer Configurations

## devcontainer.json (default)
Uses the published DDEV feature from GitHub Container Registry.

```json
{
  "features": {
    "ghcr.io/ddev/ddev/install-ddev:latest": {}
  }
}
```

## ddev-head/devcontainer.json (testing with DDEV HEAD)
Tests with the latest DDEV from the main branch, built from source.

**When to use:**
- Testing DDEV development changes in Codespaces
- Verifying d11 works with unreleased DDEV features
- Testing the install-ddev feature itself

**To use:**
1. Push this branch to GitHub
2. Create Codespace → "New with options..."
3. **Dev container configuration**: Select `.devcontainer/ddev-head/devcontainer.json`
4. Codespace will clone and build DDEV from GitHub main branch

**To test a specific DDEV branch:**
Edit the `onCreateCommand` in `ddev-head/devcontainer.json`:
```json
"onCreateCommand": "git clone --depth=1 -b BRANCH_NAME https://github.com/ddev/ddev.git /tmp/ddev-source"
```

## features/install-ddev/
Local copy of the DDEV devcontainer feature for testing. Update with:
```bash
cp ~/workspace/ddev/containers/devcontainers/install-ddev/* .devcontainer/features/install-ddev/
```
