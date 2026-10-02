
### Bug Fixes

- Recover.php bootstraps without autoloader, deploy.zip uses --prefer-dist + 7z
- recover.php: try require Application.php directly when classmap is broken,
  fall back to manual Application construction when bootstrap/app.php fails
- recover.php: add fix-autoloader cmd that downloads composer and regenerates
  vendor autoloader from scratch
- deploy-package.yml: --prefer-source → --prefer-dist (private VCS packages
  auto-fallback to source) — prevents bloated git clones in vendor/
- deploy-package.yml: Compress-Archive → 7z (fixes Windows 260-char path
  truncation that corrupted vendor in deploy.zip)


- Bust vendor cache for --prefer-dist, accept 7z warning exit code
- Cache key changed from composer- to composer-dist- to force fresh --prefer-dist vendor
- 7z exit code 1 = warning (permission on test fixture), not failure; only >1 is error



