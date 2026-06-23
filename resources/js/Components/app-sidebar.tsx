import React from "react"
import { Building2, Globe, LayoutDashboard, BarChart3, Bell, Users, Settings, Activity, IdCard, User, Calendar, FileText, ShoppingCart, Shield, Beaker, FlaskConical, CalendarCheck } from "lucide-react"
import { ModuleDebugPanel } from "@/Components/ModuleDebugPanel"
import { router, usePage } from "@inertiajs/react"
import { NavMain, type NavItem } from "@/Components/nav-main"
import { NavUser } from "@/Components/nav-user"
import { NavLogo } from "@/Components/NavLogo"
import { Badge } from "@/Components/ui/badge"
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarRail, SidebarGroup, SidebarGroupLabel, SidebarGroupContent } from "@/Components/ui/sidebar"
import { useAuth } from "@/hooks/use-auth"

const moduleIcons: Record<string, React.ComponentType<{ className?: string }>> = {
  Building2,
  Calendar,
  FileText,
  Settings,
  LayoutDashboard,
  ShoppingCart,
}

export function AppSidebar({ ...props }: React.ComponentProps<typeof Sidebar>) {
  const { isAdmin } = useAuth()
  const pageProps = usePage().props
  const moduleNavItems = (pageProps.moduleNavItems as NavItem[]) || []
  const userCapabilities = (pageProps.userCapabilities as Record<string, boolean>) || {}
  const notificationUnreadCount = (pageProps.notificationUnreadCount as number) || 0
  const userMode = (pageProps.userMode as string) || "host"
  const canSwitchMode = (pageProps.canSwitchMode as boolean) || false
  const brand = (pageProps.brand as { logo?: string; name?: string }) || {}
  const appUrl = (pageProps.app_url as string) || null

  const hasPermission = (perm: string | undefined): boolean => {
    if (!perm) return true
    if (isAdmin) return true
    if (perm in userCapabilities) return Boolean(userCapabilities[perm])
    return false
  }

  const adminNavItems: NavItem[] = [
    { title: "Users", url: route("dashboard.admin.users.index"), icon: Users },
    { title: "Roles", url: route("dashboard.admin.roles.index"), icon: Shield },
    { title: "Settings", url: route("dashboard.admin.settings"), icon: Settings },
    { title: "System Health", url: route("dashboard.admin.system-health"), icon: Activity },
  ]

  const devNavItems: NavItem[] = [
    { title: "Test Components", url: route("dashboard.developer.test-page"), icon: Beaker },
    { title: "Booking Demo", url: route("dashboard.bookings.demo"), icon: CalendarCheck },
    { title: "Notification Test", url: route("dashboard.notifications.test"), icon: FlaskConical },
  ]

  return (
    <Sidebar collapsible="icon" {...props}>
      <SidebarHeader>
        <NavLogo logo={brand.logo || null} name={brand.name || null} appUrl={appUrl} />
      </SidebarHeader>
      <SidebarContent>
        <SidebarGroup>
          <SidebarGroupLabel className="hidden">Main</SidebarGroupLabel>
          <SidebarGroupContent>
            <NavMain
              items={[
                { title: "Website", url: route("home"), icon: Globe },
                { title: "Dashboard", url: route("dashboard"), icon: LayoutDashboard },
                { title: "Stats", url: route("dashboard.stats"), icon: BarChart3 },
                { title: "Profile", url: route("dashboard.profile.edit"), icon: IdCard },
                { title: "Account", url: route("dashboard.account"), icon: User },
                {
                  title: "Notifications",
                  url: route("dashboard.notifications"),
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

        {moduleNavItems
          .filter((navItem) => hasPermission(navItem.permission))
          .map((navItem) => {
            if (navItem.module_slug === "bookings" && userMode === "guest") {
              return (
                <SidebarGroup key={navItem.title}>
                  <SidebarGroupLabel>My Trips</SidebarGroupLabel>
                  <SidebarGroupContent>
                    <NavMain
                      items={[
                        { title: "My Bookings", url: route("dashboard.bookings.index"), icon: Calendar },
                      ]}
                      iconMap={moduleIcons}
                    />
                  </SidebarGroupContent>
                </SidebarGroup>
              )
            }

            return (
              <SidebarGroup key={navItem.title}>
                <SidebarGroupContent>
                  <NavMain items={[navItem]} iconMap={moduleIcons} />
                </SidebarGroupContent>
              </SidebarGroup>
            )
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
      <ModuleDebugPanel />
      <SidebarFooter className="flex-col gap-4">
        {canSwitchMode && (
          <div className="flex items-center justify-between px-3 py-2 border-b border-border">
            <span className="text-xs text-muted-foreground">
              {userMode === "guest" ? "Guest" : "Host"}
            </span>
            <button
              type="button"
              className="inline-flex items-center justify-center rounded-md text-xs font-medium h-7 px-2.5 border border-input bg-background hover:bg-accent hover:text-accent-foreground transition-colors"
              onClick={() =>
                router.post(
                  route("dashboard.switch-mode"),
                  { mode: userMode === "guest" ? "host" : "guest" },
                  { preserveScroll: true },
                )
              }
            >
              Switch to {userMode === "guest" ? "Host" : "Guest"}
            </button>
          </div>
        )}
        <NavUser />
      </SidebarFooter>
      <SidebarRail />
    </Sidebar>
  )
}
