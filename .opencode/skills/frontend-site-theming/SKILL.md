---
name: frontend-site-theming
description: "Design-system layer for PUBLIC/marketing web pages in the nreljed stack. Activate when building or re-theming any site-facing page (Welcome/landing/About/Services/Contact marketing surfaces) or when the user asks for a 'site kit', 'frontend theme', 'design tokens for the website', 'theme structure', or to restyle the public frontend. Do NOT use for the Dashboard/admin UI — that surface is governed by the shadcn tokens in packages/platform resources/css/core.css and the base-ui-shadcn skill. Covers the site-* semantic token layer (resources/css/site.css), data-site-theme scoping, the Components/site kit, and the Google-Fonts loading pattern."
---

# Frontend Site Theming — the marketing-page design layer

The admin/dashboard UI uses the shadcn token system from core. The
**public-facing site** uses a separate, purpose-built layer so that any
modern marketing site can be themed without touching the admin kit.

Two layers, never mixed:

| Surface            | Token source                              | Skill                 |
| ------------------ | ----------------------------------------- | --------------------- |
| Dashboard / admin  | `core.css` (`bg-background`, `text-muted-foreground`, …) | base-ui-shadcn      |
| Public / marketing | `resources/css/site.css` (`bg-site-bg`, `text-site-ink`, …) | this skill         |

## The token layer — `resources/css/site.css`

All public pages render from **semantic `site-*` utilities** only. Never
write raw hex in a page component. The utilities are generated with
`@theme inline` so they resolve `var(--site-*)` at runtime — meaning a
theme swap restyles the whole sub-tree.

Current token set (add tokens here, then use the new utility):

```
Surfaces:  bg         bg-strong   surface
Text:      ink        ink-soft    ink-faint
Accents:   accent     accent-ink  secondary  alt-1  alt-2  pink (panel tint)
Chrome:    border     outline (stroke/fill text)  invert-bg / invert-ink (fixed dark header plate)
Type:      font-site-body  font-site-display  font-site-secondary
Radii:     radius-site-card   radius-site-pill
Layout:    container-site (1280px)  container-site-measure (65ch)  container-site-display (56rem)
Spacing:   --spacing-site-{xs,sm,md,lg,xl,2xl,3xl,4xl,5xl,6xl} → p-site-*, m-site-*, gap-site-*, space-y-site-*
```

Examples: `bg-site-bg`, `text-site-ink/70`, `text-site-secondary`,
`bg-site-accent text-site-accent-ink`, `border-site-border`,
`rounded-site-card`, `font-site-display`, `max-w-site`, `max-w-site-measure`,
`px-site-lg`, `gap-site-2xl`, `py-site-5xl`.

The color / type / radius / container utilities come from `@theme inline`
(runtime `var()` references, so a theme swap restyles the sub-tree). The
spacing stops come from a plain `@theme` block, so they are also emitted as
real `--spacing-site-*` custom properties (readable at runtime).

Heading scale: pick a class by SEO level, never a bespoke size — h1
`site-display` (the landing crown is `site-hero`), h2 `site-title`, h3
`site-title-sm`, h4 `site-heading`, h5 `site-heading-sm`, h6
`site-heading-xs`; deck paragraph `site-lead`. They clamp mobile→desktop and
are zero-specificity (`:where`), so Tailwind utilities can still tune them.
One **component role** opts out of the level map: a card title
(`SiteCardTitle`) renders an `h3` but uses `site-heading-sm` — a card is a
component context, sized below the section `h3`.

### Adding a new site theme

Themes are one file per palette under `resources/css/themes/` (e.g.
`themes/cb360.css`), each scoped under `[data-site-theme="<slug>"]` and
imported by `site.css`.

1. Copy `resources/css/themes/cb360.css` to `resources/css/themes/<slug>.css`.
2. Change the `[data-site-theme="<slug>"]` attribute value and re-map every
   token to your palette/type — including the dark block, which must be
   scoped `:where(html.dark) [data-site-theme="<slug>"]` (see Dark mode).
   Add the Google-Fonts `<link>` your theme needs (see below).
3. Import it in `resources/css/site.css`: `@import "./themes/<slug>.css";`.
4. Pass `theme="<slug>"` to `SiteRoot` (or set `data-site-theme="<slug>"` on
   a page root). Tokens cascade — one attribute restyles the page. Pages
   using `frontend/layouts/PublicLayout` get it centrally from `SiteRoot`, so
   don't set it per page.
5. Verify at `/style-guide` first — swatch values, type scale, pills and
   cards all re-resolve from the new token block automatically.

### Dark mode

Dark mode keys off the app's global `html.dark` class (next-themes), NOT a
`data-theme` attribute. Site themes must state their dark variant explicitly
— never inherit it from the light values. Inside the theme module add a
`:where(html.dark)`-scoped block:

```css
:where(html.dark) [data-site-theme="cb360"] { … }
```

The neutral fallback in `site.css` does the same (`html.dark` /
`html.dark [data-site-theme="neutral"]`). Scope every dark rule to the
theme; never leak `dark:` variants that assume the page follows the global
toggle unless it actually does.

## Separation model (platform vs project chrome)

Core ships the backend service + a token-agnostic `SeoHead` +
`PublicLayout`; it does NOT own the marketing chrome. Its shipped
`Components/frontend/SiteHeader` / `SiteFooter` are shadcn-tokened and
read-only (mirror). This project therefore owns its layout + chrome:

- `resources/js/frontend/layouts/PublicLayout.tsx` — project public layout. Same
  props contract as core's (`title`, `description`, `ogImage`, `ogType`,
  `canonical`, `article*` fields, `twitterCard`); renders `SeoHead`
  (`@nreljed/platform/Components/seo/SeoHead`), skip-link, `SiteHeader`,
  `main#main-content`, `SiteFooter`, inside `SiteRoot`. The blog/pages'
  `PublicLayout` import swaps from the platform to this one.
