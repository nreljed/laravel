import { Button } from "../../../Components/ui/button";
import { BellDot } from "lucide-react";

interface NotificationType {
  label: string;
  description?: string;
  default_channels?: string[];
  default_enabled?: boolean;
  required?: boolean;
  group?: string;
  severity?: string;
}

interface QuickSendSectionProps {
  sending: string | null;
  onSend: (_overrides?: Record<string, string>) => Promise<void>;
  notificationTypes: Record<string, Record<string, NotificationType>>;
}

export function QuickSendSection({
  sending,
  onSend,
  notificationTypes,
}: QuickSendSectionProps) {
  return (
    <div className="space-y-4">
      {Object.entries(notificationTypes).map(([group, types]) => (
        <div key={group}>
          <div className="flex items-center gap-2 mb-2">
            <div className="h-1.5 w-1.5 rounded-full bg-muted-foreground/40" />
            <span className="text-xs font-medium text-muted-foreground uppercase tracking-wider">
              {group}
            </span>
          </div>
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-2">
            {Object.entries(types).map(([key, cfg]) => (
              <Button
                key={key}
                variant="outline"
                className="justify-start h-auto py-2.5 px-3"
                onClick={() =>
                  onSend({
                    type: key,
                    title: cfg.label ?? key,
                    message: cfg.description ?? `A ${key} notification occurred.`,
                    severity: cfg.severity ?? "info",
                    module: group,
                  })
                }
                disabled={sending !== null}
              >
                <BellDot className="mr-2 h-4 w-4 shrink-0 text-muted-foreground" />
                <div className="text-left min-w-0">
                  <div className="text-xs font-medium text-foreground">{key}</div>
                  <div className="text-[11px] text-muted-foreground truncate max-w-[260px]">
                    {cfg.label ?? key}
                  </div>
                </div>
              </Button>
            ))}
          </div>
        </div>
      ))}
      {Object.keys(notificationTypes).length === 0 && (
        <p className="text-sm text-muted-foreground">
          No notification types are registered yet.
        </p>
      )}
    </div>
  );
}