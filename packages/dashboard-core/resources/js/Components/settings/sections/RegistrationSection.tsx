import { useState } from "react";
import { router } from "@inertiajs/react";
import { toast } from "@/Components/ui/toast";
import { Section, SectionField } from "@/Components/shared/section";
import { Checkbox } from "@/Components/ui/checkbox";
import { Label } from "@/Components/ui/label";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/Components/ui/select";
import { ShieldCheck } from "lucide-react";

interface RegistrationSectionProps {
  settings: Record<string, string>;
  roles?: { id: number; name: string; slug: string }[];
  asAccordion?: boolean;
}

export function RegistrationSection({
  settings,
  roles = [],
  asAccordion,
}: RegistrationSectionProps) {
  const [data, setData] = useState(() => {
    const firstRole = roles[0]?.slug ?? "";
    const storedRole = settings.default_role ?? firstRole;
    const validRole = roles.some((r) => r.slug === storedRole) ? storedRole : firstRole;
    return {
      enable_registration: settings.enable_registration ?? "0",
      default_role: validRole || "",
      require_email_verification: settings.require_email_verification ?? "0",
    };
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
        toast.add({ title: "Registration settings saved.", type: "success" });
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
      title="Registration"
      description="Configure public registration settings."
      icon={ShieldCheck}
      onSubmit={handleSubmit}
      loading={loading}
      fieldErrors={errors as Record<string, string[]>}
      submitLabel="Save Changes"
      asAccordion={asAccordion}
    >
      <SectionField
        label="Enable Public Registration"
        htmlFor="enable_registration"
        description="Allow new users to create accounts through the registration page. When disabled, the /register route redirects to login."
      >
        <div className="flex items-center gap-2">
          <Checkbox
            id="enable_registration"
            checked={data.enable_registration === "1"}
            onCheckedChange={(c) => setData((p) => ({ ...p, enable_registration: c ? "1" : "0" }))}
          />
          <Label htmlFor="enable_registration" className="cursor-pointer font-normal text-sm">
            {data.enable_registration === "1" ? "Registration is open" : "Registration is closed"}
          </Label>
        </div>
      </SectionField>

      <SectionField
        label="Default Role"
        htmlFor="default_role"
        description="The role assigned to every new user upon registration. Admin roles are excluded — they can only be created through the backend."
      >
        {roles.length === 0 ? (
          <p className="text-sm text-amber-500 dark:text-amber-400">
            No non-admin roles exist. Create one in the Roles section first.
          </p>
        ) : (
          <Select
            value={data.default_role || undefined}
            onValueChange={(v) => setData((p) => ({ ...p, default_role: v ?? "" }))}
          >
            <SelectTrigger id="default_role" className="w-full">
              <SelectValue placeholder="Select a role...">
                {(value: string | null) =>
                  roles.find((r) => r.slug === value)?.name ?? "Select a role..."
                }
              </SelectValue>
            </SelectTrigger>
            <SelectContent>
              {roles.map((role) => (
                <SelectItem key={role.slug} value={role.slug}>
                  {role.name}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        )}
      </SectionField>

      <SectionField
        label="Require Email Verification"
        htmlFor="require_email_verification"
        description="When enabled, new users must verify their email address before accessing the dashboard. Unverified users will be redirected to a verification notice page."
      >
        <div className="flex items-center gap-2">
          <Checkbox
            id="require_email_verification"
            checked={data.require_email_verification === "1"}
            onCheckedChange={(c) =>
              setData((p) => ({ ...p, require_email_verification: c ? "1" : "0" }))
            }
          />
          <Label
            htmlFor="require_email_verification"
            className="cursor-pointer font-normal text-sm"
          >
            {data.require_email_verification === "1"
              ? "Verification required"
              : "Verification optional"}
          </Label>
        </div>
      </SectionField>
    </Section>
  );
}
