import { Head, useForm } from "@inertiajs/react";
import { ChangeEvent, FormEvent } from "react";
import {
  Bot,
  CalendarRange,
  Clock,
  Mail,
  Percent,
  Server,
  UserCheck,
} from "lucide-react";
import AdminLayout from "@nreljed/platform/Layouts/AdminLayout";
import { Button } from "@nreljed/platform/Components/ui/button";
import { Input } from "@nreljed/platform/Components/ui/input";
import { Label } from "@nreljed/platform/Components/ui/label";
import { Switch } from "@nreljed/platform/Components/ui/switch";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@nreljed/platform/Components/ui/select";
import { SettingsAccordion } from "@nreljed/platform/Components/shared/settings-accordion";
import { cn } from "@nreljed/platform/lib/utils";
import { toast } from "@nreljed/platform/Components/ui/toast";

type Settings = Record<string, string | number | boolean | string[]>;

interface BookingSettingsForm {
  // Booking defaults
  default_currency: string;
  default_cancellation_policy: string;
  default_booking_status: string;
  default_check_in_time: string;
  default_check_out_time: string;
  default_min_nights: number;
  default_max_nights: number;
  default_preparation_days: number;
  default_advance_notice_days: number;

  // Calendar
  calendar_default_range: number;
  calendar_max_range: number;

  // Guest portal & workflow
  enable_guest_portal: boolean;
  allow_guest_cancellation_statuses: string[];
  allow_guest_modification_statuses: string[];
  grace_period_hours: number;
  grace_period_advance_days: number;

  // Automation
  enable_auto_checkout: boolean;
  auto_checkout_grace_hours: number;
  enable_no_show_auto_cancel: boolean;
  auto_cancel_no_show_hours: number;

  // Features
  enable_reviews: boolean;
  enable_damage_photos: boolean;

  // System
  default_deposit_refund_days: number;
  system_damage_claim_window_days: number;
  system_payout_hold_hours: number;

  // Emails
  email_enable_confirmation: boolean;
  email_enable_new_booking: boolean;
  email_enable_pre_arrival: boolean;
  email_pre_arrival_days: number;
  email_enable_checkin_instructions: boolean;
  email_enable_checkout_reminder: boolean;
  email_checkout_reminder_hours: number;
  email_enable_post_stay: boolean;
  email_post_stay_delay_days: number;
  email_enable_modification_request: boolean;
  email_enable_cancellation: boolean;

  // Pricing promotions
  early_bird_discount_percent: number;
  early_bird_days_before: number;
  last_minute_discount_percent: number;
  last_minute_days_within: number;
  enable_nonrefundable_discount: boolean;
  nonrefundable_discount_percent: number;
}

type ToggleKey = {
  [K in keyof BookingSettingsForm]: BookingSettingsForm[K] extends boolean ? K : never;
}[keyof BookingSettingsForm];

const CANCELLABLE_STATUSES = ["pending", "confirmed", "checked_in", "checked_out"] as const;
const MODIFIABLE_STATUSES = [
  "pending",
  "confirmed",
  "checked_in",
  "checked_out",
  "cancelled",
  "completed",
] as const;

const STATUS_LABELS: Record<string, string> = {
  pending: "Pending",
  confirmed: "Confirmed",
  checked_in: "Checked In",
  checked_out: "Checked Out",
  cancelled: "Cancelled",
  completed: "Completed",
};

const CURRENCIES = ["USD", "EUR", "GBP", "TND", "CAD", "CHF"];

