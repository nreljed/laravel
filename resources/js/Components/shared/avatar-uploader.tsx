import { useState, useEffect, useRef } from "react";
import {
  Upload,
  Image as ImageIcon,
  Loader2,
  Pencil,
  ImagePlus,
  Trash2,
  AlertCircle,
  CheckCircle2,
} from "lucide-react";
import { toast } from "@/Components/ui/toast";
import { cn } from "@/lib/utils";
import { Button } from "@/Components/ui/button";
import { Avatar, AvatarImage, AvatarFallback } from "@/Components/ui/avatar";
import { Alert, AlertDescription } from "@/Components/ui/alert";
import { getUserInitials } from "@/hooks/use-auth";
import { CropperDialog } from "@/Components/shared/cropper-dialog";
import {
  DropdownMenu,
  DropdownMenuTrigger,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
} from "@/Components/ui/dropdown-menu";

const MAX_FILE_SIZE = 50 * 1024 * 1024;
const ALLOWED_TYPES = ["image/jpeg", "image/png", "image/gif", "image/webp"];

interface AvatarUploaderProps {
  currentAvatar?: string | null;
  onUpload?: (_file: File | Blob, _uploadId: string) => void;
  onDelete?: (_deleteId: string) => void;
  uploading?: boolean;
  error?: string | null;
  hasCustomAvatar?: boolean;
  className?: string;
  userName?: string | null;
}

