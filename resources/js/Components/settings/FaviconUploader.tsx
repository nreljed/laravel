import { useState, useEffect, useRef } from "react";
import { Upload, Loader2, Trash2, ImageIcon, Info } from "lucide-react";
import { toast } from "@/Components/ui/toast";
import { Button } from "@/Components/ui/button";
import { getCsrfToken } from "@/lib/gallery-utils";

const ALLOWED_TYPES = ["image/x-icon", "image/png", "image/svg+xml"];
const ALLOWED_EXTENSIONS = new Set([".ico", ".png", ".svg"]);
const MAX_FILE_SIZE = 5 * 1024 * 1024;

interface FaviconUploaderProps {
  currentFavicon?: string;
  onFaviconChange: (_url: string) => void;
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

export function FaviconUploader({
  currentFavicon,
  onFaviconChange,
  disabled,
}: FaviconUploaderProps) {
  const [uploading, setUploading] = useState(false);
  const [preview, setPreview] = useState<string | null>(currentFavicon ?? null);
  const [dims, setDims] = useState<{ width: number; height: number } | null>(null);
  const fileInputRef = useRef<HTMLInputElement>(null);

  useEffect(() => {
    const timeout = setTimeout(() => {
      if (!uploading) setPreview(currentFavicon ?? null);
    }, 0);
    return () => clearTimeout(timeout);
  }, [currentFavicon, uploading]);

  const handleFile = async (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file) return;

    const ext = "." + file.name.split(".").pop()?.toLowerCase();
    if (!ALLOWED_TYPES.includes(file.type) && !ALLOWED_EXTENSIONS.has(ext)) {
      toast.add({ title: "Invalid file type. Use ICO, PNG, or SVG.", type: "error" });
      return;
    }
    if (file.size > MAX_FILE_SIZE) {
      toast.add({ title: "File too large. Maximum 5MB.", type: "error" });
      return;
    }

    const isSvg = file.type === "image/svg+xml" || ext === ".svg";
    if (!isSvg) {
      try {
        const { width, height } = await getImageDimensions(file);
        if (width !== height) {
          toast.add({ title: `Favicon must be square (got ${width}x${height}px).`, type: "error" });
          return;
        }
        if (width < 16 || height < 16) {
          toast.add({
            title: `Image too small. Minimum 16x16px (got ${width}x${height}px).`,
            type: "error",
          });
          return;
        }
        setDims({ width, height });
      } catch {
        toast.add({ title: "Could not read image dimensions.", type: "error" });
        return;
      }
    } else {
      setDims(null);
    }

    setUploading(true);
    const formData = new FormData();
    formData.append("favicon", file);

    try {
      const res = await fetch(route("dashboard.admin.settings.favicon.upload") as string, {
        method: "POST",
        body: formData,
        headers: {
          "X-Requested-With": "XMLHttpRequest",
          "X-CSRF-TOKEN": getCsrfToken(),
        },
      });
      const data = await res.json();
      const url = data.url;
      setPreview(url);
      onFaviconChange(url);
      toast.add({ title: "Favicon uploaded", type: "success" });
    } catch {
      toast.add({ title: "Upload failed", type: "error" });
    } finally {
      setUploading(false);
      if (fileInputRef.current) fileInputRef.current.value = "";
    }
  };

  const handleRemove = async () => {
    setUploading(true);
    try {
      await fetch(route("dashboard.admin.settings.favicon.delete") as string, {
        method: "DELETE",
        headers: {
          "Content-Type": "application/json",
          "X-Requested-With": "XMLHttpRequest",
          "X-CSRF-TOKEN": getCsrfToken(),
        },
      });
      setPreview(null);
      setDims(null);
      onFaviconChange("");
      toast.add({ title: "Favicon removed", type: "success" });
    } catch {
      toast.add({ title: "Failed to remove favicon", type: "error" });
    } finally {
      setUploading(false);
    }
  };

  const isLoading = uploading || !!disabled;

  return (
    <div className="space-y-3">
      <div className="flex items-start gap-4">
        <div
          className="relative flex h-20 w-20 shrink-0 cursor-pointer items-center justify-center overflow-hidden rounded-lg border-2 border-dashed border-muted-foreground/20 bg-muted/30 hover:border-muted-foreground/40"
          onClick={() => !isLoading && fileInputRef.current?.click()}
        >
          {preview ? (
            <img src={preview} alt="Favicon" className="h-full w-full object-contain p-1" />
          ) : (
            <div className="flex flex-col items-center gap-1 text-muted-foreground">
              <ImageIcon className="h-5 w-5" />
              <span className="text-[10px]">16x16+</span>
            </div>
          )}
          {isLoading && (
            <div className="absolute inset-0 flex items-center justify-center bg-background/60">
              <Loader2 className="h-5 w-5 animate-spin text-primary" />
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
          {dims.width !== dims.height && " — not square, may not display evenly"}
        </p>
      )}

      <div className="flex items-start gap-1.5 text-xs text-muted-foreground">
        <Info className="mt-0.5 h-3 w-3 shrink-0" />
        <span>
          Accepts <strong>ICO</strong>, <strong>PNG</strong>, or <strong>SVG</strong>. Must be
          square. Recommended: 32x32px ICO or 64x64px+ PNG.
        </span>
      </div>
    </div>
  );
}
