# DESIGN — Cost Breaker 360 visual system

Extracted from what actually ships (`packages/platform/resources/css/core.css`,
shadcn base-vega tokens). Design skills: read this BEFORE any aesthetic
direction; preserve these decisions unless the task is an explicit redesign.

## Color (OKLCH, from core.css)

Light mode:

| Token | Value | Use |
| --- | --- | --- |
| `--background` | `oklch(1 0 0)` | page bg |
| `--foreground` | `oklch(0.145 0 0)` | body text (tinted near-black, never #000) |
| `--primary` | `oklch(0.205 0 0)` | primary buttons (near-black ink) |
| `--muted-foreground` | `oklch(0.556 0 0)` | secondary text |
| `--border` | `oklch(0.922 0 0)` | hairline borders |
| `--destructive` | `oklch(0.577 0.245 27.325)` | destructive actions only |

Accents available (use sparingly, one per surface):
`--accent-purple oklch(0.558 0.288 302.321)`,
`--accent-cyan oklch(0.715 0.143 215.221)`,
`--accent-rose oklch(0.645 0.246 16.439)`,
`--accent-pink oklch(0.656 0.241 354.308)`,
status colors `--status-success / --status-error / --status-info / --warning`.

Dark mode: full token set under `.dark` in core.css — always verify parity,
never hardcode light values.

**Rules**

- Always use token utilities (`bg-background`, `text-foreground`,
  `text-muted-foreground`, `border-border`) — never raw hex/rgb.
- No purple-to-blue gradients on heroes (the #1 AI tell). If a gradient is
  used at all, it is subtle, token-derived, and justified.
- Status colors are semantic only — never decorative.

## Typography

- Family: **Instrument Sans** (`--font-sans`), heading = same family
  (`--font-heading`). Utility wrapper: `.cn-font-heading` / `font-heading`.
- Scale is Tailwind default ramp tuned by tokens: display 4xl→6xl bold
  tracking-tight; body 16–18px; meta 12–14px.
- Personality comes from **weight + tracking + size contrast**, not a second
  decorative font (platform-wide constraint: one family).
- Numbers in tables/stats: `tabular-nums`.
- Measure: keep prose ≤ ~65ch.

**Banned defaults (AI tells):** Inter as a *new* choice, Arial, system-font
headlines with no tracking treatment, gray-on-colored-background text.

## Shape & space

- Radius token: `--radius: 0.625rem` (10px). Cards 10–16px; pills `rounded-full`.
- Spacing: Tailwind scale; section padding on public pages `py-16`→`py-28`,
  container `max-w-7xl px-4 sm:px-6 lg:px-8`.
- Borders are hairline (`border-border`); prefer `border` + subtle
  `bg-card` over heavy shadows. Shadows only for genuinely elevated layers
  (popovers, dialogs) via the kit.

## Components

- Kit: Base UI + shadcn under `@nreljed/platform/Components/ui/*`.
- Composition: `render` prop (NEVER `asChild`).
- Toasts: `toast.add({ title, type })` — never sonner.
- Icons: `lucide-react`, sized `h-4 w-4`/`h-5 w-5`, inherit `currentColor`.

## Motion

- Default UI motion: CSS transitions 150–200ms, `ease-out` for enters,
  `ease-in` for exits. Never bounce/elastic on functional UI.
- Marketing/scroll motion: **GSAP** (`gsap`, `@gsap/react` `useGSAP`) —
  ScrollTrigger for pinned/scrubbed sections, timelines for hero sequencing.
  Animate only `transform`/`opacity` (gsap-performance).
- `prefers-reduced-motion`: degrade to static (gsap.matchMedia).
- One orchestrated load moment per page > scattered micro-interactions.

## Public page architecture (project-owned)

Pages live in `resources/js/Pages/Public/`, layout `@nreljed/platform/Layouts/PublicLayout`,
registered via `pageOverrides` in `resources/js/page-config.ts`.

Current inventory: `Welcome`, `About`, `Services`, `Contact`.

## Known slop in the current public pages (fix list for redesign passes)

- Welcome hero is the default centered-headline + two-buttons template.
- Feature grid is four identical rounded-square icon tiles above headings
  (Impeccable "icon tile stack" tell).
- Stats row is three evenly spaced big numbers with equal weight.
- No signature element — nothing memorable anchors the page.

## Pre-ship checklist (every public page)

1. Token-only colors; dark-mode parity verified.
2. One primary CTA per viewport; no competing hierarchy.
3. Type hierarchy: one dominant element per section.
4. No icon-tile-above-heading stacks; no cards-inside-cards.
5. GSAP motion respects reduced-motion; no bounce easing.
6. Playwright screenshots at 375 / 768 / 1440 reviewed.
7. `npx impeccable detect` on the page → 0 findings (or justified).
8. `npm run typecheck && npm run lint && npm run build` green.
