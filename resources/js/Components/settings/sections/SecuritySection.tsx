import { useState } from "react";
import { router } from "@inertiajs/react";
import { toast } from "sonner";
import { Section, SectionField } from "@/Components/section";
import { Input } from "@/Components/ui/input";
import { Lock } from "lucide-react";

interface SecuritySectionProps {
  settings: Record<string, string>;
  asAccordion?: boolean;
}

export function SecuritySection({ settings, asAccordion }: SecuritySectionProps) {
  const [data, setData] = useState({
    password_min_length: settings.password_min_length ?? "8",
    session_lifetime: settings.session_lifetime ?? "120",
    max_login_attempts: settings.max_login_attempts ?? "5",
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
        toast.success("Security settings saved.");
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
      title="Security"
      description="Configure security settings."
      icon={Lock}
      onSubmit={handleSubmit}
      loading={loading}
      fieldErrors={errors as Record<string, string[]>}
      submitLabel="Save Changes"
      asAccordion={asAccordion}
    >
      <SectionField label="Minimum Password Length" htmlFor="password_min_length">
        <Input
          id="password_min_length"
          type="number"
          value={data.password_min_length}
          onChange={(e) => setData((p) => ({ ...p, password_min_length: e.target.value }))}
        />
      </SectionField>
      <SectionField label="Session Lifetime (minutes)" htmlFor="session_lifetime">
        <Input
          id="session_lifetime"
          type="number"
          value={data.session_lifetime}
          onChange={(e) => setData((p) => ({ ...p, session_lifetime: e.target.value }))}
        />
      </SectionField>
      <SectionField label="Max Login Attempts" htmlFor="max_login_attempts">
        <Input
          id="max_login_attempts"
          type="number"
          value={data.max_login_attempts}
          onChange={(e) => setData((p) => ({ ...p, max_login_attempts: e.target.value }))}
        />
      </SectionField>
    </Section>
  );
}
