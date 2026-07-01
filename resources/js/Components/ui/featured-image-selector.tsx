import * as React from "react";
import { X, Image as ImageIcon, Loader2 } from "lucide-react";
import { Button } from "@/Components/ui/button";
import { toast } from "sonner";

interface FeaturedImageSelectorProps {
  value?: string;
  onChange: (_url: string, _attachmentId: number) => void;
  onUpload?: (_file: File) => Promise<{ url: string; id: number }>;
  label?: string;
  description?: string;
}

const FeaturedImageSelector = ({
  value = "",
  onChange,
  onUpload,
  label = "Featured Image",
  description = "Select a featured image",
}: FeaturedImageSelectorProps) => {
  const [preview, setPreview] = React.useState(value);
  const [_attachmentId, setAttachmentId] = React.useState(0);
  const [uploading, setUploading] = React.useState(false);
  const [isDragging, setIsDragging] = React.useState(false);
  const fileInputRef = React.useRef<HTMLInputElement>(null);

  React.useEffect(() => {
    const timer = setTimeout(() => {
      setPreview(value);
    }, 0);
    return () => clearTimeout(timer);
  }, [value]);

  const handleRemove = () => {
    setPreview("");
    setAttachmentId(0);
    onChange("", 0);
    toast.info("Featured image removed");
  };

  const handleFileSelect = async (files: File[]) => {
    if (!files || files.length === 0) return;

    const file = files[0]!;

    if (!file.type.startsWith("image/")) {
      toast.error("Please select an image file");
      return;
    }

    setUploading(true);

    try {
      if (onUpload) {
        const result = await onUpload(file);
        setPreview(result.url);
        setAttachmentId(result.id);
        onChange(result.url, result.id);
        toast.success("Featured image uploaded");
      } else {
        const reader = new FileReader();
        reader.addEventListener("load", (e) => {
          const url = e.target?.result as string;
          setPreview(url);
          onChange(url, 0);
        });
        reader.readAsDataURL(file);
      }
    } catch {
      toast.error("Upload failed");
    } finally {
      setUploading(false);
    }
  };

  const handleDrop = (e: React.DragEvent) => {
    e.preventDefault();
    setIsDragging(false);
    const files = [...e.dataTransfer.files];
    handleFileSelect(files);
  };

  const handleDragOver = (e: React.DragEvent) => {
    e.preventDefault();
    setIsDragging(true);
  };

  const handleDragLeave = (e: React.DragEvent) => {
    e.preventDefault();
    setIsDragging(false);
  };

  const handleInputChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const files = [...(e.target.files ?? [])];
    handleFileSelect(files);
    e.target.value = "";
  };

  return (
    <div className="space-y-2">
      {label && <label className="text-sm font-medium">{label}</label>}
      {description && <p className="text-sm text-muted-foreground">{description}</p>}

      {preview ? (
        <div className="relative overflow-hidden rounded-lg border">
          <img src={preview} alt="Featured" className="w-full h-48 object-cover" />
          <Button
            type="button"
            variant="destructive"
            size="sm"
            className="absolute top-2 right-2"
            onClick={handleRemove}
            disabled={uploading}
          >
            <X className="h-4 w-4" />
          </Button>
        </div>
      ) : (
        <div
          onDrop={handleDrop}
          onDragOver={handleDragOver}
          onDragLeave={handleDragLeave}
          onClick={() => fileInputRef.current?.click()}
          className={`flex flex-col items-center justify-center gap-2 rounded-lg border-2 border-dashed p-8 cursor-pointer transition-colors hover:bg-accent/30 ${
            isDragging ? "border-primary bg-accent/30" : "border-border"
          }`}
        >
          {uploading ? (
            <>
              <Loader2 className="h-8 w-8 animate-spin text-muted-foreground" />
              <p className="text-sm text-muted-foreground">Uploading...</p>
            </>
          ) : (
            <>
              <ImageIcon className="h-8 w-8 text-muted-foreground" />
              <p className="text-sm text-muted-foreground">Drop an image here or click to browse</p>
            </>
          )}
        </div>
      )}

      <input
        ref={fileInputRef}
        type="file"
        accept="image/*"
        className="hidden"
        onChange={handleInputChange}
      />
    </div>
  );
};

export { FeaturedImageSelector };
