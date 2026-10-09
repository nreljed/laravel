---
name: design-pagecraft
description: "Routes and enforces the full professional page-craft workflow for the nreljed stack (Cost Breaker 360 + shop/booking). Activate when building, redesigning, or polishing any PUBLIC/marketing page (Welcome, About, Services, Contact, landing pages) or any visually ambitious surface. Ensures direction comes from PRODUCT.md/DESIGN.md, motion uses GSAP correctly with reduced-motion, audits run via impeccable + web-design-guidelines, and pages are visually verified with Playwright before shipping. Also activates on 'make this page look professional', 'redesign', 'landing page', 'hero', 'scroll animation'."
---

# Design Pagecraft — routing skill for the nreljed stack

This skill does not contain design knowledge itself. It ROUTES to the installed
skills in the correct order and enforces nreljed conventions so they compose
without fighting. All referenced skills are installed globally
(`~/.agents/skills/`, `~/.config/opencode/skills/`) AND mirrored in
`.opencode/skills/`.

## Read first (always)

1. `PRODUCT.md` (repo root) — who the product is for, surface modes.
2. `DESIGN.md` (repo root) — the extracted visual system (OKLCH tokens,
   Instrument Sans, radius, motion rules, slop fix-list, pre-ship checklist).

Never invent a new palette/type system for a page inside this product. The
visual direction is already decided; the job is execution + craft.

## The workflow (role assignment — one job per skill)

### 1. Direction (new page or redesign)

- Greenfield page: `frontend-design` (Anthropic) two-pass plan — tokens, type,
  layout, signature element — constrained by DESIGN.md.
- Redesign of an existing page: `redesign-existing-projects` (Taste-Skill) —
  audit-first, preserve functionality.
- `design-taste-frontend` (Taste-Skill v2) is available for explicit
  art-direction pushes on marketing surfaces ONLY (never dashboards) when the
  user asks for a bolder/more distinctive look. Load it explicitly; do not let
  it run alongside impeccable direction commands.

### 2. Build (nreljed conventions — MANDATORY)

- Components: Base UI + shadcn, `render` prop, NEVER `asChild`.
- Toasts: `toast.add({ title, type })`, NEVER sonner.
- Class merge: `cn` from the `cn` package (`import { cn } from "cn"`).
- Imports: there is **no `@/` alias** — inside `resources/js/frontend/**` use
  relative paths; import cross-package via `@nreljed/platform/...` and
  `@nreljed/blog/...`.
- NEVER edit `vendor/**` or `packages/**` mirrors. Public pages are
  project-owned: `resources/js/frontend/pages/public/*.tsx`, registered via
  `pageOverrides` in `resources/js/page-config.ts`. Layout:
  `resources/js/frontend/layouts/PublicLayout`.
- **Marketing pages** read their own `site-*` token layer
  (`resources/css/site.css` + theme scoping) — load the
  `frontend-site-theming` skill for anything public-facing that is themed.
  Dashboard pages keep the shadcn tokens (`bg-background`, …). No raw hex
  in either case.
- All routes via Ziggy `route()`.

### 3. Motion

- Small UI motion (hover, focus, disclosure): CSS transitions, 150–200ms,
  `ease-out` enter / `ease-in` exit. Use `emil-design-eng` + `animate`
  (Emil Kowalski) for easing/duration decisions.
- Marketing/scroll motion (hero reveals, pinned sections, scrubbed
  timelines): GSAP. Load `gsap-react` (useGSAP hook) + `gsap-scrolltrigger`
  + `gsap-timeline` (+ `gsap-core`/`gsap-performance` as needed). Deps are
  installed: `gsap`, `@gsap/react`, `lenis` (Lenis drives scroll; the
  project's `frontend/components/site/motion/` kit wires Lenis → ScrollTrigger).
- Animate only `transform`/`opacity`. `useGSAP` with `scope`/refs for cleanup.
- `prefers-reduced-motion` is MANDATORY: wrap GSAP in `gsap.matchMedia()`
  with a static fallback.
- Review motion diffs with `review-animations` before shipping.

### 4. Audit

- Design/UX critique: impeccable `critique` then `audit` (skill installed;
  invoke commands from the impeccable skill, do NOT install its detector hook).
- Mechanical quality gate (a11y, forms, focus, perf, dark mode): the
  `web-design-guidelines` skill (Vercel) — review the changed files against it.

### 5. Verify (never skip — a page that wasn't looked at isn't done)

- Playwright MCP / `webapp-testing` skill: screenshot the page at 375, 768
  and 1440 widths, in light AND dark. Inspect the screenshots; fix findings
  in one batch.
- Manual detector pass: `npx impeccable detect resources/js/frontend/pages/public/`
  (skill-only mode — no hook, no auto-run).
- Gates before done: `npm run typecheck && npm run lint && npm run build`.

## Anti-patterns to reject on sight (from DESIGN.md + Impeccable)

- Purple/blue gradient hero with centered headline + two buttons.
- Four identical rounded-square icon tiles above headings.
- Cards nested inside cards; everything wrapped in a card.
- Gray text on colored backgrounds; pure black/white without tint.
- Bounce/elastic easing on functional UI; motion without reduced-motion.
- Inter/Arial chosen as a "new" font; lorem ipsum; vague CTAs
  ("Learn more", "Get started" with no specific promise).

## Conflict rule

If two installed design skills give contradictory direction, `DESIGN.md`
wins, then impeccable, then frontend-design. Taste-Skill variants only apply
when explicitly requested for a marketing surface.
