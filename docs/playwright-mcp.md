# Playwright MCP

Playwright MCP is configured in `~/.config/opencode/opencode.jsonc` (NOT a separate
`playwright-mcp.json`).

- **User data dir**: `C:\Users\nrelj\AppData\Local\Temp\opencode\playwright\profile-crm-dashboard`
  — persistent browser profile (cookies, cache, localStorage survive restarts via
  `--user-data-dir` + `--save-session`)
- **Output dir**: `C:\Users\nrelj\AppData\Local\Temp\opencode\playwright\output` —
  screenshots, logs, session state files
- **Session persistence**: `--save-session` saves cookies + localStorage on MCP server
  shutdown (opencode exit or reconnect). The `--user-data-dir` also persists browser profile
  data across sessions. Sessions survive opencode restarts automatically — **do not delete
  the output dir unless you want a fresh session**.
- **Dark mode**: Set via `browser_evaluate` (`localStorage.setItem("theme", "dark")`) before
  navigating. The `colorScheme: "dark"` context option is unreliable on Windows persistent
  contexts. Theme persists across sessions automatically via localStorage key `"theme"`
  (saved by `--save-session`).

## Session-Cleanup (only when explicitly requested)

When you need a completely fresh browser (new login, reset state), run:

```powershell
Remove-Item -LiteralPath "C:\Users\nrelj\AppData\Local\Temp\opencode\playwright" -Recurse -Force -ErrorAction SilentlyContinue; $null = New-Item -ItemType Directory -Path "C:\Users\nrelj\AppData\Local\Temp\opencode\playwright\profile-crm-dashboard" -Force; $null = New-Item -ItemType Directory -Path "C:\Users\nrelj\AppData\Local\Temp\opencode\playwright\output" -Force
```

**Do NOT run this for normal use** — it destroys all saved sessions and you'll have to re-login.

## Rules

- Never save screenshots or logs to the project root — always use `outputDir`
- `.gitignore` has `/*.png` as safety net, but output should never land in root
- `--no-sandbox`: Not used in our config. Chromium on Windows works fine with sandbox. If you
  see this message, it's from a previous config or environment variable
  (`PLAYWRIGHT_MCP_NO_SANDBOX`).
