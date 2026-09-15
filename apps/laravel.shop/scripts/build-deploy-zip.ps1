<#
.SYNOPSIS
  Builds deploy.zip identical to the CI pipeline (GitHub Actions).
.DESCRIPTION
  Requirements:
    - .env.production file in project root with real secrets
  Steps (matching .github/workflows/deploy.yml):
    1. npm run build (frontend assets)
    2. composer install --no-dev --optimize-autoloader
    3. Copy deploy.php from scripts/deploy-cron.php
    4. Generate .env from .env.production
    5. Exclude bootstrap/cache (preserves server cache)
    6. Create dist/deploy.zip with exact CI file list
    7. Restore local dev state
.PARAMETER Force
  Skip confirmation prompt
.PARAMETER SkipNpmBuild
  Skip npm run build (if you already ran it)
.EXAMPLE
  npm run build:deploy
  .\scripts\build-deploy-zip.ps1 -Force
  .\scripts\build-deploy-zip.ps1 -Force -SkipNpmBuild
#>

param(
    [switch]$Force,
    [switch]$SkipNpmBuild
)

$ErrorActionPreference = 'Continue'
$root = Split-Path -Parent $PSScriptRoot
$dist = Join-Path $root "dist"
$zip = Join-Path $dist "deploy.zip"
$envProduction = Join-Path $root ".env.production"

# ---- Validate .env.production ----
if (-not (Test-Path $envProduction)) {
    Write-Error ".env.production not found at $envProduction"
    Write-Error "Create it from the template:"
    Write-Error "  Copy-Item .env.production.example .env.production"
    Write-Error "Then fill in real secrets from your server .env backup."
    exit 1
}

$envContent = Get-Content $envProduction -Raw
$missing = @()
foreach ($line in ($envContent -split "`n")) {
    $line = $line.Trim()
    if ($line -match '^#') { continue }
    if ($line -match '^([A-Z_]+)=\s*$') {
        $missing += $matches[1]
    }
}
if ($missing.Count -gt 0) {
    Write-Error ".env.production has empty required values:"
    $missing | ForEach-Object { Write-Error "  $_ = (empty)" }
    Write-Error "Fill them in from your server .env backup, then re-run."
    Write-Error "Run this to see the server values:"
    Write-Error "  Visit https://shop.laravel.tunispromo.tn/debug.php?key=health"
    exit 1
}

# ---- Confirmation ----
if (-not $Force) {
    $answer = Read-Host "Build deploy.zip for production? This modifies vendor/ and .env temporarily. [Y/n]"
    if ($answer -eq 'n') { Write-Output "Aborted."; exit }
}


Write-Output "========================================"
Write-Output "  Building deploy.zip (CI-equivalent)"
Write-Output "========================================"

