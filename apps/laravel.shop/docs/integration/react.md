# React Integration

## Version

| Package | Version (installed) | Range in package.json | Status |
|---------|-------------------|----------------------|--------|
| `react` | 19.2.7 | `^19.0.0` | Up to date |
| `react-dom` | 19.2.7 | `^19.0.0` | Up to date |
| `@types/react` | ^19.0.0 | `^19.0.0` | Up to date |
| `@types/react-dom` | ^19.0.0 | `^19.0.0` | Up to date |

CRM-dashboard reference: `react@^19.2.7`, same major.

---

## Current Usage Patterns

### Component Style

- Functional components with hooks (no classes)
- Named function declarations (`function Foo()`) over arrow consts
- Plain function components (no `forwardRef` / `memo` wrappers unless needed)
- `React.ComponentProps<"element">` for native element props
- `React.ComponentProps<typeof Primitive.Root>` for Radix primitives

### State Management

- Inertia `useForm` for all form state (explicit preference)
- `useState` + `useReducer` for local UI state
- `useCallback` + `useMemo` for optimization
- `useEffect` for side effects (polling, event listeners)
- `useId` for accessible form field IDs
- `useRef` for DOM refs (composed via `composeRefs` utility)
- `useContext` + `createContext` for shared state (SidebarProvider, FormFieldContext)

### Key Libraries

- `next-themes` `<ThemeProvider>` for dark mode
- `@/Components/ui/toast` `toast.add()` for flash notifications (Base UI)
- `cmdk` for command palette
- `react-leaflet` for maps
- `@dnd-kit` / `@hello-pangea/dnd` for drag-and-drop
- `lucide-react` for icons
- `class-variance-authority` for component variants
- `clsx` + `tailwind-merge` via `cn()` utility
- `react-day-picker` for calendar

---

## Audit Findings

### ✅ Correct

- React 19 strict mode ready (no `forwardRef`, `useRef` instead of `createRef`)
- No deprecated lifecycle methods
- All components use proper hooks (no class components)
- No external state management library (Inertia is sufficient)
- Proper cleanup in `useEffect` returns

### ⚠️ Issues Found

| Issue | Priority | Impact | Fix |
|-------|----------|--------|-----|
| `react-hook-form` in `package.json` but project prefers Inertia `useForm` | Low | Unused dependency (57KB) | Remove if not used anywhere |
| `@hookform/resolvers` in `package.json` | Low | Unused if react-hook-form unused | Remove |
| Missing `types/` directory for shared TypeScript types | Medium | Duplicated interfaces across files | Create `resources/js/types/` |
| `use as any` casts in `app.tsx` router handling | Medium | Unsafe type assumptions | Use typed Inertia router |

### React 19 Features Not Used

| Feature | Benefit | Effort |
|---------|---------|--------|
| `useActionState` | Server action state management | Low (if paired with Inertia) |
| `useOptimistic` | Optimistic UI updates | Medium |
| `use()` hook | Direct promise reading in render | Low |
| `<Context>` as provider | Render prop provider pattern | Low |
| Document metadata (`use`) | Dynamic `<title>`, `<meta>` | Low |

---

## Bundle Analysis

| Chunk | Size (gzip) | Contents |
|-------|-------------|----------|
| `react-vendor` | 169 KB | react, react-dom, scheduler |
| `ui-vendor` | 45 KB | radix-ui primitives |
| `icons-vendor` | 15 KB | lucide-react |
| `inertia-vendor` | 99 KB | @inertiajs/react |

No significant bundle issues. Code-splitting groups could be more granular (CRM-dashboard uses 14 groups vs Laravel's 4).

---

## TypeScript Configuration

```json
{
  "jsx": "react-jsx",
  "strict": true,
  "noUnusedLocals": false,
  "noUnusedParameters": false
}
```

CRM-dashboard uses `noUnusedLocals: true` and `noUnusedParameters: true` — stricter TypeScript. Consider enabling these for better code quality.

---

## Recommended Actions

1. **Enable `noUnusedLocals` and `noUnusedParameters`** in `tsconfig.json`
2. **Clean up unused dependencies**: `react-hook-form`, `@hookform/resolvers`
3. **Add shared types** in `resources/js/types/` (User, Notification, PageProps, etc.)
4. **Match CRM-dashboard's `noUncheckedIndexedAccess: true`** for safer object access
5. **Update `package.json` range** to `^19.2.0` for `react`/`react-dom` to narrow future upgrades

---

## Version History

| Date | Action |
|------|--------|
| 2026-06-30 | Audit created — React 19.2.7 installed |
