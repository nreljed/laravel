import { Input } from "@nreljed/platform/Components/ui/input";
import { NumberInput } from "@nreljed/platform/Components/shared/number-input";
import { Card, CardContent, CardHeader, CardTitle } from "@nreljed/platform/Components/ui/card";
import { Alert, AlertDescription } from "@nreljed/platform/Components/ui/alert";
import { Repeater } from "@nreljed/platform/Components/shared/repeater";
import { useRepeaterConfig } from "@nreljed/platform/hooks/use-repeater-config";
import { Users, Info, DollarSign, Sun, Timer } from "lucide-react";
import PricePreview from "./PricePreview";

interface BookingSectionProps {
  data: Record<string, any>;
  onChange: (_field: string, _value: unknown) => void;
  loading?: boolean;
}

export function PricingSection({ data, onChange, loading }: BookingSectionProps) {
  const { getFields, getLimits } = (useRepeaterConfig as any)("booking");
  const weekdayPrices = (data.weekday_prices as Record<string, string | undefined>) || {};

  const handlePriceChange = (day: string, value: string) => {
    const updated = { ...weekdayPrices };
    if (["", null, undefined].includes(value)) {
      delete updated[day];
    } else {
      updated[day] = value;
    }
    onChange("weekday_prices", updated);
  };

  return (
    <div className="space-y-6">
      <div className="flex items-start gap-2">
        <DollarSign className="h-6 w-6 shrink-0 mt-0.5" />
        <div>
          <h2 className="text-lg font-semibold">Pricing & Rates</h2>
          <p className="text-sm text-muted-foreground">
            Set base rates, seasonal adjustments, per-day pricing, length-of-stay discounts, and
            fees
          </p>
        </div>
      </div>

      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2 text-base">
            <DollarSign className="h-5 w-5" />
            Base Rate & Fees
          </CardTitle>
          <p className="text-sm text-muted-foreground">
            Set the base nightly price, cleaning fee, and service charge for this property
          </p>
        </CardHeader>
        <CardContent>
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <div>
              <label className="text-sm font-medium mb-1 block">Price per 1 day/night ($)</label>
              <Input
                type="number"
                step="0.01"
                min="0"
                value={data.nightly_rate || ""}
                onChange={(e) => onChange("nightly_rate", e.target.value)}
                disabled={loading}
                placeholder="0.00"
              />
              <p className="text-xs text-muted-foreground mt-1">
                The starting price per night. Seasonal rates, per-day overrides, and length-of-stay
                discounts are applied on top.
              </p>
            </div>
            <div>
              <label className="text-sm font-medium mb-1 block">Cleaning Fee ($)</label>
              <Input
                type="number"
                step="0.01"
                min="0"
                value={data.cleaning_fee || ""}
                onChange={(e) => onChange("cleaning_fee", e.target.value)}
                disabled={loading}
                placeholder="0.00"
              />
            </div>
            <div>
              <label className="text-sm font-medium mb-1 block">Service Fee</label>
              <div className="flex gap-2 mb-2">
                <button
                  type="button"
                  className={`flex-1 px-3 py-1.5 text-xs font-medium rounded-md border transition-colors ${(data.service_fee_type || "fixed") === "fixed" ? "bg-primary text-primary-foreground border-primary" : "bg-background text-muted-foreground border-input hover:bg-accent"}`}
                  onClick={() => onChange("service_fee_type", "fixed")}
                  disabled={loading}
                >
                  Fixed ($)
                </button>
                <button
                  type="button"
                  className={`flex-1 px-3 py-1.5 text-xs font-medium rounded-md border transition-colors ${data.service_fee_type === "percentage" ? "bg-primary text-primary-foreground border-primary" : "bg-background text-muted-foreground border-input hover:bg-accent"}`}
                  onClick={() => onChange("service_fee_type", "percentage")}
                  disabled={loading}
                >
                  Percentage (%)
                </button>
              </div>
              <div className="relative">
                {(data.service_fee_type || "fixed") === "percentage" && (
                  <span className="absolute right-3 top-1/2 -translate-y-1/2 text-sm text-muted-foreground">
                    %
                  </span>
                )}
                <Input
                  type="number"
                  step="0.01"
                  min="0"
                  value={data.service_fee || ""}
                  onChange={(e) => onChange("service_fee", e.target.value)}
                  disabled={loading}
                  placeholder="0.00"
                />
              </div>
              <p className="text-xs text-muted-foreground mt-1">
                {data.service_fee_type === "percentage"
                  ? "Percentage of subtotal (after discounts)"
                  : "Flat fee per booking"}
              </p>
            </div>
          </div>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2 text-base">
            <Timer className="h-5 w-5" />
            Stay Length
          </CardTitle>
          <p className="text-sm text-muted-foreground">
            Control how short or long a stay can be. Min nights enforced at booking; max nights
            prevents excessively long stays. Also used as thresholds for length-of-stay tiered
            rates.
          </p>
        </CardHeader>
        <CardContent>
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label className="text-sm font-medium mb-1 block">Minimum Nights</label>
              <NumberInput
                value={data.min_nights ?? 1}
                onValueChange={(v: any) => onChange("min_nights", v)}
                min={1}
                max={365}
                disabled={loading}
              />
            </div>
            <div>
              <label className="text-sm font-medium mb-1 block">Maximum Nights</label>
              <NumberInput
                value={data.max_nights ?? 30}
                onValueChange={(v: any) => onChange("max_nights", v)}
                min={1}
                max={365}
                disabled={loading}
              />
            </div>
          </div>
        </CardContent>
      </Card>

      <Repeater
        label="Length-of-Stay Rates"
        description="Set explicit per-night rates for longer stays. The highest tier whose min nights is met applies to each night not overridden by per-day pricing. Example: set 'From 7 nights at $45/night' to replace the default rate for weekly stays."
        value={data.length_of_stay_rates || []}
        onChange={(val: any) => onChange("length_of_stay_rates", val)}
        fields={Object.values(getFields("length_of_stay_rates"))}
        maxItems={getLimits("length_of_stay_rates").max}
        minItems={getLimits("length_of_stay_rates").min}
        accordionMode={true}
        titleField="label"
        accordionDefaultOpen={true}
        disabled={loading}
      />

      <Repeater
        label="Seasonal Rates"
        description="Override the default nightly rate for specific date ranges (e.g., summer, holidays). Can also override weekly/monthly rates per season."
        value={data.seasonal_rates || []}
        onChange={(val: any) => onChange("seasonal_rates", val)}
        fields={Object.values(getFields("seasonal_rates"))}
        maxItems={getLimits("seasonal_rates").max}
        minItems={getLimits("seasonal_rates").min}
        accordionMode={true}
        titleField="label"
        accordionDefaultOpen={true}
        disabled={loading}
      />

      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2 text-base">
            <Sun className="h-5 w-5" />
            Per-Day Pricing
          </CardTitle>
          <p className="text-sm text-muted-foreground">
            Set explicit nightly prices for specific days of the week. Leave blank to use the
            default nightly rate (or seasonal rate if applicable).
          </p>
        </CardHeader>
        <CardContent>
          <div className="grid grid-cols-2 sm:grid-cols-4 md:grid-cols-7 gap-3">
            {["mon", "tue", "wed", "thu", "fri", "sat", "sun"].map((day) => {
              const isWeekend = ["fri", "sat", "sun"].includes(day);
              const hasValue = weekdayPrices[day] !== undefined && weekdayPrices[day] !== "";
              return (
                <div
                  key={day}
                  className={`rounded-lg border p-3 text-center ${isWeekend ? "bg-amber-50 dark:bg-amber-950/20 border-amber-200 dark:border-amber-800" : ""}`}
                >
                  <label
                    className={`text-xs font-semibold uppercase mb-1 block ${isWeekend ? "text-amber-700 dark:text-amber-400" : ""}`}
                  >
                    {day === "mon"
                      ? "Mon"
                      : day === "tue"
                        ? "Tue"
                        : day === "wed"
                          ? "Wed"
                          : day === "thu"
                            ? "Thu"
                            : day === "fri"
                              ? "Fri"
                              : day === "sat"
                                ? "Sat"
                                : "Sun"}
                  </label>
                  <div className="relative">
                    <span className="absolute left-2 top-1/2 -translate-y-1/2 text-xs text-muted-foreground">
                      $
                    </span>
                    <Input
                      type="number"
                      step="0.01"
                      min="0"
                      placeholder="â€”"
                      value={hasValue ? weekdayPrices[day] : ""}
                      onChange={(e) => handlePriceChange(day, e.target.value)}
                      disabled={loading}
                      className="pl-5 text-center"
                    />
                  </div>
                  <p className="text-[10px] text-muted-foreground mt-1">
                    {hasValue ? "Override" : "Default"}
                  </p>
                </div>
              );
            })}
          </div>
        </CardContent>
      </Card>

      <Repeater
        label="Length-of-Stay Discounts"
        description="Offer percentage discounts for longer bookings. Stacks on top of length-of-stay tiered rates and all other pricing."
        value={data.length_of_stay_tiers || []}
        onChange={(val: any) => onChange("length_of_stay_tiers", val)}
        fields={Object.values(getFields("length_of_stay_tiers"))}
        maxItems={getLimits("length_of_stay_tiers").max}
        minItems={getLimits("length_of_stay_tiers").min}
        accordionMode={true}
        titleField="label"
        accordionDefaultOpen={true}
        disabled={loading}
      />

      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2 text-base">
            <Users className="h-5 w-5" />
            Per-Person Pricing
          </CardTitle>
          <p className="text-sm text-muted-foreground">
            Charge extra for guests beyond the base occupancy
          </p>
        </CardHeader>
        <CardContent>
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label className="text-sm font-medium mb-1 block">Base Guests Included in Rate</label>
              <NumberInput
                value={data.base_guests ?? 2}
                onValueChange={(v: any) => onChange("base_guests", v)}
                min={1}
                max={50}
                disabled={loading}
              />
              <p className="text-xs text-muted-foreground mt-1">
                Number of guests covered by the base nightly rate
              </p>
            </div>
            <div>
              <label className="text-sm font-medium mb-1 block">
                Extra Guest Fee ($ per guest per night)
              </label>
              <Input
                type="number"
                step="0.01"
                min="0"
                value={data.extra_guest_fee || ""}
                onChange={(e) => onChange("extra_guest_fee", e.target.value)}
                disabled={loading}
                placeholder="0.00"
              />
              <p className="text-xs text-muted-foreground mt-1">
                Charged for each additional guest above the base count
              </p>
            </div>
          </div>
        </CardContent>
      </Card>

      <PricePreview settings={data} />

      <Alert>
        <Info className="h-4 w-4" />
        <AlertDescription>
          Pricing priority: Per-Day Price â†’ Weekend Multiplier â†’ Length-of-Stay Tiered Rate â†’
          Seasonal Nightly Rate â†’ Base Nightly Rate. Length-of-stay discount % stacks on top. Final:
          subtotal â€” discount + cleaning + service + extra guest + pet fees.
        </AlertDescription>
      </Alert>
    </div>
  );
}

export default PricingSection;
