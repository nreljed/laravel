import { useState } from "react";
import { router } from "@inertiajs/react";
import { toast } from "sonner";
import { Section, SectionField } from "@/Components/ui/section";
import { Input } from "@/Components/ui/input";
import { Switch } from "@/Components/ui/switch";
import { Label } from "@/Components/ui/label";
import { ShieldCheck } from "lucide-react";

interface RegistrationSectionProps {
  settings: Record<string, string>;
  asAccordion?: boolean;
}

export function RegistrationSection({ settings, asAccordion }: RegistrationSectionProps) {
  const [data, setData] = useState({
    enable_registration: settings.enable_registration ?? "0",
    default_role: settings.default_role ?? "subscriber",
    require_email_verification: settings.require_email_verification ?? "0",
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
      onSuccess: () => { toast.success("Registration settings saved."); setLoading(false); },
      onError: (errs) => { setErrors(errs); toast.error("Failed to save."); setLoading(false); },
    });
  };

  return (
    <Section
      title="Registration"
      description="Configure public registration settings."
      icon={ShieldCheck}
      onSubmit={handleSubmit}
      loading={loading}
      fieldErrors={errors as Record<string, string[]>}
      submitLabel="Save Changes"
      asAccordion={asAccordion}
    >
      <SectionField label="Enable Public Registration" htmlFor="enable_registration">
        <div className="flex items-center gap-2">
          <Switch
            id="enable_registration"
            checked={data.enable_registration === "1"}
            onCheckedChange={(c) => setData((p) => ({ ...p, enable_registration: c ? "1" : "0" }))}
          />
          <Label htmlFor="enable_registration" className="cursor-pointer font-normal">
            {data.enable_registration === "1" ? "Enabled" : "Disabled"}
          </Label>
        </div>
      </SectionField>
      <SectionField label="Default Role" htmlFor="default_role">
        <Input
          id="default_role"
          value={data.default_role}
          onChange={(e) => setData((p) => ({ ...p, default_role: e.target.value }))}
        />
      </SectionField>
      <SectionField label="Require Email Verification" htmlFor="require_email_verification">
        <div className="flex items-center gap-2">
          <Switch
            id="require_email_verification"
            checked={data.require_email_verification === "1"}
            onCheckedChange={(c) => setData((p) => ({ ...p, require_email_verification: c ? "1" : "0" }))}
          />
          <Label htmlFor="require_email_verification" className="cursor-pointer font-normal">
            {data.require_email_verification === "1" ? "Required" : "Optional"}
          </Label>
        </div>
      </SectionField>
    </Section>
  );
}
