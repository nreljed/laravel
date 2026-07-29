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
    if (Object.hasOwn(userCapabilities, perm)) return userCapabilities[perm] === true;
    return false;
  };

  const adminNavItems: NavItem[] = [
    { title: "Users", url: safeRoute("dashboard.admin.users.index"), icon: Users },
    { title: "Roles", url: safeRoute("dashboard.admin.roles.index"), icon: Shield },
    {
      title: "Permissions",
      url: safeRoute("dashboard.admin.permissions.index"),
      icon: ShieldCheck,
    },
    { title: "Settings", url: safeRoute("dashboard.admin.settings.general"), icon: Settings },
    { title: "Audit Logs", url: safeRoute("dashboard.admin.audit-logs.index"), icon: ScrollText },
    { title: "System Health", url: safeRoute("dashboard.admin.system-health"), icon: Activity },
  ];

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

            return (
              <SidebarGroup key={navItem.title}>
                {groupLabel && <SidebarGroupLabel>{groupLabel}</SidebarGroupLabel>}
                <SidebarGroupContent>
                  <NavMain items={[navItem]} iconMap={featureIcons} />
                </SidebarGroupContent>
              </SidebarGroup>
            );
          })}

        {isAdmin && (
          <SidebarGroup>
            <SidebarGroupLabel>Administration</SidebarGroupLabel>
            <SidebarGroupContent>
              <NavMain items={adminNavItems} />
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
