param(
    [ValidateSet('output', 'profiles', 'npx', 'all')]
    [string]$Target = 'all'
)

switch ($Target) {
    'output' {
        $dir = "$PSScriptRoot\..\storage\debug\playwright-output"
        if (Test-Path $dir) {
            Get-ChildItem $dir -Exclude '.gitkeep' | Remove-Item -Recurse -Force
            Write-Host "Cleaned Playwright output files"
        }
    }
    'profiles' {
        $p = Join-Path $env:USERPROFILE 'AppData\Local\ms-playwright'
        if (Test-Path $p) {
            Get-ChildItem $p -Directory -Filter 'mcp-*' | Remove-Item -Recurse -Force
            Write-Host "Cleaned Playwright MCP profiles"
        }
    }
    'npx' {
        $cache = Join-Path $env:LOCALAPPDATA 'npm-cache\_npx'
        if (Test-Path $cache) {
            Remove-Item $cache -Recurse -Force -ErrorAction SilentlyContinue
            Write-Host "Cleaned npx cache"
        }
    }
    'all' {
        & $PSCommandPath -Target 'output'
        & $PSCommandPath -Target 'profiles'
        & $PSCommandPath -Target 'npx'
    }
}
