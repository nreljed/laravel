import { useMemo, useState, useCallback } from "react";
import { usePage } from "@inertiajs/react";
import { Switch } from "@/Components/ui/switch";
import { NumberInput } from "@/Components/ui/number-input";
import { Card, CardContent, CardHeader, CardTitle } from "@/Components/ui/card";
import { CalendarDays, Users, PawPrint, DollarSign } from "lucide-react";
import DateRangePicker from "./DateRangePicker";
import type { DateRange } from "react-day-picker";
import { calculatePrice } from "../../lib/calculatePrice";

function toDateStr(date: Date): string {
  const y = date.getFullYear();
  const m = String(date.getMonth() + 1).padStart(2, "0");
  const d = String(date.getDate()).padStart(2, "0");
  return `${y}-${m}-${d}`;
}

interface PricePreviewProps {
  settings: Record<string, any>;
}

function formatPriceStatic(
  amount: number | string,
  settings?: { property_default_currency?: string; property_currency_position?: string; property_thousands_separator?: string; property_decimal_separator?: string },
  currency?: string,
): string {
  const num = typeof amount === "string" ? Number.parseFloat(amount) : amount;
  if (Number.isNaN(num)) return "$0.00";
  const cur = currency || settings?.property_default_currency || "USD";
  try {
    return new Intl.NumberFormat("en-US", { style: "currency", currency: cur }).format(num);
  } catch {
    return `$${num.toFixed(2)}`;
  }
}

