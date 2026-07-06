import * as React from "react";
import { Section, SectionField } from "@/Components/section";
import { Input } from "@/Components/ui/input";
import { Switch } from "@/Components/ui/switch";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/Components/ui/select";
import {
  ShoppingCart,
  Package,
  Clock,
  RotateCcw,
  Warehouse,
  Mail,
  CalendarDays,
} from "lucide-react";
import { Separator } from "@/Components/ui/separator";

const CURRENCY_OPTIONS = [
  { value: "USD", label: "US Dollar ($)" },
  { value: "EUR", label: "Euro (€)" },
  { value: "GBP", label: "British Pound (£)" },
  { value: "TND", label: "Tunisian Dinar (د.ت)" },
];

const CURRENCY_SYMBOL_OPTIONS = [
  { value: "$", label: "$" },
  { value: "€", label: "€" },
  { value: "£", label: "£" },
  { value: "د.ت", label: "د.ت" },
];

const WEIGHT_UNITS = [
  { value: "kg", label: "kg" },
  { value: "g", label: "g" },
  { value: "lb", label: "lb" },
  { value: "oz", label: "oz" },
];

const DIMENSION_UNITS = [
  { value: "cm", label: "cm" },
  { value: "m", label: "m" },
  { value: "in", label: "in" },
  { value: "ft", label: "ft" },
];

const BACKORDER_OPTIONS = [
  { value: "do_not_allow", label: "Do Not Allow" },
  { value: "allow_notify", label: "Allow with Notification" },
  { value: "allow", label: "Allow" },
];

interface ShopSectionProps {
  data: Record<string, string | undefined>;
  onChange: (_field: string, _value: string) => void;
  errors: Record<string, string>;
  loading?: boolean;
  onSubmit?: (_e: React.FormEvent) => void;
  asAccordion?: boolean;
}

