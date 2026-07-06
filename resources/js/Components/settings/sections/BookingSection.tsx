import * as React from "react";
import { Section, SectionField } from "@/Components/section";
import { Input } from "@/Components/ui/input";
import { Switch } from "@/Components/ui/switch";
import { CalendarDays, Clock, DollarSign, Bell, ArrowRight } from "lucide-react";

interface BookingSectionProps {
  data: Record<string, string | undefined>;
  onChange: (_field: string, _value: string) => void;
  errors: Record<string, string>;
  loading?: boolean;
  onSubmit?: (_e: React.FormEvent) => void;
  asAccordion?: boolean;
}

export function BookingSection({
  data,
  onChange,
  errors,
  loading,
  onSubmit,
  asAccordion = false,
}: BookingSectionProps) {
  return (
    <div className="space-y-6">
      <div className="rounded-md border border-border bg-muted/40 px-4 py-3 text-sm text-muted-foreground">
        <div className="flex items-start gap-2">
          <ArrowRight className="mt-0.5 size-4 shrink-0" />
          <span>
            Currency, number formatting, date format, time format, and timezone are set globally in{" "}
            <a
              href="/dashboard/admin/settings?tab=general"
              className="font-medium text-foreground underline underline-offset-2 hover:text-primary"
            >
              General settings
            </a>
            .
          </span>
        </div>
      </div>

      <Section
        title="General Booking Settings"
        description="Default check-in/out times and guest policies"
        icon={CalendarDays}
        asAccordion={asAccordion}
        onSubmit={onSubmit}
        loading={loading}
      >
        <SectionField
          label="Default Check-in Time"
          htmlFor="booking_check_in_time"
          error={errors?.booking_check_in_time}
          description="The standard check-in time for new bookings (24h format)."
        >
          <Input
            id="booking_check_in_time"
            type="time"
            value={data?.booking_check_in_time ?? "15:00"}
            onChange={(e) => onChange("booking_check_in_time", e.target.value)}
            disabled={loading}
            className="w-32"
          />
        </SectionField>

        <SectionField
          label="Default Check-out Time"
          htmlFor="booking_check_out_time"
          error={errors?.booking_check_out_time}
          description="The standard check-out time for new bookings (24h format)."
        >
          <Input
            id="booking_check_out_time"
            type="time"
            value={data?.booking_check_out_time ?? "11:00"}
            onChange={(e) => onChange("booking_check_out_time", e.target.value)}
            disabled={loading}
            className="w-32"
          />
        </SectionField>

        <SectionField
          label="Minimum Stay (nights)"
          htmlFor="booking_min_nights"
          error={errors?.booking_min_nights}
          description="Minimum number of nights required per booking."
        >
          <Input
            id="booking_min_nights"
            type="number"
            min={0}
            value={data?.booking_min_nights ?? "1"}
            onChange={(e) => onChange("booking_min_nights", e.target.value)}
            disabled={loading}
            className="w-24"
          />
        </SectionField>
      </Section>

      <Section
        title="Pricing Defaults"
        description="Default fee values for new properties"
        icon={DollarSign}
        asAccordion
        onSubmit={onSubmit}
        loading={loading}
      >
        <SectionField
          label="Default Cleaning Fee"
          htmlFor="booking_default_cleaning_fee"
          error={errors?.booking_default_cleaning_fee}
          description="The default cleaning fee applied to new properties."
        >
          <Input
            id="booking_default_cleaning_fee"
            type="number"
            min={0}
            step={0.01}
            value={data?.booking_default_cleaning_fee ?? "50"}
            onChange={(e) => onChange("booking_default_cleaning_fee", e.target.value)}
            disabled={loading}
            className="w-32"
          />
        </SectionField>

        <SectionField
          label="Service Fee Percentage"
          htmlFor="booking_service_fee_percent"
          error={errors?.booking_service_fee_percent}
          description="Percentage added to the subtotal as a service fee. Enter a value between 0 and 100."
        >
          <div className="flex items-center gap-2">
            <Input
              id="booking_service_fee_percent"
              type="number"
              min={0}
              max={100}
              step={0.5}
              value={data?.booking_service_fee_percent ?? "5"}
              onChange={(e) => onChange("booking_service_fee_percent", e.target.value)}
              disabled={loading}
              className="w-24"
            />
            <span className="text-sm text-muted-foreground">%</span>
          </div>
        </SectionField>

        <SectionField
          label="Default Damage Deposit"
          htmlFor="booking_default_damage_deposit"
          error={errors?.booking_default_damage_deposit}
          description="The default security deposit applied to new properties."
        >
          <Input
            id="booking_default_damage_deposit"
            type="number"
            min={0}
            step={0.01}
            value={data?.booking_default_damage_deposit ?? "200"}
            onChange={(e) => onChange("booking_default_damage_deposit", e.target.value)}
            disabled={loading}
            className="w-32"
          />
        </SectionField>
      </Section>

      <Section
        title="Auto-transitions"
        description="Automatic status transitions based on elapsed time"
        icon={Clock}
        asAccordion
        onSubmit={onSubmit}
        loading={loading}
      >
        <SectionField
          label="Auto Checkout Grace Period (hours)"
          htmlFor="booking_auto_checkout_hours"
          error={errors?.booking_auto_checkout_hours}
          description="After how many hours past the check-out time should a booking be automatically checked out?"
        >
          <Input
            id="booking_auto_checkout_hours"
            type="number"
            min={0}
            value={data?.booking_auto_checkout_hours ?? "24"}
            onChange={(e) => onChange("booking_auto_checkout_hours", e.target.value)}
            disabled={loading}
            className="w-24"
          />
        </SectionField>

        <SectionField
          label="No-Show After (days)"
          htmlFor="booking_no_show_days"
          error={errors?.booking_no_show_days}
          description="After how many days past the start date should a confirmed booking be marked as no-show?"
        >
          <Input
            id="booking_no_show_days"
            type="number"
            min={0}
            value={data?.booking_no_show_days ?? "2"}
            onChange={(e) => onChange("booking_no_show_days", e.target.value)}
            disabled={loading}
            className="w-24"
          />
        </SectionField>
      </Section>

      <Section
        title="Notifications"
        description="Booking-related notification preferences"
        icon={Bell}
        asAccordion
        onSubmit={onSubmit}
        loading={loading}
      >
        <div className="flex items-center gap-6 pt-2">
          <label className="flex items-center gap-2 cursor-pointer">
            <Switch
              id="booking_notify_on_create"
              checked={data?.booking_notify_on_create !== "0"}
              onCheckedChange={(checked) =>
                onChange("booking_notify_on_create", checked ? "1" : "0")
              }
              disabled={loading}
            />
            <span className="text-sm">Notify on new booking</span>
          </label>

          <label className="flex items-center gap-2 cursor-pointer">
            <Switch
              id="booking_notify_on_cancel"
              checked={data?.booking_notify_on_cancel !== "0"}
              onCheckedChange={(checked) =>
                onChange("booking_notify_on_cancel", checked ? "1" : "0")
              }
              disabled={loading}
            />
            <span className="text-sm">Notify on cancellation</span>
          </label>
        </div>

        <div className="flex items-center gap-6 pt-2">
          <label className="flex items-center gap-2 cursor-pointer">
            <Switch
              id="booking_auto_confirm"
              checked={data?.booking_auto_confirm === "1"}
              onCheckedChange={(checked) => onChange("booking_auto_confirm", checked ? "1" : "0")}
              disabled={loading}
            />
            <span className="text-sm">Auto-confirm new bookings</span>
          </label>
        </div>
      </Section>
    </div>
  );
}
