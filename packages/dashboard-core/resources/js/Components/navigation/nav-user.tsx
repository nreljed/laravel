import { useState } from "react";
import { Link, router, usePage } from "@inertiajs/react";
import {
  MoreVertical,
  LogOut,
  Sun,
  Moon,
  Monitor,
  AlertTriangle,
  LogIn,
  Hotel,
  UserRound,
  UserPlus,
  LayoutDashboard,
  CircleUserRound,
} from "lucide-react";
import { useTheme } from "next-themes";

import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuGroup,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/Components/ui/dropdown-menu";
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from "@/Components/ui/alert-dialog";
import { Avatar, AvatarImage, AvatarFallback } from "@/Components/ui/avatar";
import { SidebarMenu, SidebarMenuItem } from "@/Components/ui/sidebar";
import { useAuth, getUserInitials } from "@/hooks/use-auth";
import { cn } from "@/lib/utils";

interface NavUserProps {
  isMobile?: boolean;
  variant?: "default" | "basic";
}

export function NavUser({ isMobile = false, variant = "default" }: NavUserProps) {
  const { user, isLoggedIn } = useAuth();
  const { theme, setTheme } = useTheme();
  const pageProps = usePage().props;
  const userMode = (pageProps.userMode as string) || "guest";
  const canSwitchMode = pageProps.canSwitchMode as boolean;
  const [showLogoutDialog, setShowLogoutDialog] = useState(false);
  const isBasic = variant === "basic";

  if (!isLoggedIn || !user) {
    if (isBasic) {
      return (
        <DropdownMenu>
          <DropdownMenuTrigger
            render={
              <button
                type="button"
                className="flex items-center gap-2 rounded-md px-3 py-2 text-sm font-medium text-muted-foreground hover:text-foreground hover:bg-accent transition-colors"
              />
            }
          >
            <LogIn className="h-4 w-4" />
            <span>Sign In</span>
          </DropdownMenuTrigger>
          <DropdownMenuContent align="end" className="w-44">
            <DropdownMenuItem
              render={<Link href={route("login")} className="flex w-full items-center" />}
            >
              <LogIn className="mr-2 h-4 w-4" />
              <span>Sign In</span>
            </DropdownMenuItem>
            <DropdownMenuItem
              render={<Link href={route("register")} className="flex w-full items-center" />}
            >
              <UserPlus className="mr-2 h-4 w-4" />
              <span>Create Account</span>
            </DropdownMenuItem>
          </DropdownMenuContent>
        </DropdownMenu>
      );
    }

    const content = (
      <DropdownMenu>
        <DropdownMenuTrigger
          render={
            <button
              type="button"
              className="flex items-center gap-2 text-sidebar-foreground hover:bg-sidebar-accent hover:text-sidebar-accent-foreground rounded-md p-2 w-full"
            />
          }
        >
          <LogIn className="h-4 w-4" />
          <span className="text-sm font-medium">Sign In</span>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end" className="w-44">
          <DropdownMenuItem
            render={<Link href={route("login")} className="flex w-full items-center" />}
          >
            <LogIn className="mr-2 h-4 w-4" />
            <span>Sign In</span>
          </DropdownMenuItem>
          <DropdownMenuItem
            render={<Link href={route("register")} className="flex w-full items-center" />}
          >
            <UserPlus className="mr-2 h-4 w-4" />
            <span>Create Account</span>
          </DropdownMenuItem>
        </DropdownMenuContent>
      </DropdownMenu>
    );

    return (
      <SidebarMenu>
        <SidebarMenuItem>{content}</SidebarMenuItem>
      </SidebarMenu>
    );
  }

  const name = (user.name as string) || "User";
  const email = (user.email as string) || "";
  const avatarSrc = (user.avatar as string) || null;

  const triggerClass = isBasic
    ? "flex items-center justify-center rounded-full p-1 outline-hidden ring-sidebar-ring transition-colors hover:bg-sidebar-accent hover:text-sidebar-accent-foreground focus-visible:ring-2 active:bg-sidebar-accent active:text-sidebar-accent-foreground disabled:pointer-events-none disabled:opacity-50 data-[state=open]:bg-sidebar-accent data-[state=open]:text-sidebar-accent-foreground"
    : "flex w-full items-center gap-3 rounded-md p-2 text-left outline-hidden ring-sidebar-ring transition-[width,height,padding] hover:bg-sidebar-accent hover:text-sidebar-accent-foreground focus-visible:ring-2 active:bg-sidebar-accent active:text-sidebar-accent-foreground disabled:pointer-events-none disabled:opacity-50 data-[active=true]:bg-sidebar-accent data-[active=true]:font-medium data-[active=true]:text-sidebar-accent-foreground data-[state=open]:hover:bg-sidebar-accent data-[state=open]:hover:text-sidebar-accent-foreground md:h-8 md:p-2 group-data-[collapsible=icon]:justify-center";

  const dropdown = (
    <>
      <DropdownMenu>
        <DropdownMenuTrigger render={<button type="button" className={triggerClass} />}>
          <Avatar className="h-8 w-8 rounded-lg">
            <AvatarImage src={avatarSrc || undefined} alt={name} />
            <AvatarFallback className="rounded-lg">{getUserInitials(name)}</AvatarFallback>
          </Avatar>
          {!isBasic && (
            <>
              <div className="grid flex-1 text-left text-sm leading-tight group-data-[collapsible=icon]:hidden">
                <span className="truncate font-medium">{name}</span>
                <span className="truncate text-xs">{email}</span>
              </div>
              <MoreVertical className="ml-auto size-4 group-data-[collapsible=icon]:hidden" />
            </>
          )}
        </DropdownMenuTrigger>
        <DropdownMenuContent
          className={cn(isBasic ? "w-56 rounded-lg" : "w-(--anchor-width) min-w-56 rounded-lg")}
          side={isMobile || isBasic ? "bottom" : "right"}
          align="end"
          sideOffset={4}
        >
          <DropdownMenuGroup>
            <DropdownMenuLabel className="p-0 font-normal">
              <div className="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
                <Avatar className="h-8 w-8 rounded-lg">
                  <AvatarImage src={avatarSrc || undefined} alt={name} />
                  <AvatarFallback className="rounded-lg">{getUserInitials(name)}</AvatarFallback>
                </Avatar>
                <div className="grid flex-1 text-left text-sm leading-tight">
                  <span className="truncate font-medium">{name}</span>
                  <span className="truncate text-xs">{email}</span>
                </div>
              </div>
            </DropdownMenuLabel>
          </DropdownMenuGroup>
          <DropdownMenuSeparator />
          <DropdownMenuItem
            render={<Link href={route("dashboard")} className="flex w-full items-center" />}
          >
            <span className="flex items-center flex-1">Dashboard</span>
            <LayoutDashboard className="h-4 w-4 ml-auto text-muted-foreground" />
          </DropdownMenuItem>
          <DropdownMenuItem
            render={<Link href={route("dashboard.account")} className="flex w-full items-center" />}
          >
            <span className="flex items-center flex-1">Account</span>
            <CircleUserRound className="h-4 w-4 ml-auto text-muted-foreground" />
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
          {canSwitchMode && (
            <>
              <DropdownMenuSeparator />
              <DropdownMenuItem
                onClick={() =>
                  router.post(
                    route("dashboard.switch-mode"),
                    { mode: userMode === "guest" ? "host" : "guest" },
                    { preserveScroll: true },
                  )
                }
                className="cursor-pointer"
              >
                <span className="flex items-center flex-1 gap-2">
                  {userMode === "guest" ? (
                    <Hotel className="h-4 w-4 text-muted-foreground" />
                  ) : (
                    <UserRound className="h-4 w-4 text-muted-foreground" />
                  )}
                  Switch to {userMode === "guest" ? "Host" : "Guest"}
                </span>
                <span className="text-xs text-muted-foreground ml-auto">
                  {userMode === "guest" ? "Guest" : "Host"}
                </span>
              </DropdownMenuItem>
            </>
          )}
          <DropdownMenuSeparator />
          <DropdownMenuItem
            className="cursor-pointer text-destructive focus:text-destructive focus:bg-destructive/10"
            onClick={() => setShowLogoutDialog(true)}
          >
            <span className="flex items-center flex-1">Log out</span>
            <LogOut className="h-4 w-4 ml-auto text-muted-foreground" />
          </DropdownMenuItem>
        </DropdownMenuContent>
      </DropdownMenu>

      <AlertDialog open={showLogoutDialog} onOpenChange={setShowLogoutDialog}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle className="flex items-center gap-2">
              <AlertTriangle className="h-5 w-5 text-destructive" />
              Log out of your account?
            </AlertDialogTitle>
            <AlertDialogDescription>
              You will be redirected to the login page. You can log back in at any time.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>Cancel</AlertDialogCancel>
            <Link href={route("logout")} method="post" as="button">
              <AlertDialogAction className="bg-destructive text-destructive-foreground hover:bg-destructive/90">
                Log out
              </AlertDialogAction>
            </Link>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </>
  );

  if (isBasic) return dropdown;

  return (
    <SidebarMenu>
      <SidebarMenuItem>{dropdown}</SidebarMenuItem>
    </SidebarMenu>
  );
}
