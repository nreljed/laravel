# Sidebar Navigation

## Architecture

The sidebar has **two nav sources:**

1. **Static core items** — defined in `app-sidebar.tsx`: Dashboard, Stats, Profile, Account, Notifications (with unread badge)
2. **Dynamic feature items** — from `FeatureRegistry::getAllNavItems()`, shared to the frontend as `pageProps.featureNavItems`

---

## NavItem Contract (`resources/js/Components/nav-main.tsx`)

```typescript
interface NavItem {
  title: string;
  url?: string;
  icon?: React.ComponentType<{ className?: string }> | string;
  items?: NavItem[]; // sub-items (collapsible dropdown)
  badge?: string;
  badgeVariant?: "default" | "secondary" | "outline" | "destructive";
  permission?: string; // permission slug for filtering
  feature_slug?: string; // used for feature identification
  showModeBadge?: boolean; // shows badge on parent
  groupLabel?: string; // sidebar group heading
}
```

- If `items` is present and non-empty, the item renders as a **collapsible dropdown** (parent toggles open/close, children are links)
- If `items` is absent/empty, the item renders as a **direct link** via Inertia `<Link>`
- `permission` and `feature_slug` are consumed by `app-sidebar.tsx`, not `nav-main.tsx`

---

## NavMain Component Pattern

Uses Radix **`Collapsible`** for collapsible items (per official shadcn sidebar docs).

### Collapsible Parent (has children)

```
Collapsible (asChild, defaultOpen when child active)
  SidebarMenuItem
    CollapsibleTrigger (asChild)
      SidebarMenuButton (isActive, tooltip)
        Icon
        Title
        Badge (optional)
        ChevronRight (rotates 90deg via group-data-[state=open]/collapsible:rotate-90)
    CollapsibleContent
      SidebarMenuSub
        SidebarMenuSubItem
          SidebarMenuSubButton (asChild, isActive)
            Link (Inertia)  ← navigation target
```

Key points:

- Parent button is a **pure toggle** — clicking it opens/closes the dropdown, does NOT navigate
- `defaultOpen` is set to `true` when any child's URL matches the current page
- The `Collapsible` component manages open/close state internally (Radix handles all events)
- Sub-items use `SidebarMenuSubButton asChild` with Inertia `<Link>`

### Flat Link (no children)

```
SidebarMenuItem
  SidebarMenuButton (asChild, isActive, tooltip)
    Link (Inertia)  ← navigation target
```

No collapsible wrapper, no chevron — just a direct navigation link.

---

## Permission Filtering (`app-sidebar.tsx`)

```typescript
const hasPermission = (perm: string | undefined): boolean => {
  if (!perm) return true; // no permission required
  if (isAdmin) return true; // admins bypass all checks
  if (perm in userCapabilities) return Boolean(userCapabilities[perm]);
  return false;
};
```

- `userCapabilities` is a flat boolean map shared from `HandleInertiaRequests` (see `auth-integration.md`)
- Admins see all nav items regardless of permission
- Items without a `permission` field are always visible

---

## Icon Resolution

Feature nav items pass `icon` as a **string key** (e.g., `"Calendar"`, `"FileText"`). These are resolved in `app-sidebar.tsx` via a lookup map:

```typescript
const featureIcons: Record<string, React.ComponentType<{ className?: string }>> = {
  FileText,
  LayoutDashboard,
  BarChart3,
  Bell,
  Users,
  Settings,
  User,
  Activity,
  Globe,
  IdCard,
  Shield,
  ShieldCheck,
  ScrollText,
  Beaker,
  FlaskConical,
};
```

`NavMain` receives this as `iconMap` and resolves string icons via `iconMap[iconProp]`. Direct component references (for core nav items) pass the component directly.

The string key must match an imported Lucide icon name in the `featureIcons` map.

---

## Sidebar Component Structure

```
Sidebar (collapsible="icon")
  SidebarHeader
    NavLogo                       // brand logo + name
  SidebarContent
    SidebarGroup
      NavMain                     // core items (Dashboard, Stats, Profile, Account, Notifications)
    {featureNavItems.map}         // filtered by hasPermission
      SidebarGroup
        NavMain (items={[navItem]})  // each feature as a single-item NavMain
    {isAdmin &&
      SidebarGroup                // Administration group
        NavMain (users, roles, settings, system health)
    {isAdmin &&
      SidebarGroup                // Development group
        NavMain (test components, notification test)
  SidebarFooter
    NavUser                       // user dropdown (theme, logout, etc.)
  SidebarRail
```

---

## Active State Detection

```typescript
const currentUrl = usePage().url || globalThis.location.pathname;

// For flat items: currentUrl === normalizeUrl(item.url)
// For collapsible parents: true if any child's url matches currentUrl
```

`normalizeUrl()` extracts just the pathname from a URL to handle port/scheme differences.

---

## Data Flow

```
PHP                          Frontend
─────────────────────────────────────
FeatureRegistry::getAllNavItems()  pageProps.featureNavItems (NavItem[])
                              ↓
                              app-sidebar.tsx filters by hasPermission
                              ↓
                              NavMain renders items
                                ├─ collapsible parents (Radix Collapsible)
                                └─ flat links (Inertia Link)
```

---

## Adding a New Sidebar Item

**For a new feature:** Register nav items in your feature's service provider via `FeatureRegistry`. They automatically appear in `featureNavItems`.

**For a static core item:** Add a `NavItem` object to the core items array in `app-sidebar.tsx`.

**For an admin item:** Add to the `adminNavItems` array (visible only to users with `admin` role).
