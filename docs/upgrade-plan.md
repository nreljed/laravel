# Codebase Upgrade Plan

> Based on comparative audit with CRM-dashboard (WordPress plugin, ~90% shared codebase), shadcn/ui v4 audit, Inertia.js v3 audit, and React 19 audit.
> **Date**: 2026-06-30 | **Target**: Production-ready, best-practice-aligned Laravel + Inertia + React 19 + shadcn/ui v4

---

## Phase Overview

| Phase | Focus | Effort | Risk | Dependencies |
|-------|-------|--------|------|--------------|
| **1** | Dev tooling & code quality | 2-3h | Low | None |
| **2** | Dependency alignment | 1-2h | Medium | Phase 1 (for testing) |
| **3** | TypeScript hardening | 2-3h | Medium | Phase 2 |
| **4** | Frontend testing | 3-4h | Low | Phase 2 |
| **5** | CSS & build optimization | 1-2h | Low | Phase 2 |
| **6** | CI/CD pipeline | 2-3h | Low | All above |
| **7** | Component & hook alignment | 3-4h | Medium | Phase 2 |
| **8** | Module feature parity (optional) | 8-16h | High | All above |

---

## Phase 1: Dev Tooling & Code Quality

**Effort**: 2-3h | **Risk**: Low | **Dependencies**: None

### 1.1 ESLint + Prettier

Install linting and formatting toolchain:

```bash
npm install --save-dev \
  eslint @typescript-eslint/parser @typescript-eslint/eslint-plugin \
  eslint-plugin-react eslint-plugin-react-hooks \
  eslint-plugin-import-x eslint-import-resolver-typescript \
  eslint-plugin-unicorn eslint-config-prettier prettier globals
```

**Files to create**:
- `eslint.config.js` — port CRM-dashboard's flat config. Key adjustments:
  - Remove nonce-specific rules (WordPress-only)
  - Adjust `import-x/no-unresolved` ignore list (remove `reactjs-tiptap-editor`)
  - Add `resources/js/**/*.ts` to TS parser files
- `.prettierrc.json` — CRM config works as-is:
  ```json
  { "semi": true, "singleQuote": false, "trailingComma": "all",
    "printWidth": 100, "tabWidth": 2, "arrowParens": "always" }
  ```

**NPM scripts** to add to `package.json`:
```json
"lint": "eslint resources/js/",
"lint:fix": "eslint resources/js/ --fix",
"format": "prettier --write \"resources/js/**/*.{ts,tsx,json,css}\"",
"format:check": "prettier --check \"resources/js/**/*.{ts,tsx,json,css}\""
```

### 1.2 Husky + lint-staged + commitlint

```bash
npm install --save-dev husky lint-staged @commitlint/cli @commitlint/config-conventional
npx husky init
```

**Files to create**:

- `.husky/pre-commit`:
  ```
  npx lint-staged
  ```

- `.husky/pre-push`:
  ```
  npx eslint resources/js/ && npx tsc --noEmit && vendor/bin/pint
  ```

- `.husky/commit-msg` (Windows: `npx --no -- commitlint --edit $1`):
  ```
  npx --no -- commitlint --edit ${1}
  ```

- `commitlint.config.cjs`:
  ```js
  module.exports = { extends: ['@commitlint/config-conventional'] };
  ```

- Add to `package.json`:
  ```json
  "lint-staged": {
    "*.{ts,tsx}": ["eslint --fix", "prettier --write"],
    "*.{json,css,md}": ["prettier --write"],
    "*.php": ["vendor/bin/pint"]
  }
  ```

### 1.3 VS Code Settings (optional but recommended)

`.vscode/settings.json`:
```json
{
  "editor.formatOnSave": true,
  "editor.codeActionsOnSave": {
    "source.fixAll.eslint": "explicit"
  },
  "typescript.tsdk": "node_modules/typescript/lib",
  "files.exclude": { "node_modules/": true }
}
```

### Verification

```bash
npm run lint          # 0 errors
npm run format:check  # 0 formatting issues
npm run typecheck     # clean (baseline before Phase 3)
npx commitlint        # test on a sample message
```

---

## Phase 2: Dependency Alignment

**Effort**: 1-2h | **Risk**: Medium (minor breaking changes possible) | **Deps**: Phase 1 (for post-update lint)

