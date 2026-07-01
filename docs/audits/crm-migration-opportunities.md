# CRM-Dashboard → Laravel: Migration Opportunities

> **Source**: `C:\Users\nrelj\Local Sites\beta\app\public\wp-content\plugins\CRM-dashboard`
> **Target**: `C:\Users\nrelj\Local Sites\laravel\app`
> **Shared DNA**: ~90% — same UI components, same hooks, same module architecture, same Inertia + React + shadcn/ui stack
> **Date**: 2026-06-30

---

## Priority Legend

| Priority | Action |
|----------|--------|
| **P0** | Must do — bug/blocker |
| **P1** | Should do — significant improvement |
| **P2** | Nice to have — minor improvement |
| **P3** | Future consideration |

---

## 1. Dev Tooling & Code Quality (P1)

### 1.1 ESLint Configuration

CRM-dashboard has a comprehensive ESLint setup. Laravel has none.

**CRM config**: TypeScript parser, React plugin, React Hooks plugin, Import resolver (`eslint-plugin-import-x`), Unicorn plugin (40+ rules), Prettier integration, test file exemptions.

**Install**:
```bash
npm install --save-dev \
  eslint @typescript-eslint/parser @typescript-eslint/eslint-plugin \
  eslint-plugin-react eslint-plugin-react-hooks \
  eslint-plugin-import-x eslint-import-resolver-typescript \
  eslint-plugin-unicorn eslint-config-prettier \
  globals
```

**Files to port**: `eslint.config.js` (flat config) — can be copied directly with minimal changes.

### 1.2 Prettier Configuration

**Install**:
```bash
npm install --save-dev prettier
```

**Files to port**: `.prettierrc.json` — copy as-is (100 print width, trailing commas, LF).

### 1.3 Husky + lint-staged

**Install**:
```bash
npm install --save-dev husky lint-staged
npx husky init
```

**Git hooks to create**:
- `.husky/pre-commit`: `npx lint-staged`
- `.husky/pre-push`: `npm run lint && npm run typecheck && vendor/bin/pint`

### 1.4 Commitlint

**Install**:
```bash
npm install --save-dev @commitlint/cli @commitlint/config-conventional
```

- `.husky/commit-msg`: `npx --no -- commitlint --edit $1`

### 1.5 TypeScript Strictness

Enable in `tsconfig.json`:
```json
{
  "noUnusedLocals": true,
  "noUnusedParameters": true,
  "noUncheckedIndexedAccess": true
}
```

Currently Laravel has these set to `false` or omitted. CRM-dashboard has all three enabled. Expect ~20-30 type errors initially that need fixing.

### 1.6 dependency-cruiser or Renovate

CRM-dashboard uses Renovate with grouped updates. Consider adding:
- **Renovate**: Free, automated dependency PRs (`.github/renovate.json`)
- Or **Dependabot**: Built into GitHub

---

## 2. Testing Infrastructure (P1)

### 2.1 Vitest Setup

Laravel has **no frontend tests**. CRM-dashboard has full Vitest with:

- `vitest.config.js` — jsdom environment, coverage (v8), path aliases
- `vitest.core.config.js` — core JS tests only
- `vitest.modules.config.js` — module tests only
- `test-setup.js` — global test setup
- `__tests__` dirs in `hooks/` and `lib/`

**Install**:
```bash
npm install --save-dev vitest @testing-library/react @testing-library/jest-dom @testing-library/dom jsdom
```

**Files to port**:
- `vitest.config.js` (adapt paths to Laravel)
- `resources/js/test-setup.js`
- `resources/js/hooks/__tests__/use-auth.test.tsx`
- `resources/js/hooks/__tests__/use-notifications.test.tsx`
- `resources/js/lib/__tests__/*.test.js`

### 2.2 Add to CI/CD

Add `npm run test:core` to the `deploy.yml` GitHub workflow.

---

## 3. Frontend API Layer (P2)

### 3.1 API Client

