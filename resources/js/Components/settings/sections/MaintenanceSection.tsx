import { useState } from "react";
import { router, usePage } from "@inertiajs/react";
import { toast } from "@/Components/ui/toast";
import { Section, SectionField } from "@/Components/shared/section";
import { Input } from "@/Components/ui/input";
import { Switch } from "@/Components/ui/switch";
import { Label } from "@/Components/ui/label";
import { Wrench, Copy, Check, Clock, Mail } from "lucide-react";

interface MaintenanceSectionProps {
  settings: Record<string, string>;
  asAccordion?: boolean;
}

export function MaintenanceSection({ settings, asAccordion }: MaintenanceSectionProps) {
  const page = usePage();
  const appUrl = (page.props.app_url as string) || "";

  const toLocalDatetime = (iso: string) => {
    if (!iso) return "";
    try {
      const d = new Date(iso);
      if (Number.isNaN(d.getTime())) return "";
      const offset = d.getTimezoneOffset();
      const local = new Date(d.getTime() - offset * 60_000);
      return local.toISOString().slice(0, 16);
    } catch {
      return "";
    }
  };

  const [data, setData] = useState({
    maintenance_mode: settings.maintenance_mode ?? "0",
    maintenance_message: settings.maintenance_message ?? "Site is under maintenance.",
    maintenance_ends_at: toLocalDatetime(settings.maintenance_ends_at ?? ""),
  });
  const [loading, setLoading] = useState(false);
  const [errors, setErrors] = useState({});
  const [copied, setCopied] = useState(false);

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (loading) return;
    setLoading(true);
    const payload = {
      ...data,
      maintenance_ends_at: data.maintenance_ends_at
        ? new Date(data.maintenance_ends_at).toISOString()
        : "",
    };
    router.put(route("dashboard.admin.settings.update"), payload, {
      preserveScroll: true,
      preserveState: true,
      onSuccess: () => {
        toast.add({ title: "Maintenance settings saved.", type: "success" });
        setLoading(false);
      },
      onError: (errs) => {
        setErrors(errs);
        toast.add({ title: "Failed to save.", type: "error" });
        setLoading(false);
      },
    });
  };

  return (
    <Section
      title="Maintenance"
      description="Configure maintenance mode."
      icon={Wrench}
      onSubmit={handleSubmit}
      loading={loading}
      fieldErrors={errors as Record<string, string[]>}
      submitLabel="Save Changes"
      asAccordion={asAccordion}
    >
      <div className="rounded-md bg-muted p-3 text-sm text-muted-foreground">
        When enabled, non-admin users will see a maintenance page with a countdown timer. Admins can
        still access the dashboard. When maintenance is activated, all admin accounts automatically
        receive a recovery email. If locked out, use the recovery page below.
      </div>
      <SectionField label="Maintenance Mode" htmlFor="maintenance_mode">
        <div className="flex items-center gap-2">
          <Switch
            id="maintenance_mode"
            checked={data.maintenance_mode === "1"}
            onCheckedChange={(c) => setData((p) => ({ ...p, maintenance_mode: c ? "1" : "0" }))}
          />
          <Label htmlFor="maintenance_mode" className="cursor-pointer font-normal">
            {data.maintenance_mode === "1" ? "Enabled" : "Disabled"}
          </Label>
        </div>
      </SectionField>
      <SectionField label="Maintenance Message" htmlFor="maintenance_message">
        <Input
          id="maintenance_message"
          value={data.maintenance_message}
          onChange={(e) => setData((p) => ({ ...p, maintenance_message: e.target.value }))}
        />
      </SectionField>
      <SectionField
        label="Estimated End Time"
        htmlFor="maintenance_ends_at"
        description="Optional. Users will see a countdown timer. Leave empty for indefinite maintenance."
      >
        <div className="flex items-center gap-2">
          <Clock className="h-4 w-4 text-muted-foreground" />
          <Input
            id="maintenance_ends_at"
            type="datetime-local"
            value={data.maintenance_ends_at}
            onChange={(e) => setData((p) => ({ ...p, maintenance_ends_at: e.target.value }))}
          />
          {data.maintenance_ends_at && (
            <button
              type="button"
              onClick={() => setData((p) => ({ ...p, maintenance_ends_at: "" }))}
              className="text-sm text-muted-foreground hover:text-foreground transition-colors whitespace-nowrap"
            >
              Clear
            </button>
          )}
        </div>
      </SectionField>
      {data.maintenance_mode === "1" && (
        <SectionField
          label="Recovery Page"
          description="Admins locked out can visit this page to request a recovery email."
        >
          <div className="flex items-center gap-2">
            <Input
              readOnly
              value={appUrl ? `${appUrl}/maintenance/recovery` : "/maintenance/recovery"}
              className="font-mono text-xs"
            />
            <button
              type="button"
              onClick={async () => {
                const url = appUrl ? `${appUrl}/maintenance/recovery` : "/maintenance/recovery";
                await navigator.clipboard.writeText(url);
                setCopied(true);
                toast.add({ title: "Recovery URL copied to clipboard.", type: "success" });
                setTimeout(() => setCopied(false), 2000);
              }}
              className="shrink-0 inline-flex items-center justify-center rounded-md text-sm font-medium h-9 w-9 border border-input bg-background hover:bg-accent hover:text-accent-foreground transition-colors"
              title="Copy recovery URL"
            >
              {copied ? <Check className="h-4 w-4" /> : <Copy className="h-4 w-4" />}
            </button>
          </div>
        </SectionField>
      )}
      {data.maintenance_mode === "1" && (
        <div className="rounded-md bg-blue-50 p-3 text-sm text-blue-700 dark:bg-blue-950 dark:text-blue-300 flex items-start gap-2">
          <Mail className="h-4 w-4 mt-0.5 shrink-0" />
          <span>
            Recovery emails are sent to all admin accounts when maintenance is first enabled.
          </span>
        </div>
      )}
    </Section>
  );
}
