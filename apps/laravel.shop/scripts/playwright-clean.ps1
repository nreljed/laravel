param(
    [ValidateSet('output', 'pngs', 'profiles', 'npx', 'all')]
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
    'pngs' {
        $dirs = @("$PSScriptRoot\..\storage\debug\playwright-output", "$env:TEMP\opencode\playwright\output")
        foreach ($d in $dirs) {
            if (Test-Path $d) {
                Get-ChildItem $d -Filter *.png -File -ErrorAction SilentlyContinue | Remove-Item -Force
                Write-Host "Removed PNG screenshots from $d"
            }
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
        & $PSCommandPath -Target 'pngs'
        & $PSCommandPath -Target 'profiles'
        & $PSCommandPath -Target 'npx'
    }
}
