param(
    [string]$Server,
    [string]$Username,
    [string]$Password,
    [string]$RemoteRoot,
    [string]$LocalRoot = ".",
    [string[]]$Exclude = @()
)

$ErrorActionPreference = "Stop"
$LocalRoot = (Get-Item $LocalRoot).FullName
$RemoteRoot = $RemoteRoot.TrimEnd('/')
$parallel = 5

if ($PSVersionTable.PSVersion.Major -lt 7) {
    Write-Output "ERROR: PowerShell 7+ required for -Parallel switch. Install pwsh or upgrade."
    exit 1
}

function Test-Excluded($relativePath) {
    $relativePath = $relativePath.Replace("\", "/")
    foreach ($pattern in $Exclude) {
        $p = $pattern.Trim()
        if ($p.Contains("*")) {
            $regex = "^" + [regex]::Escape($p.TrimEnd('/')).Replace("\*\*", ".*").Replace("\*", "[^/]*") + "(/.*)?$"
            if ($relativePath -match $regex) { return $true }
        } else {
            if ($relativePath -eq $p) { return $true }
        }
    }
    return $false
}

Write-Output "Scanning files..."
$files = Get-ChildItem -Path $LocalRoot -Recurse -File | Where-Object { -not (Test-Excluded $_.FullName.Replace($LocalRoot, "").TrimStart("\")) }
$total = $files.Count
Write-Output "Uploading $total files to $Server ($parallel parallel)..."
$failures = 0
$failedFiles = [System.Collections.Concurrent.ConcurrentBag[string]]::new()

$files | ForEach-Object -Parallel {
    $f = $_
    $srv = $using:Server
    $usr = $using:Username
    $pwd = $using:Password
    $root = $using:RemoteRoot
    $local = $using:LocalRoot
    $total = $using:total
    $bag = $using:failedFiles

    $rel = $f.FullName.Replace($local, "").TrimStart("\").Replace("\", "/")
    $remoteUri = "ftp://$srv/$root/$rel"
    $result = curl.exe --noproxy "*" --user "${usr}:${pwd}" --ftp-create-dirs -T $f.FullName --silent --show-error $remoteUri 2>&1
    if ($LASTEXITCODE -ne 0) {
        $bag.Add($rel)
        Write-Output "FAIL: $rel"
    } else {
        Write-Output "OK: $rel"
    }
} -ThrottleLimit $parallel

$failures = $failedFiles.Count
if ($failures -gt 0) {
    Write-Output "=== Failed files ($failures of $total) ==="
    $failedFiles | ForEach-Object { Write-Output "  $_" }
    throw "$failures of $total files failed to upload"
}
Write-Output "Deploy complete! $total files uploaded successfully."
