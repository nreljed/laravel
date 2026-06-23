import * as React from "react";
import { usePage } from "@inertiajs/react";
import { Section, SectionField } from "@/Components/section";
import { Input } from "@/Components/ui/input";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/Components/ui/select";
import { Switch } from "@/Components/ui/switch";
import { Button } from "@/Components/ui/button";
import { Checkbox } from "@/Components/ui/checkbox";
import { Label } from "@/Components/ui/label";
import { Spinner } from "@/Components/ui/spinner";
import { ErrorMessage } from "@/Components/error-message";
import { Separator } from "@/Components/ui/separator";
import { BOOKING_STATUS } from "../../lib/booking-status";
import {
  Clock,
  Calendar,
  GitBranch,
  Globe,
  Settings2,
  Play,
  Circle,
  RefreshCw,
  Mail,
  Percent,
  Info,
  AlertTriangle,
} from "lucide-react";

const CANCELLATION_POLICY_OPTIONS = [
  { value: "flexible", label: "Flexible" },
  { value: "moderate", label: "Moderate" },
  { value: "strict", label: "Strict" },
  { value: "firm", label: "Firm — full refund up to 30 days, 50% 7–30 days" },
  { value: "limited", label: "Limited — full refund up to 14 days, 50% 7–14 days" },
  { value: "long_term", label: "Long Term (28+ nights)" },
  { value: "non_refundable", label: "Non-Refundable" },
] as const;

const BOOKING_STATUS_OPTIONS = [
  { value: BOOKING_STATUS.Pending, label: "Pending — requires manual confirmation" },
  { value: BOOKING_STATUS.Confirmed, label: "Confirmed — auto-confirm new bookings" },
] as const;

const GUEST_CANCEL_STATUS_OPTIONS = [
  { value: BOOKING_STATUS.Pending, label: "Pending" },
  { value: BOOKING_STATUS.Confirmed, label: "Confirmed" },
  { value: BOOKING_STATUS.CheckedIn, label: "Checked In" },
  { value: BOOKING_STATUS.CheckedOut, label: "Checked Out" },
] as const;

const GUEST_MODIFY_STATUS_OPTIONS = [
  { value: BOOKING_STATUS.Pending, label: "Pending" },
  { value: BOOKING_STATUS.Confirmed, label: "Confirmed" },
  { value: BOOKING_STATUS.CheckedIn, label: "Checked In" },
  { value: BOOKING_STATUS.CheckedOut, label: "Checked Out" },
] as const;

interface BookingGlobalSettingsSectionProps {
  data: Record<string, any>;
  onChange: (_field: string, _value: unknown) => void;
  errors?: Record<string, string>;
  loading?: boolean;
  onSubmit: (_e: React.FormEvent) => void;
}

