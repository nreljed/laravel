$p = Join-Path $env:USERPROFILE 'AppData\Local\ms-playwright'
if (Test-Path $p) {
    Get-ChildItem $p | ForEach-Object {
        $size = (Get-ChildItem $_.FullName -Recurse -File -ErrorAction SilentlyContinue | Measure-Object -Property Length -Sum).Sum
        $sizeMB = [math]::Round($size / 1MB, 1)
        Write-Host "$($_.Name): $sizeMB MB"
    }
} else {
    Write-Host "No ms-playwright cache found"
}
