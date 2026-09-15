import * as React from "react";
import { Section, SectionField } from "@nreljed/platform/Components/shared/section";
import { UrlVideo } from "@nreljed/platform/Components/shared/url-video";
import { Video } from "lucide-react";

interface VideoSectionData {
  meta?: { video?: string };
}

interface VideoSectionProps {
  data: VideoSectionData;
  onChange: (_field: string, _value: unknown) => void;
  errors?: Record<string, string>;
  loading?: boolean;
  onSubmit?: (_e: React.FormEvent) => void;
}

export function VideoSection({ data, onChange, errors, loading, onSubmit }: VideoSectionProps) {
  const handleMetaChange = React.useCallback(
    (field: string, value: string) => {
      onChange(`meta.${field}`, value);
    },
    [onChange],
  );

  return (
    <Section
      title="Video"
      description="Add a property video tour"
      icon={Video}
      onSubmit={onSubmit}
      loading={loading}
      error={errors?.general}
      fieldErrors={errors as unknown as Record<string, string[]>}
      submitLabel="Save Video"
    >
      <SectionField
        label="Video URL"
        htmlFor="video"
        error={errors?.video}
        description="Enter a YouTube or Vimeo link"
        className="md:col-span-2"
      >
        <UrlVideo
          id="video"
          value={data.meta?.video || ""}
          onChange={(val) => handleMetaChange("video", val)}
          error={errors?.video}
          disabled={loading}
        />
      </SectionField>
    </Section>
  );
}

export default VideoSection;
