import React from "react";
import {
  Globe,
  LayoutDashboard,
  BarChart3,
  Bell,
  Users,
  Settings,
  Activity,
  Shield,
  ShieldCheck,
  ScrollText,
  Beaker,
  FlaskConical,
  Database,
} from "lucide-react";
import { usePage } from "@inertiajs/react";
import { NavMain, type NavItem } from "@/Components/navigation/nav-main";
import { NavUser } from "@/Components/navigation/nav-user";
import { NavLogo } from "@/Components/navigation/nav-logo";
import {
  Sidebar,
  SidebarContent,
  SidebarFooter,
  SidebarHeader,
  SidebarRail,
  SidebarGroup,
  SidebarGroupLabel,
  SidebarGroupContent,
} from "@/Components/ui/sidebar";
import { useAuth } from "@/hooks/use-auth";
import { featureIcons } from "@/lib/icons";

export function AppSidebar({ ...props }: React.ComponentProps<typeof Sidebar>) {
  const { isAdmin } = useAuth();
  const pageProps = usePage().props;
  const featureNavItems = (pageProps.featureNavItems as NavItem[]) || [];
  const userCapabilities = (pageProps.userCapabilities as Record<string, boolean>) || {};
  const notificationUnreadCount = (pageProps.notificationUnreadCount as number) || 0;
  const brand = (pageProps.brand as { logo?: string; favicon?: string; name?: string }) || {};
  const appUrl = (pageProps.app_url as string) || null;
  const databaseMaintenanceEnabled =
    (pageProps.features as { databaseMaintenanceEnabled?: boolean } | undefined)
      ?.databaseMaintenanceEnabled ?? false;

  const safeRoute = (name: string, fallback = "#"): string => {
    try {
      return route(name);
    } catch {
      return fallback;
    }
  };

  const hasPermission = (perm: string | undefined): boolean => {
    if (!perm) return true;
    if (isAdmin) return true;
    const caps = userCapabilities as Record<string, unknown>;
    const can = (caps.can as Record<string, boolean>) || {};
    if (Object.hasOwn(can, perm)) return can[perm] === true;
    if (Object.hasOwn(caps, perm)) return caps[perm] === true;
    return false;
  };

  const filterSubItems = (items: NavItem[] | undefined): NavItem[] | undefined => {
    if (!items) return undefined;
    return items
      .filter((sub) => hasPermission(sub.permission))
      .map((sub) => ({
        ...sub,
        items: filterSubItems(sub.items),
      }));
  };

  const adminNavItems: NavItem[] = [
    {
      title: "Users",
      url: safeRoute("dashboard.admin.users.index"),
      icon: Users,
      permission: "manage-users",
    },
    {
      title: "Roles",
      url: safeRoute("dashboard.admin.roles.index"),
      icon: Shield,
      permission: "manage-roles",
    },
    {
      title: "Permissions",
      url: safeRoute("dashboard.admin.permissions.manage"),
      icon: ShieldCheck,
      permission: "manage-roles",
    },
    {
      title: "Settings",
      url: safeRoute("dashboard.admin.settings.general"),
      icon: Settings,
      permission: "manage-settings",
    },
    {
      title: "Audit Logs",
      url: safeRoute("dashboard.admin.audit-logs.index"),
      icon: ScrollText,
      permission: "manage-audit-logs",
    },
    {
      title: "System Health",
      url: safeRoute("dashboard.admin.system-health"),
      icon: Activity,
      permission: "view-system-health",
    },
    {
      title: "Database",
      url: safeRoute("dashboard.admin.database"),
      icon: Database,
      permission: "manage-options",
    },
  ];

  const visibleAdminItems = adminNavItems.filter(
    (item) =>
      hasPermission(item.permission) && (item.title !== "Database" || databaseMaintenanceEnabled),
  );

  const devNavItems: NavItem[] = [
    {
      title: "UI Style Guide",
      url: safeRoute("dashboard.admin.developer.style-guide"),
      icon: Beaker,
    },
    {
      title: "Notification Test",
      url: safeRoute("dashboard.admin.notifications.test"),
      icon: FlaskConical,
    },
  ];

  return (
    <Sidebar collapsible="icon" {...props}>
      <SidebarHeader>
        <NavLogo
          logo={brand.logo || null}
          favicon={brand.favicon || null}
          name={brand.name || null}
          appUrl={appUrl}
        />
      </SidebarHeader>
      <SidebarContent>
        <SidebarGroup>
          <SidebarGroupLabel className="hidden">Main</SidebarGroupLabel>
          <SidebarGroupContent>
            <NavMain
              items={[
                { title: "Website", url: safeRoute("home"), icon: Globe },
                { title: "Dashboard", url: safeRoute("dashboard"), icon: LayoutDashboard },
                { title: "Stats", url: safeRoute("dashboard.stats"), icon: BarChart3 },
                { title: "Account", url: safeRoute("dashboard.account"), icon: Settings },
                {
                  title: "Notifications",
                  url: safeRoute("dashboard.notifications"),
                  icon: Bell,
                  ...(notificationUnreadCount > 0 && {
                    badge: String(notificationUnreadCount),
                    badgeVariant: "default" as const,
                  }),
                },
              ]}
            />
          </SidebarGroupContent>
        </SidebarGroup>

        {featureNavItems
          .filter((navItem) => hasPermission(navItem.permission))
          .map((navItem) => {
            const groupLabel = navItem.groupLabel ?? "";
            const filtered = { ...navItem, items: filterSubItems(navItem.items) };

            return (
              <SidebarGroup key={navItem.title}>
                {groupLabel && <SidebarGroupLabel>{groupLabel}</SidebarGroupLabel>}
                <SidebarGroupContent>
                  <NavMain items={[filtered]} iconMap={featureIcons} />
                </SidebarGroupContent>
              </SidebarGroup>
            );
          })}

        {visibleAdminItems.length > 0 && (
          <SidebarGroup>
            <SidebarGroupLabel>Administration</SidebarGroupLabel>
            <SidebarGroupContent>
              <NavMain items={visibleAdminItems} />
            </SidebarGroupContent>
          </SidebarGroup>
        )}

        {isAdmin && (
          <SidebarGroup>
            <SidebarGroupLabel>Development</SidebarGroupLabel>
            <SidebarGroupContent>
              <NavMain items={devNavItems} />
            </SidebarGroupContent>
          </SidebarGroup>
        )}
      </SidebarContent>
      <SidebarFooter>
        <NavUser />
      </SidebarFooter>
      <SidebarRail />
    </Sidebar>
  );
}
