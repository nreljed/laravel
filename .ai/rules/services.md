---
paths:
  - vendor/nreljed/platform/src/Services/SystemHealthService.php
---

# Services

## Log reader is basename-restricted
SystemHealthService::readLog() only serves files matching its own basename (rejects any path separators) and requires a `.log` suffix, resolved under storage/logs. Keep that guard on any future log-read endpoint — it is the only defense against path traversal; the router may normalize `../` to 404 itself, so rely on this check, not the HTTP layer.
