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

function Upload-FileWithRetry {
    param($LocalPath, $RemoteUri, $User, $Pass, $MaxRetries = 3)
    for ($attempt = 1; $attempt -le $MaxRetries; $attempt++) {
        $result = curl.exe --noproxy "*" --user "${User}:${Pass}" --ftp-create-dirs -T $LocalPath --silent --show-error --connect-timeout 15 --max-time 60 $RemoteUri 2>&1
        if ($LASTEXITCODE -eq 0) {
            return $true
        }
        if ($attempt -lt $MaxRetries) {
            $delay = $attempt * 2
            Start-Sleep -Seconds $delay
        }
    }
    return $false
}

Write-Output "Scanning files..."
$files = Get-ChildItem -Path $LocalRoot -Recurse -File | Where-Object { -not (Test-Excluded $_.FullName.Replace($LocalRoot, "").TrimStart("\")) }
$total = $files.Count
$parallel = 3
Write-Output "Uploading $total files to $Server ($parallel parallel, 3 retries per file)..."
$failedFiles = [System.Collections.Concurrent.ConcurrentBag[string]]::new()
$okFiles = [System.Collections.Concurrent.ConcurrentBag[string]]::new()

$files | ForEach-Object -Parallel {
    $f = $_
    $srv = $using:Server
    $usr = $using:Username
    $pwd = $using:Password
    $root = $using:RemoteRoot
    $local = $using:LocalRoot
    $failedBag = $using:failedFiles
    $okBag = $using:okFiles

    $rel = $f.FullName.Replace($local, "").TrimStart("\").Replace("\", "/")
    $remoteUri = if ($root -eq "" -or $root -eq ".") { "ftp://$srv/$rel" } else { "ftp://$srv/$root/$rel" }

    $success = Upload-FileWithRetry -LocalPath $f.FullName -RemoteUri $remoteUri -User $usr -Pass $pwd
    if ($success) {
        $okBag.Add($rel)
        Write-Output "OK: $rel"
    } else {
        $failedBag.Add($rel)
        Write-Output "FAIL: $rel"
    }
} -ThrottleLimit $parallel

$failures = $failedFiles.Count
$uploaded = $okFiles.Count
if ($failures -gt 0) {
    Write-Output "=== Failed files ($failures of $total) ==="
    $failedFiles | ForEach-Object { Write-Output "  $_" }
    throw "$failures of $total files failed to upload (even after 3 retries)"
}
Write-Output "Deploy complete! $total files uploaded successfully."
