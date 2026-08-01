import * as React from "react";
import { router } from "@inertiajs/react";
import { toast } from "@/Components/ui/toast";
import { Section } from "@/Components/shared/section";
import { Repeater } from "@/Components/shared/repeater";
import { Building2, Phone } from "lucide-react";

const SOCIAL_PRESETS = [
  { label: "Facebook", value: "Facebook" },
  { label: "Instagram", value: "Instagram" },
  { label: "LinkedIn", value: "LinkedIn" },
  { label: "X (Twitter)", value: "X (Twitter)" },
  { label: "YouTube", value: "YouTube" },
  { label: "TikTok", value: "TikTok" },
  { label: "Other", value: "Other" },
];

const PHONE_LABEL_PRESETS = [
  { label: "Mobile", value: "Mobile" },
  { label: "Work", value: "Work" },
  { label: "Home", value: "Home" },
  { label: "WhatsApp", value: "WhatsApp" },
  { label: "Other", value: "Other" },
];

interface AdditionalInfoSectionProps {
  data?: Record<string, unknown> | null;
  asAccordion?: boolean;
  accordionDefaultOpen?: boolean;
}

export function AdditionalInfoSection({
  data,
  asAccordion,
  accordionDefaultOpen,
}: AdditionalInfoSectionProps) {
  const [formData, setFormData] = React.useState<Record<string, unknown>>(data || {});
  const [loading, setLoading] = React.useState(false);
  const [errors, setErrors] = React.useState<Record<string, unknown>>({});

  const handleChange = (field: string, value: unknown) => {
    setFormData((prev) => ({ ...prev, [field]: value }));
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (loading) return;

    setLoading(true);
    setErrors({});

    router.patch(
      route("dashboard.account.profile"),
      {
        _update_type: "additional-info",
        profile_data: {
          phone_numbers: formData.phone_numbers as Array<Record<string, string>>,
          social_media: formData.social_media as Array<Record<string, string>>,
        },
      },
      {
        preserveScroll: true,
        preserveState: true,
        replace: true,
        onSuccess: () => {
          toast.add({ title: "Additional info updated successfully!", type: "success" });
          setLoading(false);
        },
        onError: (errs: Record<string, string>) => {
          setErrors(errs);
          toast.add({
            title: errs?.error || errs?.message || "Failed to update additional info.",
            type: "error",
          });
          setLoading(false);
        },
        onFinish: () => setLoading(false),
      },
    );
  };

  return (
    <Section
      title="Additional Info"
      description="Phone numbers and social media links"
      icon={Building2}
      onSubmit={handleSubmit}
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
        <div className="flex items-center gap-2 mb-1">
          <Phone className="h-4 w-4 text-muted-foreground" />
          <span className="text-sm font-medium text-foreground">Phone Numbers</span>
        </div>
        <Repeater
          label=""
          description="Add multiple phone numbers with labels (e.g., Mobile, Work, WhatsApp)"
          value={(formData.phone_numbers as Array<Record<string, unknown>>) || []}
          onChange={(value: Array<Record<string, unknown>>) => handleChange("phone_numbers", value)}
          fields={[
            {
              name: "label",
              label: "Label",
              type: "select",
              placeholder: "Choose label",
              options: PHONE_LABEL_PRESETS,
              presetMode: true,
              presetOtherValue: "Other",
              customPlaceholder: "Enter custom label",
              required: true,
            },
            {
              name: "phone",
              label: "Phone Number",
              type: "phone",
              placeholder: "Enter phone number",
              required: true,
            },
          ]}
          maxItems={10}
          disabled={loading}
          accordionMode={true}
          titleField="label"
          showFieldsInTitle={["label_custom"]}
          accordionDefaultOpen={false}
          errors={errors as Record<string, string>}
        />
      </div>

      <div className="md:col-span-2">
        <div className="flex items-center gap-2 mb-1">
          <Building2 className="h-4 w-4 text-muted-foreground" />
          <span className="text-sm font-medium text-foreground">Social Media Links</span>
        </div>
        <Repeater
          label=""
          description="Add your social media profiles"
          value={(formData.social_media as Array<Record<string, unknown>>) || []}
          onChange={(value: Array<Record<string, unknown>>) => handleChange("social_media", value)}
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

export default AdditionalInfoSection;
