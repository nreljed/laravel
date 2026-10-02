---
paths:
  - 'vendor/nreljed/platform/src/Deploy/**'
---

# Deploy

## Release-layout hosts write .env backups into the active release, not root
DeployController::updateEnv()/updateEnvValues() write .env.backup.<ts> beside base_path('.env'). On release-layout hosts base_path('.env') is the active release's .env symlink, so backups land INSIDE the release dir (pruned by release-cleanup keep=3), not at FTP root. Any .env.backup.* sitting at the FTP root are legacy residue from the pre-release-layout era — safe to archive. (Archived 2026-09-14 into legacy-root-env-backups-2026-09-14/ on all 3 hosts.)