# ---- Step 1: Frontend build ----
if (-not $SkipNpmBuild) {
    Write-Output "[1/5] Building frontend assets (npm run build)..."
    cmd /c "cd /d `"$root`" && npm run build" 2>&1 | Out-Host
    if ($LASTEXITCODE -ne 0) { Write-Error "npm run build failed (exit $LASTEXITCODE)"; exit 1 }
    Write-Output "   Done"
} else {
    Write-Output "[1/5] Skipping npm run build (--SkipNpmBuild)"
}

# ---- Step 2: Strip dev deps ----
Write-Output "[2/5] Optimizing vendor (composer install --no-dev)..."
$composerPhar = Join-Path $root "composer.phar"
if (Test-Path $composerPhar) {
    cmd /c "cd /d `"$root`" && php `"$composerPhar`" install --no-dev --optimize-autoloader --no-scripts --prefer-dist" 2>&1 | Out-Host
} else {
    cmd /c "cd /d `"$root`" && composer install --no-dev --optimize-autoloader --no-scripts --prefer-dist" 2>&1 | Out-Host
}
if ($LASTEXITCODE -ne 0) { Write-Error "composer install --no-dev failed (exit $LASTEXITCODE)"; exit 1 }
Write-Output "   Done"

# ---- Step 3: Backup dev .env, apply production .env ----
Write-Output "[3/5] Applying production .env..."
$envBackup = Join-Path $root ".env.buildbak"
Copy-Item (Join-Path $root ".env") $envBackup -Force
Copy-Item $envProduction (Join-Path $root ".env") -Force
Write-Output "   Using: .env.production"

# Copy deploy.php
Copy-Item (Join-Path $root "scripts\deploy-cron.php") (Join-Path $root "deploy.php") -Force
Write-Output "   deploy.php copied"

# ---- Step 4: Create zip (exclude bootstrap/cache) ----
Write-Output "[4/5] Creating deploy.zip..."
Remove-Item $zip -Force -ErrorAction SilentlyContinue

# Move bootstrap/cache out (same as CI)
$cacheDir = Join-Path $root "bootstrap\cache"
$cacheBak = Join-Path $root "bootstrap\cache.bak"
if (Test-Path $cacheBak) { Remove-Item $cacheBak -Recurse -Force -ErrorAction SilentlyContinue }
if (Test-Path $cacheDir) { Move-Item $cacheDir $cacheBak -Force }
# Ensure empty cache dir exists in zip for fresh deployments
New-Item -ItemType Directory -Force -Path $cacheDir | Out-Null
Set-Content -Path (Join-Path $cacheDir ".gitkeep") -Value "" -NoNewLine

# Ensure CHANGELOG.md exists
if (-not (Test-Path (Join-Path $root "CHANGELOG.md"))) {
    "# Changelog`n`nBuild: $(Get-Date -Format 'yyyy-MM-dd HH:mm')" | Set-Content (Join-Path $root "CHANGELOG.md") -Encoding UTF8
}

$items = @(
    "artisan", "composer.json", "composer.lock", "package.json", "package-lock.json",
    "app", "bootstrap", "config", "database", "public", "resources", "routes",
    "storage", "vendor", ".env", "deploy.php", "CHANGELOG.md", ".htaccess"
)
$fullPaths = $items | ForEach-Object { Join-Path $root $_ }

$missing = $fullPaths | Where-Object { -not (Test-Path $_) }
if ($missing) { Write-Warning "Missing from filesystem: $($missing -join ', ')" }

Compress-Archive -Path $fullPaths -DestinationPath $zip -CompressionLevel Optimal

# Remove the .gitkeep we added for the zip
Remove-Item (Join-Path $cacheDir ".gitkeep") -Force -ErrorAction SilentlyContinue
# Restore original bootstrap/cache
if (Test-Path $cacheBak) {
    if (Test-Path $cacheDir) { Remove-Item $cacheDir -Recurse -Force -ErrorAction SilentlyContinue }
    Move-Item $cacheBak $cacheDir -Force
}

$fileSize = [Math]::Round((Get-Item $zip).Length / 1MB, 1)
Write-Output "   Created: $fileSize MB"

# ---- Verify zip contents ----
Write-Output "[Extra] Verifying zip contents..."
Add-Type -AssemblyName System.IO.Compression.FileSystem
$archive = [System.IO.Compression.ZipFile]::OpenRead($zip)

$expectedTop = @("artisan", "composer.json", "composer.lock", "package.json", "package-lock.json",
                 "app", "bootstrap", "config", "database", "public", "resources", "routes",
                 "storage", "vendor", ".env", "deploy.php", "CHANGELOG.md", ".htaccess")
$actualTop = $archive.Entries | ForEach-Object { $_.FullName.Split('\')[0] } | Sort-Object -Unique
$topMissing = $expectedTop | Where-Object { $_ -notin $actualTop }
if ($topMissing) { Write-Warning "Missing top-level entries: $($topMissing -join ', ')" }

$critical = @(
    "vendor\autoload.php",
    "vendor\symfony\polyfill-mbstring\bootstrap.php",
    "vendor\symfony\deprecation-contracts\function.php",
    "vendor\composer\autoload_real.php",
    "public\index.php",
    "public\build\manifest.json",
    "public\debug.php",
    "public\.htaccess",
    "bootstrap\cache\.gitkeep",
    "artisan",
    ".env",
    "deploy.php"
)
$allFound = $true
foreach ($file in $critical) {
    $found = ($archive.Entries.FullName -contains $file)
    if (-not $found) { Write-Warning "   MISSING: $file"; $allFound = $false }
}
$totalFiles = $archive.Entries.Count
$archive.Dispose()

# ---- Step 5: Restore local dev state ----
Write-Output "[5/5] Restoring local dev state..."
if (Test-Path $envBackup) {
    Remove-Item (Join-Path $root ".env") -Force
    Move-Item $envBackup (Join-Path $root ".env") -Force
}
Remove-Item (Join-Path $root "deploy.php") -Force -ErrorAction SilentlyContinue
Remove-Item (Join-Path $root "CHANGELOG.md") -Force -ErrorAction SilentlyContinue

# ---- Summary ----
Write-Output ""
Write-Output "========================================"
Write-Output "  Build Complete"
Write-Output "========================================"
Write-Output "  File:     dist\deploy.zip"
Write-Output "  Size:     $fileSize MB"
Write-Output "  Files:    $totalFiles"
Write-Output "  Verify:   $(if($allFound){'ALL CRITICAL FILES PRESENT'}else{'SOME MISSING!'})"
Write-Output "  .env:     from .env.production"
Write-Output "  Frontend: $(if(-not $SkipNpmBuild){'Rebuilt'}else{'Skipped (--SkipNpmBuild)'})"
Write-Output "  Vendor:   Production-optimized (--no-dev)"
Write-Output ""
Write-Output "  To deploy:"
Write-Output "    1. Backup .env on server"
Write-Output "    2. Upload dist\deploy.zip to public_html/"
Write-Output "    3. Extract (overwrites all)"
Write-Output "    4. Restore server .env from backup"
Write-Output "    5. Visit https://shop.laravel.tunispromo.tn/debug.php?key=health"
Write-Output "    6. Visit https://shop.laravel.tunispromo.tn/deploy/cache-clear?token=YOUR_TOKEN"
Write-Output "========================================"
