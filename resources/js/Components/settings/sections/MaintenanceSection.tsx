import { useState } from "react";
import { router } from "@inertiajs/react";
import { toast } from "sonner";
import { Section, SectionField } from "@/Components/section";
import { Input } from "@/Components/ui/input";
import { Switch } from "@/Components/ui/switch";
import { Label } from "@/Components/ui/label";
import { Wrench } from "lucide-react";

interface MaintenanceSectionProps {
  settings: Record<string, string>;
  asAccordion?: boolean;
}

export function MaintenanceSection({ settings, asAccordion }: MaintenanceSectionProps) {
  const [data, setData] = useState({
    maintenance_mode: settings.maintenance_mode ?? "0",
    maintenance_message: settings.maintenance_message ?? "Site is under maintenance.",
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
        toast.success("Maintenance settings saved.");
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
      title="Maintenance"
      description="Configure maintenance mode."
      icon={Wrench}
      onSubmit={handleSubmit}
      loading={loading}
      fieldErrors={errors as Record<string, string[]>}
      submitLabel="Save Changes"
      asAccordion={asAccordion}
    >
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
    </Section>
  );
}