CRM-dashboard has `lib/api/client.js` (396 lines) — a full Inertia REST API client class with:
- `api.get()`, `api.post()`, `api.put()`, `api.patch()`, `api.delete()`
- Automatic nonce injection (WordPress-specific — replace with Laravel CSRF)
- Error normalization
- Inertia visit integration

**Value for Laravel**: If you ever need REST endpoints beyond Inertia (e.g., for partial page updates, auto-save, typeahead), this is a clean wrapper.

**Port note**: Replace nonce logic with Laravel's `csrf_token()`.

### 3.2 Endpoint Constants

`lib/api/endpoints.ts` — centralized endpoint paths. Helpful if the codebase grows beyond current routing.

### 3.3 Image Security

`lib/image-security.js` — client-side magic byte validation, EXIF stripping, animated GIF detection. Laravel's `gallery-utils.ts` handles processing but has no security layer.

**Port if you handle user image uploads**.

---

## 4. UI Components & Hooks (P2)

### 4.1 Hooks to Port

| Hook | Purpose | Effort |
|------|---------|--------|
| `use-gallery-config.ts` | Gallery drag-and-drop configuration | Low (if gallery uploader exists) |
| `use-tip-tap-dark-mode.ts` | Rich text editor dark mode | Low (if TipTap is added) |

### 4.2 Shared Components to Port

| Component | Location in CRM | Effort |
|-----------|----------------|--------|
| `FlashMessages.tsx` | `Components/FlashMessages.tsx` | Low |
| `ThemeProvider.tsx` | `Components/ThemeProvider.tsx` | Low (simplifies app.tsx) |
| `kbd.tsx` | `Components/shared/kbd.tsx` | Low |
| `native-select.tsx` | `Components/shared/native-select.tsx` | Low |
| `state-select.tsx` | `Components/shared/state-select.tsx` | Low |
| `variations-manager.tsx` | `Components/shared/variations-manager.tsx` | Medium |
| `item.tsx` | `Components/shared/item.tsx` | Low |
| `direction.tsx` | `Components/shared/direction.tsx` | Low |
| `cropper.tsx` | `Components/shared/cropper.tsx` | Low |
| `error-message.tsx` | Already exists in Laravel | ✅ |
| `section.tsx` | Already exists in Laravel | ✅ |

### 4.3 TipTap Rich Text Editor

CRM-dashboard has a full TipTap integration (`components/tiptap/`):
- 20+ extensions (table, image, link, task list, highlight, code block, etc.)
- Custom toolbar
- Image upload handler
- Config presets

**Value for Laravel**: If any module needs rich text editing (e.g., property descriptions, post content, email templates), this is production-ready.

---

## 5. Package Version Alignment (P1)

### Versions to Upgrade

| Package | Laravel (package.json) | CRM-dashboard | Action |
|---------|----------------------|---------------|--------|
| `react` | `^19.0.0` | `^19.2.7` | Update to `^19.2.0` |
| `react-dom` | `^19.0.0` | `^19.2.7` | Update to `^19.2.0` |
| `@inertiajs/react` | `^3.0.0` | `^3.5.0` | Update to `^3.5.0` |
| `@vitejs/plugin-react` | `^6.0.2` | `^6.0.3` | Update to `^6.0.3` |
| `tailwindcss` | `^4.0.0` | `^4.3.1` | Update to `^4.3.0` |
| `@tailwindcss/vite` | `^4.0.0` | `^4.3.1` | Update to `^4.3.0` |
| `typescript` | `^6.0.0` | `^6.0.3` | Update to `^6.0.3` |
| `vite` | `^8.0.0` | `^8.1.0` | Update to `^8.1.0` |
| `lucide-react` | `^1.20.0` | `^1.21.0` | Update to `^1.21.0` |
| `@base-ui/react` | `^1.5.0` | `^1.6.0` | Update to `^1.6.0` |
| `@zxcvbn-ts/core` | `^4.1.2` | `^4.1.2` | Same |
| `date-fns` | `^4.4.0` | `^4.4.0` | Same |

