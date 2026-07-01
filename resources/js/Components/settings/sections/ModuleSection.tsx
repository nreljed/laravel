import { useState } from "react";
import { router } from "@inertiajs/react";
import { Section } from "@/Components/section";
import { Switch } from "@/Components/ui/switch";
import { Puzzle } from "lucide-react";

interface ModuleSectionProps {
  availableModules: { id: number; name: string; slug: string; enabled: boolean; version: string }[];
  asAccordion?: boolean;
}

export function ModuleSection({ availableModules, asAccordion }: ModuleSectionProps) {
  const [toggling, setToggling] = useState<Record<number, boolean>>({});

  return (
    <Section
      title="Modules"
      description="Manage application modules."
      icon={Puzzle}
      asAccordion={asAccordion}
    >
      {availableModules.length === 0 ? (
        <p className="text-sm text-muted-foreground px-1">No modules available.</p>
      ) : (
        <div className="space-y-3 px-1">
          {availableModules.map((mod) => (
            <div key={mod.id} className="flex items-center justify-between rounded-lg border p-3">
              <div>
                <p className="text-sm font-medium">{mod.name}</p>
                <p className="text-xs text-muted-foreground">
                  v{mod.version} &middot; {mod.slug}
                </p>
              </div>
              <div className="flex items-center gap-2">
                <span className="text-xs text-muted-foreground">
                  {mod.enabled ? "Enabled" : "Disabled"}
                </span>
                <Switch
                  checked={mod.enabled}
                  disabled={toggling[mod.id]}
                  onCheckedChange={() => {
                    setToggling((prev) => ({ ...prev, [mod.id]: true }));
                    router.put(
                      route("dashboard.admin.modules.toggle", mod.id),
                      {},
                      {
                        preserveScroll: true,
                        preserveState: true,
                        replace: true,
                        onFinish: () => setToggling((prev) => ({ ...prev, [mod.id]: false })),
                      },
                    );
                  }}
                />
              </div>
            </div>
          ))}
        </div>
      )}
    </Section>
  );
}
