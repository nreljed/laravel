import { Button } from "@/Components/ui/button";
import { BellDot } from "lucide-react";

const QUICK_BY_MODULE: Record<
  string,
  { type: string; title: string; message: string; severity: string }[]
> = {
  booking: [
    {
      type: "booking.created",
      title: "New Booking: Beach Villa #3",
      message: "Guest John has requested to book Beach Villa #3 from Jun 15 to Jun 20.",
      severity: "info",
    },
    {
      type: "booking.confirmed",
      title: "Booking Confirmed: Beach Villa #3",
      message: "Booking confirmed for Beach Villa #3 — Jun 15 to Jun 20.",
      severity: "info",
    },
    {
      type: "booking.cancelled",
      title: "Booking Cancelled: Mountain Cabin",
      message: "Guest Sarah cancelled their booking at Mountain Cabin.",
      severity: "warning",
    },
    {
      type: "booking.modified",
      title: "Booking Modified: City Apartment",
      message: "Booking #1283 dates changed to Jul 10 — Jul 15.",
      severity: "warning",
    },
  ],
  properties: [
    {
      type: "property.created",
      title: "New Property Listed: Downtown Loft",
      message: "Your property 'Downtown Loft' has been published.",
      severity: "info",
    },
    {
      type: "property.updated",
      title: "Property Updated: Downtown Loft",
      message: "Property 'Downtown Loft' details have been updated.",
      severity: "info",
    },
  ],
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

const MODULE_LABELS: Record<string, string> = {
  booking: "Booking",
  properties: "Properties",
  core: "Account",
};

interface QuickSendSectionProps {
  sending: string | null;
  onSend: (_overrides?: Record<string, string>) => Promise<void>;
}

export function QuickSendSection({ sending, onSend }: QuickSendSectionProps) {
  return (
    <div className="space-y-4">
      {Object.entries(QUICK_BY_MODULE).map(([moduleKey, items]) => (
        <div key={moduleKey}>
          <div className="flex items-center gap-2 mb-2">
            <div className="h-1.5 w-1.5 rounded-full bg-muted-foreground/40" />
            <span className="text-xs font-medium text-muted-foreground uppercase tracking-wider">
              {MODULE_LABELS[moduleKey] || moduleKey}
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
                    module: moduleKey,
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