### 2.1 Animation Plugin Migration

```bash
npm uninstall tailwindcss-animate
npm install tw-animate-css@^1.4.0
```

Update `resources/css/app.css`:
```css
/* Replace: @plugin "tailwindcss-animate"; */
@plugin "tw-animate-css";
```

Run `npm run build` to verify animations still work (fade-in, zoom-in, slide-in, etc.) — test Dialog, Sheet, DropdownMenu, Drawer.

### 2.2 Install @inertiajs/vite

```bash
npm install @inertiajs/vite@^3.5.0
```

Add to `vite.config.js`:
```js
import inertia from "@inertiajs/vite";
// In plugins array, before react():
plugins: [laravel({...}), inertia(), react(), tailwindcss()]
```

### 2.3 Bulk Version Updates

Update `package.json` to match CRM-dashboard ranges:

| Package | Old Range | New Range | Reason |
|---------|-----------|-----------|--------|
| `react` | `^19.0.0` | `^19.2.0` | Latest stable |
| `react-dom` | `^19.0.0` | `^19.2.0` | Latest stable |
| `@inertiajs/react` | `^3.0.0` | `^3.5.0` | Latest Inertia features |
| `@vitejs/plugin-react` | `^6.0.2` | `^6.0.3` | Patch fix |
| `tailwindcss` | `^4.0.0` | `^4.3.0` | Tailwind improvements |
| `@tailwindcss/vite` | `^4.0.0` | `^4.3.0` | Tailwind improvements |
| `typescript` | `^6.0.0` | `^6.0.3` | Latest TS |
| `vite` | `^8.0.0` | `^8.1.0` | Latest Vite |
| `lucide-react` | `^1.20.0` | `^1.21.0` | Latest icons |
| `@base-ui/react` | `^1.5.0` | `^1.6.0` | Latest Base UI |

Then run:
```bash
npm update
npm run build
# Check for deprecation warnings or breaking changes
```

### 2.4 Clean Unused Dependencies

Remove if not directly used:
```bash
npm uninstall react-hook-form @hookform/resolvers
```

These are unused in favor of Inertia `useForm`. Verify no imports exist first:
```bash
rg "from ['\"]react-hook-form['\"]" resources/js/ --type tsx
rg "from ['\"]@hookform/resolvers['\"]" resources/js/ --type tsx
```

### Verification

```bash
npm run build        # Clean production build
npm run typecheck    # 0 errors
npm run lint         # 0 errors
php artisan test     # All passing
```

---

## Phase 3: TypeScript Hardening

**Effort**: 2-3h | **Risk**: Medium (~20-40 type errors to fix) | **Deps**: Phase 2

### 3.1 Create Shared Types Directory

`resources/js/types/`:

| File | Contents |
|------|----------|
| `index.d.ts` | Re-exports |
| `user.d.ts` | `User` interface (id, name, username, email, role, permissions, avatar, etc.) |
| `page-props.d.ts` | `PageProps<T>` generic type |
| `notification.d.ts` | `Notification` interface (id, type, data, read_at, created_at) |

Example — `resources/js/types/user.d.ts`:
```ts
export interface User {
  id: number;
  name: string;
  username: string;
  email: string;
  role: string;
  permissions: string[];
  avatar?: string;
  email_verified_at?: string;
  created_at: string;
  updated_at: string;
}
```

Example — `resources/js/types/page-props.d.ts`:
```ts
import type { User } from "./user";

export type PageProps<T = {}> = T & {
  auth: { user: User | null };
  flash?: Record<string, unknown>;
  notifications: AppNotification[];
  userMode: "host" | "guest";
  canSwitchMode: boolean;
  moduleDebugData: Record<string, unknown>;
};
```

### 3.2 Enable Strict TS Flags

In `tsconfig.json`:
```json
{
  "compilerOptions": {
    "noUnusedLocals": true,
    "noUnusedParameters": true,
    "noUncheckedIndexedAccess": true,
    "strict": true
  }
}
```

**Expected type errors**: ~20-40
- Unused imports (prefix with `_` or remove)
- Unused parameters (prefix with `_`)
- Array/object access without undefined check (add guards or `!` assertions)

### 3.3 Fix app.tsx Router Types

