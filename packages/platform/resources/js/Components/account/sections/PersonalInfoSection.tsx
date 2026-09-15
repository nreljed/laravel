import { useCallback, useMemo } from "react";
import { Section, SectionField } from "../../shared/section";
import { Input } from "../../ui/input";
import { Textarea } from "../../ui/textarea";
import { Repeater } from "../../shared/repeater";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "../../ui/select";
import { Building2, User } from "lucide-react";

const SOCIAL_PRESETS = [
  { label: "Facebook", value: "Facebook" },
  { label: "Instagram", value: "Instagram" },
  { label: "LinkedIn", value: "LinkedIn" },
  { label: "X (Twitter)", value: "X (Twitter)" },
  { label: "YouTube", value: "YouTube" },
  { label: "TikTok", value: "TikTok" },
  { label: "Other", value: "Other" },
];

interface PersonalInfoSectionProps {
  data?: Record<string, unknown> | null;
  onChange?: (_field: string, _value: unknown) => void;
  errors?: Record<string, unknown>;
  loading?: boolean;
  onSubmit?: (_e: React.FormEvent) => void;
  asAccordion?: boolean;
  accordionDefaultOpen?: boolean;
}

export function PersonalInfoSection({
  data,
  onChange,
  errors,
  loading,
  onSubmit,
  asAccordion,
  accordionDefaultOpen,
}: PersonalInfoSectionProps) {
  const handleChange = useCallback(
    (field: string, value: unknown) => {
      onChange?.(field, value);
    },
    [onChange],
  );

  const displayNameOptions = useMemo(() => {
    const d = (data || {}) as Record<string, unknown>;
    return (d.display_name_options as Record<string, string>) || {};
  }, [data]);

  const hasMultipleOptions = Object.keys(displayNameOptions).length > 1;

  const sortedOptions = useMemo(() => {
    const displayNameOrder = ["username", "first", "last", "first_last", "last_first"];
    const entries = Object.entries(displayNameOptions);
    return [...entries].toSorted((a, b) => {
      const indexA = displayNameOrder.indexOf(a[0]);
      const indexB = displayNameOrder.indexOf(b[0]);
      return (indexA === -1 ? 999 : indexA) - (indexB === -1 ? 999 : indexB);
    });
  }, [displayNameOptions]);

  const formatKey = (key: string): string => {
    const labels: Record<string, string> = {
      username: "Username",
      first: "First Name",
      last: "Last Name",
      first_last: "First & Last",
      last_first: "Last & First",
    };
    return labels[key] || key;
  };

  return (
    <Section
      title="Personal Information"
      description="Your personal information and social media links"
      icon={User}
      onSubmit={onSubmit}
      loading={loading}
      error={
        (errors?.general as string) || (errors?.message as string) || (errors?.error as string)
      }
      fieldErrors={errors as Record<string, string[]>}
      submitLabel="Save Changes"
      columns={2}
      asAccordion={asAccordion}
      accordionDefaultOpen={accordionDefaultOpen}
    >
      <div className="md:col-span-2">
        <SectionField label="Username" htmlFor="username" description="Usernames cannot be changed">
          <Input
            id="username"
            type="text"
            value={(data?.username as string) || (data?.name as string) || ""}
            disabled
            className="bg-muted cursor-not-allowed"
          />
        </SectionField>
      </div>

      <SectionField
        label="First Name"
        htmlFor="first_name"
        error={errors?.first_name as string}
        description="Your first name"
      >
        <Input
          id="first_name"
          type="text"
          value={(data?.first_name as string) || ""}
          onChange={(e) => handleChange("first_name", e.target.value)}
          placeholder="First Name"
          disabled={loading}
          aria-invalid={!!errors?.first_name}
        />
      </SectionField>

      <SectionField
        label="Last Name"
        htmlFor="last_name"
        error={errors?.last_name as string}
        description="Your last name"
      >
        <Input
          id="last_name"
          type="text"
          value={(data?.last_name as string) || ""}
          onChange={(e) => handleChange("last_name", e.target.value)}
          placeholder="Last Name"
          disabled={loading}
          aria-invalid={!!errors?.last_name}
        />
      </SectionField>

      <SectionField
        label="Display Name"
        htmlFor="display_name"
        error={errors?.display_name as string}
        required
        description="How your name appears to others"
      >
        {hasMultipleOptions ? (
          <Select
            value={(data?.display_name as string) || ""}
            onValueChange={(value) => handleChange("display_name", value ?? "")}
            disabled={loading}
          >
            <SelectTrigger
              id="display_name"
              className="w-full"
              aria-invalid={!!errors?.display_name}
            >
              <SelectValue placeholder="Select your display name" />
            </SelectTrigger>
            <SelectContent>
              {sortedOptions.map(([key, value]) => (
                <SelectItem key={key} value={value} className="flex flex-col items-start">
                  <span className="font-medium">{value}</span>
                  <span className="text-muted-foreground text-xs">{formatKey(key)}</span>
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        ) : (
          <Input
            id="display_name"
            type="text"
            value={(data?.display_name as string) || ""}
            onChange={(e) => handleChange("display_name", e.target.value)}
            placeholder="Your display name"
            disabled={loading}
            aria-invalid={!!errors?.display_name}
          />
        )}
      </SectionField>

      <div className="md:col-span-2">
        <SectionField
          label="Biographical Info"
          htmlFor="bio"
          error={errors?.bio as string}
          description="This text will be displayed publicly on your profile page"
        >
          <Textarea
            id="bio"
            value={(data?.bio as string) || ""}
            onChange={(e) => handleChange("bio", e.target.value)}
            placeholder="Share a little biographical information about yourself..."
            rows={5}
            disabled={loading}
            aria-invalid={!!errors?.bio}
          />
        </SectionField>
      </div>

      <div className="md:col-span-2">
        <div className="flex items-center gap-2 mb-1">
          <Building2 className="h-4 w-4 text-muted-foreground" />
          <span className="text-sm font-medium text-foreground">Social Media Links</span>
        </div>
        <Repeater
          label=""
          description="Add your social media profiles"
          value={
            ((data?.social_media as Array<Record<string, unknown>>) || []).length > 0
              ? (data?.social_media as Array<Record<string, unknown>>)
              : []
          }
          onChange={(value) => handleChange("social_media", value)}
          fields={[
            {
              name: "social_title",
              label: "Platform",
              type: "select",
              placeholder: "Choose platform",
              options: SOCIAL_PRESETS,
              presetMode: true,
              presetOtherValue: "Other",
              customPlaceholder: "Enter custom platform name",
              required: true,
            },
            {
              name: "social_value",
              label: "URL",
              type: "url",
              placeholder: "https://...",
              required: true,
            },
          ]}
          maxItems={20}
          disabled={loading}
          accordionMode={true}
          titleField="social_title"
          showFieldsInTitle={["social_title_custom"]}
          accordionDefaultOpen={false}
          errors={errors as Record<string, string>}
        />
      </div>
    </Section>
  );
}

export default PersonalInfoSection;
