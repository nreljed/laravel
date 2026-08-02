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
import { SidebarMenu, SidebarMenuItem } from "@/Components/ui/sidebar";

interface NavLogoProps {
  logo?: string | null;
  favicon?: string | null;
  name?: string | null;
  appUrl?: string | null;
}

export function NavLogo({ logo, favicon, name, appUrl }: NavLogoProps) {
  const renderLogoIcon = () => {
    const src = favicon || logo;

    if (src) {
      return (
        <Avatar className="h-8 w-8 rounded-lg shrink-0">
          <AvatarImage src={src} alt={name || "App Logo"} />
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
          <div className="flex items-center gap-3 px-2 py-1.5 flex-1 group-data-[collapsible=icon]:justify-center">
            {renderLogoIcon()}
            <div className="grid flex-1 text-left text-sm leading-tight group-data-[collapsible=icon]:hidden">
              <span className="truncate font-medium">{name || "Cost Breaker 360"}</span>
            </div>
          </div>

          <DropdownMenu>
            <DropdownMenuTrigger
              render={
                <button
                  type="button"
                  className="inline-flex items-center justify-center rounded-md p-1 text-sidebar-foreground/60 hover:bg-sidebar-accent hover:text-sidebar-accent-foreground transition-colors group-data-[collapsible=icon]:hidden"
                  aria-label="More options"
                />
              }
            >
              <MoreVertical className="size-4" />
            </DropdownMenuTrigger>
            <DropdownMenuContent
              className="w-56 rounded-lg"
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
                <Link href={route("dashboard")} className="flex w-full items-center">
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
