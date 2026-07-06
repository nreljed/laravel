import { useState, useEffect, useRef } from "react";
import { Upload, Loader2, Trash2, ImageIcon, Info } from "lucide-react";
import { toast } from "sonner";
import { Button } from "@/Components/ui/button";
import axios from "axios";

const ALLOWED_TYPES = ["image/jpeg", "image/png", "image/gif", "image/webp", "image/svg+xml"];
const MAX_FILE_SIZE = 10 * 1024 * 1024;
const MIN_DIM = 100;
const MAX_DIM = 4096;

interface LogoUploaderProps {
  currentLogo?: string;
  onLogoChange: (_url: string) => void;
  disabled?: boolean;
}

function getImageDimensions(file: File): Promise<{ width: number; height: number }> {
  return new Promise((resolve, reject) => {
    const img = new Image();
    const url = URL.createObjectURL(file);
    img.addEventListener("load", () => {
      URL.revokeObjectURL(url);
      resolve({ width: img.naturalWidth, height: img.naturalHeight });
    });
    img.addEventListener("error", () => {
      URL.revokeObjectURL(url);
      reject(new Error("Failed to read image"));
    });
    img.src = url;
  });
}

export function LogoUploader({ currentLogo, onLogoChange, disabled }: LogoUploaderProps) {
  const [uploading, setUploading] = useState(false);
  const [preview, setPreview] = useState<string | null>(currentLogo ?? null);
  const [dims, setDims] = useState<{ width: number; height: number } | null>(null);
  const fileInputRef = useRef<HTMLInputElement>(null);

  useEffect(() => {
    const timeout = setTimeout(() => {
      if (!uploading) setPreview(currentLogo ?? null);
    }, 0);
    return () => clearTimeout(timeout);
  }, [currentLogo, uploading]);

  const handleFile = async (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file) return;

    if (!ALLOWED_TYPES.includes(file.type)) {
      toast.error("Invalid file type. Use JPG, PNG, GIF, WebP, or SVG.");
      return;
    }
    if (file.size > MAX_FILE_SIZE) {
      toast.error("File too large. Maximum 10MB.");
      return;
    }

    const isSvg = file.type === "image/svg+xml";
    if (!isSvg) {
      try {
        const { width, height } = await getImageDimensions(file);
        if (width < MIN_DIM || height < MIN_DIM) {
          toast.error(
            `Image too small. Minimum ${MIN_DIM}x${MIN_DIM}px (got ${width}x${height}px).`,
          );
          return;
        }
        if (width > MAX_DIM || height > MAX_DIM) {
          toast.error(
            `Image too large. Maximum ${MAX_DIM}x${MAX_DIM}px (got ${width}x${height}px).`,
          );
          return;
        }
        setDims({ width, height });
      } catch {
        toast.error("Could not read image dimensions.");
        return;
      }
    } else {
      setDims(null);
    }

    setUploading(true);
    const formData = new FormData();
    formData.append("logo", file);

    try {
      const res = await axios.post(
        route("dashboard.admin.settings.logo.upload") as string,
        formData,
        {
          headers: { "Content-Type": "multipart/form-data" },
        },
      );
      const url = res.data.url;
      setPreview(url);
      onLogoChange(url);
      toast.success("Logo uploaded");
    } catch {
      toast.error("Upload failed");
    } finally {
      setUploading(false);
      if (fileInputRef.current) fileInputRef.current.value = "";
    }
  };

  const handleRemove = async () => {
    setUploading(true);
    try {
      await axios.delete(route("dashboard.admin.settings.logo.delete") as string);
      setPreview(null);
      setDims(null);
      onLogoChange("");
      toast.success("Logo removed");
    } catch {
      toast.error("Failed to remove logo");
    } finally {
      setUploading(false);
    }
  };

  const isLoading = uploading || !!disabled;

  return (
    <div className="space-y-3">
      <div className="flex items-start gap-4">
        <div
          className="relative flex h-24 w-48 shrink-0 cursor-pointer items-center justify-center overflow-hidden rounded-lg border-2 border-dashed border-muted-foreground/20 bg-muted/30 hover:border-muted-foreground/40"
          onClick={() => !isLoading && fileInputRef.current?.click()}
        >
          {preview ? (
            <img src={preview} alt="Logo" className="max-h-full max-w-full object-contain p-2" />
          ) : (
            <div className="flex flex-col items-center gap-1 text-muted-foreground">
              <ImageIcon className="h-6 w-6" />
              <span className="text-xs">Click to upload</span>
            </div>
          )}
          {isLoading && (
            <div className="absolute inset-0 flex items-center justify-center bg-background/60">
              <Loader2 className="h-6 w-6 animate-spin text-primary" />
            </div>
          )}
        </div>

        <input
          ref={fileInputRef}
          type="file"
          accept={ALLOWED_TYPES.join(",")}
          onChange={handleFile}
          className="hidden"
          disabled={isLoading}
        />

        <div className="flex flex-col gap-2 pt-1">
          <Button
            type="button"
            variant="outline"
            size="sm"
            disabled={isLoading}
            onClick={() => fileInputRef.current?.click()}
          >
            <Upload className="mr-1 h-3 w-3" />
            {preview ? "Change" : "Upload"}
          </Button>
          {preview && (
            <Button
              type="button"
              variant="outline"
              size="sm"
              disabled={isLoading}
              onClick={handleRemove}
              className="text-destructive"
            >
              <Trash2 className="mr-1 h-3 w-3" />
              Remove
            </Button>
          )}
        </div>
      </div>

      {dims && (
        <p className="text-xs text-muted-foreground">
          {dims.width} x {dims.height}px
          {dims.width !== dims.height && " — not square, may not display evenly as favicon"}
        </p>
      )}

      <div className="flex items-start gap-1.5 text-xs text-muted-foreground">
        <Info className="mt-0.5 h-3 w-3 shrink-0" />
        <span>
          Recommended: <strong>SVG</strong> (vector, any size) or a <strong>512x512px+</strong>{" "}
          square PNG.
          {preview && " This logo also serves as the browser tab favicon."}
        </span>
      </div>
    </div>
  );
}