export default function BookingSettingsPage({ settings }: { settings: Settings }) {
  const { data, setData, put, processing, errors } = useForm<BookingSettingsForm>({
    default_currency: String(settings.default_currency ?? "USD"),
    default_cancellation_policy: String(settings.default_cancellation_policy ?? "flexible"),
    default_booking_status: String(settings.default_booking_status ?? "pending"),
    default_check_in_time: String(settings.default_check_in_time ?? "15:00"),
    default_check_out_time: String(settings.default_check_out_time ?? "11:00"),
    default_min_nights: Number(settings.default_min_nights ?? 1),
    default_max_nights: Number(settings.default_max_nights ?? 30),
    default_preparation_days: Number(settings.default_preparation_days ?? 1),
    default_advance_notice_days: Number(settings.default_advance_notice_days ?? 1),
    calendar_default_range: Number(settings.calendar_default_range ?? 2),
    calendar_max_range: Number(settings.calendar_max_range ?? 12),
    enable_guest_portal: Boolean(settings.enable_guest_portal ?? true),
    allow_guest_cancellation_statuses: (settings.allow_guest_cancellation_statuses as string[]) ?? ["pending", "confirmed"],
    allow_guest_modification_statuses: (settings.allow_guest_modification_statuses as string[]) ?? ["pending", "confirmed", "checked_in"],
    grace_period_hours: Number(settings.grace_period_hours ?? 24),
    grace_period_advance_days: Number(settings.grace_period_advance_days ?? 7),
    enable_auto_checkout: Boolean(settings.enable_auto_checkout ?? true),
    auto_checkout_grace_hours: Number(settings.auto_checkout_grace_hours ?? 24),
    enable_no_show_auto_cancel: Boolean(settings.enable_no_show_auto_cancel ?? true),
    auto_cancel_no_show_hours: Number(settings.auto_cancel_no_show_hours ?? 48),
    enable_reviews: Boolean(settings.enable_reviews ?? true),
    enable_damage_photos: Boolean(settings.enable_damage_photos ?? true),
    default_deposit_refund_days: Number(settings.default_deposit_refund_days ?? 7),
    system_damage_claim_window_days: Number(settings.system_damage_claim_window_days ?? 30),
    system_payout_hold_hours: Number(settings.system_payout_hold_hours ?? 24),
    email_enable_confirmation: Boolean(settings.email_enable_confirmation ?? true),
    email_enable_new_booking: Boolean(settings.email_enable_new_booking ?? true),
    email_enable_pre_arrival: Boolean(settings.email_enable_pre_arrival ?? true),
    email_pre_arrival_days: Number(settings.email_pre_arrival_days ?? 3),
    email_enable_checkin_instructions: Boolean(settings.email_enable_checkin_instructions ?? true),
    email_enable_checkout_reminder: Boolean(settings.email_enable_checkout_reminder ?? true),
    email_checkout_reminder_hours: Number(settings.email_checkout_reminder_hours ?? 24),
    email_enable_post_stay: Boolean(settings.email_enable_post_stay ?? true),
    email_post_stay_delay_days: Number(settings.email_post_stay_delay_days ?? 1),
    email_enable_modification_request: Boolean(settings.email_enable_modification_request ?? true),
    email_enable_cancellation: Boolean(settings.email_enable_cancellation ?? true),
    early_bird_discount_percent: Number(settings.early_bird_discount_percent ?? 0),
    early_bird_days_before: Number(settings.early_bird_days_before ?? 30),
    last_minute_discount_percent: Number(settings.last_minute_discount_percent ?? 0),
    last_minute_days_within: Number(settings.last_minute_days_within ?? 7),
    enable_nonrefundable_discount: Boolean(settings.enable_nonrefundable_discount ?? false),
    nonrefundable_discount_percent: Number(settings.nonrefundable_discount_percent ?? 10),
  });

  const submit = (e: FormEvent) => {
    e.preventDefault();
    put(route("dashboard.bookings.settings.update"), {
      preserveScroll: true,
      onSuccess: () => toast.add({ title: "Booking settings saved.", type: "success" }),
      onError: () => toast.add({ title: "Failed to save settings. Check the form for errors.", type: "error" }),
    });
  };

  const setValue = <K extends keyof BookingSettingsForm>(key: K, value: BookingSettingsForm[K]) => {
    setData(key, value as never);
  };

  const field = <K extends keyof BookingSettingsForm>(key: K) => ({
    value: String(data[key]),
    error: errors[key],
    onChange: (e: ChangeEvent<HTMLInputElement>) =>
      setValue(
        key,
        (typeof data[key] === "number" ? Number(e.target.value) : e.target.value) as BookingSettingsForm[K],
      ),
  });

  const num = <K extends keyof BookingSettingsForm>(key: K) => ({
    id: key,
    type: "number",
    ...field(key),
  });

  const toggle = (key: ToggleKey, label: string, description?: string) => (
    <div className="flex items-center justify-between rounded-lg border p-3">
      <div className="space-y-0.5">
        <Label className="font-medium">{label}</Label>
        {description && <p className="text-xs text-muted-foreground">{description}</p>}
      </div>
      <Switch checked={Boolean(data[key])} onCheckedChange={(v) => setData(key, v === true)} />
    </div>
  );

  const statusGrid = (
    key: "allow_guest_cancellation_statuses" | "allow_guest_modification_statuses",
    options: readonly string[],
  ) => (
    <div>
      <Label className="mb-2 block">
        {key === "allow_guest_cancellation_statuses" ? "Guests may cancel while" : "Guests may modify while"}
      </Label>
      <div className="grid grid-cols-2 sm:grid-cols-3 gap-2">
        {options.map((status) => {
          const selected = (data[key] as string[]).includes(status);
          return (
            <button
              key={status}
              type="button"
              onClick={() =>
                setData(
                  key,
                  selected
                    ? data[key].filter((s) => s !== status)
                    : [...data[key], status],
                )
              }
              className={cn(
                "text-sm px-3 py-1.5 rounded-md border transition-colors text-left",
                selected ? "bg-primary text-primary-foreground border-primary" : "bg-card hover:bg-muted",
              )}
            >
              {STATUS_LABELS[status]}
            </button>
          );
        })}
      </div>
      {errors[key] && <p className="text-sm text-destructive mt-1">{errors[key]}</p>}
    </div>
  );

  const accordionItems = [
    {
      value: "defaults",
      icon: Clock,
      title: "Booking Defaults",
      description: "Currency, policies and default times applied to new bookings.",
      content: (
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div className="space-y-1.5">
            <Label htmlFor="default_currency">Default Currency</Label>
            <Select value={data.default_currency} onValueChange={(v) => setData("default_currency", v)}>
              <SelectTrigger id="default_currency"><SelectValue /></SelectTrigger>
              <SelectContent>
                {CURRENCIES.map((c) => <SelectItem key={c} value={c}>{c}</SelectItem>)}
              </SelectContent>
            </Select>
          </div>
          <div className="space-y-1.5">
            <Label htmlFor="default_cancellation_policy">Cancellation Policy</Label>
            <Select value={data.default_cancellation_policy} onValueChange={(v) => setData("default_cancellation_policy", v)}>
              <SelectTrigger id="default_cancellation_policy"><SelectValue /></SelectTrigger>
              <SelectContent>
                {["flexible", "moderate", "limited", "firm", "strict", "long_term", "non_refundable"].map((p) => (
                  <SelectItem key={p} value={p}>{p.replace(/_/g, " ")}</SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>
          <div className="space-y-1.5">
            <Label htmlFor="default_booking_status">New Bookings Start As</Label>
            <Select value={data.default_booking_status} onValueChange={(v) => setData("default_booking_status", v)}>
              <SelectTrigger id="default_booking_status"><SelectValue /></SelectTrigger>
              <SelectContent>
                <SelectItem value="pending">Pending</SelectItem>
                <SelectItem value="confirmed">Confirmed</SelectItem>
              </SelectContent>
            </Select>
          </div>
          <div className="grid grid-cols-2 gap-3">
            <div className="space-y-1.5">
              <Label htmlFor="default_check_in_time">Check-in Time</Label>
              <Input id="default_check_in_time" type="time" {...field("default_check_in_time")} />
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="default_check_out_time">Check-out Time</Label>
              <Input id="default_check_out_time" type="time" {...field("default_check_out_time")} />
            </div>
          </div>
        </div>
      ),
    },
    {
      value: "availability",
      icon: CalendarRange,
      title: "Availability & Calendar",
      description: "Stay length limits, buffer times and calendar display ranges.",
      content: (
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div className="space-y-1.5">
            <Label htmlFor="default_min_nights">Minimum Nights</Label>
            <Input {...num("default_min_nights")} min={1} max={365} />
          </div>
          <div className="space-y-1.5">
            <Label htmlFor="default_max_nights">Maximum Nights</Label>
            <Input {...num("default_max_nights")} min={1} max={365} />
          </div>
          <div className="space-y-1.5">
            <Label htmlFor="default_preparation_days">Preparation Days Between Stays</Label>
            <Input {...num("default_preparation_days")} min={0} max={365} />
          </div>
          <div className="space-y-1.5">
            <Label htmlFor="default_advance_notice_days">Advance Notice (days)</Label>
            <Input {...num("default_advance_notice_days")} min={0} max={365} />
          </div>
          <div className="space-y-1.5">
            <Label htmlFor="calendar_default_range">Calendar Default Range (months)</Label>
            <Input {...num("calendar_default_range")} min={1} max={12} />
          </div>
          <div className="space-y-1.5">
            <Label htmlFor="calendar_max_range">Calendar Maximum Range (months)</Label>
            <Input {...num("calendar_max_range")} min={1} max={12} />
          </div>
        </div>
      ),
    },
    {
      value: "guest-portal",
      icon: UserCheck,
      title: "Guest Portal & Workflow",
      description: "What guests can do themselves and how much slack they get.",
      content: (
        <div className="space-y-4">
          {toggle("enable_guest_portal", "Guest portal", "Let guests view bookings, check in/out and request changes.")}
          {statusGrid("allow_guest_cancellation_statuses", CANCELLABLE_STATUSES)}
          {statusGrid("allow_guest_modification_statuses", MODIFIABLE_STATUSES)}
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div className="space-y-1.5">
              <Label htmlFor="grace_period_hours">Modification Grace Period (hours after booking)</Label>
              <Input {...num("grace_period_hours")} min={1} max={72} />
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="grace_period_advance_days">Grace Period Advance Days</Label>
              <Input {...num("grace_period_advance_days")} min={1} max={30} />
            </div>
          </div>
        </div>
      ),
    },
    {
      value: "automation",
      icon: Bot,
      title: "Automation",
      description: "Automatic transitions for check-outs and no-shows.",
      content: (
        <div className="space-y-4">
          {toggle("enable_auto_checkout", "Auto check-out", "Automatically complete stays after the check-out time.")}
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div className="space-y-1.5">
              <Label htmlFor="auto_checkout_grace_hours">Auto Check-out Grace (hours)</Label>
              <Input {...num("auto_checkout_grace_hours")} min={0} max={168} />
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="auto_cancel_no_show_hours">No-show Cancel After (hours)</Label>
              <Input {...num("auto_cancel_no_show_hours")} min={0} max={168} />
            </div>
          </div>
          {toggle("enable_no_show_auto_cancel", "Auto-cancel no-shows")}
        </div>
      ),
    },
    {
      value: "emails",
      icon: Mail,
      title: "Email Notifications",
      description: "Lifecycle emails sent to hosts and guests.",
      content: (
        <div className="space-y-3">
          <div className="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-1">
            {toggle("email_enable_confirmation", "Booking confirmation")}
            {toggle("email_enable_new_booking", "New booking alert")}
            {toggle("email_enable_pre_arrival", "Pre-arrival instructions")}
            {toggle("email_enable_checkin_instructions", "Check-in instructions")}
            {toggle("email_enable_checkout_reminder", "Checkout reminder")}
            {toggle("email_enable_post_stay", "Post-stay follow-up")}
            {toggle("email_enable_modification_request", "Modification requests")}
            {toggle("email_enable_cancellation", "Cancellations")}
          </div>
          <div className="grid grid-cols-1 md:grid-cols-3 gap-4 pt-2">
            <div className="space-y-1.5">
              <Label htmlFor="email_pre_arrival_days">Pre-arrival (days before)</Label>
              <Input {...num("email_pre_arrival_days")} min={0} max={30} />
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="email_checkout_reminder_hours">Checkout reminder (hours before)</Label>
              <Input {...num("email_checkout_reminder_hours")} min={1} max={72} />
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="email_post_stay_delay_days">Post-stay delay (days)</Label>
              <Input {...num("email_post_stay_delay_days")} min={0} max={14} />
            </div>
          </div>
        </div>
      ),
    },
    {
      value: "pricing",
      icon: Percent,
      title: "Pricing Promotions",
      description: "Early-bird, last-minute and non-refundable discounts.",
      content: (
        <div className="space-y-4">
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div className="space-y-1.5">
              <Label htmlFor="early_bird_discount_percent">Early-bird Discount (%)</Label>
              <Input {...num("early_bird_discount_percent")} min={0} max={100} />
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="early_bird_days_before">Booked At Least (days ahead)</Label>
              <Input {...num("early_bird_days_before")} min={1} max={365} />
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="last_minute_discount_percent">Last-minute Discount (%)</Label>
              <Input {...num("last_minute_discount_percent")} min={0} max={100} />
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="last_minute_days_within">Within (days of arrival)</Label>
              <Input {...num("last_minute_days_within")} min={1} max={90} />
            </div>
          </div>
          {toggle("enable_nonrefundable_discount", "Non-refundable rate discount")}
          {data.enable_nonrefundable_discount && (
            <div className="max-w-xs space-y-1.5">
              <Label htmlFor="nonrefundable_discount_percent">Non-refundable Discount (%)</Label>
              <Input {...num("nonrefundable_discount_percent")} min={5} max={20} />
            </div>
          )}
        </div>
      ),
    },
    {
      value: "system",
      icon: Server,
      title: "System & Features",
      description: "Deposits, damage claims, payouts and module features.",
      content: (
        <div className="space-y-4">
          <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div className="space-y-1.5">
              <Label htmlFor="default_deposit_refund_days">Deposit Refund Window (days)</Label>
              <Input {...num("default_deposit_refund_days")} min={1} max={90} />
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="system_damage_claim_window_days">Damage Claim Window (days)</Label>
              <Input {...num("system_damage_claim_window_days")} min={1} max={90} />
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="system_payout_hold_hours">Payout Hold (hours)</Label>
              <Input {...num("system_payout_hold_hours")} min={0} max={168} />
            </div>
          </div>
          {toggle("enable_reviews", "Guest reviews")}
          {toggle("enable_damage_photos", "Damage photo uploads")}
        </div>
      ),
    },
  ];

  return (
    <AdminLayout>
      <Head title="Booking Settings" />
      <form onSubmit={submit} className="p-6 max-w-5xl mx-auto space-y-6">
        <div>
          <h1 className="text-2xl font-bold flex items-center gap-2">
            <CalendarRange className="h-6 w-6" />
            Booking Settings
          </h1>
          <p className="text-muted-foreground text-sm mt-1">
            Configure global booking defaults, guest portal behaviour, emails and promotions.
          </p>
        </div>

        <SettingsAccordion items={accordionItems} />

        <div className="flex justify-end">
          <Button type="submit" disabled={processing}>
            {processing ? "Saving..." : "Save Settings"}
          </Button>
        </div>
      </form>
    </AdminLayout>
  );
}
