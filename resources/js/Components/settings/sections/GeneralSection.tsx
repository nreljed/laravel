import * as React from "react";
import { Section, SectionField } from "@/Components/section";
import { Input } from "@/Components/ui/input";
import { LogoUploader } from "@/Components/settings/LogoUploader";
import { Globe } from "lucide-react";

interface GeneralSectionProps {
  data: Record<string, string | undefined>;
  onChange: (_field: string, _value: string) => void;
  errors: Record<string, unknown>;
  loading?: boolean;
  onSubmit?: (_e: React.FormEvent) => void;
  asAccordion?: boolean;
}

function GeneralSection({
  data,
  onChange,
  errors,
  loading,
  onSubmit,
  asAccordion,
}: GeneralSectionProps) {
  return (
    <Section
      title="App Settings"
      description="Configure general settings for your CRM Dashboard app"
      icon={Globe}
      onSubmit={onSubmit}
      loading={loading}
      error={(errors?.general as string) || (errors?.message as string)}
      fieldErrors={errors as Record<string, string[]>}
      submitLabel="Save Changes"
      asAccordion={asAccordion}
    >
      <SectionField
        label="Application Name"
        htmlFor="app_name"
        error={errors?.app_name as string}
        description="The name of your application"
      >
        <Input
          id="app_name"
          value={data?.app_name || ""}
          onChange={(e) => onChange("app_name", e.target.value)}
          placeholder="My CRM"
          disabled={loading}
        />
      </SectionField>

      <SectionField
        label="Description"
        htmlFor="app_description"
        error={errors?.app_description as string}
        description="Brief description of your application"
      >
        <Input
          id="app_description"
          value={data?.app_description || ""}
          onChange={(e) => onChange("app_description", e.target.value)}
          placeholder="SaaS application for cost management"
          disabled={loading}
        />
      </SectionField>

      <SectionField
        label="Application URL"
        htmlFor="app_url"
        error={errors?.app_url as string}
        description="The base URL of your application"
      >
        <Input
          id="app_url"
          value={data?.app_url || ""}
          onChange={(e) => onChange("app_url", e.target.value)}
          placeholder="https://example.com"
          disabled={loading}
        />
      </SectionField>

      <SectionField
        label="Logo"
        htmlFor="app_logo"
        error={errors?.app_logo as string}
        description="Upload your app logo (displayed in sidebar, auth pages, and emails)"
      >
        <LogoUploader
          currentLogo={data?.app_logo}
          onLogoChange={(url) => onChange("app_logo", url)}
          disabled={loading}
        />
      </SectionField>
    </Section>
  );
}

export { GeneralSection };
