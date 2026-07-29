import * as React from "react";
import { Section, SectionField } from "@/Components/shared/section";
import { Input } from "@/Components/ui/input";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/Components/ui/select";
import { LogoUploader } from "@/Components/settings/LogoUploader";
import { FaviconUploader } from "@/Components/settings/FaviconUploader";
import { Globe } from "lucide-react";

const LOCALE_OPTIONS = [
  { value: "en", label: "English" },
  { value: "fr", label: "French" },
  { value: "ar", label: "Arabic" },
  { value: "de", label: "German" },
  { value: "es", label: "Spanish" },
  { value: "it", label: "Italian" },
  { value: "pt", label: "Portuguese" },
  { value: "tr", label: "Turkish" },
  { value: "nl", label: "Dutch" },
  { value: "ja", label: "Japanese" },
  { value: "zh", label: "Chinese" },
];

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
      description="Configure general settings for your application."
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
          placeholder="Cost Breaker 360"
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
          type="url"
          value={data?.app_url || ""}
          onChange={(e) => onChange("app_url", e.target.value)}
          placeholder="https://example.com"
          disabled={loading}
        />
      </SectionField>

      <SectionField
        label="Locale"
        htmlFor="locale"
        error={errors?.locale as string}
        description="Default language for the application"
      >
        <Select
          value={data?.locale || "en"}
          onValueChange={(value) => onChange("locale", value ?? "")}
          disabled={loading}
        >
          <SelectTrigger id="locale">
            <SelectValue placeholder="Select locale" />
          </SelectTrigger>
          <SelectContent>
            {LOCALE_OPTIONS.map((option) => (
              <SelectItem key={option.value} value={option.value}>
                {option.label}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
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

      <SectionField
        label="Favicon"
        htmlFor="app_favicon"
        error={errors?.app_favicon as string}
        description="Browser tab icon. Separate from the logo — upload a square ICO, PNG, or SVG."
      >
        <FaviconUploader
          currentFavicon={data?.app_favicon}
          onFaviconChange={(url) => onChange("app_favicon", url)}
          disabled={loading}
        />
      </SectionField>
    </Section>
  );
}

export { GeneralSection };
