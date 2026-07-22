import { useState } from "react";
import { router } from "@inertiajs/react";
import { toast } from "sonner";
import { Section, SectionField } from "@/Components/shared/section";
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
      <div className="rounded-md bg-muted p-3 text-sm text-muted-foreground">
        These settings are enforced in real-time. Password length applies to registration and
        password changes. Session lifetime and login attempts take effect on the next request.
      </div>
      <SectionField
        label="Minimum Password Length"
        htmlFor="password_min_length"
        description="Minimum characters required for new passwords"
      >
        <Input
          id="password_min_length"
          type="number"
          min={8}
          max={128}
          value={data.password_min_length}
          onChange={(e) => setData((p) => ({ ...p, password_min_length: e.target.value }))}
        />
      </SectionField>
      <SectionField
        label="Session Lifetime (minutes)"
        htmlFor="session_lifetime"
        description="How long a user stays logged in before re-authentication"
      >
        <Input
          id="session_lifetime"
          type="number"
          min={1}
          max={43_200}
          value={data.session_lifetime}
          onChange={(e) => setData((p) => ({ ...p, session_lifetime: e.target.value }))}
        />
      </SectionField>
      <SectionField
        label="Max Login Attempts"
        htmlFor="max_login_attempts"
        description="Number of failed attempts before temporary lockout"
      >
        <Input
          id="max_login_attempts"
          type="number"
          min={1}
          max={100}
          value={data.max_login_attempts}
          onChange={(e) => setData((p) => ({ ...p, max_login_attempts: e.target.value }))}
        />
      </SectionField>
    </Section>
  );
}
