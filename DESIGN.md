# DESIGN — Cost Breaker 360 visual system

Extracted from what actually ships (`packages/platform/resources/css/core.css`,
shadcn base-vega tokens). Design skills: read this BEFORE any aesthetic
direction; preserve these decisions unless the task is an explicit redesign.

## Color (OKLCH, from core.css)

Light mode:

| Token                | Value                       | Use                                       |
| -------------------- | --------------------------- | ----------------------------------------- |
| `--background`       | `oklch(1 0 0)`              | page bg                                   |
| `--foreground`       | `oklch(0.145 0 0)`          | body text (tinted near-black, never #000) |
| `--primary`          | `oklch(0.205 0 0)`          | primary buttons (near-black ink)          |
| `--muted-foreground` | `oklch(0.556 0 0)`          | secondary text                            |
| `--border`           | `oklch(0.922 0 0)`          | hairline borders                          |
| `--destructive`      | `oklch(0.577 0.245 27.325)` | destructive actions only                  |

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

**Banned defaults (AI tells):** Inter as a _new_ choice, Arial, system-font
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

## Public site system (marketing surfaces)

The public site is a **separate token layer** from the dashboard. Dashboard
surfaces read shadcn OKLCH tokens from `core.css`; public/marketing surfaces read
the semantic `--site-*` tokens from `resources/css/site.css`, scoped by
`SiteRoot` (`data-site-theme="<slug>"`). Never mix the two layers on one element.

- Tokens (semantic, never raw hex in components): `--site-bg`, `--site-bg-strong`,
  `--site-surface`, `--site-ink`, `--site-ink-soft`, `--site-ink-faint`,
  `--site-accent`, `--site-accent-ink`, `--site-secondary`, `--site-alt-1`,
  `--site-alt-2`, `--site-pink`, `--site-border`, `--site-outline`, plus
  `--radius-card` / `--radius-pill` and the three font-family slots. Exposed as
  Tailwind utilities (`bg-site-surface`, `text-site-ink-soft`, …).
- Themes are isolated modules under `resources/css/themes/<slug>.css`, each
  stating its own light **and** dark palette under `[data-site-theme="<slug>"]`
  (with a `:where(html.dark)` variant). `site.css` imports them and holds the
  neutral fallback. Swap a theme by passing `theme="<slug>"` to `SiteRoot`, or
  live from the style guide.
- Type scale: `site-hero` / `site-display` / `site-title` / `site-title-sm` /
  `site-heading` / `site-heading-sm` / `site-heading-xs` / `site-lead` (fluid
  clamps). Each SEO heading level maps to **exactly one** size token — pick by
  level, never a bespoke size. One `h1` per page.

  | Level | Token           | Fluid (mobile → desktop) |
  | ----- | --------------- | ------------------------ |
  | `h1`  | `site-display`  | 44 → 90px                |
  | `h2`  | `site-title`    | 36 → 72px                |
  | `h3`  | `site-title-sm` | 30 → 48px                |
  | `h4`  | `site-heading`  | 24 → 36px                |
  | `h5`  | `site-heading-sm` | 20 → 26px              |
  | `h6`  | `site-heading-xs` | 17 → 20px              |

  Two tokens sit outside the level map: `site-hero` is the display **crown**,
  reserved for the landing `h1` (Home); `site-lead` is the deck paragraph that
  follows a heading. Eyebrow labels (`text-xs … uppercase tracking-[0.2em]`)
  and numeric displays are **not** headings and take no level token.

### CB360 "Ledger" identity (default public theme)

The original Cost Breaker 360 brand — `resources/css/themes/cb360.css`.
Warm-paper ground with a single confident signal-green accent: calm,
instrument-grade, no hype, no gradient tells.

| Role                       | Light                | Dark                    |
| -------------------------- | -------------------- | ----------------------- |
| ground (`--site-bg`)       | `#f4f1ea` paper      | `#141512`               |
| surface (`--site-surface`) | `#ffffff`            | `#1c1e18`               |
| ink (`--site-ink`)         | `#191a17`            | `#f2efe6`               |
| accent (`--site-accent`)   | `#0e7a4b` green      | `#35c97f`               |
| sand (`--site-alt-1`)      | `#e7e1d3`            | `#23261f`               |
| forest (`--site-alt-2`)    | `#1f2a24`            | `#2e3a33`               |
| border                     | `rgba(25,26,23,.14)` | `rgba(242,239,230,.14)` |

- **Typography:** Instrument Sans only; personality from weight + tracking + size
  contrast. Numeric voice is `tabular-nums`.
- **Layout language:** asymmetric two-column heroes
  (`lg:grid-cols-[1.05fr_0.95fr]`), hairline grids (`grid gap-px` over
  `bg-site-border`, cells `bg-site-surface`), numbered items (`01`/`02` in
  `font-site-display text-site-accent`) and accent-dot eyebrow labels
  (`text-xs font-bold tracking-[0.2em] uppercase` with an
  `h-1.5 w-1.5 rounded-full bg-site-accent` dot).
- **Signature element:** the cost ledger panel (the Home hero) — spend rows with
  hairline bars, deltas and a tabular total. Reuse this motif for numeric
  storytelling; it is the brand's one memorable anchor.
- **Motion:** GSAP `useReveal` from the site motion kit — per-element scroll
  reveals (`data-reveal`) plus one `group` load intro for hero items
  (`data-hero`); `prefers-reduced-motion` degrades to static.
- **Single theme:** CB360 "Ledger" (`themes/cb360.css`) is the only shipped
  palette; `SiteRoot` defaults to it. `themes/` stays the extension point for
  forks that want another palette.

## Public page architecture (project-owned)

Everything the frontend team owns lives under `resources/js/frontend/`
(`pages/`, `layouts/`, `components/site/`) — separated from the Laravel backend
(`app/`, `routes/`). Public pages live in `resources/js/frontend/pages/public/`,
layout `resources/js/frontend/layouts/PublicLayout`, registered via
`pageOverrides` in `resources/js/page-config.ts`.

Current inventory: `Home` (page key `public/welcome`), `About`, `Services`,
`Contact`, `StyleGuide`, plus the blog public templates `Index` / `Show`.

## Redesign status (public pages)

Phases 3–5 shipped the CB360 "Ledger" identity across every public template:
`Home`, `About`, `Services`, `Contact` (project-owned) and the blog public
templates `Index` / `Show`. Every one uses the site-token layer (never raw hex),
one `h1`, a single primary CTA per viewport, and GSAP reveals that respect
`prefers-reduced-motion`.

Remaining backlog:

- `StyleGuide` — token reference for the CB360 "Ledger" palette; re-check the
  specimen after any token change.
- Optional: a featured-post treatment on the blog index (first post wider).

## Pre-ship checklist (every public page)

1. Token-only colors; dark-mode parity verified.
2. One primary CTA per viewport; no competing hierarchy.
3. Type hierarchy: one dominant element per section.
4. No icon-tile-above-heading stacks; no cards-inside-cards.
5. GSAP motion respects reduced-motion; no bounce easing.
6. Playwright screenshots at 375 / 768 / 1440 reviewed.
7. `npx impeccable detect` on the page → 0 findings (or justified).
8. `npm run typecheck && npm run lint && npm run build` green.
