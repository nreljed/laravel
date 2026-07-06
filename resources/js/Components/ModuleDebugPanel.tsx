import { usePage } from "@inertiajs/react";
import { Bug, ChevronDown, ChevronRight, Check, X } from "lucide-react";
import { useState } from "react";
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from "@/Components/ui/collapsible";

interface ModuleDebugItem {
  name: string;
  slug: string;
  enabled: boolean;
  booted: boolean;
  dependencies: string[];
  nav: {
    parent?: { title: string; url: string | null };
    children?: { title: string; url: string | null }[];
  };
}

export function ModuleDebugPanel() {
  const pageProps = usePage().props as Record<string, unknown>;
  const debug = pageProps.debug as boolean | undefined;
  const moduleDebugData = pageProps.moduleDebugData as ModuleDebugItem[] | undefined;

  const [open, setOpen] = useState(false);

  if (!debug || !moduleDebugData?.length) return null;

  return (
    <div className="border-t border-border">
      <Collapsible open={open} onOpenChange={setOpen}>
        <CollapsibleTrigger className="flex w-full items-center gap-2 px-3 py-2 text-xs text-muted-foreground hover:text-foreground hover:bg-accent/50 transition-colors">
          <Bug className="h-3 w-3" />
          <span className="flex-1 text-left">Module Debug</span>
          {open ? <ChevronDown className="h-3 w-3" /> : <ChevronRight className="h-3 w-3" />}
        </CollapsibleTrigger>
        <CollapsibleContent className="px-3 pb-2 space-y-2">
          {moduleDebugData.map((mod) => {
            const statusIcon =
              mod.enabled && mod.booted ? (
                <Check className="h-3 w-3 text-green-500" />
              ) : (
                <X className="h-3 w-3 text-red-500" />
              );

            return (
              <div key={mod.slug} className="text-[11px] font-mono">
                <div className="flex items-center gap-1.5">
                  {statusIcon}
                  <span
                    className={
                      mod.enabled && mod.booted ? "text-foreground" : "text-muted-foreground"
                    }
                  >
                    {mod.name}
                  </span>
                  <span className="text-[10px] text-muted-foreground">({mod.slug})</span>
                  {mod.dependencies.length > 0 && (
                    <span className="text-[10px] text-muted-foreground">
                      deps: {mod.dependencies.join(",")}
                    </span>
                  )}
                </div>
                {mod.nav.parent && (
                  <div className="ml-4 text-muted-foreground">
                    <div className="flex items-center gap-1">
                      {mod.nav.parent.url ? (
                        <Check className="h-2.5 w-2.5 text-green-500 shrink-0" />
                      ) : (
                        <X className="h-2.5 w-2.5 text-red-500 shrink-0" />
                      )}
                      <span className="truncate">{mod.nav.parent.title}: </span>
                      <span className="truncate text-[10px]">{mod.nav.parent.url ?? "—"}</span>
                    </div>
                    {(mod.nav.children ?? []).map((child, i) => (
                      <div key={i} className="flex items-center gap-1 ml-3">
                        {child.url ? (
                          <Check className="h-2.5 w-2.5 text-green-500 shrink-0" />
                        ) : (
                          <X className="h-2.5 w-2.5 text-red-500 shrink-0" />
                        )}
                        <span className="truncate">{child.title}: </span>
                        <span className="truncate text-[10px]">{child.url ?? "—"}</span>
                      </div>
                    ))}
                  </div>
                )}
              </div>
            );
          })}
        </CollapsibleContent>
      </Collapsible>
    </div>
  );
}
