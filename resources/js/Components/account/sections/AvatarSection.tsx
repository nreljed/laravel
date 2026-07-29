import { Section } from "@/Components/shared/section";
import { AvatarUploader } from "@/Components/shared/avatar-uploader";
import { Camera } from "lucide-react";

interface AvatarSectionProps {
  currentAvatar?: string | null;
  hasCustomAvatar?: boolean;
  onUpload?: (_file: File | Blob, _uploadId: string) => void;
  onDelete?: (_deleteId: string) => void;
  uploading?: boolean;
  error?: string | null;
  userName?: string | null;
  asAccordion?: boolean;
  accordionDefaultOpen?: boolean;
}

export function AvatarSection({
  currentAvatar,
  hasCustomAvatar,
  onUpload,
  onDelete,
  uploading,
  error,
  userName,
  asAccordion,
  accordionDefaultOpen,
}: AvatarSectionProps) {
  return (
    <Section
      title="Profile Picture"
      description="Upload a photo to represent yourself"
      icon={Camera}
      asAccordion={asAccordion}
      accordionDefaultOpen={accordionDefaultOpen}
    >
      <AvatarUploader
        currentAvatar={currentAvatar}
        onUpload={onUpload}
        onDelete={onDelete}
        uploading={uploading}
        error={error}
        userName={userName}
        hasCustomAvatar={hasCustomAvatar}
      />
    </Section>
  );
}

export default AvatarSection;
