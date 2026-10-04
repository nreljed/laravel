---
description: Diagnose frontend failures - blank page, ERR_CONNECTION_REFUSED, missing component, broken theming, build output
agent: build
---

# Debug the frontend

Symptom-first triage for the React/Inertia/Vite/Tailwind stack. Find the layer first;
most "frontend is broken" reports are one of the first three.

## Triage by symptom

| Symptom | Layer | Go to |
| --- | --- | --- |
| `ERR_CONNECTION_REFUSED` | dev server | §1 |
| Blank page, no console error | page resolution | §2 |
| `Inertia page not found` | page resolution | §2 |
| Component renders but unstyled | CSS / build | §3 |
| Text invisible in one theme | theming | §4 |
| Stale UI after a change | build output | §5 |

## 1. `ERR_CONNECTION_REFUSED`

`public/hot` must **not** exist. Its presence makes the app look for a Vite dev
server that is not running - this site is served from the production build.

```bash
if (Test-Path public/hot) { Remove-Item public/hot; "removed stale hot file" } else { "public/hot absent (correct)" }
```

## 2. Blank page / `Inertia page not found`

Inertia resolves pages by **lowercased** component name. The controller renders the
name; `resources/js/page-config.ts` must map it.

- `Inertia::render('dashboard/index')` -> a `dashboard/index` entry in the page map
- `Inertia::render('blog/index')` -> `Blog/Public/Index.tsx`
- `Inertia::render('public/about')` -> the project's `./Pages/Public/About.tsx`

`resources/js/app.tsx` imports `pageConfig` and spreads
`registerCoreTemplates()` plus `projectPages` / `pageOverrides`. CORE never
auto-registers; a page must be opted into explicitly. Never duplicate that config
block in the entry.

Check the browser console first - the unresolved name is usually printed there.

## 3. Unstyled / missing CSS

```bash
npm run build
```

If styles are missing after a successful build, the source is not being imported.
The project CSS must import core's tokens:

```css
@import "core.css";
```

followed by any `@layer base { --token: ... }` overrides.

## 4. Theming / invisible text

Dark mode is owned by `next-themes` `<ThemeProvider>`. The recurring bug is a text
colour that only resolves in one theme. Use semantic tokens - `text-foreground`,
`text-muted-foreground`, `bg-background`, `bg-card` - never raw palette classes like
`text-gray-900`. Flip the scheme in devtools to confirm both themes before
reporting done.

## 5. Stale UI

```bash
npm run build          # required after ANY frontend source change
```

Then hard-reload. LocalWP serves the built bundle, so an unbuilt change is invisible
by design. Do not leave `public/hot` behind after debugging.

## 6. Type and lint failures

```bash
npm run typecheck
npm run typecheck:packages
npm run lint
```

A type error inside `packages/*` is still a finding - that tree is a mirror, so the
fix belongs upstream. Report it as upstream work rather than editing the mirror.

## 7. Escalation

If the stack shows `Inertia page not found` **and** the page-map entry exists, check
whether the controller is rendering a name the app never registered, and confirm the
route actually resolves (Ziggy `route()` names, not hardcoded URLs).

## Report

The failing layer, the evidence (console message, build output, file:line), the fix,
and the actual output of any command you ran. Do not claim a fix works without
re-running the build.