export function AvatarUploader({
  currentAvatar,
  onUpload,
  onDelete,
  uploading,
  error,
  hasCustomAvatar,
  className,
  userName,
}: AvatarUploaderProps) {
  const isUploading = uploading || false;
  const errorMessage = error || null;
  const [processing, setProcessing] = useState(false);
  const [preview, setPreview] = useState<string | null>(currentAvatar ?? null);
  const [showSuccess, setShowSuccess] = useState(false);
  const [cropDialogOpen, setCropDialogOpen] = useState(false);
  const [cropImageSrc, setCropImageSrc] = useState<string | null>(null);
  const [dragActive, setDragActive] = useState(false);
  const uploadIdRef = useRef<string | null>(null);
  const fileInputRef = useRef<HTMLInputElement>(null);

  useEffect(() => {
    if (isUploading || processing) return;
    uploadIdRef.current = null;
    const timer = setTimeout(() => {
      setPreview(currentAvatar || null);
    }, 0);
    return () => clearTimeout(timer);
  }, [currentAvatar, isUploading, processing]);

  useEffect(() => {
    if (!showSuccess) return;
    const timer = setTimeout(() => setShowSuccess(false), 3000);
    return () => clearTimeout(timer);
  }, [showSuccess]);

  const validateFile = (file: File) => {
    if (!ALLOWED_TYPES.includes(file.type)) {
      throw new Error("Invalid file type. Please use JPG, PNG, GIF, or WebP.");
    }
    if (file.size > MAX_FILE_SIZE) {
      throw new Error("File too large. Maximum 50MB allowed.");
    }
    return true;
  };

  const handleFile = async (file: File) => {
    try {
      validateFile(file);

      const dataUrl = await new Promise<string>((resolve, reject) => {
        const reader = new FileReader();
        reader.addEventListener("load", (e) => resolve(e.target?.result as string));
        reader.addEventListener("error", () => reject(new Error("Failed to read file")));
        reader.readAsDataURL(file);
      });

      setCropImageSrc(dataUrl);
      setCropDialogOpen(true);
    } catch (error_) {
      toast.add({ title: (error_ as Error).message || "Failed to process image", type: "error" });
    }
  };

  const handleCropSave = async (blob: Blob) => {
    const thisUploadId = Date.now() + "_" + Math.random().toString(36).slice(2, 11);
    uploadIdRef.current = thisUploadId;

    const previewUrl = URL.createObjectURL(blob);
    setPreview(previewUrl);
    setCropImageSrc(null);
    setCropDialogOpen(false);
    setProcessing(true);

    try {
      if (onUpload) {
        await onUpload(blob, thisUploadId);
        setShowSuccess(true);
      }
    } catch {
      setPreview(currentAvatar ?? null);
      toast.add({ title: "Upload failed", type: "error" });
    } finally {
      URL.revokeObjectURL(previewUrl);
      if (uploadIdRef.current === thisUploadId) {
        uploadIdRef.current = null;
        setProcessing(false);
      }
    }
  };

  const handleChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (file) handleFile(file);
  };

  const handleDrag = (e: React.DragEvent) => {
    e.preventDefault();
    e.stopPropagation();
    if (e.type === "dragenter" || e.type === "dragover") {
      setDragActive(true);
    } else if (e.type === "dragleave") {
      setDragActive(false);
    }
  };

  const handleDrop = (e: React.DragEvent) => {
    e.preventDefault();
    e.stopPropagation();
    setDragActive(false);
    const file = e.dataTransfer?.files?.[0];
    if (file) handleFile(file);
  };

  const handleDelete = async () => {
    if (!hasCustomAvatar || isUploading || processing) return;

    const thisDeleteId = Date.now() + "_" + Math.random().toString(36).slice(2, 11);
    setProcessing(true);

    if (onDelete) {
      try {
        await onDelete(thisDeleteId);
      } catch {
        setPreview(currentAvatar ?? null);
      } finally {
        setProcessing(false);
      }
    }
  };

  const triggerInput = () => {
    if (fileInputRef.current) {
      fileInputRef.current.value = "";
    }
    fileInputRef.current?.click();
  };

  const isLoading = isUploading || processing;

  return (
    <div className={cn("flex flex-col items-center gap-4", className)}>
      <div
        className={cn(
          "relative",
          hasCustomAvatar && preview ? "cursor-default" : "cursor-pointer group",
          "w-32 h-32 rounded-full",
          hasCustomAvatar && preview ? "overflow-visible" : "overflow-hidden",
          "border-2 border-dashed transition-all duration-200",
          !hasCustomAvatar && dragActive
            ? "border-primary bg-primary/5"
            : !hasCustomAvatar
              ? "border-muted-foreground/20 hover:border-muted-foreground/40"
              : "border-transparent",
          isLoading && "opacity-50 pointer-events-none",
        )}
        onDragEnter={!hasCustomAvatar ? handleDrag : undefined}
        onDragLeave={!hasCustomAvatar ? handleDrag : undefined}
        onDragOver={!hasCustomAvatar ? handleDrag : undefined}
        onDrop={!hasCustomAvatar ? handleDrop : undefined}
        onClick={!isLoading && !hasCustomAvatar ? triggerInput : undefined}
      >
        <div
          className={cn(
            "w-full h-full rounded-full overflow-hidden",
            hasCustomAvatar && "border-2 border-muted-foreground/20",
          )}
        >
          {preview ? (
            <Avatar className="w-full h-full">
              <AvatarImage
                src={preview}
                alt="Avatar preview"
                className="w-full h-full object-cover"
              />
              <AvatarFallback className="w-full h-full">
                {userName ? (
                  <span className="text-2xl font-medium text-muted-foreground">
                    {getUserInitials(userName)}
                  </span>
                ) : (
                  <ImageIcon className="w-12 h-12 text-muted-foreground" />
                )}
              </AvatarFallback>
            </Avatar>
          ) : (
            <div className="w-full h-full bg-muted flex items-center justify-center">
              {userName ? (
                <span className="text-2xl font-medium text-muted-foreground">
                  {getUserInitials(userName)}
                </span>
              ) : (
                <ImageIcon className="w-12 h-12 text-muted-foreground" />
              )}
            </div>
          )}

          {(isUploading || processing) && (
            <div className="absolute inset-0 z-20 overflow-hidden rounded-full bg-background/80 flex items-center justify-center">
              <div className="flex flex-col items-center">
                {processing ? (
                  <Loader2 className="w-8 h-8 animate-spin text-primary" />
                ) : (
                  <>
                    <Upload className="w-6 h-6 animate-pulse text-primary" />
                    <span className="text-xs text-muted-foreground mt-1">Uploading...</span>
                  </>
                )}
              </div>
            </div>
          )}

          {!isLoading && !hasCustomAvatar && (
            <div className="absolute inset-0 z-10 bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center rounded-full">
              <Upload className="w-8 h-8 text-white" />
            </div>
          )}
        </div>

        {!isLoading && hasCustomAvatar && preview && (
          <div className="absolute -bottom-1 -right-1 z-10">
            <DropdownMenu>
              <DropdownMenuTrigger
                render={
                  <Button
                    variant="default"
                    size="icon-sm"
                    className="rounded-full w-8 h-8 bg-primary text-primary-foreground hover:bg-primary/90"
                    onClick={(e) => e.stopPropagation()}
                  />
                }
              >
                <Pencil className="w-4 h-4" />
              </DropdownMenuTrigger>
              <DropdownMenuContent align="end" className="w-40">
                <DropdownMenuItem onClick={triggerInput}>
                  <ImagePlus className="w-4 h-4 mr-2" />
                  Change Photo
                </DropdownMenuItem>
                <DropdownMenuSeparator />
                <DropdownMenuItem
                  onClick={handleDelete}
                  className="text-destructive focus:text-destructive focus:bg-destructive/10"
                >
                  <Trash2 className="w-4 h-4 mr-2" />
                  Remove
                </DropdownMenuItem>
              </DropdownMenuContent>
            </DropdownMenu>
          </div>
        )}
      </div>

      <input
        ref={fileInputRef}
        type="file"
        accept={ALLOWED_TYPES.join(",")}
        onChange={handleChange}
        className="hidden"
        disabled={isLoading}
      />

      <CropperDialog
        open={cropDialogOpen}
        onOpenChange={setCropDialogOpen}
        imageSrc={cropImageSrc ?? ""}
        onSave={handleCropSave}
      />

      {errorMessage && (
        <Alert variant="destructive" className="max-w-xs py-2 px-3">
          <AlertCircle className="h-4 w-4 shrink-0" />
          <AlertDescription className="text-xs">{errorMessage}</AlertDescription>
        </Alert>
      )}

      {showSuccess && (
        <Alert className="max-w-xs py-2 px-3 border-green-500/50 bg-green-500/10 dark:border-green-400/50 dark:bg-green-400/10">
          <CheckCircle2 className="h-4 w-4 shrink-0 text-green-600 dark:text-green-400" />
          <AlertDescription className="text-xs text-green-700 dark:text-green-400">
            Avatar uploaded successfully!
          </AlertDescription>
        </Alert>
      )}

      <p className="text-xs text-muted-foreground text-center">
        {processing
          ? "Processing image..."
          : isUploading
            ? "Uploading..."
            : hasCustomAvatar && preview
              ? "Click the pen icon to change or remove your photo"
              : "JPG, PNG, GIF or WebP. Click to choose and crop a photo."}
      </p>
    </div>
  );
}

export default AvatarUploader;
