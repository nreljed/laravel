import * as React from "react";
import { ChevronDown } from "lucide-react";

import { Collapsible, CollapsibleContent, CollapsibleTrigger } from "../ui/collapsible";
import { cn } from "../../lib/utils";

interface SidebarSectionProps {
  icon: React.ComponentType<{ className?: string }>;
  title: string;
  defaultOpen?: boolean;
  badge?: string | number | null;
  children: React.ReactNode;
  className?: string;
}

function SidebarSection({
  icon: Icon,
  title,
  defaultOpen = true,
  badge,
  children,
  className,
}: SidebarSectionProps) {
  return (
    <Collapsible defaultOpen={defaultOpen} className={cn("group/sidebar-section", className)}>
      <CollapsibleTrigger
        render={
          <button
            type="button"
            className="flex w-full items-center gap-2 px-4 py-3 text-left hover:bg-accent/50 transition-colors"
          />
        }
      >
        <Icon className="h-4 w-4 shrink-0 text-muted-foreground" />
        <span className="flex-1 truncate text-sm font-medium text-foreground">{title}</span>
        {badge != null && badge !== "" && (
          <span className="shrink-0 rounded-full bg-muted px-2 py-0.5 text-xs text-muted-foreground">
            {badge}
          </span>
        )}
        <ChevronDown className="h-4 w-4 shrink-0 text-muted-foreground transition-transform duration-200 group-data-open/sidebar-section:rotate-180" />
      </CollapsibleTrigger>
      <CollapsibleContent>
        <div className="px-4 pt-2 pb-4">{children}</div>
      </CollapsibleContent>
    </Collapsible>
  );
}

export { SidebarSection };
export type { SidebarSectionProps };