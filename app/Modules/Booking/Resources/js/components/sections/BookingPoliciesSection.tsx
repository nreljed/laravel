import { Textarea } from "@/Components/ui/textarea";
import { Switch } from "@/Components/ui/switch";
import { NumberInput } from "@/Components/ui/number-input";
import { Card, CardContent, CardHeader, CardTitle } from "@/Components/ui/card";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/Components/ui/select";
import { Users } from "lucide-react";

interface BookingSectionProps {
  data: Record<string, any>;
  onChange: (_field: string, _value: unknown) => void;
  loading?: boolean;
}

export function BookingPoliciesSection({ data, onChange, loading }: BookingSectionProps) {
  return (
    <div className="space-y-6">
      <div className="flex items-start gap-2">
        <Users className="h-6 w-6 shrink-0 mt-0.5" />
        <div>
          <h2 className="text-lg font-semibold">Guest Policies</h2>
          <p className="text-sm text-muted-foreground">
            Configure children, infants, smoking, unregistered guests, and early/late check-in/out
          </p>
        </div>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Guest Policies</CardTitle>
          <p className="text-sm text-muted-foreground">
            Define who can stay — children, infants, and smoking rules
          </p>
        </CardHeader>
        <CardContent>
          <div className="space-y-4">
            <div className="flex items-center justify-between">
              <div>
                <p className="font-medium text-sm">Children Allowed</p>
              </div>
              <Switch
                checked={data.children_allowed ?? true}
                onCheckedChange={(v) => onChange("children_allowed", v)}
                disabled={loading}
              />
            </div>
            <div className="flex items-center justify-between">
              <div>
                <p className="font-medium text-sm">Infants Allowed (under 2)</p>
              </div>
              <Switch
                checked={data.infants_allowed ?? true}
                onCheckedChange={(v) => onChange("infants_allowed", v)}
                disabled={loading}
              />
            </div>
            <div>
              <label className="text-sm font-medium mb-1 block">Smoking Policy</label>
              <Select
                value={data.smoking_allowed || "not_allowed"}
                onValueChange={(v) => onChange("smoking_allowed", v)}
                disabled={loading}
              >
                <SelectTrigger className="w-full">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="not_allowed">Not Allowed</SelectItem>
                  <SelectItem value="outside_only">Outside Only</SelectItem>
                  <SelectItem value="anywhere">Anywhere</SelectItem>
                </SelectContent>
              </Select>
            </div>
          </div>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>Unregistered Visitors</CardTitle>
          <p className="text-sm text-muted-foreground">
            Allow registered guests to have daytime visitors and set limits
          </p>
        </CardHeader>
        <CardContent>
          <div className="space-y-4">
            <div className="flex items-center justify-between">
              <div>
                <p className="font-medium text-sm">Allow Unregistered Guests</p>
                <p className="text-sm text-muted-foreground">
                  Allow registered guests to have daytime visitors
                </p>
              </div>
              <Switch
                checked={data.allow_unregistered_guests ?? false}
                onCheckedChange={(v) => onChange("allow_unregistered_guests", v)}
                disabled={loading}
              />
            </div>
            {data.allow_unregistered_guests && (
              <>
                <div>
                  <label className="text-sm font-medium mb-1 block">Max Unregistered Guests</label>
                  <NumberInput
                    value={data.unregistered_guest_limit ?? 0}
                    onValueChange={(v: any) => onChange("unregistered_guest_limit", v)}
                    min={0}
                    disabled={loading}
                  />
                </div>
                <div>
                  <label className="text-sm font-medium mb-1 block">Visitor Rules</label>
                  <Textarea
                    value={data.unregistered_guest_rules || ""}
                    onChange={(e) => onChange("unregistered_guest_rules", e.target.value)}
                    placeholder="e.g., 'Daytime only', 'Must check in with host'"
                    rows={2}
                    disabled={loading}
                  />
                </div>
              </>
            )}
          </div>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>Early Check-in / Late Check-out</CardTitle>
          <p className="text-sm text-muted-foreground">
            Offer flexible arrival and departure times, with optional fees
          </p>
        </CardHeader>
        <CardContent>
          <div className="space-y-4">
            <div className="flex items-center justify-between">
              <div>
                <p className="font-medium text-sm">Allow Early Check-in</p>
              </div>
              <Switch
                checked={data.allow_early_check_in ?? false}
                onCheckedChange={(v) => onChange("allow_early_check_in", v)}
                disabled={loading}
              />
            </div>
            {data.allow_early_check_in && (
              <div>
                <label className="text-sm font-medium mb-1 block">Early Check-in Fee ($)</label>
                <input
                  type="number"
                  step="0.01"
                  min="0"
                  value={data.early_check_in_fee || ""}
                  onChange={(e) => onChange("early_check_in_fee", e.target.value)}
                  disabled={loading}
                  placeholder="0.00"
                  className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                />
              </div>
            )}
            <div className="flex items-center justify-between">
              <div>
                <p className="font-medium text-sm">Allow Late Check-out</p>
              </div>
              <Switch
                checked={data.allow_late_check_out ?? false}
                onCheckedChange={(v) => onChange("allow_late_check_out", v)}
                disabled={loading}
              />
            </div>
            {data.allow_late_check_out && (
              <div>
                <label className="text-sm font-medium mb-1 block">Late Check-out Fee ($)</label>
                <input
                  type="number"
                  step="0.01"
                  min="0"
                  value={data.late_check_out_fee || ""}
                  onChange={(e) => onChange("late_check_out_fee", e.target.value)}
                  disabled={loading}
                  placeholder="0.00"
                  className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                />
              </div>
            )}
          </div>
        </CardContent>
      </Card>
    </div>
  );
}

export default BookingPoliciesSection;
