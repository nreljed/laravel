import * as React from "react";
import { useMemo } from "react";
import { router, usePage } from "@inertiajs/react";
import {
  CommandDialog,
  CommandEmpty,
  CommandGroup,
  CommandInput,
  CommandItem,
  CommandList,
  CommandSeparator,
} from "@/Components/ui/command";
import { Button } from "@/Components/ui/button";
import {
  Search,
  LayoutDashboard,
  BarChart3,
  Bell,
  User,
  Users,
  Settings,
  Activity,
} from "lucide-react";
import { useAuth } from "@/hooks/use-auth";
import { moduleIcons } from "@/lib/icons";

interface NavMenuItem {
  title: string;
  icon: React.ComponentType<{ className?: string }>;
  url: string;
}

interface NavMenuGroup {
  heading: string;
  items: NavMenuItem[];
}

interface ModuleNavItem {
  title: string;
  url: string;
  icon?: string;
  permission?: string;
  module_slug?: string;
  groupLabel?: string;
  items?: { title: string; url: string }[];
}

export function CommandSearch() {
  const [open, setOpen] = React.useState(false);
  const { isAdmin } = useAuth();
  const pageProps = usePage().props;
  const moduleNavItems = pageProps.moduleNavItems as ModuleNavItem[] | undefined;
  const userCapabilities = useMemo(
    () => (pageProps.userCapabilities as Record<string, boolean>) || {},
    [pageProps.userCapabilities],
  );

  const navigationItems: NavMenuGroup[] = React.useMemo(() => {
    const hasPermissionInner = (perm: string | undefined): boolean => {
      if (!perm) return true;
      if (isAdmin) return true;
      if (Object.hasOwn(userCapabilities, perm)) return userCapabilities[perm] === true;
      return false;
    };

    const items: NavMenuGroup[] = [
      {
        heading: "Navigation",
        items: [
          { title: "Dashboard", icon: LayoutDashboard, url: route("dashboard") },
          { title: "Stats", icon: BarChart3, url: route("dashboard.stats") },
          { title: "Notifications", icon: Bell, url: route("dashboard.notifications") },
          { title: "Account", icon: User, url: route("dashboard.account") },
          { title: "Profile", icon: User, url: route("dashboard.profile.edit") },
        ],
      },
    ];

    const navList = moduleNavItems || [];
    for (const nav of navList) {
      if (!hasPermissionInner(nav.permission)) continue;

      const heading = nav.groupLabel ?? nav.title;
      const IconComponent =
        nav.icon && Object.hasOwn(moduleIcons, nav.icon) ? moduleIcons[nav.icon] : LayoutDashboard;
      const moduleItems: NavMenuItem[] = [];

      if (nav.items && nav.items.length > 0) {
        for (const child of nav.items) {
          moduleItems.push({
            title: child.title,
            icon: IconComponent as React.ComponentType<{ className?: string }>,
            url: child.url,
          });
        }
      } else {
        moduleItems.push({
          title: nav.title,
          icon: IconComponent as React.ComponentType<{ className?: string }>,
          url: nav.url,
        });
      }

      if (moduleItems.length > 0) {
        items.push({ heading, items: moduleItems });
      }
    }

    if (isAdmin) {
      items.push({
        heading: "Administration",
        items: [
          { title: "Users", icon: Users, url: route("dashboard.admin.users.index") },
          { title: "Settings", icon: Settings, url: route("dashboard.admin.settings") },
          { title: "System Health", icon: Activity, url: route("dashboard.admin.system-health") },
        ],
      });
    }

    return items;
  }, [isAdmin, moduleNavItems, userCapabilities]);

  React.useEffect(() => {
    const down = (e: KeyboardEvent) => {
      if (!(e.key === "k" && (e.metaKey || e.ctrlKey))) {
        return;
      }

      e.preventDefault();
      setOpen((open) => !open);
    };

    document.addEventListener("keydown", down);
    return () => document.removeEventListener("keydown", down);
  }, []);

  return (
    <>
      <Button
        variant="outline"
        className="relative h-9 w-9 sm:w-64 sm:justify-start sm:px-3 border-border bg-background hover:bg-accent hover:text-accent-foreground"
        onClick={() => setOpen(true)}
      >
        <Search className="size-4 sm:mr-2" />
        <span className="hidden sm:inline-flex text-muted-foreground">Search...</span>
        <kbd className="pointer-events-none absolute right-1.5 top-1.5 hidden h-5 select-none items-center gap-1 rounded border bg-muted px-1.5 font-mono text-[10px] font-medium opacity-100 sm:flex">
          <span className="text-xs">⌘</span>K
        </kbd>
      </Button>

      <CommandDialog open={open} onOpenChange={setOpen}>
        <CommandInput placeholder="Type a command or search..." />
        <CommandList>
          <CommandEmpty>No results found.</CommandEmpty>
          {navigationItems.map((group, groupIndex) => (
            <React.Fragment key={group.heading}>
              <CommandGroup heading={group.heading}>
                {group.items.map((item) => (
                  <CommandItem
                    key={item.url}
                    className="cursor-pointer"
                    onSelect={() => {
                      setOpen(false);
                      router.visit(item.url);
                    }}
                  >
                    <item.icon className="mr-2 h-4 w-4" />
                    <span>{item.title}</span>
                  </CommandItem>
                ))}
              </CommandGroup>
              {groupIndex < navigationItems.length - 1 && <CommandSeparator />}
            </React.Fragment>
          ))}
        </CommandList>
      </CommandDialog>
    </>
  );
}
