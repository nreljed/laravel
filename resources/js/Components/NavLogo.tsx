import { Building2, ExternalLink, MoreVertical } from "lucide-react";
import { Link } from "@inertiajs/react";
import {
  DropdownMenu,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
  DropdownMenuContent,
} from "@/Components/ui/dropdown-menu";
import { Avatar, AvatarFallback, AvatarImage } from "@/Components/ui/avatar";
import { SidebarMenu, SidebarMenuItem, SidebarMenuButton } from "@/Components/ui/sidebar";
import { cn } from "@/lib/utils";

interface NavLogoProps {
  logo?: string | null;
  name?: string | null;
  appUrl?: string | null;
}

export function NavLogo({ logo, name, appUrl }: NavLogoProps) {
  const renderLogoIcon = () => {
    if (logo) {
      return (
        <Avatar className="h-8 w-8 rounded-lg shrink-0">
          <AvatarImage src={logo} alt={name || "App Logo"} />
          <AvatarFallback className="rounded-lg">
            <Building2 className="h-4 w-4" />
          </AvatarFallback>
        </Avatar>
      );
    }

    return (
      <Avatar className="h-8 w-8 rounded-lg shrink-0">
        <AvatarFallback className="rounded-lg bg-primary">
          <Building2 className="h-4 w-4 text-primary-foreground" />
        </AvatarFallback>
      </Avatar>
    );
  };

  return (
    <SidebarMenu>
      <SidebarMenuItem>
        <div className="flex items-center gap-0 group-data-[collapsible=icon]:justify-center">
          <SidebarMenuButton asChild size="lg" className="flex-1">
            <a href={route("home")} className="flex items-center gap-3">
              {renderLogoIcon()}
              <div className="grid flex-1 text-left text-sm leading-tight group-data-[collapsible=icon]:hidden">
                <span className="truncate font-medium">{name || "Cost Breaker 360"}</span>
              </div>
            </a>
          </SidebarMenuButton>

          <DropdownMenu>
            <DropdownMenuTrigger asChild>
              <button
                type="button"
                className="inline-flex items-center justify-center rounded-md p-1 text-sidebar-foreground/60 hover:bg-sidebar-accent hover:text-sidebar-accent-foreground transition-colors group-data-[collapsible=icon]:hidden"
                aria-label="More options"
              >
                <MoreVertical className="size-4" />
              </button>
            </DropdownMenuTrigger>
            <DropdownMenuContent
              className={cn(
                "z-50 max-h-(--radix-dropdown-menu-content-available-height) min-w-[8rem] origin-(--radix-dropdown-menu-content-transform-origin) overflow-x-hidden overflow-y-auto rounded-md border bg-popover p-1 text-popover-foreground shadow-md data-[side=bottom]:slide-in-from-top-2 data-[side=left]:slide-in-from-right-2 data-[side=right]:slide-in-from-left-2 data-[side=top]:slide-in-from-bottom-2 data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=closed]:zoom-out-95 data-[state=open]:animate-in data-[state=open]:fade-in-0 data-[state=open]:zoom-in-95",
                "w-56 rounded-lg",
              )}
              side="right"
              align="start"
              sideOffset={4}
            >
              {appUrl && (
                <>
                  <DropdownMenuItem className="cursor-pointer">
                    <a
                      href={appUrl}
                      target="_self"
                      rel="noopener noreferrer"
                      className="flex w-full items-center"
                    >
                      <span className="flex items-center flex-1">Visit Website</span>
                      <ExternalLink className="h-4 w-4 ml-auto" />
                    </a>
                  </DropdownMenuItem>
                  <DropdownMenuSeparator />
                </>
              )}
              <DropdownMenuItem className="cursor-pointer">
                <Link href="/dashboard" className="flex w-full items-center">
                  <span className="flex items-center flex-1">Dashboard</span>
                </Link>
              </DropdownMenuItem>
            </DropdownMenuContent>
          </DropdownMenu>
        </div>
      </SidebarMenuItem>
    </SidebarMenu>
  );
}

export default NavLogo;
