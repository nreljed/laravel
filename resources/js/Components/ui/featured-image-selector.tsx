import * as React from "react";
import { Upload, X, Image as ImageIcon, Loader2 } from "lucide-react";
import { Button } from "@/Components/ui/button";
import { toast } from "sonner";
import { processImage, DEFAULT_IMAGE_CONFIG } from "@/lib/gallery-utils";

interface UploadResult {
  url: string;
  attachment_id?: number;
  id?: number;
}

interface FeaturedImageSelectorProps {
  value?: string;
  onChange: (_url: string, _attachmentId: number) => void;
  onUpload?: (
    _file: File,
  ) => Promise<UploadResult>;
  onDelete?: (
    _url: string,
    _attachmentId: number,
  ) => Promise<void>;
  label?: string;
  description?: string;
}

const FeaturedImageSelector = ({
  value = "",
  onChange,
  onUpload,
  onDelete,
  label = "Featured Image",
  description = "Select a featured image for this post",
}: FeaturedImageSelectorProps) => {
  const [preview, setPreview] = React.useState(value);
  const [attachmentId, setAttachmentId] = React.useState(0);
  const [uploading, setUploading] = React.useState(false);
  const [isDragging, setIsDragging] = React.useState(false);
  const fileInputRef = React.useRef<HTMLInputElement>(null);

  React.useEffect(() => {
    const timer = setTimeout(() => {
      setPreview(value);
    }, 0);
    return () => clearTimeout(timer);
  }, [value]);

  const handleRemove = async (): Promise<void> => {
    const imageUrl = preview;
    const attId = attachmentId;

    setPreview("");
    setAttachmentId(0);
    onChange("", 0);

    if (onDelete && imageUrl) {
      await onDelete(imageUrl, attId);
    }

    toast.info("Featured image removed");
  };

  const handleFileSelect = async (files: File[]): Promise<void> => {
    if (!files || files.length === 0) return;

    const file = files[0];

    if (!file.type.startsWith("image/")) {
      toast.error("Please select an image file");
      return;
    }

    setUploading(true);

    try {
      const processed = await processImage(file, DEFAULT_IMAGE_CONFIG);

      let result: UploadResult;

      if (onUpload) {
        result = await onUpload(processed);
      } else {
        throw new Error("No upload handler provided");
      }

      if (result?.url) {
        const imageUrl = result.url;
        const attId = result.attachment_id || result.id || 0;

        setPreview(imageUrl);
        setAttachmentId(attId);

        onChange(imageUrl, attId);
        toast.success("Featured image uploaded successfully");
      } else {
        throw new Error("Upload failed: no URL returned");
      }
    } catch (error) {
      const errorMessage = error instanceof Error ? error.message : "Failed to upload image";
      toast.error(errorMessage);
    } finally {
      setUploading(false);
    }
  };

  const handleClick = (): void => {
    fileInputRef.current?.click();
  };

  const handleFileInputChange = (e: React.ChangeEvent<HTMLInputElement>): void => {
    const files = e.target.files;
    if (files && files.length > 0) {
      handleFileSelect([files[0]]);
    }
    e.target.value = "";
  };

  const handleDragEnter = (e: React.DragEvent<HTMLDivElement>): void => {
    e.preventDefault();
    e.stopPropagation();
    setIsDragging(true);
  };

  const handleDragLeave = (e: React.DragEvent<HTMLDivElement>): void => {
    e.preventDefault();
    e.stopPropagation();
    setIsDragging(false);
  };

  const handleDragOver = (e: React.DragEvent<HTMLDivElement>): void => {
    e.preventDefault();
    e.stopPropagation();
  };

  const handleDrop = (e: React.DragEvent<HTMLDivElement>): void => {
    e.preventDefault();
    e.stopPropagation();
    setIsDragging(false);

    const files = e.dataTransfer.files;
    if (files && files.length > 0) {
      handleFileSelect([files[0]]);
    }
  };

  return (
    <div className="space-y-3">
      {label && (
        <div>
          <label className="text-sm font-medium">{label}</label>
          {description && <p className="text-xs text-muted-foreground">{description}</p>}
        </div>
      )}

      <input
        ref={fileInputRef}
        type="file"
        accept="image/*"
        className="hidden"
        onChange={handleFileInputChange}
      />

      {preview ? (
        <div className="relative group rounded-lg overflow-hidden border">
          <img src={preview} alt="Featured image preview" className="w-full h-48 object-cover" />

          <div className="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center gap-2">
            <Button
              type="button"
              variant="secondary"
              size="sm"
              onClick={handleClick}
              disabled={uploading}
            >
              {uploading ? (
                <Loader2 className="h-4 w-4 animate-spin mr-2" />
              ) : (
                <Upload className="h-4 w-4 mr-2" />
              )}
              Change
            </Button>
            <Button
              type="button"
              variant="destructive"
              size="sm"
              onClick={handleRemove}
            >
              <X className="h-4 w-4 mr-2" />
              Remove
            </Button>
          </div>

          <div className="absolute top-2 left-2">
            <span className="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-primary/90 text-primary-foreground">
              Featured
            </span>
          </div>
        </div>
      ) : (
        <div
          className={
            `border-2 border-dashed rounded-lg p-6 text-center cursor-pointer
            transition-colors hover:bg-accent/50
            ${isDragging ? "border-primary bg-primary/10" : "border-muted-foreground/25"}`
          }
          onClick={handleClick}
          onDragEnter={handleDragEnter}
          onDragLeave={handleDragLeave}
          onDragOver={handleDragOver}
          onDrop={handleDrop}
        >
          {uploading ? (
            <div className="flex flex-col items-center gap-2">
              <Loader2 className="h-8 w-8 animate-spin text-muted-foreground" />
              <p className="text-sm text-muted-foreground">Processing and uploading...</p>
            </div>
          ) : (
            <div className="flex flex-col items-center gap-2">
              <div className="p-3 rounded-full bg-muted">
                <ImageIcon className="h-6 w-6 text-muted-foreground" />
              </div>
              <div>
                <p className="text-sm font-medium">Click or drag to upload</p>
                <p className="text-xs text-muted-foreground mt-1">
                  Images are processed securely
                </p>
              </div>
            </div>
          )}
        </div>
      )}
    </div>
  );
};

export { FeaturedImageSelector };
export default FeaturedImageSelector;
