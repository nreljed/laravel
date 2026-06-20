import { Switch } from "@/Components/ui/switch";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/Components/ui/select";
import { Card, CardContent } from "@/Components/ui/card";
import { Radio } from "lucide-react";

interface BookingSectionProps {
  data: Record<string, any>;
  onChange: (_field: string, _value: unknown) => void;
  loading?: boolean;
}

export function BookingModeSection({ data, onChange, loading }: BookingSectionProps) {
  return (
    <div className="space-y-6">
      <div className="flex items-start gap-2">
        <Radio className="h-6 w-6 shrink-0 mt-0.5" />
        <div>
          <h2 className="text-lg font-semibold">Booking Mode</h2>
          <p className="text-sm text-muted-foreground">
            Set how bookings are processed: auto-accept or manual review, default confirmation
            status
          </p>
        </div>
      </div>

      <Card>
        <CardContent className="pt-6">
          <div className="space-y-4">
            <div className="flex items-center justify-between">
              <div>
                <p className="font-medium text-sm">Instant Booking</p>
                <p className="text-sm text-muted-foreground">
                  When ON: guests can book immediately without host approval. When OFF: each booking
                  requires manual confirmation.
                </p>
              </div>
              <Switch
                checked={data.instant_book ?? true}
                onCheckedChange={(v) => onChange("instant_book", v)}
                disabled={loading}
              />
            </div>
            <div>
              <label className="text-sm font-medium mb-1 block">Default New Booking Status</label>
              <Select
                value={data.default_status || "pending"}
                onValueChange={(v) => onChange("default_status", v)}
                disabled={loading}
              >
                <SelectTrigger className="w-full">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="pending">Pending — requires manual confirmation</SelectItem>
                  <SelectItem value="confirmed">Confirmed — auto-approved</SelectItem>
                </SelectContent>
              </Select>
              <p className="text-xs text-muted-foreground mt-1">
                When Instant Booking is ON, use "Confirmed" to auto-approve. Use "Pending" to
                require review even with instant booking enabled.
              </p>
            </div>
          </div>
        </CardContent>
      </Card>
    </div>
  );
}

export default BookingModeSection;