Replace `(router as any).page` with typed access:
```ts
import { router } from "@inertiajs/react";
const page = router.page; // Already typed
```

### 3.4 Fix handle-inertia-requests.ts

Add explicit return types for `share()` method:
```ts
public share(): SharedProps {
  return {
    auth: { user: ... },
    flash: this.flash(),
    notifications: this.notifications(),
    userMode: session('user_mode', 'guest'),
    canSwitchMode: ...,
    moduleDebugData: ...,
  };
}
```

### Verification

```bash
npm run typecheck     # 0 errors (after fixes)
npm run build         # Clean build
```

---

## Phase 4: Frontend Testing

**Effort**: 3-4h | **Risk**: Low | **Deps**: Phase 2 (for correct module resolution)

### 4.1 Vitest Setup

```bash
npm install --save-dev vitest @testing-library/react @testing-library/jest-dom \
  @testing-library/dom @testing-library/user-event jsdom
```

**Files to create**:

- `vitest.config.js` — port CRM config:
  ```js
  import { defineConfig } from "vitest/config";
  import path from "node:path";

  export default defineConfig({
    test: {
      environment: "jsdom",
      globals: true,
      include: ["resources/js/**/*.test.{ts,tsx}"],
      setupFiles: ["./resources/js/test-setup.ts"],
      coverage: {
        provider: "v8",
        reporter: ["text", "lcov"],
        include: ["resources/js/**/*.{ts,tsx}"],
        exclude: ["resources/js/**/__tests__/**", "resources/js/**/*.test.*",
                   "resources/js/components/ui/**"],
      },
    },
    resolve: {
      alias: { "@": path.resolve(__dirname, "resources/js") },
    },
  });
  ```

- `resources/js/test-setup.ts`:
  ```ts
  import "@testing-library/jest-dom/vitest";
  ```

- Update `package.json` scripts:
  ```json
  "test": "vitest run",
  "test:watch": "vitest",
  "test:coverage": "vitest run --coverage"
  ```

### 4.2 Port Test Files from CRM

| File | What it tests | Source |
|------|---------------|--------|
| `hooks/__tests__/use-auth.test.tsx` | Auth hook — user state, login, logout, admin check | CRM-dashboard |
| `hooks/__tests__/use-notifications.test.tsx` | Notification polling, mark-as-read | CRM-dashboard |
| `lib/__tests__/utils.test.ts` | `cn()`, `zodErrorsToMap()`, date formatting | CRM-dashboard |
| `lib/__tests__/schemas.test.ts` | Zod schema validation (login, register, password) | CRM-dashboard |

### 4.3 Write New Tests

Priority order:
1. `lib/__tests__/utils.test.ts` — utility functions
2. `lib/__tests__/schemas.test.ts` — Zod validation schemas
3. `hooks/__tests__/use-auth.test.tsx` — auth hook
4. Page-level smoke tests for critical pages (Login, Dashboard)

### Verification

```bash
npm run test          # All passing
npm run test:coverage # Report
npm run typecheck     # 0 errors (vitest types)
```

---

## Phase 5: CSS & Build Optimization

**Effort**: 1-2h | **Risk**: Low | **Deps**: Phase 2

### 5.1 Tailwind Source Directives

Update `resources/css/app.css`:
```css
@import "tailwindcss" source(none);
@source "../resources/js/";
@source "../../app/Modules/Properties/Resources/js/";
@source "../../app/Modules/Booking/Resources/js/";
@source "../../app/Modules/Shop/Resources/js/";
@source "../../app/Modules/Posts/Resources/js/";

@plugin "tw-animate-css";
@custom-variant dark (&:is(.dark *));
```

This prevents Tailwind from scanning `vendor/` and `node_modules/` for class names, improving build performance and preventing false positives.

### 5.2 Granular Code Splitting

Update `vite.config.js` `build.rolldownOptions.output.codeSplitting.groups`:

