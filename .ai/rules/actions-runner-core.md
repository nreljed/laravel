---
paths:
  - 'C:/actions-runner-core/**'
---

# Actions Runner Core

## Invisible .runner blocks config.cmd — use config.cmd remove
If runner config.cmd configure keeps saying "already configured" but `Test-Path .runner` is False and `Get-ChildItem -Force` won't list it, a stale hidden/system-locked `.runner` file still exists. Run `config.cmd remove --token <reg-token>` (from the runner folder) — it WILL report "V Removed .runner" even though PowerShell can't see it — then register fresh. Also kill lingering Runner.Listener/Runner.Worker processes before re-registering after `start -Force`.
