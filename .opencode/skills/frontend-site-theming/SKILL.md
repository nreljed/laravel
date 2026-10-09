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
Accents:   accent     accent-ink  secondary  alt-1  alt-2
Chrome:    border     outline (stroke/fill text)
Type:      font-site-body  font-site-display
Radii:     radius-site-card   radius-site-pill
Layout:    container-site (1280px)
```

Examples: `bg-site-bg`, `text-site-ink/70`, `text-site-secondary`,
`bg-site-accent text-site-accent-ink`, `border-site-border`,
`rounded-site-card`, `font-site-display`, `max-w-site`.

### Adding a new site theme

1. Open `resources/css/site.css`.
2. Copy the `[data-site-theme="canvasly"]` block, rename the attribute
   value to your theme slug, and re-map every token to your palette/type.
   Add the Google-Fonts `<link>` your theme needs (see below).
3. Set `data-site-theme="<slug>"` on the page root element. Tokens cascade —
   one attribute restyles the page. Pages using `frontend/layouts/PublicLayout`
   get it centrally from `SiteRoot`, so don't set it per page.
4. Verify at `/style-guide` first — swatch values, type scale, pills and
   cards all re-resolve from the new token block automatically.

### Dark mode

Site themes must state their dark variant explicitly, same pattern the
app uses elsewhere. Inside the theme block add:

```css
[data-site-theme="canvasly"][data-theme="dark"] { … }
```

Scope it to the theme; never leak `dark:` variants that depend on the
global `.dark` toggle unless the page has opted in.

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

- `SiteRoot` — sets `data-site-theme="canvasly"` on the page root, injects
  the Instrument Sans webfont once, wraps the page in a flex-column shell
  (`min-h-screen bg-site-bg font-site-body text-site-ink`) and mounts
  `SmoothScroll` (Lenis).
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
- `SiteMenuOverlay` — full-screen menu overlay driven by `SiteMenuLink[]`.

Project-owned; lives in the app shell, not the platform package. Page-level
composition (hero, works grid, services rows, testimonial track) stays in
`resources/js/frontend/pages/public/*`.

## Style guide page — `/style-guide`

A living design-system spec on the project route (`routes/web.php` →
`Inertia::render('public/styleguide')`; `page-config.ts` override
`public/styleguide`). Sections: Colors (live swatches via
`getComputedStyle`), Typography, Buttons, Tags & pills, Cards, Pagination. It
is the theme's proof — the recipe for a new theme is to copy the canvasly
token block in `site.css`, re-map values, and this page resolves the new
tokens automatically (no raw hex in components).

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
`--font-display-family` tokens in `site.css` declare the same stack with a
system fallback so text is readable before/without the webfont.

For a new theme: add its font stylesheet the same way and update the
`--font-*-family` tokens in `site.css`.

## Rules

- Public pages read colors/type/radii via `site-*` utilities ONLY.
- Theme values live in `site.css`; never inline a hex in TSX.
- Add a reusable primitive to `Components/site/` when a pattern repeats
  across pages (menu, CTA pill).
- Do NOT touch `core.css` / `packages/**` for site theming — the marketing
  layer is project-owned in `resources/css/site.css`.