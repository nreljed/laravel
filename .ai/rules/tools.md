---
paths:
  - 'C:/tools/gha-runner.ps1'
---

# Tools

## Self-hosted runners live under C:\runners\<suffix>
All 8 GitHub Actions runner installs moved from flat `C:\actions-runner-*` to `C:\runners\<suffix>` (shop, laravel, booking, platform, blog, shopmodule, bookingmodule, projectwp) on 2026-08-31. Each runner keeps its own dir (GitHub constraint). The launcher `C:\tools\gha-runner.ps1` maps these paths. Defender excludes `C:\runners`. Never reference the old `C:\actions-runner-*` paths.
