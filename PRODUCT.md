# PRODUCT — Cost Breaker 360

Durable product truth. Read before any design work. Do not confuse with surface
visual direction (that lives in DESIGN.md).

## Product

Cost Breaker 360 — a SaaS cost-management platform. Laravel 13 + Inertia v3 +
React 19. Server-rendered SPA, self-hosted.

## Users

- Finance/ops leads and founders of small-to-mid teams who need to know where
  money goes across teams, projects and vendors.
- They are analytical, time-poor, and skeptical of "another dashboard". They
  trust numbers, clear hierarchy and calm interfaces — not hype.
- Secondary: admin operators of the platform itself (dashboard/admin surfaces).

## Product purpose (by surface)

| Surface                                      | Job                                       | Success looks like                                    |
| -------------------------------------------- | ----------------------------------------- | ----------------------------------------------------- |
| Public pages (`/`, about, services, contact) | **Persuade** — earn a signup or a login   | Visitor understands the value in < 10s and hits a CTA |
| Dashboard (`/dashboard`)                     | **Operate** — act on data fast            | User finds a number or performs a task in < 3 clicks  |
| Admin (`/dashboard/admin`)                   | **Operate** — manage users/options safely | Dense tables, clear states, no ambiguity              |
| Blog (`/dashboard/posts`, public blog)       | **Read** — long-form comprehension        | Comfortable measure, strong headings, calm prose      |

## Voice

Direct, confident, slightly sharp. "Break every cost." Not "Unlock your
potential today". No vague superlatives, no exclamation marks, no emoji in UI.

## Brand

The public identity is **Cost Breaker 360 — "Ledger"**: warm paper, near-black
ink, one signal-green accent; instrument-grade and calm. The refrain is "Break
every cost." Palette, tokens and layout language live in DESIGN.md (§ Public
site system) — that is the visual source of truth; this file stays product/voice.

## Accessibility & inclusion

- Dark mode is first-class (class-based `.dark`, `next-themes`) — every public
  surface must have full contrast parity in both modes.
- All animation must respect `prefers-reduced-motion`.
- Keyboard operability on every interactive element; visible focus.
- Minimum body text 16px; touch targets ≥ 40px on mobile.

## Constraints

- Brand tokens live in `packages/platform/resources/css/core.css` (shadcn
  base-vega, OKLCH). Public theme may override CSS variables under
  `@layer base` in `resources/css/app.css` — never edit core.css itself.
- UI kit is Base UI + shadcn (`render` prop, never `asChild`); toasts via
  `toast.add()`, never sonner.
- Public pages are project-owned (`resources/js/frontend/pages/public/`, swapped in
  via `pageOverrides` in `page-config.ts`). Routes/controllers stay in core.
- AI features are BYOK (zero platform cost) — a genuine differentiator, say it plainly.

## Anti-patterns (copy + UX)

- Banned copy: "Unlock your potential", "Supercharge", "Seamless", "Empower",
  "Take your X to the next level", "All-in-one solution".
- No lorem ipsum, no fake logos, no invented customer quotes.
- One primary CTA per viewport; secondary actions are outline/ghost.
