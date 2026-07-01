import * as React from "react";
import { router, usePage } from "@inertiajs/react";
import { toast } from "sonner";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { SettingsNav } from "@/Components/settings/SettingsNav";
import {
  Breadcrumb,
  BreadcrumbItem,
  BreadcrumbLink,
  BreadcrumbList,
  BreadcrumbPage,
  BreadcrumbSeparator,
} from "@/Components/ui/breadcrumb";
import { BOOKING_STATUS } from "../../lib/booking-status";
import { BookingGlobalSettingsSection } from "../../components/settings/BookingGlobalSettingsSection";
import { Calendar } from "lucide-react";

interface BookingGlobalSettingsProps {
  settings: Record<string, unknown>;
  nonces: Record<string, string>;
}

export default function BookingGlobalSettings({ settings, nonces }: BookingGlobalSettingsProps) {
  const { props } = usePage() as unknown as { props: Record<string, unknown> };

  const enabledModules = React.useMemo(() => {
    const modules = props?.modules as Record<string, unknown> | undefined;
    if (modules && Array.isArray(modules?.enabled)) return modules.enabled as string[];
    if (modules && Array.isArray(modules?.list))
      return (modules.list as Array<{ enabled: boolean; name: string }>)
        .filter((m) => m.enabled)
        .map((m) => m.name);
    return [];
  }, [props?.modules]);

  const settingsData = React.useMemo(() => settings || {}, [settings]);

  const [formData, setFormData] = React.useState<Record<string, unknown> | null>(null);
  const [formErrors, setFormErrors] = React.useState<Record<string, string>>({});
  const [processing, setProcessing] = React.useState(false);
  const [isLoading, setIsLoading] = React.useState(true);

  const buildFormData = React.useCallback(() => {
    return {
      _site_timezone: (settingsData?._site_timezone as string) || "UTC",
      _site_timezone_label:
        (settingsData?._site_timezone_label as string) ||
        (settingsData?._site_timezone as string) ||
        "UTC",
      default_check_in_time: settingsData?.default_check_in_time ?? "15:00",
      default_check_out_time: settingsData?.default_check_out_time ?? "11:00",
      default_min_nights: settingsData?.default_min_nights ?? 1,
      default_max_nights: settingsData?.default_max_nights ?? 30,
      default_cancellation_policy: settingsData?.default_cancellation_policy ?? "flexible",
      default_preparation_days: settingsData?.default_preparation_days ?? 1,
      default_advance_notice_days: settingsData?.default_advance_notice_days ?? 1,
      default_booking_status: settingsData?.default_booking_status ?? BOOKING_STATUS.Pending,
      default_deposit_refund_days: settingsData?.default_deposit_refund_days ?? 7,
      calendar_default_range: settingsData?.calendar_default_range ?? 2,
      calendar_max_range: settingsData?.calendar_max_range ?? 12,
      email_checkout_reminder_hours: settingsData?.email_checkout_reminder_hours ?? 24,
      auto_checkout_grace_hours: settingsData?.auto_checkout_grace_hours ?? 24,
      auto_cancel_no_show_hours: settingsData?.auto_cancel_no_show_hours ?? 48,
      enable_auto_checkout: settingsData?.enable_auto_checkout ?? true,
      enable_no_show_auto_cancel: settingsData?.enable_no_show_auto_cancel ?? true,
      allow_guest_cancellation_statuses: settingsData?.allow_guest_cancellation_statuses ?? [
        BOOKING_STATUS.Pending,
        BOOKING_STATUS.Confirmed,
      ],
      allow_guest_modification_statuses: settingsData?.allow_guest_modification_statuses ?? [
        BOOKING_STATUS.Pending,
        BOOKING_STATUS.Confirmed,
        BOOKING_STATUS.CheckedIn,
      ],
      grace_period_hours: settingsData?.grace_period_hours ?? 24,
      grace_period_advance_days: settingsData?.grace_period_advance_days ?? 7,
      enable_nonrefundable_discount: settingsData?.enable_nonrefundable_discount ?? false,
      nonrefundable_discount_percent: settingsData?.nonrefundable_discount_percent ?? 10,
      system_damage_claim_window_days: settingsData?.system_damage_claim_window_days ?? 30,
      system_payout_hold_hours: settingsData?.system_payout_hold_hours ?? 24,
      email_enable_pre_arrival: settingsData?.email_enable_pre_arrival ?? true,
      email_pre_arrival_days: settingsData?.email_pre_arrival_days ?? 3,
      email_enable_checkout_reminder: settingsData?.email_enable_checkout_reminder ?? true,
      email_enable_post_stay: settingsData?.email_enable_post_stay ?? true,
      email_post_stay_delay_days: settingsData?.email_post_stay_delay_days ?? 1,
      email_enable_new_booking: settingsData?.email_enable_new_booking ?? true,
      email_enable_modification_request: settingsData?.email_enable_modification_request ?? true,
      early_bird_discount_percent: settingsData?.early_bird_discount_percent ?? 0,
      early_bird_days_before: settingsData?.early_bird_days_before ?? 30,
      last_minute_discount_percent: settingsData?.last_minute_discount_percent ?? 0,
      last_minute_days_within: settingsData?.last_minute_days_within ?? 7,
    };
  }, [settingsData]);

  React.useEffect(() => {
    if (formData !== null || !settingsData) return;
    const timer = setTimeout(() => {
      setFormData(buildFormData());
      setIsLoading(false);
    }, 0);
    return () => clearTimeout(timer);
  }, [settingsData, formData, buildFormData]);

  const handleFieldChange = (field: string, value: unknown) => {
    setFormData((prev) => ({ ...prev, [field]: value }));
    setFormErrors((prev) => {
      if (Object.hasOwn(prev, field)) {
        const next = { ...prev };
        delete next[field];
        return next;
      }
      return prev;
    });
  };

  const validate = React.useCallback(() => {
    const errors: Record<string, string> = {};
    const d = formData || {};

    const requiredFields: Record<string, string> = {
      default_check_in_time: "Check-in time is required",
      default_check_out_time: "Check-out time is required",
      default_min_nights: "Minimum nights is required",
      default_max_nights: "Maximum nights is required",
      default_deposit_refund_days: "Deposit refund days is required",
      default_preparation_days: "Preparation days is required",
      default_advance_notice_days: "Advance notice is required",
      calendar_default_range: "Default range is required",
      calendar_max_range: "Maximum range is required",
      grace_period_hours: "Grace period hours is required",
      grace_period_advance_days: "Grace period advance days is required",
      system_damage_claim_window_days: "Damage claim window is required",
      system_payout_hold_hours: "Payout hold hours is required",
    };

    if ((d as Record<string, unknown>).enable_auto_checkout !== false) {
      requiredFields.auto_checkout_grace_hours = "Check-out grace hours is required";
    }

    if ((d as Record<string, unknown>).enable_no_show_auto_cancel !== false) {
      requiredFields.auto_cancel_no_show_hours = "No-show cancel grace hours is required";
    }

    if ((d as Record<string, unknown>).email_enable_pre_arrival !== false) {
      requiredFields.email_pre_arrival_days = "Pre-arrival days is required";
    }

    if ((d as Record<string, unknown>).email_enable_checkout_reminder !== false) {
      requiredFields.email_checkout_reminder_hours = "Checkout reminder hours is required";
    }

    if ((d as Record<string, unknown>).email_enable_post_stay !== false) {
      requiredFields.email_post_stay_delay_days = "Post-stay delay is required";
    }

    const earlyBirdPercent = Number(
      (d as Record<string, unknown>).early_bird_discount_percent ?? 0,
    );
    if (earlyBirdPercent > 0) {
      requiredFields.early_bird_days_before = "Early bird days before check-in is required";
    }

    const lastMinutePercent = Number(
      (d as Record<string, unknown>).last_minute_discount_percent ?? 0,
    );
    if (lastMinutePercent > 0) {
      requiredFields.last_minute_days_within = "Last minute window is required";
    }

    for (const [field, msg] of Object.entries(requiredFields)) {
      const val = (d as Record<string, unknown>)[field];
      if (val == null || val === "") {
        errors[field] = msg;
      }
    }

    const rangeErrors: Record<string, { min: number; max: number; label: string }> = {
      default_min_nights: { min: 1, max: 365, label: "Minimum nights" },
      default_max_nights: { min: 1, max: 365, label: "Maximum nights" },
      default_deposit_refund_days: { min: 1, max: 90, label: "Deposit refund days" },
      default_preparation_days: { min: 0, max: 365, label: "Preparation days" },
      default_advance_notice_days: { min: 0, max: 365, label: "Advance notice" },
      calendar_default_range: { min: 1, max: 12, label: "Default range" },
      calendar_max_range: { min: 1, max: 12, label: "Maximum range" },
      auto_checkout_grace_hours: { min: 0, max: 168, label: "Check-out grace hours" },
      auto_cancel_no_show_hours: { min: 0, max: 168, label: "No-show cancel grace hours" },
      email_pre_arrival_days: { min: 0, max: 30, label: "Pre-arrival days" },
      email_checkout_reminder_hours: { min: 1, max: 72, label: "Checkout reminder hours" },
      email_post_stay_delay_days: { min: 0, max: 14, label: "Post-stay delay" },
      early_bird_discount_percent: { min: 0, max: 100, label: "Early bird discount percent" },
      early_bird_days_before: { min: 1, max: 365, label: "Early bird days before" },
      last_minute_discount_percent: { min: 0, max: 100, label: "Last minute discount percent" },
      last_minute_days_within: { min: 1, max: 90, label: "Last minute window" },
      system_damage_claim_window_days: { min: 1, max: 90, label: "Damage claim window" },
      system_payout_hold_hours: { min: 0, max: 168, label: "Payout hold hours" },
      grace_period_hours: { min: 1, max: 72, label: "Grace period hours" },
      grace_period_advance_days: { min: 1, max: 30, label: "Grace period advance days" },
    };

    if ((d as Record<string, unknown>).enable_nonrefundable_discount === true) {
      rangeErrors.nonrefundable_discount_percent = {
        min: 5,
        max: 20,
        label: "Non-refundable discount percent",
      };
    }

    for (const [field, cfg] of Object.entries(rangeErrors)) {
      const val = (d as Record<string, unknown>)[field];
      if (val == null || val === "") continue;
      const num = Number(val);
      if (!Number.isFinite(num) || num < cfg.min || num > cfg.max) {
        errors[field] = `${cfg.label} must be between ${cfg.min} and ${cfg.max}`;
      }
    }

    return errors;
  }, [formData]);

  const handleSubmit = React.useCallback(
    async (e: React.FormEvent) => {
      e.preventDefault();

      if (processing) return;

      const validationErrors = validate();
      if (Object.keys(validationErrors).length > 0) {
        setFormErrors(validationErrors);
        const firstKey = Object.keys(validationErrors)[0] ?? "";
        toast.error(validationErrors[firstKey]);
        return;
      }

      try {
        router.put(
          "/dashboard/settings/booking",
          {
            ...formData,
            _wpnonce: nonces?.booking_settings,
          },
          {
            preserveScroll: true,
            preserveState: true,
            replace: true,
            onStart: () => setProcessing(true) as unknown as void,
            onSuccess: (page: { props: Record<string, unknown> }) => {
              setFormErrors({});
              toast.success(
                (page.props.status as string) || "Booking settings saved successfully!",
              );
            },
            onError: (errors: Record<string, string>) => {
              setFormErrors(errors);
              const firstErrorKey = Object.keys(errors)[0] ?? "";
              const errorMsg = errors[firstErrorKey] || "Failed to save settings";
              toast.error(errorMsg);
            },
            onFinish: () => setProcessing(false),
          },
        );
      } catch {
        toast.error("An error occurred while saving");
      }
    },
    [formData, processing, nonces, validate],
  );

  if (isLoading) {
    return (
      <AuthenticatedLayout header="Booking Settings">
        <div className="max-w-4xl mx-auto space-y-6">
          <Breadcrumb>
            <BreadcrumbList>
              <BreadcrumbItem>
                <BreadcrumbLink href="/dashboard">Dashboard</BreadcrumbLink>
              </BreadcrumbItem>
              <BreadcrumbSeparator />
              <BreadcrumbItem>
                <BreadcrumbLink href="/dashboard/settings">Settings</BreadcrumbLink>
              </BreadcrumbItem>
              <BreadcrumbSeparator />
              <BreadcrumbItem>
                <BreadcrumbPage>Booking Settings</BreadcrumbPage>
              </BreadcrumbItem>
            </BreadcrumbList>
          </Breadcrumb>
          <div className="flex items-center justify-center py-12">
            <div className="animate-pulse text-muted-foreground">Loading...</div>
          </div>
        </div>
      </AuthenticatedLayout>
    );
  }

  return (
    <AuthenticatedLayout header="Booking Settings">
      <div className="max-w-4xl mx-auto space-y-6">
        <Breadcrumb>
          <BreadcrumbList>
            <BreadcrumbItem>
              <BreadcrumbLink href="/dashboard">Dashboard</BreadcrumbLink>
            </BreadcrumbItem>
            <BreadcrumbSeparator />
            <BreadcrumbItem>
              <BreadcrumbLink href="/dashboard/settings">Settings</BreadcrumbLink>
            </BreadcrumbItem>
            <BreadcrumbSeparator />
            <BreadcrumbItem>
              <BreadcrumbPage>Booking Settings</BreadcrumbPage>
            </BreadcrumbItem>
          </BreadcrumbList>
        </Breadcrumb>

        <div className="flex items-center gap-3">
          <Calendar className="h-6 w-6 text-foreground shrink-0" />
          <div>
            <h1 className="text-2xl font-bold text-foreground">Booking Settings</h1>
            <p className="text-muted-foreground">Configure global booking defaults and behavior</p>
          </div>
        </div>

        <SettingsNav currentPath="/dashboard/settings/booking" enabledModules={enabledModules} />

        <BookingGlobalSettingsSection
          data={formData as unknown}
          onChange={handleFieldChange}
          errors={formErrors}
          loading={processing}
          onSubmit={handleSubmit}
        />
      </div>
    </AuthenticatedLayout>
  );
}