| Group | Pattern | Priority | Benefit |
|-------|---------|----------|---------|
| `react-vendor` | react, react-dom, scheduler | 30 | Already exists |
| `leaflet-vendor` | leaflet, react-leaflet | 25 | Isolate 255KB map lib |
| `ui-vendor` | radix-ui | 20 | Already exists |
| `icons-vendor` | lucide-react | 20 | Already exists |
| `inertia-vendor` | @inertiajs | 20 | Already exists |
| `dnd-vendor` | @dnd-kit | 25 | Drag-and-drop |
| `date-vendor` | date-fns | 20 | Date formatting |
| `zxcvbn-vendor` | @zxcvbn-ts | 20 | Password strength |
| `phone-vendor` | react-phone-number-input, libphonenumber-js | 20 | Phone input |
| `forms-vendor` | react-hook-form | 20 | If dependency kept |
| `baseui-vendor` | @base-ui | 20 | Combobox |

### 5.3 Build Visualizer (optional)

```bash
npm install --save-dev rollup-plugin-visualizer
```

Add to `vite.config.js`:
```js
import { visualizer } from "rollup-plugin-visualizer";
// In plugins:
visualizer({ filename: "build-stats.html", open: false, gzipSize: true })
```

### Verification

```bash
npm run build         # Check chunk sizes in CLI output
# Open build-stats.html if visualizer installed
```

---

## Phase 6: CI/CD Pipeline

**Effort**: 2-3h | **Risk**: Low | **Deps**: All previous phases

### 6.1 CI Validation Workflow

Create `.github/workflows/ci-validation.yml`:

```yaml
name: CI Validation
on:
  push:
    branches: [main, develop]
    paths-ignore: ['docs/**', '**.md', '.gitignore']
  pull_request:
    branches: [main]

jobs:
  validate:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with: { php-version: '8.4', tools: composer:v2 }
      - uses: actions/setup-node@v4
        with: { node-version: 22, cache: 'npm' }
      - run: cp .env.example .env
      - run: composer install
      - run: npm ci
      - run: php artisan key:generate
      - run: php artisan migrate --force
      - run: npm run lint                  # ESLint
      - run: npm run typecheck             # tsc --noEmit
      - run: npm run format:check          # Prettier
      - run: vendor/bin/pint --test        # Laravel Pint
      - run: npm run test                  # Vitest
      - run: php artisan test              # PHPUnit
      - run: npm run build                 # Production build
```

### 6.2 Update Deploy Workflow

In `deploy.yml`, add before the `npm install` step:
```yaml
- name: Frontend validation
  run: npm run lint && npm run typecheck && npm run test
```

### 6.3 Add Check Script (convenience)

Add a script to run all checks locally before push:
```json
"check:ci": "npm run lint && npm run typecheck && npm run format:check && vendor/bin/pint && npm run test && php artisan test"
```

### Verification

Push a test branch and verify CI passes.

---

## Phase 7: Component & Hook Alignment

**Effort**: 3-4h | **Risk**: Medium | **Deps**: Phase 2 (for @radix-ui/react-compose-refs)

### 7.1 compose-refs

Replace `resources/js/lib/compose-refs.ts` (25 lines, hand-rolled) with Radix re-export (1 line, battle-tested):

```ts
export { composeRefs } from "@radix-ui/react-compose-refs";
```

This requires adding the package:
```bash
npm install @radix-ui/react-compose-refs
```

Or if available via `radix-ui`:
```ts
// Check if radix-ui exports composeRefs
// If not, use the scoped package
```

### 7.2 Date Formatting (optional)

Replace custom `phpToJsFormat()` + `applyJsFormat()` in `utils.ts` with `date-fns`-based approach from CRM:

```ts
import { format } from "date-fns";

const PHP_DATE_TOKENS: Record<string, string> = {
  "Y": "yyyy", "y": "yy", "m": "MM", "n": "M",
  "d": "dd", "j": "d", "H": "HH", "h": "hh",
  "i": "mm", "s": "ss", "A": "aaa", "a": "aa",
};

export function phpToDateFnsFormat(phpFormat: string): string {
  return phpFormat.replace(/[A-Za-z]/g, (match) =>
    PHP_DATE_TOKENS[match] ?? match
  );
}

export function formatDate(date: string | Date, phpFormat: string): string {
  return format(new Date(date), phpToDateFnsFormat(phpFormat));
}
```

Benefits: handles `\T` escapes, timezone tokens, ordinal suffixes (`S`), more robust edge case coverage.

### 7.3 Additional Zod Schemas

Port from CRM `lib/schemas/index.ts`:

