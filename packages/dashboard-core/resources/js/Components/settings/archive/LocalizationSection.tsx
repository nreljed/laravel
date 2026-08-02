import { useState } from "react";
import { router } from "@inertiajs/react";
import { toast } from "@/Components/ui/toast";
import { Section, SectionField } from "@/Components/shared/section";
import { Input } from "@/Components/ui/input";
import { Languages } from "lucide-react";

interface LocalizationSectionProps {
  settings: Record<string, string>;
  asAccordion?: boolean;
}

export function LocalizationSection({ settings, asAccordion }: LocalizationSectionProps) {
  const [data, setData] = useState({
    locale: settings.locale ?? "en",
    date_format: settings.date_format ?? "Y-m-d",
    time_format: settings.time_format ?? "H:i",
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
        toast.add({ title: "Localization settings saved.", type: "success" });
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
      title="Localization"
      description="Configure locale and date/time formats."
      icon={Languages}
      onSubmit={handleSubmit}
      loading={loading}
      fieldErrors={errors as Record<string, string[]>}
      submitLabel="Save Changes"
      asAccordion={asAccordion}
    >
      <SectionField label="Locale" htmlFor="locale">
        <Input
          id="locale"
          value={data.locale}
          onChange={(e) => setData((p) => ({ ...p, locale: e.target.value }))}
        />
      </SectionField>
      <SectionField label="Date Format" htmlFor="date_format">
        <Input
          id="date_format"
          value={data.date_format}
          onChange={(e) => setData((p) => ({ ...p, date_format: e.target.value }))}
        />
      </SectionField>
      <SectionField label="Time Format" htmlFor="time_format">
        <Input
          id="time_format"
          value={data.time_format}
          onChange={(e) => setData((p) => ({ ...p, time_format: e.target.value }))}
        />
      </SectionField>
    </Section>
  );
}