export function ShopSection({
  data,
  onChange,
  errors,
  loading,
  onSubmit,
  asAccordion = false,
}: ShopSectionProps) {
  return (
    <div className="space-y-6">
      <Section
        title="Product Defaults"
        description="Default values applied to new products"
        icon={Package}
        asAccordion={asAccordion}
        onSubmit={onSubmit}
        loading={loading}
      >
        <div className="grid grid-cols-2 gap-4">
          <SectionField
            label="Default Product Status"
            htmlFor="default_product_status"
            error={errors?.default_product_status}
          >
            <Select
              value={data?.default_product_status ?? "draft"}
              onValueChange={(v) => onChange("default_product_status", v)}
            >
              <SelectTrigger id="default_product_status">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="draft">Draft</SelectItem>
                <SelectItem value="published">Published</SelectItem>
              </SelectContent>
            </Select>
          </SectionField>
          <SectionField
            label="Backorder Default"
            htmlFor="backorder_default"
            error={errors?.backorder_default}
            description="Default backorder behavior for new products"
          >
            <Select
              value={data?.backorder_default ?? "do_not_allow"}
              onValueChange={(v) => onChange("backorder_default", v)}
            >
              <SelectTrigger id="backorder_default">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                {BACKORDER_OPTIONS.map((opt) => (
                  <SelectItem key={opt.value} value={opt.value}>
                    {opt.label}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </SectionField>
        </div>
      </Section>

      <Section
        title="Currency & Units"
        description="Store currency and measurement units"
        icon={ShoppingCart}
        asAccordion
        onSubmit={onSubmit}
        loading={loading}
      >
        <div className="grid grid-cols-2 gap-4">
          <SectionField label="Currency" htmlFor="currency" error={errors?.currency}>
            <Select value={data?.currency ?? "EUR"} onValueChange={(v) => onChange("currency", v)}>
              <SelectTrigger id="currency">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                {CURRENCY_OPTIONS.map((opt) => (
                  <SelectItem key={opt.value} value={opt.value}>
                    {opt.label}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </SectionField>
          <SectionField
            label="Currency Symbol"
            htmlFor="currency_symbol"
            error={errors?.currency_symbol}
            description="Display symbol for prices"
          >
            <Select
              value={data?.currency_symbol ?? "€"}
              onValueChange={(v) => onChange("currency_symbol", v)}
            >
              <SelectTrigger id="currency_symbol">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                {CURRENCY_SYMBOL_OPTIONS.map((opt) => (
                  <SelectItem key={opt.value} value={opt.value}>
                    {opt.label}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </SectionField>
          <SectionField
            label="Currency Position"
            htmlFor="currency_position"
            error={errors?.currency_position}
          >
            <Select
              value={data?.currency_position ?? "before"}
              onValueChange={(v) => onChange("currency_position", v)}
            >
              <SelectTrigger id="currency_position">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="before">Before (€10)</SelectItem>
                <SelectItem value="after">After (10€)</SelectItem>
              </SelectContent>
            </Select>
          </SectionField>
        </div>

        <div className="grid grid-cols-2 gap-4">
          <SectionField label="Weight Unit" htmlFor="weight_unit" error={errors?.weight_unit}>
            <Select
              value={data?.weight_unit ?? "kg"}
              onValueChange={(v) => onChange("weight_unit", v)}
            >
              <SelectTrigger id="weight_unit">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                {WEIGHT_UNITS.map((u) => (
                  <SelectItem key={u.value} value={u.value}>
                    {u.label}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </SectionField>
          <SectionField
            label="Dimension Unit"
            htmlFor="dimension_unit"
            error={errors?.dimension_unit}
          >
            <Select
              value={data?.dimension_unit ?? "cm"}
              onValueChange={(v) => onChange("dimension_unit", v)}
            >
              <SelectTrigger id="dimension_unit">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                {DIMENSION_UNITS.map((u) => (
                  <SelectItem key={u.value} value={u.value}>
                    {u.label}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </SectionField>
        </div>
      </Section>

      <Section
        title="Inventory Management"
        description="Stock tracking and hold settings"
        icon={Warehouse}
        asAccordion
        onSubmit={onSubmit}
        loading={loading}
      >
        <div className="flex flex-wrap items-center gap-6 pt-2">
          <label className="flex items-center gap-2 cursor-pointer">
            <Switch
              id="inventory_enabled"
              checked={data?.inventory_enabled !== "0"}
              onCheckedChange={(checked) => onChange("inventory_enabled", checked ? "1" : "0")}
              disabled={loading}
            />
            <span className="text-sm">Enable Inventory Tracking</span>
          </label>
        </div>

        <div className="grid grid-cols-2 gap-4 mt-4">
          <SectionField
            label="Low Stock Threshold"
            htmlFor="low_stock_threshold"
            error={errors?.low_stock_threshold}
            description="Default threshold for low stock warnings on new products"
          >
            <Input
              id="low_stock_threshold"
              type="number"
              min="0"
              max="999"
              value={data?.low_stock_threshold ?? "5"}
              onChange={(e) => onChange("low_stock_threshold", e.target.value)}
              disabled={loading}
            />
          </SectionField>
          <div />
        </div>

        <Separator className="my-4" />

        <div className="flex items-center gap-2 pb-1">
          <Clock className="size-4 text-muted-foreground" />
          <span className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
            Hold Stock
          </span>
        </div>

        <p className="text-xs text-muted-foreground mb-3">
          Temporarily reserve stock when a pending order is placed. Stock is released if the order
          is not completed within the set time.
        </p>

        <div className="flex flex-wrap items-center gap-6 pt-2">
          <label className="flex items-center gap-2 cursor-pointer">
            <Switch
              id="hold_stock_enabled"
              checked={data?.hold_stock_enabled !== "0"}
              onCheckedChange={(checked) => onChange("hold_stock_enabled", checked ? "1" : "0")}
              disabled={loading}
            />
            <span className="text-sm">Enable Hold Stock</span>
          </label>
        </div>

        <div className="grid grid-cols-2 gap-4 mt-4">
          <SectionField
            label="Hold Stock Minutes"
            htmlFor="hold_stock_minutes"
            error={errors?.hold_stock_minutes}
            description="How long (in minutes) stock is held before releasing. Max 10,080 (7 days)."
          >
            <Input
              id="hold_stock_minutes"
              type="number"
              min="1"
              max="10080"
              value={data?.hold_stock_minutes ?? "60"}
              onChange={(e) => onChange("hold_stock_minutes", e.target.value)}
              disabled={loading}
            />
          </SectionField>
        </div>
      </Section>

      <Section
        title="Abandoned Cart"
        description="Recover abandoned carts with automated email sequences"
        icon={Mail}
        asAccordion
        onSubmit={onSubmit}
        loading={loading}
      >
        <div className="flex flex-wrap items-center gap-6 pt-2">
          <label className="flex items-center gap-2 cursor-pointer">
            <Switch
              id="abandoned_cart_enabled"
              checked={data?.abandoned_cart_enabled !== "0"}
              onCheckedChange={(checked) => onChange("abandoned_cart_enabled", checked ? "1" : "0")}
              disabled={loading}
            />
            <span className="text-sm">Enable Abandoned Cart Recovery</span>
          </label>
        </div>

        <div className="grid grid-cols-2 gap-4 mt-4">
          <SectionField
            label="Hours Until Abandoned"
            htmlFor="abandoned_cart_hours_until_abandoned"
            error={errors?.abandoned_cart_hours_until_abandoned}
            description="Hours of inactivity before cart is marked abandoned (1-168)"
          >
            <Input
              id="abandoned_cart_hours_until_abandoned"
              type="number"
              min="1"
              max="168"
              value={data?.abandoned_cart_hours_until_abandoned ?? "1"}
              onChange={(e) => onChange("abandoned_cart_hours_until_abandoned", e.target.value)}
              disabled={loading}
            />
          </SectionField>
          <SectionField
            label="Sequence Hours"
            htmlFor="abandoned_cart_sequence_hours"
            error={errors?.abandoned_cart_sequence_hours}
            description="Comma-separated hours after abandonment for each email (e.g. 1,24,72)"
          >
            <Input
              id="abandoned_cart_sequence_hours"
              type="text"
              value={data?.abandoned_cart_sequence_hours ?? "1,24,72"}
              onChange={(e) => onChange("abandoned_cart_sequence_hours", e.target.value)}
              disabled={loading}
            />
          </SectionField>
        </div>
      </Section>

      <Section
        title="Sale Scheduling"
        description="Configure sale price scheduling options"
        icon={CalendarDays}
        asAccordion
        onSubmit={onSubmit}
        loading={loading}
      >
        <div className="flex flex-wrap items-center gap-6 pt-2">
          <label className="flex items-center gap-2 cursor-pointer">
            <Switch
              id="sale_scheduling_enabled"
              checked={data?.sale_scheduling_enabled !== "0"}
              onCheckedChange={(checked) =>
                onChange("sale_scheduling_enabled", checked ? "1" : "0")
              }
              disabled={loading}
            />
            <span className="text-sm">Enable Sale Scheduling</span>
          </label>
          <label className="flex items-center gap-2 cursor-pointer">
            <Switch
              id="sale_scheduling_normalize_to_day_boundaries"
              checked={data?.sale_scheduling_normalize_to_day_boundaries !== "0"}
              onCheckedChange={(checked) =>
                onChange("sale_scheduling_normalize_to_day_boundaries", checked ? "1" : "0")
              }
              disabled={loading}
            />
            <span className="text-sm">Normalize to Day Boundaries</span>
          </label>
        </div>
        <p className="text-xs text-muted-foreground mt-2">
          When enabled, sale start/end times are automatically adjusted to midnight for cleaner date
          handling.
        </p>
      </Section>

      <Section
        title="Feature Toggles"
        description="Enable or disable store features"
        icon={RotateCcw}
        asAccordion
        onSubmit={onSubmit}
        loading={loading}
      >
        <div className="flex flex-wrap items-center gap-6 pt-2">
          {[
            { key: "show_out_of_stock", label: "Show Out of Stock" },
            { key: "enable_reviews", label: "Enable Reviews" },
            { key: "enable_wishlist", label: "Enable Wishlist" },
            { key: "auto_complete_orders", label: "Auto-Complete Orders" },
          ].map(({ key, label }) => (
            <label key={key} className="flex items-center gap-2 cursor-pointer">
              <Switch
                id={key}
                checked={data?.[key] !== "0"}
                onCheckedChange={(checked) => onChange(key, checked ? "1" : "0")}
                disabled={loading}
              />
              <span className="text-sm">{label}</span>
            </label>
          ))}
        </div>
      </Section>
    </div>
  );
}
