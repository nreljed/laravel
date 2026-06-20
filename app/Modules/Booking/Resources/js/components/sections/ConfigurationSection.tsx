import { NumberInput } from "@/Components/ui/number-input";
import { Switch } from "@/Components/ui/switch";
import { Card, CardContent, CardHeader, CardTitle } from "@/Components/ui/card";
import { Timer, CalendarDays } from "lucide-react";

interface BookingSectionProps {
  data: Record<string, any>;
  onChange: (_field: string, _value: unknown) => void;
  loading?: boolean;
}

export function ConfigurationSection({ data, onChange, loading }: BookingSectionProps) {
  return (
    <div className="space-y-6">
      <div className="flex items-start gap-2">
        <Timer className="h-6 w-6 shrink-0 mt-0.5" />
        <div>
          <h2 className="text-lg font-semibold">Configuration</h2>
          <p className="text-sm text-muted-foreground">
            Control booking timing: how far in advance guests can book, cleaning buffer between
            stays, earliest/latest check-in dates, and checkout day pricing
          </p>
        </div>
      </div>

      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2 text-base">
            <Timer className="h-5 w-5" />
            Booking Timing
          </CardTitle>
          <p className="text-sm text-muted-foreground">
            Control advance notice requirements and preparation time between stays
          </p>
        </CardHeader>
        <CardContent>
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label className="text-sm font-medium mb-1 block">Advance Notice (days)</label>
              <NumberInput
                value={data.advance_notice_days ?? 1}
                onValueChange={(v: any) => onChange("advance_notice_days", v)}
                min={0}
                max={365}
                disabled={loading}
              />
              <p className="text-xs text-muted-foreground mt-1">
                Minimum days before today that a guest can check in. Set to 0 to allow same-day
                bookings.
              </p>
            </div>
            <div>
              <label className="text-sm font-medium mb-1 block">Preparation Time (days)</label>
              <NumberInput
                value={data.preparation_days ?? 1}
                onValueChange={(v: any) => onChange("preparation_days", v)}
                min={0}
                max={30}
                disabled={loading}
              />
              <p className="text-xs text-muted-foreground mt-1">
                Days blocked in the calendar after a guest checks out for cleaning or maintenance.
                Set to 0 if checkout day can be the same as the next guest's check-in. Default: 1.
              </p>
            </div>
          </div>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2 text-base">
            <CalendarDays className="h-5 w-5" />
            Booking Window
          </CardTitle>
          <p className="text-sm text-muted-foreground">
            Set how far in advance guests can book and when the earliest check-in can be
          </p>
        </CardHeader>
        <CardContent>
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label className="text-sm font-medium mb-1 block">Earliest Bookable Date</label>
              <NumberInput
                value={data.earliest_bookable_date ?? 0}
                onValueChange={(v: any) => onChange("earliest_bookable_date", v)}
                min={0}
                max={365}
                disabled={loading}
              />
              <p className="text-xs text-muted-foreground mt-1">
                How many days from today the earliest check-in can be. 0 = guests can book today.
              </p>
            </div>
            <div>
              <label className="text-sm font-medium mb-1 block">Latest Bookable Date</label>
              <NumberInput
                value={data.latest_bookable_date ?? 12}
                onValueChange={(v: any) => onChange("latest_bookable_date", v)}
                min={0}
                max={60}
                disabled={loading}
              />
              <p className="text-xs text-muted-foreground mt-1">
                How many months ahead guests can book. 0 = no limit. Default: 12.
              </p>
            </div>
          </div>
        </CardContent>
      </Card>

      <Card>
        <CardHeader className="flex flex-row items-center justify-between space-y-0">
          <div>
            <CardTitle className="text-sm">Include Checkout Day in Pricing</CardTitle>
            <p className="text-sm text-muted-foreground">
              When ON: the night of checkout is charged (guest pays for checkout night). When OFF:
              pricing stops at the night before checkout. Example: 3-night stay (Mon check-in → Thu
              check-out) charges 3 nights when ON, 2 nights when OFF.
            </p>
          </div>
          <Switch
            checked={data.include_checkout_day ?? false}
            onCheckedChange={(v) => onChange("include_checkout_day", v)}
            disabled={loading}
          />
        </CardHeader>
      </Card>
    </div>
  );
}

export default ConfigurationSection;
