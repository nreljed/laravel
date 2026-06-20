import { Separator } from "@/Components/ui/separator";
import { DollarSign } from "lucide-react";

export interface NightlyRateItem {
  date: string;
  is_weekend?: boolean;
  base_rate: number;
}

export interface PricingData {
  nightly_rates?: NightlyRateItem[];
  nights?: number;
  weekend_nights?: number;
  subtotal?: number;
  base_nightly_rate?: number;
  length_of_stay_discount_amount?: number;
  length_of_stay_discount_percent?: number;
  after_discount?: number;
  extra_guest_charge?: number;
  extra_guest_fee?: number;
  base_guests?: number;
  guests?: number;
  cleaning_fee?: number;
  service_fee?: number;
  service_fee_type?: string;
  service_fee_rate?: number;
  pet_fee?: number;
  damage_deposit?: number;
  promo_discount_percent?: number;
  promo_discount_amount?: number;
  promo_discount_label?: string;
  total?: number;
  pricing_mode?: string;
  applied_length_of_stay_rate?: number;
  currency?: string;
}

const fmt = (value: number | undefined | null) => {
  if (value == null || value === 0) return "";
  return `$${value.toFixed(2)}`;
};

export const PricingBreakdown = ({ price }: { price: PricingData | null | undefined }) => {
  if (!price) return null;

  return (
    <div className="space-y-1.5 text-xs">
      <p className="flex items-center gap-1.5 text-sm font-semibold mb-2">
        <DollarSign className="h-3.5 w-3.5" /> Price Breakdown
      </p>

      {price.pricing_mode === "standard" && (
        <p className="text-muted-foreground text-[11px] italic">Standard nightly pricing</p>
      )}

      {Number(price.applied_length_of_stay_rate) > 0 && (
        <div className="bg-blue-50 dark:bg-blue-950/30 mb-2 rounded px-2 py-1">
          <p className="text-blue-700 dark:text-blue-300 text-[11px] font-medium">
            Length-of-stay rate: {fmt(price.applied_length_of_stay_rate)}/night
          </p>
        </div>
      )}

      {price.nightly_rates && price.nightly_rates.length > 0 ? (
        <div className="space-y-0.5">
          <div className="text-muted-foreground flex justify-between border-b pb-1 text-[11px] font-medium">
            <span>Date</span>
            <span>Rate</span>
          </div>
          {price.nightly_rates.map((night, i) => (
            <div key={i} className="flex justify-between border-border/30 border-b py-0.5 last:border-0">
              <span>
                {night.date}
                {night.is_weekend && <span className="text-amber-600 dark:text-amber-400 ml-1">(weekend)</span>}
              </span>
              <span className="font-medium">{fmt(night.base_rate)}</span>
            </div>
          ))}
          <div className="flex justify-between border-t pt-1 font-medium">
            <span>Subtotal ({price.nights} night{price.nights !== 1 ? "s" : ""})</span>
            <span>{fmt(price.subtotal)}</span>
          </div>
        </div>
      ) : (
        <div className="flex justify-between py-0.5">
          <span>{fmt(price.base_nightly_rate)} &times; {price.nights} night{price.nights !== 1 ? "s" : ""}</span>
          <span className="font-medium">{fmt(price.subtotal)}</span>
        </div>
      )}

      {Number(price.length_of_stay_discount_amount) > 0 && (
        <div className="flex justify-between font-medium text-green-600 dark:text-green-400">
          <span>LOS discount ({Number(price.length_of_stay_discount_percent).toFixed(1)}%)</span>
          <span>-{fmt(price.length_of_stay_discount_amount)}</span>
        </div>
      )}

      {Number(price.promo_discount_amount) > 0 && (
        <div className="flex justify-between font-medium text-purple-600 dark:text-purple-400">
          <span>{price.promo_discount_label || "Promo discount"}</span>
          <span>-{fmt(price.promo_discount_amount)}</span>
        </div>
      )}

      <Separator className="my-1" />

      {price.after_discount !== undefined && price.after_discount !== price.subtotal && (
        <div className="flex justify-between font-medium">
          <span>After discount</span>
          <span>{fmt(price.after_discount)}</span>
        </div>
      )}

      {Number(price.extra_guest_charge) > 0 && (
        <div className="flex justify-between">
          <span>Extra guest charge ({(price.guests || 0) - (price.base_guests || 2)} guest{(price.guests || 0) - (price.base_guests || 2) !== 1 ? "s" : ""} &times; {fmt(price.extra_guest_fee)}/guest/night)</span>
          <span>{fmt(price.extra_guest_charge)}</span>
        </div>
      )}

      {Number(price.cleaning_fee) > 0 && (
        <div className="flex justify-between">
          <span>Cleaning fee</span>
          <span>{fmt(price.cleaning_fee)}</span>
        </div>
      )}

      {Number(price.service_fee) > 0 && (
        <div className="flex justify-between">
          <span>Service fee{price.service_fee_type === "percentage" && Number(price.service_fee_rate) > 0 ? ` (${Number(price.service_fee_rate).toFixed(1)}%)` : ""}</span>
          <span>{fmt(price.service_fee)}</span>
        </div>
      )}

      {Number(price.pet_fee) > 0 && (
        <div className="flex justify-between">
          <span>Pet fee</span>
          <span>{fmt(price.pet_fee)}</span>
        </div>
      )}

      {Number(price.damage_deposit) > 0 && (
        <div className="flex justify-between text-amber-600 dark:text-amber-400">
          <span>Damage deposit (refundable)</span>
          <span>{fmt(price.damage_deposit)}</span>
        </div>
      )}

      <Separator className="my-1" />

      <div className="flex justify-between pt-0.5 text-sm font-bold">
        <span>Total</span>
        <span>{fmt(price.total)}</span>
      </div>

      {price.currency && price.currency !== "USD" && (
        <p className="text-muted-foreground text-right text-[10px]">Currency: {price.currency}</p>
      )}
    </div>
  );
};
