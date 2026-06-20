import { usePage } from "@inertiajs/react";
import { Input } from "@/Components/ui/input";
import { Checkbox } from "@/Components/ui/checkbox";
import { Card, CardContent, CardHeader, CardTitle } from "@/Components/ui/card";
import { Clock, CalendarDays } from "lucide-react";

const WEEKDAYS = [
  { value: "mon", label: "Monday" },
  { value: "tue", label: "Tuesday" },
  { value: "wed", label: "Wednesday" },
  { value: "thu", label: "Thursday" },
  { value: "fri", label: "Friday" },
  { value: "sat", label: "Saturday" },
  { value: "sun", label: "Sunday" },
] as const;

interface BookingSectionProps {
  data: Record<string, any>;
  onChange: (_field: string, _value: unknown) => void;
  loading?: boolean;
}

export function ScheduleSection({ data, onChange, loading }: BookingSectionProps) {
  const { props: pageProps } = usePage() as any;
  const siteTimezone =
    pageProps?.booking_global_settings?._site_timezone_label ||
    pageProps?.booking_global_settings?._site_timezone ||
    "UTC";
  const checkinDays = (data.weekday_checkin_only as string[]) || [];
  const checkoutDays = (data.weekday_checkout_only as string[]) || [];

  const toggleDay = (list: string[], setter: (_val: string[]) => void, day: string) => {
    const updated = list.includes(day) ? list.filter((d) => d !== day) : [...list, day];
    setter(updated);
  };

  return (
    <div className="space-y-6">
      <div className="flex items-start gap-2">
        <CalendarDays className="h-6 w-6 shrink-0 mt-0.5" />
        <div>
          <h2 className="text-lg font-semibold">Schedule</h2>
          <p className="text-sm text-muted-foreground">
            Set check-in/out times and restrict which days of the week guests can arrive or leave
          </p>
        </div>
      </div>

      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2 text-base">
            <Clock className="h-5 w-5" />
            Check-in / Check-out Times
          </CardTitle>
        </CardHeader>
        <CardContent>
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label className="text-sm font-medium mb-1 block">Check-in Time</label>
              <Input
                type="time"
                value={data.check_in_time || "15:00"}
                onChange={(e) => onChange("check_in_time", e.target.value)}
                disabled={loading}
                className="[color-scheme:light] dark:[color-scheme:dark]"
              />
            </div>
            <div>
              <label className="text-sm font-medium mb-1 block">Check-out Time</label>
              <Input
                type="time"
                value={data.check_out_time || "11:00"}
                onChange={(e) => onChange("check_out_time", e.target.value)}
                disabled={loading}
                className="[color-scheme:light] dark:[color-scheme:dark]"
              />
            </div>
          </div>
          <p className="text-xs text-muted-foreground mt-2">
            All times are in <strong>{siteTimezone}</strong>.
          </p>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2 text-base">
            <CalendarDays className="h-5 w-5" />
            Weekday Booking Rules
          </CardTitle>
          <p className="text-sm text-muted-foreground">
            Choose which weekdays guests can check in or check out. Leave all unchecked (or select
            all) to allow any day. This blocks dates in the calendar so guests can only select
            allowed days as arrival/departure dates.
          </p>
        </CardHeader>
        <CardContent>
          <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
              <h4 className="text-sm font-medium mb-3">Allowed Check-in Days</h4>
              <div className="space-y-2">
                {WEEKDAYS.map((day) => (
                  <label key={day.value} className="flex items-center gap-2">
                    <Checkbox
                      checked={checkinDays.includes(day.value)}
                      onCheckedChange={() =>
                        toggleDay(
                          checkinDays,
                          (v) => onChange("weekday_checkin_only", v),
                          day.value,
                        )
                      }
                      disabled={loading}
                    />
                    <span className="text-sm">{day.label}</span>
                  </label>
                ))}
              </div>
            </div>
            <div>
              <h4 className="text-sm font-medium mb-3">Allowed Check-out Days</h4>
              <div className="space-y-2">
                {WEEKDAYS.map((day) => (
                  <label key={day.value} className="flex items-center gap-2">
                    <Checkbox
                      checked={checkoutDays.includes(day.value)}
                      onCheckedChange={() =>
                        toggleDay(
                          checkoutDays,
                          (v) => onChange("weekday_checkout_only", v),
                          day.value,
                        )
                      }
                      disabled={loading}
                    />
                    <span className="text-sm">{day.label}</span>
                  </label>
                ))}
              </div>
            </div>
          </div>
        </CardContent>
      </Card>
    </div>
  );
}

export default ScheduleSection;