function SystemStatusSection({
  data,
  onChange,
  loading,
  errors,
}: {
  data: Record<string, unknown>;
  onChange: (_field: string, _value: unknown) => void;
  loading?: boolean;
  errors?: Record<string, string>;
}) {
  const [systemStatus, setSystemStatus] = React.useState<{
    cron_scheduled: boolean;
    cron_last_run: string;
    running: boolean;
  }>({ cron_scheduled: false, cron_last_run: "checking...", running: false });
  const [runResult, setRunResult] = React.useState<string | null>(null);

  React.useEffect(() => {
    (async () => {
      try {
        const r = await fetch("/dashboard/manage-bookings/system/status");
        const data = await r.json();
        setSystemStatus({
          cron_scheduled: data.cron_scheduled,
          cron_last_run: data.cron_last_run || "never",
          running: false,
        });
      } catch {
        setSystemStatus((s) => ({ ...s, cron_last_run: "unavailable" }));
      }
    })();
  }, []);

  const handleRunTransitions = async () => {
    setSystemStatus((s) => ({ ...s, running: true }));
    setRunResult(null);
    try {
      const res = await fetch("/dashboard/manage-bookings/system/run-transitions", {
        method: "POST",
        headers: { "X-Requested-With": "XMLHttpRequest" },
      });
      const data = await res.json();
      setRunResult(data.message || "Done");
      setSystemStatus((s) => ({
        ...s,
        cron_last_run: data.cron_last_run || s.cron_last_run,
        running: false,
      }));
    } catch {
      setRunResult("Failed to run transitions");
      setSystemStatus((s) => ({ ...s, running: false }));
    }
  };

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between p-3 rounded-lg bg-muted/50 border border-border/60">
        <div className="flex items-center gap-2">
          {systemStatus.cron_scheduled ? (
            <Circle className="h-3 w-3 fill-green-500 text-green-500" />
          ) : (
            <Circle className="h-3 w-3 fill-amber-500 text-amber-500" />
          )}
          <span className="text-sm font-medium text-foreground">
            Cron: {systemStatus.cron_scheduled ? "Active" : "Inactive"}
          </span>
        </div>
        <span className="text-xs text-muted-foreground">
          Last run: {systemStatus.cron_last_run}
        </span>
      </div>

      <Separator className="border-border/60" />

      <div className="space-y-2">
        <p className="text-sm font-medium text-foreground">Manual Auto-Transition Trigger</p>
        <p className="text-xs text-muted-foreground">
          Run auto-checkout and no-show cancellation checks immediately. Useful for testing or if
          cron is delayed.
        </p>
        <Button
          type="button"
          variant="outline"
          size="sm"
          onClick={handleRunTransitions}
          disabled={systemStatus.running}
          className="gap-2"
        >
          {systemStatus.running ? (
            <RefreshCw className="h-3.5 w-3.5 animate-spin" />
          ) : (
            <Play className="h-3.5 w-3.5" />
          )}
          {systemStatus.running ? "Running..." : "Run Auto-Transitions Now"}
        </Button>
        {runResult && <p className="text-xs text-muted-foreground mt-1">{runResult}</p>}
      </div>

      <Separator className="border-border/60" />

      <div className="space-y-4">
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <SectionField
            label="Damage Claim Window (days)"
            htmlFor="system_damage_claim_window_days"
            description="Days after checkout for host to file a damage claim."
            error={errors?.system_damage_claim_window_days}
            required
          >
            <Input
              id="system_damage_claim_window_days"
              type="number"
              min="1"
              max="90"
              value={String(data.system_damage_claim_window_days ?? 30)}
              onChange={(e) => onChange("system_damage_claim_window_days", e.target.value)}
              disabled={loading}
            />
          </SectionField>

          <SectionField
            label="Payout Hold (hours)"
            htmlFor="system_payout_hold_hours"
            description="Hours after check-in before host payout is released."
            error={errors?.system_payout_hold_hours}
            required
          >
            <Input
              id="system_payout_hold_hours"
              type="number"
              min="0"
              max="168"
              value={String(data.system_payout_hold_hours ?? 24)}
              onChange={(e) => onChange("system_payout_hold_hours", e.target.value)}
              disabled={loading}
            />
          </SectionField>
        </div>
      </div>
    </div>
  );
}

function isChecked(value: unknown): boolean {
  return value === true || value === "1";
}