```ts
export const profileSchema = z.object({
  name: z.string().min(1, "Name is required").max(100),
  username: z.string().min(3).max(60).regex(/^[a-zA-Z0-9_.-]+$/),
  display_name_option: z.string(),
});

export const passwordSchema = z.object({
  current_password: z.string().min(1),
  password: z.string().min(8, "Password must be at least 8 characters"),
  password_confirmation: z.string(),
}).refine((data) => data.password === data.password_confirmation, {
  message: "Passwords don't match",
  path: ["password_confirmation"],
});

export const emailSchema = z.object({
  email: z.string().email("Valid email required"),
});
```

### 7.4 FlashMessages Component (optional)

Port CRM's `FlashMessages.tsx` for a dedicated flash consumer component — simplifies `app.tsx` by extracting the flash listener logic.

### 7.5 Use in Pages

Wire Zod schemas into Account page (`Edit.tsx`):
- Profile section → `profileSchema`
- Password section → `passwordSchema`
- Email section → `emailSchema`

### Verification

```bash
npm run typecheck     # 0 errors
npm run test          # All passing (including new Zod schema tests)
npm run build         # Clean build
```

---

## Phase 8: Module Feature Parity (Optional)

**Effort**: 8-16h | **Risk**: High | **Deps**: All above

### 8.1 Rich Text Editor (TipTap)

If modules need rich text input (property descriptions, post content, email templates):

```bash
npm install @tiptap/core @tiptap/react @tiptap/starter-kit @tiptap/pm \
  @tiptap/extension-link @tiptap/extension-image @tiptap/extension-placeholder \
  @tiptap/extension-table @tiptap/extension-table-row @tiptap/extension-table-cell \
  @tiptap/extension-table-header @tiptap/extension-task-item \
  @tiptap/extension-task-list @tiptap/extension-underline \
  @tiptap/extension-text-align @tiptap/extension-highlight \
  @tiptap/extension-typography @tiptap/extension-color \
  @tiptap/extension-code-block-lowlight lowlight \
  @tiptap/extension-blockquote dompurify
```

Port `resources/js/Components/tiptap/` from CRM-dashboard (container, editors, toolbar, config, image upload).

### 8.2 API Client Layer

If you add REST endpoints beyond Inertia:

Port `lib/api/client.js`, `lib/api/endpoints.ts`, `lib/api/index.js` from CRM. Replace:
- `use-nonce` → `csrf_token()` meta tag
- `apiUrl()` → Ziggy's `route()`
- WordPress-specific error handling → Laravel error format

### 8.3 Image Security

If user image uploads are critical:
- Port `lib/image-security.js` for client-side validation
- Add server-side validation in PHP upload handlers
- Magic byte checking, EXIF stripping, dimension limits

### 8.4 Additional CSS Variables

If needed for status badges, password strength, or accent colors:

```css
:root {
  --warning: oklch(0.8 0.115 85);
  --password-fair: oklch(0.64 0.145 75);
  --password-strong: oklch(0.62 0.14 145);
  --featured: oklch(0.62 0.19 55);
}
```

### Verification

```bash
npm run build         # Check bundle size impact
npm run typecheck     # 0 errors
npm run test          # All passing
```

---

## Effort Summary

| Phase | Hours | Can parallelize |
|-------|-------|-----------------|
| 1 — Dev tooling | 2-3h | Independent |
| 2 — Dependencies | 1-2h | Independent |
| 3 — TypeScript | 2-3h | After phase 2 |
| 4 — Testing | 3-4h | After phase 2 |
| 5 — CSS & build | 1-2h | After phase 2 |
| 6 — CI/CD | 2-3h | After all above |
| 7 — Components | 3-4h | After phase 2 |
| 8 — Module parity | 8-16h | After all above |

**Total core** (Phases 1-6): ~11-17h
**Total with extensions** (Phases 7-8): ~22-37h

---

## Quick Start (what to start now)

1. **Phase 1** has zero code risk and the highest impact on code quality — start here
2. **Phase 2** is mechanical npm work with clear rollback (commit `package.json` before)
3. **Phase 3** will surface real bugs — do it while codebase is fresh
4. **Phase 4-6** lock in quality with automation

Recommended order: **1 → 2 → 3 → 5 → 4 → 6 → 7 → 8**
