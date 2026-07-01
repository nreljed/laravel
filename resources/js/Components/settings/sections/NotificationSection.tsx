import { useState } from "react";
import { router } from "@inertiajs/react";
import { toast } from "sonner";
import { Section, SectionField } from "@/Components/section";
import { Switch } from "@/Components/ui/switch";
import { Label } from "@/Components/ui/label";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/Components/ui/select";
import { Bell } from "lucide-react";

interface NotificationSectionProps {
  settings: Record<string, string>;
  asAccordion?: boolean;
}

export function NotificationSection({ settings, asAccordion }: NotificationSectionProps) {
  const [data, setData] = useState({
    default_notification_channel: settings.default_notification_channel ?? "in_app",
    email_notifications_enabled: settings.email_notifications_enabled ?? "1",
  });
  const [loading, setLoading] = useState(false);
  const [errors, setErrors] = useState({});

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (loading) return;
    setLoading(true);
    router.put(route("dashboard.admin.settings.update"), data, {
      preserveScroll: true,
      preserveState: true,
      onSuccess: () => {
        toast.success("Notification settings saved.");
        setLoading(false);
      },
      onError: (errs) => {
        setErrors(errs);
        toast.error("Failed to save.");
        setLoading(false);
      },
    });
  };

  return (
    <Section
      title="Notifications"
      description="Configure notification defaults."
      icon={Bell}
      onSubmit={handleSubmit}
      loading={loading}
      fieldErrors={errors as Record<string, string[]>}
      submitLabel="Save Changes"
      asAccordion={asAccordion}
    >
      <SectionField label="Default Notification Channel" htmlFor="default_notification_channel">
        <Select
          value={data.default_notification_channel}
          onValueChange={(v) => setData((p) => ({ ...p, default_notification_channel: v }))}
        >
          <SelectTrigger id="default_notification_channel">
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="in_app">In-app only</SelectItem>
            <SelectItem value="email">Email only</SelectItem>
            <SelectItem value="in_app,email">Both (in-app + email)</SelectItem>
          </SelectContent>
        </Select>
      </SectionField>
      <SectionField label="Enable Email Notifications" htmlFor="email_notifications_enabled">
        <div className="flex items-center gap-2">
          <Switch
            id="email_notifications_enabled"
            checked={data.email_notifications_enabled === "1"}
            onCheckedChange={(c) =>
              setData((p) => ({ ...p, email_notifications_enabled: c ? "1" : "0" }))
            }
          />
          <Label htmlFor="email_notifications_enabled" className="cursor-pointer font-normal">
            {data.email_notifications_enabled === "1" ? "Enabled" : "Disabled"}
          </Label>
        </div>
      </SectionField>
    </Section>
  );
}