export function BookingGlobalSettingsSection({
  data,
  onChange,
  errors,
  loading,
  onSubmit,
}: BookingGlobalSettingsSectionProps) {
  const d = data as Record<string, any>;
  const handleChange = React.useCallback(
    (field: string, value: unknown) => {
      onChange(field, value);
    },
    [onChange],
  );

  const toggleCancelStatus = (status: string, checked: boolean) => {
    const current = new Set<string>(
      Array.isArray(d?.allow_guest_cancellation_statuses)
        ? d.allow_guest_cancellation_statuses
        : [BOOKING_STATUS.Pending, BOOKING_STATUS.Confirmed],
    );
    if (checked) {
      current.add(status);
    } else {
      current.delete(status);
    }
    handleChange("allow_guest_cancellation_statuses", [...current]);
  };

  const toggleModifyStatus = (status: string, checked: boolean) => {
    const current = new Set<string>(
      Array.isArray(d?.allow_guest_modification_statuses)
        ? d.allow_guest_modification_statuses
        : [BOOKING_STATUS.Pending, BOOKING_STATUS.Confirmed, BOOKING_STATUS.CheckedIn],
    );
    if (checked) {
      current.add(status);
    } else {
      current.delete(status);
    }
    handleChange("allow_guest_modification_statuses", [...current]);
  };

  const { props: globalProps } = usePage() as any;
  const siteData = (globalProps?.site as Record<string, string>) || {};
  const gs = (globalProps?.booking_global_settings as Record<string, string>) || {};
  const globalTz = gs?._site_timezone_label || gs?._site_timezone || siteData?.timezone || "UTC";
  const globalDateFormat = siteData?.date_format || "d/m/Y";
  const globalTimeFormat = siteData?.time_format || "H:i";

  return (
    <form onSubmit={onSubmit}>
      <div className="space-y-6">
        {errors && Object.keys(errors).length > 0 && (
          <div className="mb-4">
            <ErrorMessage
              error="Please fix the errors below before saving."
              variant="alert"
              fieldErrors={errors as unknown as Record<string, string[]>}
            />
          </div>
        )}

        <div className="rounded-md border border-border bg-muted/40 px-4 py-3 text-sm text-muted-foreground">
          <div className="flex items-start gap-2">
            <Info className="mt-0.5 size-4 shrink-0 text-foreground" />
            <div>
              <p className="font-medium text-foreground">Global settings reference</p>
              <p className="mt-0.5">
                Timezone: <strong>{globalTz}</strong> &middot; Date format:{" "}
                <strong>{globalDateFormat}</strong> &middot; Time format:{" "}
                <strong>{globalTimeFormat}</strong>
                <br />
                These are set in{" "}
                <a
                  href="/dashboard/settings?tab=general"
                  className="font-medium underline underline-offset-2 hover:text-primary"
                >
                  General settings &rarr; Date &amp; Time Format
                </a>{" "}
                and apply to all modules.
              </p>
            </div>
          </div>
        </div>

        <Section
          title="Booking Defaults"
          description="Fallback values used when a property doesn't specify its own"
          icon={Clock}
          asAccordion
        >
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <SectionField
              label="Default Check-in Time"
              htmlFor="default_check_in_time"
              error={errors?.default_check_in_time}
              required
            >
              <Input
                id="default_check_in_time"
                type="time"
                value={d?.default_check_in_time ?? "15:00"}
                onChange={(e) => handleChange("default_check_in_time", e.target.value)}
                disabled={loading}
              />
            </SectionField>

            <SectionField
              label="Default Check-out Time"
              htmlFor="default_check_out_time"
              error={errors?.default_check_out_time}
              required
            >
              <Input
                id="default_check_out_time"
                type="time"
                value={d?.default_check_out_time ?? "11:00"}
                onChange={(e) => handleChange("default_check_out_time", e.target.value)}
                disabled={loading}
              />
            </SectionField>
          </div>
          <p className="text-xs text-muted-foreground mt-1">
            All times are in{" "}
            <strong>{d?._site_timezone_label || d?._site_timezone || "UTC"}</strong>.
          </p>

          <SectionField
            label="Default Cancellation Policy"
            htmlFor="default_cancellation_policy"
            error={errors?.default_cancellation_policy}
            description="Fallback cancellation policy for properties without one."
          >
            <Select
              value={d?.default_cancellation_policy ?? "flexible"}
              onValueChange={(v) => handleChange("default_cancellation_policy", v)}
              disabled={loading}
            >
              <SelectTrigger>
                <SelectValue placeholder="Select policy" />
              </SelectTrigger>
              <SelectContent>
                {CANCELLATION_POLICY_OPTIONS.map((o) => (
                  <SelectItem key={o.value} value={o.value}>
                    {o.label}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </SectionField>

          <Separator className="border-border/60" />
          <h3 className="text-sm font-semibold text-foreground pt-1">Cancellation Grace Period</h3>
          <p className="text-xs text-muted-foreground -mt-2">
            Guests can cancel for a full refund within this window after booking, provided the
            booking was made with enough notice before check-in.
          </p>

          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <SectionField
              label="Grace Period Hours"
              htmlFor="grace_period_hours"
              error={errors?.grace_period_hours}
              description="Hours after booking during which guest can cancel for full refund (1–72)."
              required
            >
              <Input
                id="grace_period_hours"
                type="number"
                min="1"
                max="72"
                value={d?.grace_period_hours ?? 24}
                onChange={(e) => handleChange("grace_period_hours", e.target.value)}
                disabled={loading}
              />
            </SectionField>

            <SectionField
              label="Minimum Advance Notice"
              htmlFor="grace_period_advance_days"
              error={errors?.grace_period_advance_days}
              description="Minimum days before check-in that the booking must have been made (1–30)."
              required
            >
              <Input
                id="grace_period_advance_days"
                type="number"
                min="1"
                max="30"
                value={d?.grace_period_advance_days ?? 7}
                onChange={(e) => handleChange("grace_period_advance_days", e.target.value)}
                disabled={loading}
              />
            </SectionField>
          </div>

          <Separator className="border-border/60" />

          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <SectionField
              label="Min Nights"
              htmlFor="default_min_nights"
              error={errors?.default_min_nights}
              description="Global minimum stay length."
              required
            >
              <Input
                id="default_min_nights"
                type="number"
                min="1"
                max="365"
                value={d?.default_min_nights ?? 1}
                onChange={(e) => handleChange("default_min_nights", e.target.value)}
                disabled={loading}
              />
            </SectionField>

            <SectionField
              label="Max Nights"
              htmlFor="default_max_nights"
              error={errors?.default_max_nights}
              description="Global maximum stay length."
              required
            >
              <Input
                id="default_max_nights"
                type="number"
                min="1"
                max="365"
                value={d?.default_max_nights ?? 30}
                onChange={(e) => handleChange("default_max_nights", e.target.value)}
                disabled={loading}
              />
            </SectionField>
          </div>

          <SectionField
            label="Default Deposit Refund Days"
            htmlFor="default_deposit_refund_days"
            error={errors?.default_deposit_refund_days}
            description="Global default for how many days after checkout before the deposit is returned (1-90)."
            required
          >
            <Input
              id="default_deposit_refund_days"
              type="number"
              min="1"
              max="90"
              value={d?.default_deposit_refund_days ?? 7}
              onChange={(e) => handleChange("default_deposit_refund_days", e.target.value)}
              disabled={loading}
            />
          </SectionField>

          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <SectionField
              label="Preparation Days"
              htmlFor="default_preparation_days"
              error={errors?.default_preparation_days}
              description="Days needed between bookings."
              required
            >
              <Input
                id="default_preparation_days"
                type="number"
                min="0"
                max="365"
                value={d?.default_preparation_days ?? 1}
                onChange={(e) => handleChange("default_preparation_days", e.target.value)}
                disabled={loading}
              />
            </SectionField>

            <SectionField
              label="Advance Notice (days)"
              htmlFor="default_advance_notice_days"
              error={errors?.default_advance_notice_days}
              description="Minimum days ahead a booking can be made."
              required
            >
              <Input
                id="default_advance_notice_days"
                type="number"
                min="0"
                max="365"
                value={d?.default_advance_notice_days ?? 1}
                onChange={(e) => handleChange("default_advance_notice_days", e.target.value)}
                disabled={loading}
              />
            </SectionField>
          </div>

          <SectionField
            label="Default Booking Status"
            htmlFor="default_booking_status"
            error={errors?.default_booking_status}
            description="Status assigned to new bookings by default."
          >
            <Select
              value={d?.default_booking_status ?? BOOKING_STATUS.Pending}
              onValueChange={(v) => handleChange("default_booking_status", v)}
              disabled={loading}
            >
              <SelectTrigger>
                <SelectValue placeholder="Select status" />
              </SelectTrigger>
              <SelectContent>
                {BOOKING_STATUS_OPTIONS.map((o) => (
                  <SelectItem key={o.value} value={o.value}>
                    {o.label}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </SectionField>
        </Section>

        <Section
          title="Calendar View"
          description="How many months the calendar shows by default and at maximum"
          icon={Calendar}
          asAccordion
        >
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <SectionField
              label="Default Range"
              htmlFor="calendar_default_range"
              error={errors?.calendar_default_range}
              description="Months shown when the calendar first loads."
              required
            >
              <Input
                id="calendar_default_range"
                type="number"
                min="1"
                max="12"
                value={d?.calendar_default_range ?? 2}
                onChange={(e) => handleChange("calendar_default_range", e.target.value)}
                disabled={loading}
              />
            </SectionField>

            <SectionField
              label="Maximum Range"
              htmlFor="calendar_max_range"
              error={errors?.calendar_max_range}
              description="Maximum months the user can select to view."
              required
            >
              <Input
                id="calendar_max_range"
                type="number"
                min="1"
                max="12"
                value={d?.calendar_max_range ?? 12}
                onChange={(e) => handleChange("calendar_max_range", e.target.value)}
                disabled={loading}
              />
            </SectionField>
          </div>
        </Section>

        <Section
          title="Auto-Transition Controls"
          description="Automatic status transitions for bookings past their dates"
          icon={Globe}
          asAccordion
        >
          <div className="space-y-6">
            <div className="rounded-lg border border-border/60 p-4 space-y-4">
              <SectionField
                label="Enable Auto Check-out"
                htmlFor="enable_auto_checkout"
                error={errors?.enable_auto_checkout}
                description="Automatically transition checked_in bookings to checked_out after the grace period."
              >
                <Switch
                  id="enable_auto_checkout"
                  checked={d?.enable_auto_checkout !== false}
                  onCheckedChange={(checked) => handleChange("enable_auto_checkout", checked)}
                  disabled={loading}
                />
              </SectionField>

              {d?.enable_auto_checkout !== false && (
                <SectionField
                  label="Check-out Grace Hours"
                  htmlFor="auto_checkout_grace_hours"
                  error={errors?.auto_checkout_grace_hours}
                  description="Hours after checkout date before auto-transitioning checked_in → checked_out (0-168). 24 = 1 day after checkout."
                  required
                >
                  <Input
                    id="auto_checkout_grace_hours"
                    type="number"
                    min="0"
                    max="168"
                    value={d?.auto_checkout_grace_hours ?? 24}
                    onChange={(e) => handleChange("auto_checkout_grace_hours", e.target.value)}
                    disabled={loading}
                  />
                </SectionField>
              )}
            </div>

            <div className="rounded-lg border border-border/60 p-4 space-y-4">
              <SectionField
                label="Enable No-Show Auto Cancel"
                htmlFor="enable_no_show_auto_cancel"
                error={errors?.enable_no_show_auto_cancel}
                description="Automatically cancel confirmed bookings if check-in passes without action (no-show)."
              >
                <Switch
                  id="enable_no_show_auto_cancel"
                  checked={d?.enable_no_show_auto_cancel !== false}
                  onCheckedChange={(checked) => handleChange("enable_no_show_auto_cancel", checked)}
                  disabled={loading}
                />
              </SectionField>

              {d?.enable_no_show_auto_cancel !== false && (
                <SectionField
                  label="No-Show Cancel Grace Hours"
                  htmlFor="auto_cancel_no_show_hours"
                  error={errors?.auto_cancel_no_show_hours}
                  description="Hours after check-in date before auto-cancelling confirmed → cancelled (0-168). 48 = 2 days after check-in."
                  required
                >
                  <Input
                    id="auto_cancel_no_show_hours"
                    type="number"
                    min="0"
                    max="168"
                    value={d?.auto_cancel_no_show_hours ?? 48}
                    onChange={(e) => handleChange("auto_cancel_no_show_hours", e.target.value)}
                    disabled={loading}
                  />
                </SectionField>
              )}
            </div>
          </div>
        </Section>

        <Section
          title="Booking Workflow"
          description="The booking lifecycle model and guest interaction rules"
          icon={GitBranch}
          asAccordion
        >
          <SectionField
            label="Allow Guest Cancellation From"
            description="Which booking statuses allow guests to cancel their own booking."
            error={errors?.allow_guest_cancellation_statuses}
          >
            <div className="flex flex-col gap-2">
              {GUEST_CANCEL_STATUS_OPTIONS.map((opt) => (
                <div key={opt.value} className="flex items-center gap-2">
                  <Checkbox
                    id={`cancel-status-${opt.value}`}
                    checked={
                      Array.isArray(d?.allow_guest_cancellation_statuses) &&
                      d.allow_guest_cancellation_statuses.includes(opt.value)
                    }
                    onCheckedChange={(checked) => toggleCancelStatus(opt.value, checked === true)}
                    disabled={loading}
                  />
                  <Label
                    htmlFor={`cancel-status-${opt.value}`}
                    className="text-sm font-normal cursor-pointer"
                  >
                    {opt.label}
                  </Label>
                </div>
              ))}
            </div>
          </SectionField>

          <SectionField
            label="Allow Guest Modification From"
            description="Which booking statuses allow guests to request date or guest count changes."
            error={errors?.allow_guest_modification_statuses}
          >
            <div className="flex flex-col gap-2">
              {GUEST_MODIFY_STATUS_OPTIONS.map((opt) => (
                <div key={opt.value} className="flex items-center gap-2">
                  <Checkbox
                    id={`modify-status-${opt.value}`}
                    checked={
                      Array.isArray(d?.allow_guest_modification_statuses) &&
                      d.allow_guest_modification_statuses.includes(opt.value)
                    }
                    onCheckedChange={(checked) => toggleModifyStatus(opt.value, checked === true)}
                    disabled={loading}
                  />
                  <Label
                    htmlFor={`modify-status-${opt.value}`}
                    className="text-sm font-normal cursor-pointer"
                  >
                    {opt.label}
                  </Label>
                </div>
              ))}
            </div>
          </SectionField>
        </Section>

        <Section
          title="Workflow & Integrations"
          description="WooCommerce payments integration — active when WooCommerce plugin is detected"
          icon={Globe}
          asAccordion
        >
          <div className="space-y-4">
            <div className="flex items-start gap-3 rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 dark:border-blue-800 dark:bg-blue-950/30">
              <Info className="mt-0.5 h-4 w-4 shrink-0 text-blue-600" />
              <div className="text-sm text-blue-700 dark:text-blue-400">
                WooCommerce integration is active and working. If the WooCommerce plugin is
                installed, booking payments are routed through WooCommerce orders automatically. No
                per-property configuration needed. This section is preliminary and will be expanded
                in a future update.
              </div>
            </div>

            <SectionField
              label="WooCommerce Product ID"
              htmlFor="woocommerce_product_id"
              error={errors?.woocommerce_product_id}
              description="Product ID used for booking fee line items."
            >
              <Input
                id="woocommerce_product_id"
                type="number"
                min="0"
                value={d?.woocommerce_product_id ?? 0}
                onChange={(e) => handleChange("woocommerce_product_id", e.target.value)}
                placeholder="0"
                disabled={loading}
              />
            </SectionField>
          </div>
        </Section>

        <Section
          title="Email Notifications"
          description="Automated emails sent to guests and hosts during the booking lifecycle"
          icon={Mail}
          asAccordion
        >
          <div className="space-y-4">
            <div className="flex items-start gap-3 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 dark:border-amber-800 dark:bg-amber-950/30">
              <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0 text-amber-600" />
              <div className="text-sm text-amber-700 dark:text-amber-400">
                Emails require SMTP configuration to be delivered reliably. Without it, WordPress
                uses{" "}
                <code className="rounded bg-amber-100 px-1 text-xs dark:bg-amber-900/50">
                  wp_mail()
                </code>{" "}
                which is often blocked or flagged as spam.{" "}
                <a
                  href="/dashboard/settings?tab=email"
                  className="font-medium underline underline-offset-2 hover:text-amber-800 dark:hover:text-amber-300"
                >
                  Configure SMTP &rarr;
                </a>
              </div>
            </div>

            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm font-medium text-foreground">Confirmation Email</p>
                <p className="text-xs text-muted-foreground">
                  Sent to guest when booking is confirmed. Always active.
                </p>
              </div>
              <span className="inline-flex items-center rounded-full bg-green-500 px-2 py-0.5 text-xs font-medium text-white">
                Always on
              </span>
            </div>

            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm font-medium text-foreground">Check-In Instructions</p>
                <p className="text-xs text-muted-foreground">
                  Sent on check-in day or when booking is checked in. Always active.
                </p>
              </div>
              <span className="inline-flex items-center rounded-full bg-green-500 px-2 py-0.5 text-xs font-medium text-white">
                Always on
              </span>
            </div>

            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm font-medium text-foreground">Cancellation Email</p>
                <p className="text-xs text-muted-foreground">
                  Sent to guest when booking is cancelled. Always active.
                </p>
              </div>
              <span className="inline-flex items-center rounded-full bg-green-500 px-2 py-0.5 text-xs font-medium text-white">
                Always on
              </span>
            </div>

            <Separator />

            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm font-medium text-foreground">Pre-Arrival Reminder</p>
                <p className="text-xs text-muted-foreground">
                  Sent X days before check-in for confirmed bookings.
                </p>
              </div>
              <Switch
                id="email_enable_pre_arrival"
                checked={isChecked(d?.email_enable_pre_arrival)}
                onCheckedChange={(checked) => handleChange("email_enable_pre_arrival", checked)}
                disabled={loading}
              />
            </div>

            {d?.email_enable_pre_arrival && (
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <SectionField
                  label="Pre-Arrival Days"
                  htmlFor="email_pre_arrival_days"
                  description="Days before check-in to send the pre-arrival reminder."
                  required
                >
                  <Input
                    id="email_pre_arrival_days"
                    type="number"
                    min="0"
                    max="30"
                    value={String(d?.email_pre_arrival_days ?? 3)}
                    onChange={(e) => handleChange("email_pre_arrival_days", e.target.value)}
                    disabled={loading}
                  />
                </SectionField>
              </div>
            )}

            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm font-medium text-foreground">Checkout Reminder</p>
                <p className="text-xs text-muted-foreground">
                  Sent day before checkout for checked-in bookings.
                </p>
              </div>
              <Switch
                id="email_enable_checkout_reminder"
                checked={isChecked(d?.email_enable_checkout_reminder)}
                onCheckedChange={(checked) =>
                  handleChange("email_enable_checkout_reminder", checked)
                }
                disabled={loading}
              />
            </div>

            {d?.email_enable_checkout_reminder && (
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <SectionField
                  label="Checkout Reminder Hours Before"
                  htmlFor="email_checkout_reminder_hours"
                  description="Hours before checkout to send the reminder (1-72)."
                  required
                >
                  <Input
                    id="email_checkout_reminder_hours"
                    type="number"
                    min="1"
                    max="72"
                    value={String(d?.email_checkout_reminder_hours ?? 24)}
                    onChange={(e) => handleChange("email_checkout_reminder_hours", e.target.value)}
                    disabled={loading}
                  />
                </SectionField>
              </div>
            )}

            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm font-medium text-foreground">Post-Stay Review Request</p>
                <p className="text-xs text-muted-foreground">
                  Sent X days after checkout for completed stays.
                </p>
              </div>
              <Switch
                id="email_enable_post_stay"
                checked={isChecked(d?.email_enable_post_stay)}
                onCheckedChange={(checked) => handleChange("email_enable_post_stay", checked)}
                disabled={loading}
              />
            </div>

            {d?.email_enable_post_stay && (
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <SectionField
                  label="Post-Stay Delay (days)"
                  htmlFor="email_post_stay_delay_days"
                  description="Days after checkout to send the review request."
                  required
                >
                  <Input
                    id="email_post_stay_delay_days"
                    type="number"
                    min="0"
                    max="14"
                    value={String(d?.email_post_stay_delay_days ?? 1)}
                    onChange={(e) => handleChange("email_post_stay_delay_days", e.target.value)}
                    disabled={loading}
                  />
                </SectionField>
              </div>
            )}

            <Separator />

            <p className="text-sm font-medium text-foreground">Host Notifications</p>
            <p className="text-xs text-muted-foreground -mt-2">
              In-app alerts and emails sent to the property host when guests take action.
            </p>

            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm font-medium text-foreground">New Booking Alert</p>
                <p className="text-xs text-muted-foreground">
                  Notify host when a new booking is created.
                </p>
              </div>
              <Switch
                id="email_enable_new_booking"
                checked={isChecked(d?.email_enable_new_booking)}
                onCheckedChange={(checked) => handleChange("email_enable_new_booking", checked)}
                disabled={loading}
              />
            </div>

            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm font-medium text-foreground">Modification Request Alert</p>
                <p className="text-xs text-muted-foreground">
                  Notify host when a guest modifies their booking dates.
                </p>
              </div>
              <Switch
                id="email_enable_modification_request"
                checked={isChecked(d?.email_enable_modification_request)}
                onCheckedChange={(checked) =>
                  handleChange("email_enable_modification_request", checked)
                }
                disabled={loading}
              />
            </div>
          </div>
        </Section>

        <Section
          title="Pricing Promotions"
          description="Early bird and last minute discounts applied automatically to qualifying bookings"
          icon={Percent}
          asAccordion
        >
          <div className="space-y-4">
            <div className="rounded-lg border border-border/60 p-4 space-y-3">
              <p className="text-sm font-medium text-foreground">Early Bird Discount</p>
              <p className="text-xs text-muted-foreground">
                Applies when a booking is created at least X days before check-in.
              </p>
              <div className="space-y-3">
                <SectionField
                  label="Discount Percent"
                  htmlFor="early_bird_discount_percent"
                  description="Percentage off the subtotal (after LOS discount). Set to 0 to disable."
                >
                  <Input
                    id="early_bird_discount_percent"
                    type="number"
                    min="0"
                    max="100"
                    value={String(d?.early_bird_discount_percent ?? 0)}
                    onChange={(e) => handleChange("early_bird_discount_percent", e.target.value)}
                    disabled={loading}
                  />
                </SectionField>
                {(d?.early_bird_discount_percent ?? 0) > 0 && (
                  <SectionField
                    label="Days Before Check-in"
                    htmlFor="early_bird_days_before"
                    description="Minimum days between booking creation and check-in to qualify."
                    required
                  >
                    <Input
                      id="early_bird_days_before"
                      type="number"
                      min="1"
                      max="365"
                      value={String(d?.early_bird_days_before ?? 30)}
                      onChange={(e) => handleChange("early_bird_days_before", e.target.value)}
                      disabled={loading}
                    />
                  </SectionField>
                )}
              </div>
            </div>

            <div className="rounded-lg border border-border/60 p-4 space-y-3">
              <p className="text-sm font-medium text-foreground">Last Minute Discount</p>
              <p className="text-xs text-muted-foreground">
                Applies when a booking is created within X days of check-in. Only applies if early
                bird does not.
              </p>
              <div className="space-y-3">
                <SectionField
                  label="Discount Percent"
                  htmlFor="last_minute_discount_percent"
                  description="Percentage off the subtotal (after LOS discount). Set to 0 to disable."
                >
                  <Input
                    id="last_minute_discount_percent"
                    type="number"
                    min="0"
                    max="100"
                    value={String(d?.last_minute_discount_percent ?? 0)}
                    onChange={(e) => handleChange("last_minute_discount_percent", e.target.value)}
                    disabled={loading}
                  />
                </SectionField>
                {(d?.last_minute_discount_percent ?? 0) > 0 && (
                  <SectionField
                    label="Window (days)"
                    htmlFor="last_minute_days_within"
                    description="Booking must be created within this many days of check-in."
                    required
                  >
                    <Input
                      id="last_minute_days_within"
                      type="number"
                      min="1"
                      max="90"
                      value={String(d?.last_minute_days_within ?? 7)}
                      onChange={(e) => handleChange("last_minute_days_within", e.target.value)}
                      disabled={loading}
                    />
                  </SectionField>
                )}
              </div>
            </div>

            <div className="rounded-lg border border-border/60 p-4 space-y-3">
              <p className="text-sm font-medium text-foreground">Non-Refundable Discount</p>
              <p className="text-xs text-muted-foreground">
                Offer a discount when guests choose a non-refundable booking option.
              </p>
              <div className="flex items-center justify-between">
                <div>
                  <p className="text-sm font-medium text-foreground">
                    Enable Non-Refundable Discount
                  </p>
                  <p className="text-xs text-muted-foreground">
                    Show a non-refundable pricing option at checkout with a percentage discount.
                  </p>
                </div>
                <Switch
                  id="enable_nonrefundable_discount"
                  checked={d?.enable_nonrefundable_discount === true}
                  onCheckedChange={(checked) =>
                    handleChange("enable_nonrefundable_discount", checked)
                  }
                  disabled={loading}
                />
              </div>
              {d?.enable_nonrefundable_discount && (
                <SectionField
                  label="Non-Refundable Discount Percent"
                  htmlFor="nonrefundable_discount_percent"
                  description="Percentage off the total for non-refundable bookings."
                  required
                >
                  <Input
                    id="nonrefundable_discount_percent"
                    type="number"
                    min="5"
                    max="20"
                    value={String(d?.nonrefundable_discount_percent ?? 10)}
                    onChange={(e) => handleChange("nonrefundable_discount_percent", e.target.value)}
                    disabled={loading}
                  />
                </SectionField>
              )}
            </div>
          </div>
        </Section>

        <Section
          title="System"
          description="Cron status, auto-transition controls, and system settings"
          icon={Settings2}
          asAccordion
        >
          <SystemStatusSection data={d} onChange={handleChange} loading={loading} errors={errors} />
        </Section>
      </div>

      <div className="mt-6 pt-6 border-t border-border flex justify-end">
        <Button type="submit" disabled={loading} size="lg">
          {loading ? (
            <>
              <Spinner className="mr-2" data-icon="inline-start" />
              Saving...
            </>
          ) : (
            "Save Booking Settings"
          )}
        </Button>
      </div>
    </form>
  );
}

export default BookingGlobalSettingsSection;