### Packages to Add (Dev)

| Package | Version | Purpose |
|---------|---------|---------|
| `eslint` | ^10.6.0 | Linting |
| `prettier` | ^3.8.5 | Formatting |
| `husky` | ^9.1.7 | Git hooks |
| `lint-staged` | ^17.0.8 | Staged linting |
| `@commitlint/cli` | ^21.1.0 | Commit convention |
| `@commitlint/config-conventional` | ^21.1.0 | Conventional commits |
| `vitest` | ^4.1.9 | Frontend testing |
| `@testing-library/react` | ^16.3.2 | React test utilities |
| `@testing-library/jest-dom` | ^6.9.1 | DOM matchers |
| `jsdom` | ^29.1.1 | DOM environment |

### Animation Plugin Migration

CRM-dashboard uses `tw-animate-css` (Tailwind v4 native). Laravel uses `tailwindcss-animate` (legacy v3 port).

```bash
npm uninstall tailwindcss-animate
npm install tw-animate-css@^1.4.0
```

Replace in `resources/css/app.css`:
```css
/* Remove: @plugin "tailwindcss-animate"; */
/* Add:    @plugin "tw-animate-css"; */
```

---

## 6. CSS Improvements (P2)

### 6.1 Tailwind v4 Source Directives

Current Laravel:
```css
@import "tailwindcss";
```

CRM-dashboard (more explicit):
```css
@import "tailwindcss" source(none);
@source "../resources/js/";
@source "../../app/Modules/Properties/Resources/js/";
@source ...
```

The `source(none)` + `@source` pattern explicitly declares which directories Tailwind should scan, preventing false positive class generation from `vendor/` and `node_modules/`.

### 6.2 Additional CSS Variables

| Variable | CRM Value | Priority |
|----------|-----------|----------|
| `--warning` | oklch(0.8 0.115 85) | Low |
| `--password-fair` | oklch(0.64 0.145 75) | Low |
| `--password-strong` | oklch(0.62 0.14 145) | Low |
| `--accent-purple` / `--accent-rose` / etc. | various | Low |

### 6.3 @tailwindcss/typography

CRM-dashboard uses `@tailwindcss/typography` for prose content. Install if you render Markdown or rich text:

```bash
npm install @tailwindcss/typography
```

Add to CSS:
```css
@plugin "@tailwindcss/typography";
```

---

## 7. Backend Alignment (P2)

### 7.1 `compose-refs.ts`

Laravel has a 25-line hand-rolled implementation. CRM-dashboard re-exports from `@radix-ui/react-compose-refs` (1 line, battle-tested):

```ts
export { composeRefs as composeRefs } from "@radix-ui/react-compose-refs";
```

**Action**: Replace Laravel's hand-rolled version with the Radix re-export.

### 7.2 Date Formatting

Laravel uses a custom `phpToJsFormat()` + `applyJsFormat()` in `utils.ts` without a date library.

CRM-dashboard uses `date-fns` with `phpToDateFnsFormat()`. This is more reliable (handles edge cases like `\T`, timezone tokens) and is already a dependency.

**Action**: Consider switching to `date-fns`-based formatting for better localization and edge case coverage.

### 7.3 Zod Schemas

Laravel has `lib/schemas/index.ts` with auth schemas. CRM-dashboard has additional schemas:

| Schema | CRM | Laravel | Action |
|--------|-----|---------|--------|
| `profileSchema` | ✅ | ❌ | Port for Account page |
| `passwordSchema` | ✅ | ❌ | Port for password change |
| `emailSchema` | ✅ | ❌ | Port for email change |

---

## 8. CI/CD Improvements (P2)

### 8.1 CI Validation Workflow

CRM-dashboard has a separate `ci-validation.yml` that runs on every push to `beta` and every PR:

