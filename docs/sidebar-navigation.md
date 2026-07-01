# Sidebar Navigation

## Architecture

The sidebar has **two nav sources:**

1. **Static core items** — defined in `app-sidebar.tsx`: Dashboard, Stats, Profile, Account, Notifications (with unread badge)
2. **Dynamic module items** — from `ModuleRegistry::getAllNavItems()`, shared to the frontend as `pageProps.moduleNavItems`

---

## NavItem Contract (`resources/js/Components/nav-main.tsx`)

```typescript
interface NavItem {
  title: string
  url?: string
  icon?: React.ComponentType<{ className?: string }> | string
  items?: NavItem[]                // sub-items (collapsible dropdown)
  badge?: string
  badgeVariant?: "default" | "secondary" | "outline" | "destructive"
  permission?: string              // permission slug for filtering
  module_slug?: string             // used for guest mode and Host badge
  showModeBadge?: boolean          // shows "Host" badge on parent
}
```

- If `items` is present and non-empty, the item renders as a **collapsible dropdown** (parent toggles open/close, children are links)
- If `items` is absent/empty, the item renders as a **direct link** via Inertia `<Link>`
- `permission`, `module_slug`, and `showModeBadge` are consumed by `app-sidebar.tsx`, not `nav-main.tsx`

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
  if (!perm) return true          // no permission required
  if (isAdmin) return true        // admins bypass all checks
  if (perm in userCapabilities) return Boolean(userCapabilities[perm])
  return false
}
```

- `userCapabilities` is a flat boolean map shared from `HandleInertiaRequests` (see `auth-integration.md`)
- Admins see all nav items regardless of permission
- Items without a `permission` field are always visible

---

## Host/Guest Mode

The Bookings module supports a **guest mode** toggle stored in session (`user_mode`).

```typescript
// app-sidebar.tsx
if (navItem.module_slug === "bookings" && userMode === "guest") {
  // Render under "My Trips" group label with only "My Bookings"
  <SidebarGroup key={navItem.title}>
    <SidebarGroupLabel>My Trips</SidebarGroupLabel>
    <SidebarGroupContent>
      <NavMain items={[
        { title: "My Bookings", url: route("dashboard.bookings.index"), icon: Calendar }
      ]} iconMap={moduleIcons} />
    </SidebarGroupContent>
  </SidebarGroup>
}
```

- Guest mode shows only "My Bookings" under "My Trips" label
- Host mode shows the full Bookings dropdown (All Bookings, Calendar)
- Mode switch trigger in sidebar footer: POST to `dashboard.switch-mode`

---

## Icon Resolution

Module nav items pass `icon` as a **string key** (e.g., `"Calendar"`, `"Building2"`). These are resolved in `app-sidebar.tsx` via a lookup map:

```typescript
const moduleIcons: Record<string, React.ComponentType<{ className?: string }>> = {
  Building2, Calendar, FileText, Settings, LayoutDashboard, ShoppingCart,
}
```

`NavMain` receives this as `iconMap` and resolves string icons via `iconMap[iconProp]`. Direct component references (for core nav items) pass the component directly.

The string key must match an imported Lucide icon name in the `moduleIcons` map.

---

## Sidebar Component Structure

```
Sidebar (collapsible="icon")
  SidebarHeader
    NavLogo                       // brand logo + name
  SidebarContent
    SidebarGroup
      NavMain                     // core items (Dashboard, Stats, Profile, Account, Notifications)
    {moduleNavItems.map}          // filtered by hasPermission
      SidebarGroup
        NavMain (items={[navItem]})  // each module as a single-item NavMain
    {isAdmin &&
      SidebarGroup                // Administration group
        NavMain (users, roles, settings, system health)
  SidebarFooter
    ModeSwitch (host/guest)       // when canSwitchMode
    NavUser                       // user dropdown (theme, logout, etc.)
  SidebarRail
```

---

## Active State Detection

```typescript
const currentUrl = usePage().url || globalThis.location.pathname

// For flat items: currentUrl === normalizeUrl(item.url)
// For collapsible parents: true if any child's url matches currentUrl
```

`normalizeUrl()` extracts just the pathname from a URL to handle port/scheme differences.

---

## Data Flow

```
PHP                          Frontend
─────────────────────────────────────
Module::getNavItems()        pageProps.moduleNavItems (NavItem[])
                             ↓
                             app-sidebar.tsx filters by hasPermission
                             ↓
                             NavMain renders items
                               ├─ collapsible parents (Radix Collapsible)
                               └─ flat links (Inertia Link)
```

---

## Adding a New Sidebar Item

**For a new module:** Implement `getNavItems()` in the module class (as shown in `module-system.md`). It automatically appears in `moduleNavItems`.

**For a static core item:** Add a `NavItem` object to the core items array in `app-sidebar.tsx`.

**For an admin item:** Add to the `adminNavItems` array (visible only to users with `admin` role).
