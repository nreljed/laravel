import * as React from "react";
import { X, Image as ImageIcon, Loader2, AlertCircle } from "lucide-react";
import { Button } from "@/Components/ui/button";
import { Progress } from "@/Components/ui/progress";
import { toast } from "@/Components/ui/toast";
import { useImageUpload } from "@/hooks/use-image-upload";

interface FeaturedImageSelectorProps {
  value?: string;
  onChange: (_url: string) => void;
  label?: string;
  description?: string;
  uploadUrl?: string;
  deleteUrl?: string;
  compress?: boolean;
}

const FeaturedImageSelector = ({
  value = "",
  onChange,
  label = "Featured Image",
  description = "Select a featured image",
  uploadUrl,
  deleteUrl,
  compress = true,
}: FeaturedImageSelectorProps) => {
  const [preview, setPreview] = React.useState(value);
  const [isDragging, setIsDragging] = React.useState(false);
  const [progress, setProgress] = React.useState(0);
  const [error, setError] = React.useState<string | null>(null);
  const fileInputRef = React.useRef<HTMLInputElement>(null);
  const { upload, remove } = useImageUpload({
    uploadUrl: uploadUrl || "",
    deleteUrl: deleteUrl || "",
    compress,
  });

  React.useEffect(() => {
    const timer = setTimeout(() => {
      setPreview(value);
    }, 0);
    return () => clearTimeout(timer);
  }, [value]);

  const handleRemove = async () => {
    const oldUrl = preview;
    setPreview("");
    setError(null);
    onChange("");

    if (oldUrl && oldUrl.startsWith("/storage/")) {
      try {
        const path = oldUrl.replace(/^\/storage\//, "");
        await remove(path);
      } catch {
        // Best-effort delete
      }
    }

    toast.add({ title: "Featured image removed", type: "info" });
  };

  const handleFileSelect = async (files: File[]) => {
    if (!files || files.length === 0) return;

    const file = files[0]!;

    if (!file.type.startsWith("image/")) {
      toast.add({ title: "Please select an image file", type: "error" });
      return;
    }

    setError(null);
    setProgress(10);

    try {
      const result = await upload(file);
      setProgress(100);
      setPreview(result.url);
      onChange(result.url);
      toast.add({ title: "Featured image uploaded", type: "success" });
    } catch (error_) {
      setError(error_ instanceof Error ? error_.message : "Upload failed");
      toast.add({ title: "Upload failed", type: "error" });
    } finally {
      setProgress(0);
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
          {progress > 0 && progress < 100 ? (
            <>
              <Loader2 className="h-8 w-8 animate-spin text-muted-foreground" />
              <p className="text-sm text-muted-foreground">Uploading...</p>
              <Progress value={progress} className="w-32" />
            </>
          ) : error ? (
            <>
              <AlertCircle className="h-8 w-8 text-destructive" />
              <p className="text-sm text-destructive">{error}</p>
              <p className="text-xs text-muted-foreground">Click to try again</p>
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
