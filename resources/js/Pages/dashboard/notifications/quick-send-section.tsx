import { Button } from "@/Components/ui/button";
import { BellDot } from "lucide-react";

const QUICK_BY_FEATURE: Record<
  string,
  { type: string; title: string; message: string; severity: string }[]
> = {
  core: [
    {
      type: "user.registered",
      title: "New User Registered: jdoe",
      message: "User jdoe (john@example.com) has created an account.",
      severity: "info",
    },
    {
      type: "password.changed",
      title: "Password Changed",
      message: "Your account password was changed successfully.",
      severity: "warning",
    },
    {
      type: "email.changed",
      title: "Email Address Updated",
      message: "Your account email was changed to user@example.com.",
      severity: "warning",
    },
  ],
};

const FEATURE_LABELS: Record<string, string> = {
  core: "Account",
};

interface QuickSendSectionProps {
  sending: string | null;
  onSend: (_overrides?: Record<string, string>) => Promise<void>;
}

export function QuickSendSection({ sending, onSend }: QuickSendSectionProps) {
  return (
    <div className="space-y-4">
      {Object.entries(QUICK_BY_FEATURE).map(([featureKey, items]) => (
        <div key={featureKey}>
          <div className="flex items-center gap-2 mb-2">
            <div className="h-1.5 w-1.5 rounded-full bg-muted-foreground/40" />
            <span className="text-xs font-medium text-muted-foreground uppercase tracking-wider">
              {FEATURE_LABELS[featureKey] || featureKey}
            </span>
          </div>
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-2">
            {items.map((qt) => (
              <Button
                key={qt.type}
                variant="outline"
                className="justify-start h-auto py-2.5 px-3"
                onClick={() =>
                  onSend({
                    type: qt.type,
                    title: qt.title,
                    message: qt.message,
                    severity: qt.severity,
                    feature: featureKey,
                  })
                }
                disabled={sending !== null}
              >
                <BellDot className="mr-2 h-4 w-4 shrink-0 text-muted-foreground" />
                <div className="text-left min-w-0">
                  <div className="text-xs font-medium text-foreground">{qt.type}</div>
                  <div className="text-[11px] text-muted-foreground truncate max-w-[260px]">
                    {qt.title}
                  </div>
                </div>
              </Button>
            ))}
          </div>
        </div>
      ))}
    </div>
  );
}
