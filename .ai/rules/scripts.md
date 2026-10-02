---
paths:
  - 'scripts/**'
  - scripts/deploy-runner.php
---

# Scripts

## Runner budgets cap at host HTTP connection timeout, not PHP max_execution_time
On tunispromo.tn shared hosting, the web server (LiteSpeed/Apache) kills HTTP requests at ~120s regardless of `set_time_limit(600)` — the PHP cap is meaningless for long-running runner actions. The runner's default budget is therefore fixed at 85s (not derived from max_execution_time), returning a `partial` status so idempotent re-invocations continue. Any new long-running runner action must keep its budget under ~110s.

## deploy runner: never follow storage/.env symlinks; rank releases by name timestamp
rd_rrmdir and rd_rrmdir_collect MUST never descend into symlinks. Each release/stage dir symlinks shared storage + .env back to home; is_dir() follows symlinks, so a naive recursive delete gutted the SHARED storage on all 3 hosts (2026-09-14 incident, 500s + media loss). Both functions now unlink symlinks instead of recursing, and both guard is_link() on the entry path too (rd_link_release_shared passes a symlink to rd_rrmdir). rd_list_releases sorts by the ISO timestamp embedded in the dir NAME, never filemtime — a gutted shell's refreshed mtime would otherwise rank it "newest" and cause keep-3 pruning to delete real releases. Regression tests: test_rrmdir_never_follows_directory_symlinks, test_list_releases_orders_by_embedded_timestamp_not_mtime.
