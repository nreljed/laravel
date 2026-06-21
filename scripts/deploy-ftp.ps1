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

function Test-Excluded($relativePath) {
    $relativePath = $relativePath.Replace("\", "/")
    foreach ($pattern in $Exclude) {
        $p = $pattern.Trim()
        if ($p -like "*\**") {
            $regex = "^" + [regex]::Escape($p.TrimEnd('/')).Replace("\*\*", ".*").Replace("\*", "[^/]*") + "(/.*)?$"
            if ($relativePath -match $regex) { return $true }
        } else {
            if ($relativePath -eq $p) { return $true }
        }
    }
    return $false
}

$files = Get-ChildItem -Path $LocalRoot -Recurse -File | Where-Object { -not (Test-Excluded $_.FullName.Replace($LocalRoot, "").TrimStart("\")) }
$total = $files.Count
$count = 0
$failures = 0

foreach ($file in $files) {
    $count++
    $rel = $file.FullName.Replace($LocalRoot, "").TrimStart("\").Replace("\", "/")
    $remotePath = "$RemoteRoot/$rel"
    $remoteUri = "ftp://$Server/$remotePath"
    $pct = [math]::Round($count / $total * 100, 0)
    Write-Progress -Activity "Deploying to FTP" -Status "$pct% ($count/$total)" -PercentComplete $pct -CurrentOperation $rel

    $result = curl.exe --noproxy "*" --user "$Username`:$Password" --ftp-create-dirs -T $file.FullName --silent --show-error "$remoteUri" 2>&1
    if ($LASTEXITCODE -ne 0) {
        Write-Warning "FAILED: $rel - $result"
        $failures++
    } else {
        Write-Output "[$count/$total] $rel"
    }
}

if ($failures -gt 0) {
    throw "$failures of $total files failed to upload"
}
Write-Output "Deploy complete! $total files uploaded successfully."