- Brand wordmark reads `props.brand.name` (= `app_name` setting via
  `HandleInertiaRequests`) — header/footer, never a hardcoded string.

## The site kit — `resources/js/frontend/components/site/`

Shared presentational primitives used across pages. Keep them generic and
token-driven (no per-brand literals):

- `SiteRoot` — takes a `theme` prop (default `"cb360"`), applies
  `data-site-theme="<theme>"` to the page root and exposes it via
  `SiteThemeContext`, injects the Instrument Sans webfont once, wraps the
  page in a flex-column shell (`min-h-screen bg-site-bg font-site-body
  text-site-ink`) and mounts `SmoothScroll` (Lenis).
- `SiteHeader` — sticky `h-20` bar (transparent → glass on scroll): brand
  wordmark, then the controls cluster — `ThemeToggle`, `UserMenu`
  (→ dashboard/login), and a Menu button toggling `SiteMenuOverlay`
  (nav: Home/About/Services/Blog/Style Guide/Contact via `route()`).
- `SiteFooter` — 3-column brand + Explore + Connect, then a legal bar
  (`© {year}`, legal links, Back to top). No CTA (the CTA is a page section).
- `SitePill` — tag/category pill; `<span>` or `<Link>` when `href` given.
- `SitePagination` — token pager, same `links` shape as platform
  `PaginationLinks`; active page = ink pill with `aria-current="page"`.
- `PillButton` — pill CTA (internal `Link` or external `<a>`). Renders its
  own `Link` — never wrap it in another.
- `SiteCardGrid` / `SiteCard` / `SiteCardTitle` — the card system: a
  `grid gap-site-lg` grid of discrete bordered `bg-site-surface` cards
  (`rounded-site-card border border-site-border overflow-hidden`), **never** a
  painted `gap-px`/`bg-site-border` hairline plate — a plate only reads as a
  grid when every row is full and leaks its background into empty cells.
  `SiteCard` is a `Link` when given `href`; cards are static (no hover tint,
  no image zoom). `SiteCardTitle` uses the `site-heading-sm` token.
- `SiteFeatureCard` — numbered/icon feature card (index, optional `LucideIcon`,
  title, optional value line, body) for Home/Services/About/Contact.
- `BlogCard` — blog archive card: featured image, category eyebrow, title,
  excerpt, author byline (`SiteAvatar` · name · date).
- `SiteAvatar` — author avatar: the user's image when `avatar_url` is set, else
  an accent initials monogram (`getUserInitials`) — never a broken/empty image.
  Sizes `sm`/`md`/`lg` (lg = header account menu).
- `formatSiteDate(value, "short" | "long")` — the one site date formatter, pinned
  to `en-US` so server and client render the same string; `null` for empty input.
- `SiteMenuOverlay` — full-screen menu overlay driven by `SiteMenuLink[]`.

Project-owned; lives in the app shell, not the platform package. Page-level
composition (hero, works grid, services rows, testimonial track) stays in
`resources/js/frontend/pages/public/*`.

## Style guide page — `/style-guide`

A living design-system spec on the project route (`routes/web.php` →
`Inertia::render('public/styleguide')`; `page-config.ts` override
`public/styleguide`). Sections: 01 Colors (live swatches via
`getComputedStyle`), 02 Typography, 03 Fonts, 04 Spacing scale, 05 Buttons,
06 Tags & pills, 07 Menu overlay links, 08 Cards (feature + blog + avatar),
09 Pagination. It is the
theme's proof — the recipe for a new theme is to copy
`resources/css/themes/cb360.css`, re-map values, and this page resolves the
new tokens automatically (no raw hex in components).

## Blog prose on the site theme

The blog posts use core's `ProseContent` with `prose prose-neutral
dark:prose-invert` classes; Tailwind's typography plugin is NOT installed,
so only core.css's custom `.prose` rules apply — including `!important`
in `.dark .prose …` dark-mode rules (next-themes dark toggle). Because the
site renders its own light theme regardless of `.dark`, site.css re-asserts
the site look under `[data-site-theme] .prose …` with higher/same
specificity and `!important` where core hard-locks: ink text/headings,
display font for h1/h2, `site-secondary` links, table th bg + site-border
borders, inline-code bg. Keep the `!important` on the rules core also makes
`!important` (e.g. inline-code bg) or the `.dark` rule wins again.

## Fonts

Load the theme's display/body fonts with `<link>` tags. The current theme
uses **Instrument Sans** (a variable font): `resources/views/app.blade.php`
preconnects to `fonts.bunny.net`, and `SiteRoot` injects the Google-Fonts
variable-font stylesheet once. The `--font-body-family` /
`--font-display-family` / `--font-secondary-family` tokens declare the same
stack with a system fallback so text is readable before/without the webfont —
declared in the theme module (`themes/<slug>.css`) and, for the neutral
fallback, in `site.css`.

For a new theme: add its font stylesheet the same way and update the
`--font-*-family` tokens in the theme module.

## Rules

- Public pages read colors/type/spacing/radii via `site-*` utilities ONLY.
- Theme values live in `resources/css/themes/<slug>.css` (the neutral fallback
  lives in `site.css`); never inline a hex in TSX.
- Add a reusable primitive to `Components/site/` when a pattern repeats
  across pages (menu, CTA pill).
- Do NOT touch `core.css` / `packages/**` for site theming — the marketing
  layer is project-owned in `resources/css/site.css` + `resources/css/themes/`.