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
import { Kbd } from "@/Components/ui/kbd";
import {
  Search,
  LayoutDashboard,
  BarChart3,
  Bell,
  Users,
  Settings,
  Activity,
  Database,
} from "lucide-react";
import { useAuth } from "@/hooks/use-auth";
import { featureIcons } from "@/lib/icons";

interface NavMenuItem {
  title: string;
  icon: React.ComponentType<{ className?: string }>;
  url: string;
}

interface NavMenuGroup {
  heading: string;
  items: NavMenuItem[];
}

interface FeatureNavItem {
  title: string;
  url: string;
  icon?: string;
  permission?: string;
  feature_slug?: string;
  groupLabel?: string;
  items?: { title: string; url: string }[];
}

export function CommandSearch() {
  const [open, setOpen] = React.useState(false);
  const { isAdmin } = useAuth();
  const pageProps = usePage().props;
  const featureNavItems = pageProps.featureNavItems as FeatureNavItem[] | undefined;
  const userCapabilities = useMemo(
    () =>
      (pageProps.userCapabilities as {
        isAdmin?: boolean;
        can?: Record<string, boolean>;
      }) || {},
    [pageProps.userCapabilities],
  );

  const navigationItems: NavMenuGroup[] = React.useMemo(() => {
    const can = userCapabilities.can || {};
    const hasPermissionInner = (perm: string | undefined): boolean => {
      if (!perm) return true;
      if (isAdmin) return true;
      if (Object.hasOwn(can, perm)) return can[perm] === true;
      return false;
    };

    const items: NavMenuGroup[] = [
      {
        heading: "Navigation",
        items: [
          { title: "Dashboard", icon: LayoutDashboard, url: route("dashboard") },
          { title: "Stats", icon: BarChart3, url: route("dashboard.stats") },
          { title: "Notifications", icon: Bell, url: route("dashboard.notifications") },
          { title: "Account", icon: Settings, url: route("dashboard.account") },
        ],
      },
    ];

    const navList = featureNavItems || [];
    for (const nav of navList) {
      if (!hasPermissionInner(nav.permission)) continue;

      const heading = nav.groupLabel ?? nav.title;
      const IconComponent =
        nav.icon && Object.hasOwn(featureIcons, nav.icon)
          ? featureIcons[nav.icon]
          : LayoutDashboard;
      const featureItems: NavMenuItem[] = [];

      if (nav.items && nav.items.length > 0) {
        for (const child of nav.items) {
          featureItems.push({
            title: child.title,
            icon: IconComponent as React.ComponentType<{ className?: string }>,
            url: child.url,
          });
        }
      } else {
        featureItems.push({
          title: nav.title,
          icon: IconComponent as React.ComponentType<{ className?: string }>,
          url: nav.url,
        });
      }

      if (featureItems.length > 0) {
        items.push({ heading, items: featureItems });
      }
    }

    if (isAdmin) {
      const dbEnabled =
        (pageProps.features as { databaseMaintenanceEnabled?: boolean } | undefined)
          ?.databaseMaintenanceEnabled ?? false;

      items.push({
        heading: "Administration",
        items: [
          { title: "Users", icon: Users, url: route("dashboard.admin.users.index") },
          { title: "Settings", icon: Settings, url: route("dashboard.admin.settings.general") },
          { title: "System Health", icon: Activity, url: route("dashboard.admin.system-health") },
          ...(dbEnabled
            ? [{ title: "Database", icon: Database, url: route("dashboard.admin.database") }]
            : []),
        ],
      });
    }

    return items;
  }, [isAdmin, featureNavItems, userCapabilities, pageProps.features]);

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
        <Kbd className="absolute right-1.5 top-1.5 hidden font-mono text-[10px] sm:inline-flex">
          <span className="text-xs">⌘</span>K
        </Kbd>
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
