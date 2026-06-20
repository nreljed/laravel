import { useState } from "react"
import { Link } from "@inertiajs/react"
import { ChevronsUpDown, LogOut, User, Shield, Sun, Moon, Monitor } from "lucide-react"
import { useTheme } from "next-themes"

import { Avatar, AvatarFallback } from "@/Components/ui/avatar"
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/Components/ui/dropdown-menu"
import {
  SidebarMenu,
  SidebarMenuButton,
  SidebarMenuItem,
  useSidebar,
} from "@/Components/ui/sidebar"
import { useAuth, getUserInitials } from "@/hooks/use-auth"

export function NavUser() {
  const { isMobile } = useSidebar()
  const { user } = useAuth()
  const { theme, setTheme } = useTheme()
  const [showLogoutDialog, setShowLogoutDialog] = useState(false)

  const name = user?.name ?? "User"
  const email = user?.email ?? ""

  return (
    <SidebarMenu>
      <SidebarMenuItem>
        <DropdownMenu>
          <DropdownMenuTrigger asChild>
            <SidebarMenuButton
              size="lg"
              className="data-[state=open]:bg-sidebar-accent data-[state=open]:text-sidebar-accent-foreground"
            >
              <Avatar className="h-8 w-8 rounded-lg">
                <AvatarFallback className="rounded-lg">
                  {getUserInitials(name)}
                </AvatarFallback>
              </Avatar>
              <div className="grid flex-1 text-left text-sm leading-tight">
                <span className="truncate font-medium">{name}</span>
                <span className="truncate text-xs">{email}</span>
              </div>
              <ChevronsUpDown className="ml-auto size-4" />
            </SidebarMenuButton>
          </DropdownMenuTrigger>
          <DropdownMenuContent
            className="w-(--radix-dropdown-menu-trigger-width) min-w-56 rounded-lg"
            side={isMobile ? "bottom" : "right"}
            align="end"
            sideOffset={4}
          >
            <DropdownMenuLabel className="p-0 font-normal">
              <div className="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
                <Avatar className="h-8 w-8 rounded-lg">
                  <AvatarFallback className="rounded-lg">
                    {getUserInitials(name)}
                  </AvatarFallback>
                </Avatar>
                <div className="grid flex-1 text-left text-sm leading-tight">
                  <span className="truncate font-medium">{name}</span>
                  <span className="truncate text-xs">{email}</span>
                </div>
              </div>
            </DropdownMenuLabel>
            <DropdownMenuSeparator />
            <DropdownMenuItem asChild>
              <Link href={route("dashboard.profile.edit")} className="flex w-full items-center">
                <span className="flex items-center flex-1">Profile</span>
                <User className="h-4 w-4 ml-auto" />
              </Link>
            </DropdownMenuItem>
            <DropdownMenuItem asChild>
              <Link href={route("dashboard.account")} className="flex w-full items-center">
                <span className="flex items-center flex-1">Account</span>
                <Shield className="h-4 w-4 ml-auto" />
              </Link>
            </DropdownMenuItem>
            <DropdownMenuSeparator />
            <div className="flex items-center justify-between px-2 py-1.5">
              <span className="text-sm text-muted-foreground">Theme</span>
              <div className="flex items-center gap-1">
                <button
                  type="button"
                  onClick={() => setTheme("light")}
                  className={`p-1.5 rounded-md transition-colors ${
                    theme === "light"
                      ? "bg-sidebar-accent text-sidebar-accent-foreground"
                      : "text-muted-foreground hover:bg-sidebar-accent hover:text-sidebar-accent-foreground"
                  }`}
                  title="Light"
                >
                  <Sun className="h-3.5 w-3.5" />
                </button>
                <button
                  type="button"
                  onClick={() => setTheme("dark")}
                  className={`p-1.5 rounded-md transition-colors ${
                    theme === "dark"
                      ? "bg-sidebar-accent text-sidebar-accent-foreground"
                      : "text-muted-foreground hover:bg-sidebar-accent hover:text-sidebar-accent-foreground"
                  }`}
                  title="Dark"
                >
                  <Moon className="h-3.5 w-3.5" />
                </button>
                <button
                  type="button"
                  onClick={() => setTheme("system")}
                  className={`p-1.5 rounded-md transition-colors ${
                    theme === "system"
                      ? "bg-sidebar-accent text-sidebar-accent-foreground"
                      : "text-muted-foreground hover:bg-sidebar-accent hover:text-sidebar-accent-foreground"
                  }`}
                  title="System"
                >
                  <Monitor className="h-3.5 w-3.5" />
                </button>
              </div>
            </div>
            <DropdownMenuSeparator />
            <DropdownMenuItem asChild>
              <Link href={route("logout")} method="post" as="button" className="w-full">
                <LogOut />
                Log out
              </Link>
            </DropdownMenuItem>
          </DropdownMenuContent>
        </DropdownMenu>
      </SidebarMenuItem>
    </SidebarMenu>
  )
}
