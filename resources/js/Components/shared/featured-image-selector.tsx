import { useState, useRef, useCallback, useEffect } from "react";
import { Upload, X, Image as ImageIcon, Loader2 } from "lucide-react";
import { Button } from "@/Components/ui/button";
import { toast } from "sonner";

interface FeaturedImageSelectorProps {
  value?: string;
  onChange: (_url: string, _path?: string) => void;
  label?: string;
  description?: string;
  disabled?: boolean;
}

export function FeaturedImageSelector({
  value = "",
  onChange,
  label = "Featured Image",
  description = "Select a featured image",
  disabled,
}: FeaturedImageSelectorProps) {
  const [preview, setPreview] = useState(value);
  const [uploading, setUploading] = useState(false);
  const fileInputRef = useRef<HTMLInputElement>(null);

  useEffect(() => {
    // eslint-disable-next-line react-hooks/set-state-in-effect
    setPreview(value);
  }, [value]);

  const handleFileSelect = async (file: File) => {
    if (!file.type.startsWith("image/")) {
      toast.error("Please select an image file");
      return;
    }

    setUploading(true);
    try {
      const formData = new FormData();
      formData.append("file", file);

      const csrfToken =
        document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") || "";
      const res = await fetch(route("dashboard.shop.products.upload-image"), {
        method: "POST",
        body: formData,
        headers: { "X-Requested-With": "XMLHttpRequest", "X-CSRF-TOKEN": csrfToken },
      });

      if (!res.ok) throw new Error("Upload failed");

      const data = await res.json();
      if (data?.url) {
        setPreview(data.url);
        onChange(data.url);
        toast.success("Image uploaded successfully");
      }
    } catch {
      toast.error("Upload failed");
    } finally {
      setUploading(false);
    }
  };

  const handleRemove = () => {
    setPreview("");
    onChange("");
  };

  const handleClick = () => fileInputRef.current?.click();

  const handleDrop = useCallback((e: React.DragEvent) => {
    e.preventDefault();
    const file = e.dataTransfer.files?.[0];
    if (file) handleFileSelect(file);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const handleDragOver = (e: React.DragEvent) => e.preventDefault();

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
        onChange={(e) => {
          const file = e.target.files?.[0];
          if (file) handleFileSelect(file);
          e.target.value = "";
        }}
      />

      {preview ? (
        <div className="relative group rounded-lg overflow-hidden border">
          <img src={preview} alt="Featured" loading="lazy" className="w-full h-48 object-cover" />
          <div className="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center gap-2">
            <Button
              type="button"
              variant="secondary"
              size="sm"
              onClick={handleClick}
              disabled={uploading || disabled}
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
              disabled={disabled}
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
          className="border-2 border-dashed rounded-lg p-6 text-center cursor-pointer transition-colors hover:bg-accent/50"
          onClick={handleClick}
          onDrop={handleDrop}
          onDragOver={handleDragOver}
        >
          {uploading ? (
            <div className="flex flex-col items-center gap-2">
              <Loader2 className="h-8 w-8 animate-spin text-muted-foreground" />
              <p className="text-sm text-muted-foreground">Uploading...</p>
            </div>
          ) : (
            <div className="flex flex-col items-center gap-2">
              <div className="p-3 rounded-full bg-muted">
                <ImageIcon className="h-6 w-6 text-muted-foreground" />
              </div>
              <p className="text-sm font-medium">Click or drag to upload</p>
              <p className="text-xs text-muted-foreground">Recommended: 1920x1080px</p>
            </div>
          )}
        </div>
      )}
    </div>
  );
}