```
lint → typecheck → format:check → phpcs → phpstan → phpunit → vitest:core → vitest:modules → build
```

**Action**: Create `.github/workflows/ci-validation.yml` for the Laravel project with:
```yaml
- npm run lint        (once ESLint is added)
- npm run typecheck
- npm run format:check  (once Prettier is added)
- vendor/bin/pint
- php artisan test
- npm run test:core   (once Vitest is added)
- npm run build
```

### 8.2 Build Stats

CRM-dashboard uses `rollup-plugin-visualizer`. Add to `vite.config.js`:
```js
import { visualizer } from "rollup-plugin-visualizer";
// In plugins: visualizer({ filename: "build-stats.html", open: false, gzipSize: true })
```

### 8.3 Granular Code Splitting

CRM-dashboard has 14 code-splitting groups. Laravel has 4. Consider adding groups for:
- `leaflet-vendor` (if map bundle is significant)
- `dnd-vendor` (@dnd-kit)
- `date-vendor` (date-fns)
- `forms-vendor` (react-hook-form — if kept)
- `phone-vendor` (react-phone-number-input)
- `zxcvbn-vendor` (@zxcvbn-ts)

---

## 9. WordPress-Specific Patterns (Do NOT Port)

These CRM-dashboard patterns are WordPress-specific and have no equivalent in a standalone Laravel app:

| Pattern | CRM Reason | Laravel Equivalent |
|---------|-----------|-------------------|
| `use-nonce.ts` | WordPress CSRF nonces | Laravel's `csrf_token()` / Sanctum |
| `apiUrl()` in utils | WordPress `plugins_url()` | Ziggy's `route()` |
| `@radix-ui/react-direction` | RTL support for WP admin | Optional |
| `image-security.js` | WordPress image handling | Server-side validation in PHP |
| `plugin.php` header | WordPress plugin bootstrap | `routes/web.php` + `artisan serve` |
| Fix permissions shell script | Shared hosting deployment | Not needed (Dedicated server) |
| `acorn-integration` | Roots Acorn WordPress bridge | Not needed (Native Laravel) |

---

## Quick Win Checklist

### Can be done in 30 minutes

- [ ] Update `package.json` version ranges (`react`, `react-dom`, `tailwindcss`, etc.)
- [ ] Install `tw-animate-css` + uninstall `tailwindcss-animate`
- [ ] Update CSS `@plugin` directive
- [ ] Replace `compose-refs.ts` with Radix re-export
- [ ] Add `@inertiajs/vite` plugin
- [ ] Enable strict `tsconfig.json` flags (expect ~20 type errors)

### Can be done in 2 hours

- [ ] Install ESLint + Prettier + Husky + lint-staged + commitlint
- [ ] Port ESLint + Prettier config from CRM
- [ ] Set up Vitest + port test files
- [ ] `npm update` all aligned packages
- [ ] Update Vite code-splitting groups

### Can be done in 4 hours

- [ ] Port CRM hooks: `use-gallery-config.ts`
- [ ] Port CRM lib: Zod schemas for profile/password/email
- [ ] Add `@tailwindcss/typography` + TipTap (if needed)
- [ ] Create `ci-validation.yml`
- [ ] Add `rollup-plugin-visualizer`

---

## Migration Status

| Area | CRM-dashboard | Laravel | Gap |
|------|---------------|---------|-----|
| UI Components | 32 | 80 | CRM has subset |
| Hooks | 16 | 13 | CRM has 3 unique |
| Tests | Vitest + PHPUnit + PHPStan | PHPUnit only | Frontend tests missing |
| Linting | ESLint + Prettier | None | Missing |
| Git Hooks | Husky + lint-staged + commitlint | None | Missing |
| CI/CD | CI validation + Release | Deploy only | Missing validation |
| Rich Text | TipTap full | None | Not needed yet |
| API Client | Full class | Direct Inertia | Not needed yet |
| Version Currency | Mostly up to date | Slightly behind | 10 packages to update |
