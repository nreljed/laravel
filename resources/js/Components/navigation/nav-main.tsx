import * as React from "react";
import { Link, usePage } from "@inertiajs/react";
import { ChevronRight } from "lucide-react";
import { Badge } from "@/Components/ui/badge";
import {
  SidebarMenu,
  SidebarMenuButton,
  SidebarMenuItem,
  SidebarMenuSub,
  SidebarMenuSubButton,
  SidebarMenuSubItem,
} from "@/Components/ui/sidebar";
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from "@/Components/ui/collapsible";

interface NavItem {
  title: string;
  url?: string;
  icon?: React.ComponentType<{ className?: string }> | string;
  items?: NavItem[];
  badge?: string;
  badgeVariant?: "default" | "secondary" | "outline" | "destructive";
  permission?: string;
  feature_slug?: string;
  showModeBadge?: boolean;
  groupLabel?: string;
}

interface NavMainProps {
  items?: NavItem[];
  defaultOpenKeys?: string[];
  iconMap?: Record<string, React.ComponentType<{ className?: string }>>;
}

function normalizeUrl(url: string | undefined): string {
  if (!url) return "";
  try {
    return new URL(url, globalThis.location?.origin).pathname;
  } catch {
    return url;
  }
}

const NavMain = ({ items = [], defaultOpenKeys = [], iconMap = {} }: NavMainProps) => {
  const inertiaPage = usePage();
  const inertiaUrl = inertiaPage.url;
  const currentUrl =
    globalThis.window !== undefined ? inertiaUrl || location.pathname : inertiaUrl || "";

  const isActive = React.useCallback(
    (item: NavItem) => {
      if (!item.url) return false;
      if (item.items && item.items.length > 0) {
        const hasActiveChild = item.items.some(
          (subItem) =>
            subItem.url &&
            normalizeUrl(subItem.url) !== normalizeUrl(item.url) &&
            currentUrl === normalizeUrl(subItem.url),
        );
        return hasActiveChild;
      }
      return currentUrl === normalizeUrl(item.url);
    },
    [currentUrl],
  );

  const isParentActive = React.useCallback(
    (item: NavItem) => {
      if (!item.items) return isActive(item);
      return item.items.some((subItem) => subItem.url && currentUrl === normalizeUrl(subItem.url));
    },
    [currentUrl, isActive],
  );

  const resolveIcon = React.useCallback(
    (iconProp: NavItem["icon"]): React.ComponentType<{ className?: string }> | null => {
      if (!iconProp) return null;
      if (typeof iconProp === "function" || (typeof iconProp === "object" && iconProp !== null)) {
        return iconProp as React.ComponentType<{ className?: string }>;
      }
      if (typeof iconProp === "string" && Object.hasOwn(iconMap, iconProp)) {
        return iconMap[iconProp] ?? null;
      }
      return null;
    },
    [iconMap],
  );

  return (
    <SidebarMenu>
      {items.map((item) => {
        const hasChildren = item.items && item.items.length > 0;
        const isItemActive = isActive(item);
        const parentActive = isParentActive(item);
        // eslint-disable-next-line @eslint-react/static-components -- icons are stable references from the iconMap, not created during render
        const IconComponent = resolveIcon(item.icon);

        if (hasChildren) {
          return (
            <Collapsible
              key={item.title}
              defaultOpen={parentActive || defaultOpenKeys.includes(item.title)}
              className="group/collapsible"
            >
              <SidebarMenuItem>
                <CollapsibleTrigger render={<div />}>
                  <SidebarMenuButton isActive={isItemActive} tooltip={item.title}>
                    {/* eslint-disable-next-line @eslint-react/static-components -- stable icon reference */}
                    {IconComponent && <IconComponent className="size-4 shrink-0" />}
                    <span className="flex-1 truncate group-data-[collapsible=icon]:hidden">
                      {item.title}
                    </span>
                    {item.badge && (
                      <Badge
                        variant={item.badgeVariant || "outline"}
                        className="ml-auto group-data-[collapsible=icon]:hidden text-[10px] px-1.5 py-0 h-4"
                      >
                        {item.badge}
                      </Badge>
                    )}
                    <ChevronRight className="ml-auto h-4 w-4 shrink-0 transition-transform duration-200 group-data-open/collapsible:rotate-90" />
                  </SidebarMenuButton>
                </CollapsibleTrigger>
                <CollapsibleContent>
                  <SidebarMenuSub>
                    {item.items?.map((subItem) => (
                      <SidebarMenuSubItem key={subItem.title}>
                        <SidebarMenuSubButton
                          render={<Link href={subItem.url ?? "#"} prefetch />}
                          isActive={currentUrl === normalizeUrl(subItem.url)}
                        >
                          <span>{subItem.title}</span>
                        </SidebarMenuSubButton>
                      </SidebarMenuSubItem>
                    ))}
                  </SidebarMenuSub>
                </CollapsibleContent>
              </SidebarMenuItem>
            </Collapsible>
          );
        }

        return (
          <SidebarMenuItem key={item.title}>
            <SidebarMenuButton
              render={<Link href={item.url} prefetch />}
              isActive={isItemActive}
              tooltip={item.title}
            >
              {/* eslint-disable-next-line @eslint-react/static-components -- stable icon reference */}
              {IconComponent && <IconComponent className="size-4 shrink-0" />}
              <span className="flex-1 truncate group-data-[collapsible=icon]:hidden">
                {item.title}
              </span>
              {item.badge && (
                <Badge
                  variant={item.badgeVariant || "outline"}
                  className="ml-auto group-data-[collapsible=icon]:hidden text-[10px] px-1.5 py-0 h-4"
                >
                  {item.badge}
                </Badge>
              )}
            </SidebarMenuButton>
          </SidebarMenuItem>
        );
      })}
    </SidebarMenu>
  );
};

export { NavMain };
export type { NavItem };