function PricePreview({ settings }: PricePreviewProps) {
  const [dateRange, setDateRange] = useState<DateRange | undefined>();
  const [guests, setGuests] = useState(2);
  const [hasPets, setHasPets] = useState(false);

  const { props: pageProps } = usePage() as any;
  const formattingSettings = pageProps.formatting_settings || {};
  const gs = useMemo(
    () => pageProps.booking_global_settings || {},
    [],
  );

  const checkIn = dateRange?.from ? toDateStr(dateRange.from) : "";
  const checkOut = dateRange?.to ? toDateStr(dateRange.to) : "";

  const result = useMemo(() => {
    if (!checkIn || !checkOut) return null;
    const merged = {
      ...settings,
      early_bird_discount_percent:
        gs.early_bird_discount_percent ?? settings.early_bird_discount_percent ?? 0,
      early_bird_days_before: gs.early_bird_days_before ?? settings.early_bird_days_before ?? 30,
      last_minute_discount_percent:
        gs.last_minute_discount_percent ?? settings.last_minute_discount_percent ?? 0,
      last_minute_days_within: gs.last_minute_days_within ?? settings.last_minute_days_within ?? 7,
    };
    return calculatePrice({
      checkIn,
      checkOut,
      guests,
      hasPets,
      settings: merged,
      createdAt: (() => {
        const now = new Date();
        return now.toISOString().split("T", 1)[0];
      })(),
    });
  }, [checkIn, checkOut, guests, hasPets, settings, gs]);

  const currency =
    settings.booking_currency || formattingSettings.property_default_currency || "USD";

  const formatCurrency = useCallback(
    (amount: number | string): string => {
      const effectiveSettings = {
        ...formattingSettings,
        property_default_currency: currency,
      };
      return formatPriceStatic(amount, effectiveSettings, currency);
    },
    [formattingSettings, currency],
  );

  return (
    <Card className="border-primary/30 bg-primary/5">
      <CardHeader>
        <CardTitle className="flex items-center gap-2 text-base">
          <DollarSign className="h-4 w-4 text-primary" />
          Live Price Preview
        </CardTitle>
        <p className="text-sm text-muted-foreground">
          Test your pricing by entering a sample stay. Updates in real time as you change rates
          above.
        </p>
      </CardHeader>
      <CardContent className="space-y-4">
        <div>
          <label className="text-xs font-medium mb-1 flex items-center gap-1">
            <CalendarDays className="h-3 w-3" /> Stay Dates
          </label>
          <DateRangePicker value={dateRange} onChange={setDateRange} />
        </div>

        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label className="text-xs font-medium mb-1 flex items-center gap-1">
              <Users className="h-3 w-3" /> Guests
            </label>
            <NumberInput value={guests} onValueChange={setGuests} min={1} max={50} />
          </div>
          <div className="flex items-center justify-between pt-5">
            <label className="text-xs font-medium flex items-center gap-1">
              <PawPrint className="h-3 w-3" /> Including Pets
            </label>
            <Switch checked={hasPets} onCheckedChange={setHasPets} />
          </div>
        </div>

        {result && result.nights > 0 ? (
          <div className="bg-background rounded-lg border p-4 space-y-2 text-sm">
            <div className="flex justify-between items-center border-b pb-2 mb-2">
              <span className="font-semibold">
                {result.applied_length_of_stay_rate ? "Length-of-Stay Rate" : "Standard Rate"}
              </span>
              <span className="text-xs text-muted-foreground">
                {result.nights} night{result.nights !== 1 ? "s" : ""}
              </span>
            </div>

            {result.nightly_rates.length > 0 && (
              <div className="max-h-32 overflow-y-auto space-y-1 mb-2">
                {result.nightly_rates.map((nr: { date: string; base_rate: string; is_weekend?: boolean }, i: number) => (
                  <div key={i} className="flex justify-between text-xs">
                    <span className={nr.is_weekend ? "text-amber-600 font-medium" : ""}>
                      {nr.date}
                      {nr.is_weekend ? " (weekend)" : ""}
                    </span>
                    <span>{formatCurrency(nr.base_rate)}</span>
                  </div>
                ))}
              </div>
            )}

            {result.applied_length_of_stay_rate && (
              <div className="flex justify-between text-xs mb-2 text-blue-600">
                <span>
                  Tiered rate applied ({formatCurrency(result.applied_length_of_stay_rate)}/night)
                </span>
                <span className="font-medium">{formatCurrency(result.base_nightly_rate)}</span>
              </div>
            )}

            {result.length_of_stay_discount_percent > 0 && (
              <div className="flex justify-between text-green-600">
                <span>LOS Discount ({result.length_of_stay_discount_percent}%)</span>
                <span>-{formatCurrency(result.length_of_stay_discount_amount)}</span>
              </div>
            )}

            {result.promo_discount_percent > 0 && (
              <div className="flex justify-between text-purple-600">
                <span>{result.promo_discount_label}</span>
                <span>-{formatCurrency(result.promo_discount_amount)}</span>
              </div>
            )}

            <div className="flex justify-between font-medium border-t pt-1">
              <span>Subtotal</span>
              <span>{formatCurrency(result.subtotal)}</span>
            </div>

            <div className="flex justify-between">
              <span>After Discount</span>
              <span>{formatCurrency(result.after_discount)}</span>
            </div>

            {result.service_fee > 0 && (
              <div className="flex justify-between">
                <span>Service Fee</span>
                <span>{formatCurrency(result.service_fee)}</span>
              </div>
            )}

            {result.cleaning_fee > 0 && (
              <div className="flex justify-between">
                <span>Cleaning Fee</span>
                <span>{formatCurrency(result.cleaning_fee)}</span>
              </div>
            )}

            {result.extra_guest_charge > 0 && (
              <div className="flex justify-between">
                <span>
                  Extra Guest ({guests - result.base_guests} ×{" "}
                  {formatCurrency(result.extra_guest_fee)}/night)
                </span>
                <span>{formatCurrency(result.extra_guest_charge)}</span>
              </div>
            )}

            {result.pet_fee > 0 && (
              <div className="flex justify-between">
                <span>Pet Fee</span>
                <span>{formatCurrency(result.pet_fee)}</span>
              </div>
            )}

            {result.damage_deposit > 0 && (
              <div className="flex justify-between text-muted-foreground">
                <span>Damage Deposit (refundable)</span>
                <span>{formatCurrency(result.damage_deposit)}</span>
              </div>
            )}

            <div className="flex justify-between text-base font-bold border-t pt-2 mt-2">
              <span>Total</span>
              <span className="text-primary">{formatCurrency(result.total)}</span>
            </div>
          </div>
        ) : checkIn && checkOut ? (
          <p className="text-sm text-muted-foreground text-center py-4">
            No nights calculated. Check your dates.
          </p>
        ) : (
          <p className="text-sm text-muted-foreground text-center py-4">
            Select check-in and check-out dates to preview pricing.
          </p>
        )}
      </CardContent>
    </Card>
  );
}

export default PricePreview;
