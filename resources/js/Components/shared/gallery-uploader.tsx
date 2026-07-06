import * as React from "react";
import { useDropzone, type FileRejection } from "react-dropzone";
import {
  DndContext,
  closestCenter,
  KeyboardSensor,
  PointerSensor,
  useSensor,
  useSensors,
  DragOverlay,
} from "@dnd-kit/core";
import {
  arrayMove,
  SortableContext,
  sortableKeyboardCoordinates,
  rectSortingStrategy,
} from "@dnd-kit/sortable";
import { restrictToWindowEdges } from "@dnd-kit/modifiers";
import { Upload, Loader2, X, Ban, Check, Trash2, Star } from "lucide-react";
import { toast } from "sonner";

import { Button } from "@/Components/ui/button";
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from "@/Components/ui/alert-dialog";
import { Badge } from "@/Components/ui/badge";
import { SortableImage } from "@/Components/ui/gallery-sortable-image";
import {
  getImageUrl,
  processImage,
  createPreview,
  formatFileSize,
  GALLERY_MAX_SIZE,
  DEFAULT_IMAGE_CONFIG,
} from "@/lib/gallery-utils";

export interface GalleryImage {
  id?: number | string | null;
  url?: string;
  path?: string;
  filepath?: string;
  featured?: boolean;
  url_raw?: string;
  urlRaw?: string;
  name?: string | null;
  filename?: string | null;
  attachment_id?: string | number | null;
}

interface PendingFile {
  file: File;
  preview: string | null;
  progress: number;
  id: string;
  status: "pending" | "uploading" | "error";
  errorMessage?: string;
}

export interface UploadResult {
  url?: string;
  path?: string;
  filepath?: string;
  attachment_id?: string | number;
  id?: string | number;
  filename?: string;
}

interface GalleryUploaderProps {
  label?: string;
  description?: string;
  value?: GalleryImage[];
  thumbnailUrl?: string;
  onChange?: (_images: GalleryImage[]) => void;
  onThumbnailChange?: (_url: string) => void;
  disabled?: boolean;
  maxImages?: number;
  imageConfig?: Partial<typeof DEFAULT_IMAGE_CONFIG>;
  enableDrag?: boolean;
  enableDragOverlay?: boolean;
  enableDragHandle?: boolean;
  enableFeatured?: boolean;
  onUpload?: (
    _file: File,
    _onProgress: (_pct: number) => void,
    _signal: AbortSignal,
    _pendingId: string,
  ) => Promise<UploadResult>;
  onDelete?: (_image: GalleryImage) => Promise<void>;
  onSetFeatured?: (_image: GalleryImage) => Promise<void>;
  onReorder?: (_images: GalleryImage[]) => Promise<void>;
}

const UPLOAD_CONCURRENCY = 3;

function markPendingStatus(
  setter: React.Dispatch<React.SetStateAction<PendingFile[]>>,
  id: string,
  status: PendingFile["status"],
) {
  setter((prev) => prev.map((pf) => (pf.id === id ? { ...pf, status } : pf)));
}

function normalizeId(image: GalleryImage): string | number {
  return image.id ?? image.url ?? crypto.randomUUID();
}

export function GalleryUploader({
  label = "Gallery",
  description = "Add images & videos",
  value = [],
  thumbnailUrl,
  onChange,
  onThumbnailChange,
  disabled = false,
  maxImages = 20,
  imageConfig,
  enableDrag = true,
  enableDragOverlay = false,
  enableDragHandle = true,
  enableFeatured = true,
  onUpload,
  onDelete,
  onSetFeatured,
  onReorder,
}: GalleryUploaderProps) {
  const [uploadedImages, setUploadedImages] = React.useState<GalleryImage[]>([]);
  const [isUploading, setIsUploading] = React.useState(false);
  const [pendingFiles, setPendingFiles] = React.useState<PendingFile[]>([]);

  const [showDeleteDialog, setShowDeleteDialog] = React.useState(false);
  const [imageToDelete, setImageToDelete] = React.useState<GalleryImage | null>(null);
  const [deletingImageKeys, setDeletingImageKeys] = React.useState<Set<string>>(new Set());
  const [isSelecting, setIsSelecting] = React.useState(false);
  const [selectedIds, setSelectedIds] = React.useState<Set<string | number | null>>(new Set());
  const [showBulkDeleteDialog, setShowBulkDeleteDialog] = React.useState(false);
  const [bulkDeleteCount, setBulkDeleteCount] = React.useState(0);

  const userHasSelectedFeaturedRef = React.useRef(false);
  const processingRef = React.useRef<Map<string, File>>(new Map());
  const cancelledIdsRef = React.useRef<Set<string>>(new Set());
  const abortRef = React.useRef<AbortController>(new AbortController());
  const accumulatedRef = React.useRef<GalleryImage[]>([]);
  const [activeImage, setActiveImage] = React.useState<GalleryImage | null>(null);

  React.useEffect(() => {
    if (!value || !Array.isArray(value)) return;

    let processed = [...value];

    if (thumbnailUrl && !userHasSelectedFeaturedRef.current && enableFeatured) {
      const normalizedThumbnail = thumbnailUrl;
      processed = processed.map((img) => {
        const url = img.url || "";
        return url === normalizedThumbnail
          ? { ...img, featured: true }
          : { ...img, featured: false };
      });
    } else if (enableFeatured) {
      const hasExplicitFeatured = processed.some((img) => img.featured === true);
      if (!hasExplicitFeatured && processed.length > 0) {
        processed = processed.map((img, i) =>
          i === 0 ? { ...img, featured: true } : { ...img, featured: false },
        );
      }
    } else {
      processed = processed.map((img) => ({ ...img, featured: false }));
    }

    setUploadedImages(processed);
  }, [value, thumbnailUrl, enableFeatured]);

  React.useEffect(() => {
    const errored = pendingFiles.filter((pf) => pf.status === "error");
    if (errored.length === 0) return;

    const timers = errored.map((pf) =>
      setTimeout(() => {
        setPendingFiles((prev) => prev.filter((p) => p.id !== pf.id));
        if (pf.preview) URL.revokeObjectURL(pf.preview);
      }, 4000),
    );

    return () => {
      for (const t of timers) clearTimeout(t);
    };
  }, [pendingFiles]);

  React.useEffect(() => {
    return () => {
      for (const pf of pendingFiles) {
        if (pf.preview) {
          URL.revokeObjectURL(pf.preview);
        }
      }
    };
  }, [pendingFiles]);

  const sensors = useSensors(
    useSensor(PointerSensor, {
      activationConstraint: ((event: { pointerType?: string }) => {
        if (event?.pointerType === "touch") {
          return { delay: 250, tolerance: 5 };
        }
        return { distance: 8 };
      }) as unknown as never,
    }),
    useSensor(KeyboardSensor, {
      coordinateGetter: sortableKeyboardCoordinates,
    }),
  );

  const uploadFile = React.useCallback(
    async (
      file: File,
      onProgress: (_progress: number) => void,
      signal: AbortSignal,
      pendingId: string,
    ) => {
      const cacheKey = `${file.name}_${file.size}_${file.lastModified}`;
      let processedFile = processingRef.current.get(cacheKey);
      if (!processedFile) {
        processedFile = await processImage(file, imageConfig);
        if (signal?.aborted) throw new Error("Upload cancelled");
        processingRef.current.set(cacheKey, processedFile);
      }

      if (onUpload) {
        const serverData = await onUpload(processedFile, onProgress, signal, pendingId);
        return serverData;
      }

      throw new Error("No upload handler provided");
    },
    [onUpload, imageConfig],
  );

  const onDrop = React.useCallback(
    async (acceptedFiles: File[], fileRejections: FileRejection[]) => {
      for (const { file, errors } of fileRejections) {
        const errorMessages = errors.map((e) => {
          switch (e.code) {
            case "file-too-large": {
              return "File too large (max 50MB)";
            }
            case "file-invalid-type": {
              return "Invalid file type";
            }
            default: {
              return e.message;
            }
          }
        });
        toast.error(`${file.name}: ${errorMessages.join(", ")}`);
      }

      if (acceptedFiles.length === 0) return;

      const remainingSlots = maxImages - accumulatedRef.current.length;
      const filesToUpload = acceptedFiles.slice(0, remainingSlots);

      if (acceptedFiles.length > remainingSlots) {
        toast.warning(`Only ${remainingSlots} more file(s) can be added.`);
      }

      if (filesToUpload.length === 0) return;

      abortRef.current = new AbortController();
      cancelledIdsRef.current = new Set();
      accumulatedRef.current = [...uploadedImages];

      const newPendingFiles: PendingFile[] = filesToUpload.map((file) => ({
        file,
        preview: createPreview(file),
        progress: 0,
        id: crypto.randomUUID(),
        status: "pending",
      }));

      setIsUploading(true);
      setPendingFiles((prev) => [...prev, ...newPendingFiles]);

      const signal = abortRef.current.signal;
      let completedCount = 0;

      async function worker() {
        while (newPendingFiles.length > 0 && !signal.aborted) {
          const pendingFile = newPendingFiles.shift();
          if (!pendingFile || cancelledIdsRef.current.has(pendingFile.id)) continue;

          markPendingStatus(setPendingFiles, pendingFile.id, "uploading");

          try {
            const serverData = await uploadFile(
              pendingFile.file,
              (progress: number) => {
                setPendingFiles((prev) =>
                  prev.map((pf) => (pf.id === pendingFile.id ? { ...pf, progress } : pf)),
                );
              },
              signal,
              pendingFile.id,
            );

            if (signal.aborted) break;

            const newImage: GalleryImage = {
              id: `upload_${Date.now()}_${completedCount}`,
              url: serverData?.url,
              path: serverData?.path,
              name: pendingFile.file.name,
            };

            completedCount++;

            setPendingFiles((prev) => prev.filter((pf) => pf.id !== pendingFile.id));

            const currentAccumulated = accumulatedRef.current;
            const hasFeatured = currentAccumulated.some((img) => img.featured === true);
            const imageToAdd = hasFeatured ? newImage : { ...newImage, featured: true };

            if (!hasFeatured && imageToAdd.url) {
              userHasSelectedFeaturedRef.current = true;
            }

            accumulatedRef.current = [...currentAccumulated, imageToAdd];
            setUploadedImages(accumulatedRef.current);
            onChange?.(accumulatedRef.current);
          } catch (error: unknown) {
            if (error instanceof Error && error.message === "Upload cancelled") {
              setPendingFiles((prev) => prev.filter((pf) => pf.id !== pendingFile.id));
            } else {
              setPendingFiles((prev) =>
                prev.map((pf) =>
                  pf.id === pendingFile.id
                    ? {
                        ...pf,
                        status: "error",
                        errorMessage: error instanceof Error ? error.message : "Upload failed",
                      }
                    : pf,
                ),
              );
            }
          }
        }
      }

      const workerCount = Math.min(UPLOAD_CONCURRENCY, filesToUpload.length);
      const workers = Array.from({ length: workerCount }, () => worker());

      try {
        await Promise.all(workers);
      } finally {
        setIsUploading(false);
        if (completedCount > 0 && !signal.aborted) {
          toast.success(`${completedCount} file(s) uploaded successfully`);
        } else if (signal.aborted && completedCount > 0) {
          toast.success(`${completedCount} file(s) uploaded`);
        }
      }
    },
    [maxImages, uploadedImages, onChange, uploadFile],
  );

  const cancelAll = React.useCallback(() => {
    abortRef.current.abort();
    setIsUploading(false);
    toast.info("Upload cancelled");
  }, []);

  const { getRootProps, getInputProps, isDragActive } = useDropzone({
    onDrop,
    accept: {
      "image/*": [".jpg", ".jpeg", ".png", ".gif", ".webp", ".avif"],
      "video/*": [".mp4", ".webm", ".ogg", ".mov", ".avi", ".mkv"],
    },
    maxSize: GALLERY_MAX_SIZE,
    disabled: isUploading || disabled || uploadedImages.length >= maxImages,
  });

  const handleRemoveUploaded = React.useCallback(
    async (imageToRemove: GalleryImage) => {
      const imageUrl = imageToRemove.url || "";
      const imageKey = imageToRemove.id + "" || imageUrl;

      setDeletingImageKeys((prev) => new Set(prev).add(imageKey));

      try {
        if (onDelete) {
          await onDelete(imageToRemove);
        }

        const updatedImages = uploadedImages.filter((img) => img.url !== imageUrl);
        setUploadedImages(updatedImages);
        onChange?.(updatedImages);
      } catch {
        toast.error("Failed to remove file from server");
      } finally {
        setDeletingImageKeys((prev) => {
          const next = new Set(prev);
          next.delete(imageKey);
          return next;
        });
      }
    },
    [uploadedImages, onChange, onDelete],
  );

  const confirmDelete = React.useCallback((image: GalleryImage) => {
    setImageToDelete(image);
    setShowDeleteDialog(true);
  }, []);

  const handleConfirmDelete = React.useCallback(async () => {
    if (!imageToDelete) return;
    await handleRemoveUploaded(imageToDelete);
    setImageToDelete(null);
    setShowDeleteDialog(false);
  }, [imageToDelete, handleRemoveUploaded]);

  const handleCancelPending = React.useCallback((pendingFile: PendingFile) => {
    cancelledIdsRef.current.add(pendingFile.id);
    if (pendingFile.preview) {
      URL.revokeObjectURL(pendingFile.preview);
    }
    setPendingFiles((prev) => prev.filter((pf) => pf.id !== pendingFile.id));
  }, []);

  const handleToggleSelect = React.useCallback((imageId: string | number | null) => {
    setSelectedIds((prev) => {
      const next = new Set(prev);
      if (next.has(imageId)) {
        next.delete(imageId);
      } else {
        next.add(imageId);
      }
      return next;
    });
  }, []);

  const handleSelectAll = React.useCallback(() => {
    setSelectedIds((prev) => {
      if (prev.size === uploadedImages.length) {
        return new Set();
      }
      return new Set(uploadedImages.map((img) => normalizeId(img)));
    });
  }, [uploadedImages]);

  const handleBulkDelete = React.useCallback(() => {
    const imagesToDelete = uploadedImages.filter((img) => selectedIds.has(normalizeId(img)));
    if (imagesToDelete.length === 0) return;
    setBulkDeleteCount(imagesToDelete.length);
    setShowBulkDeleteDialog(true);
  }, [uploadedImages, selectedIds]);

  const handleConfirmBulkDelete = React.useCallback(async () => {
    const imagesToDelete = uploadedImages.filter((img) => selectedIds.has(normalizeId(img)));
    if (imagesToDelete.length === 0) return;

    setIsSelecting(false);
    setSelectedIds(new Set());
    setShowBulkDeleteDialog(false);

    for (const image of imagesToDelete) {
      await handleRemoveUploaded(image);
    }
  }, [uploadedImages, selectedIds, handleRemoveUploaded]);

  const handleSetFeatured = React.useCallback(
    async (imageToFeature: GalleryImage) => {
      const imageUrl = imageToFeature.url || "";

      userHasSelectedFeaturedRef.current = true;

      const newItems = uploadedImages.map((item) => {
        const url = item.url || "";
        return url === imageUrl ? { ...item, featured: true } : { ...item, featured: false };
      });
      setUploadedImages(newItems);
      onChange?.(newItems);
      if (imageUrl && onThumbnailChange) {
        onThumbnailChange(imageUrl);
      }
      if (onSetFeatured) {
        await onSetFeatured(imageToFeature);
      }
      toast.success("Cover image updated");
    },
    [onChange, onThumbnailChange, uploadedImages, onSetFeatured],
  );

  const handleDragStart = React.useCallback(
    (event: { active: { id: string | number } }) => {
      const image = uploadedImages.find((img) => normalizeId(img) === event.active.id);
      setActiveImage(image ?? null);
    },
    [uploadedImages],
  );

  const handleDragEnd = React.useCallback(
    async (event: { active: { id: string | number }; over: null | { id: string | number } }) => {
      const { active, over } = event;

      if (over && active.id !== over.id) {
        const oldIndex = uploadedImages.findIndex((item) => normalizeId(item) === active.id);
        const newIndex = uploadedImages.findIndex((item) => normalizeId(item) === over.id);

        if (oldIndex !== -1 && newIndex !== -1) {
          const newItems = arrayMove(uploadedImages, oldIndex, newIndex);
          setUploadedImages(newItems);
          onChange?.(newItems);
          if (onReorder) {
            await onReorder(newItems);
          }
        }
      }

      setActiveImage(null);
    },
    [onChange, uploadedImages, onReorder],
  );

  const handleDragCancel = React.useCallback(() => {
    setActiveImage(null);
  }, []);

  const imageIds = uploadedImages.map((img) => normalizeId(img));

  const isLoading = pendingFiles.some((pf) => pf.status === "uploading");
  const hasPending = pendingFiles.length > 0;

  return (
    <div className="space-y-4">
      <div className="space-y-1">
        <p className="text-sm font-medium">{label}</p>
        <p className="text-sm text-muted-foreground">{description}</p>
      </div>

      {isUploading && (
        <div className="flex items-center justify-between px-3 py-2 bg-muted/50 rounded-lg">
          <div className="flex items-center gap-2">
            <Loader2 className="h-4 w-4 animate-spin" />
            <span className="text-sm text-muted-foreground">
              {hasPending
                ? `Uploading ${pendingFiles.filter((p) => p.status === "uploading").length} of ${pendingFiles.length}...`
                : "Processing..."}
            </span>
          </div>
          {isLoading && (
            <Button
              type="button"
              variant="ghost"
              size="sm"
              onClick={cancelAll}
              className="gap-1 text-destructive hover:text-destructive h-7 text-xs"
            >
              <Ban className="size-3" />
              Cancel all
            </Button>
          )}
        </div>
      )}

      {uploadedImages.length > 0 && (
        <div className="space-y-2">
          <div className="flex items-center justify-between">
            <p className="text-xs text-muted-foreground">
              {isSelecting
                ? `${selectedIds.size}/${uploadedImages.length} selected`
                : enableDrag
                  ? "Drag to reorder"
                  : ""}
              {!isSelecting && enableDrag && enableFeatured ? " \u{2022} " : ""}
              {!isSelecting && enableDrag && enableFeatured ? "Click star to set as cover" : ""}
            </p>
            <div className="flex items-center gap-1">
              {isSelecting && (
                <>
                  <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    className="h-7 text-xs gap-1"
                    onClick={handleSelectAll}
                  >
                    <Check className="size-3" />
                    {selectedIds.size === uploadedImages.length ? "Deselect all" : "Select all"}
                  </Button>
                  <Button
                    type="button"
                    variant="destructive"
                    size="sm"
                    className="h-7 text-xs gap-1"
                    disabled={selectedIds.size === 0}
                    onClick={handleBulkDelete}
                  >
                    <Trash2 className="size-3" />
                    Delete ({selectedIds.size})
                  </Button>
                  <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    className="h-7 text-xs"
                    onClick={() => {
                      setIsSelecting(false);
                      setSelectedIds(new Set());
                    }}
                  >
                    Cancel
                  </Button>
                </>
              )}
              {!isSelecting && !isUploading && (
                <Button
                  type="button"
                  variant="ghost"
                  size="sm"
                  className="h-7 text-xs"
                  onClick={() => setIsSelecting(true)}
                >
                  Select
                </Button>
              )}
            </div>
          </div>
          <DndContext
            sensors={sensors}
            collisionDetection={closestCenter}
            onDragStart={handleDragStart}
            onDragEnd={handleDragEnd}
            onDragCancel={handleDragCancel}
            modifiers={[restrictToWindowEdges]}
          >
            <SortableContext items={imageIds} strategy={rectSortingStrategy} disabled={!enableDrag}>
              <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">
                {uploadedImages.map((image) => (
                  <SortableImage
                    key={normalizeId(image)}
                    image={{
                      id: normalizeId(image),
                      name: image.name ?? null,
                      filename: image.filename ?? null,
                      url: image.url,
                      url_raw: image.url_raw,
                      urlRaw: image.urlRaw,
                    }}
                    onRemove={handleRemoveUploaded}
                    onSetFeatured={handleSetFeatured}
                    isUploading={isUploading}
                    isFeatured={image.featured === true}
                    enableDragHandle={enableDragHandle}
                    enableDrag={enableDrag}
                    onConfirmDelete={confirmDelete}
                    enableFeatured={enableFeatured}
                    isDeleting={deletingImageKeys.has(image.id + "" || image.url || "")}
                    selectionMode={isSelecting}
                    isSelected={selectedIds.has(normalizeId(image))}
                    onToggleSelect={handleToggleSelect}
                  />
                ))}
              </div>
            </SortableContext>
            {enableDragOverlay && (
              <DragOverlay>
                {activeImage ? (
                  <div className="aspect-square rounded-lg border-2 border-primary bg-muted overflow-hidden shadow-lg">
                    {getImageUrl(
                      activeImage as { url?: string; url_raw?: string; urlRaw?: string },
                    ) ? (
                      <img
                        src={getImageUrl(
                          activeImage as { url?: string; url_raw?: string; urlRaw?: string },
                        )}
                        alt={activeImage.name || "Dragging image"}
                        loading="lazy"
                        className="size-full object-cover"
                        draggable="false"
                      />
                    ) : (
                      <div className="size-full flex items-center justify-center bg-muted">
                        <span className="text-xs text-muted-foreground">No preview</span>
                      </div>
                    )}
                    {activeImage.featured === true && (
                      <div className="absolute top-2 left-2 z-10">
                        <Badge
                          variant="default"
                          className="gap-1 bg-yellow-500 text-white border-yellow-400 hover:bg-yellow-600"
                        >
                          <Star className="size-3 fill-white text-white" />
                          Cover
                        </Badge>
                      </div>
                    )}
                  </div>
                ) : null}
              </DragOverlay>
            )}
          </DndContext>
        </div>
      )}

      {hasPending && (
        <div className="border rounded-lg divide-y">
          {pendingFiles.map((pendingFile) => (
            <div key={pendingFile.id} className="flex items-center gap-3 p-3 transition-all">
              <div className="size-12 rounded overflow-hidden bg-muted flex-shrink-0">
                {pendingFile.preview ? (
                  <img
                    src={pendingFile.preview}
                    alt={pendingFile.file.name}
                    loading="lazy"
                    className="size-full object-cover"
                    draggable="false"
                  />
                ) : (
                  <div className="size-full flex items-center justify-center">
                    <span className="text-xs text-muted-foreground">img</span>
                  </div>
                )}
              </div>

              <div className="flex-1 min-w-0 space-y-1">
                <div className="flex items-center justify-between gap-2">
                  <p className="text-sm font-medium truncate">{pendingFile.file.name}</p>
                  <p className="text-xs text-muted-foreground flex-shrink-0">
                    {formatFileSize(pendingFile.file.size)}
                  </p>
                </div>

                {pendingFile.status === "error" ? (
                  <p className="text-xs text-destructive">
                    {pendingFile.errorMessage || "Upload failed"}
                  </p>
                ) : (
                  <>
                    <div className="w-full bg-muted rounded-full h-1.5 overflow-hidden">
                      <div
                        className="bg-primary h-full transition-all duration-300 rounded-full"
                        style={{ width: `${Math.round(pendingFile.progress || 0)}%` }}
                      />
                    </div>
                    <p className="text-xs text-muted-foreground">
                      {pendingFile.status === "uploading"
                        ? `Uploading... ${Math.round(pendingFile.progress || 0)}%`
                        : "Waiting..."}
                    </p>
                  </>
                )}
              </div>

              <button
                type="button"
                onClick={() => handleCancelPending(pendingFile)}
                className="flex-shrink-0 rounded-full p-1 hover:bg-muted transition-colors"
                title="Cancel"
              >
                <X className="size-4 text-muted-foreground" />
              </button>
            </div>
          ))}
        </div>
      )}

      {!isUploading && uploadedImages.length < maxImages && (
        <div
          {...getRootProps()}
          className={`
            relative flex flex-col items-center justify-center gap-3 p-6
            border-2 border-dashed rounded-lg cursor-pointer
            transition-colors duration-200 ease-in-out
            ${isDragActive ? "border-primary bg-primary/5" : "border-border hover:border-primary/50 hover:bg-muted/50"}
            ${disabled ? "opacity-50" : ""}
          `}
        >
          <input {...getInputProps()} className="sr-only" aria-hidden="true" tabIndex={-1} />

          <div className="flex items-center justify-center rounded-full border p-3">
            <Upload className="size-5 text-muted-foreground" />
          </div>

          <div className="space-y-1 text-center">
            <p className="text-sm font-medium">
              {isDragActive ? "Drop the files here..." : "Click or drag images & videos here"}
            </p>
            <p className="text-xs text-muted-foreground">
              Images (JPG, PNG, GIF, WebP) &bull; Videos (MP4, WebM, MOV, AVI, MKV) &bull;{" "}
              {uploadedImages.length}/{maxImages} files
            </p>
          </div>

          <Button variant="outline" size="sm" type="button">
            Browse files
          </Button>
        </div>
      )}

      {uploadedImages.length >= maxImages && !isUploading && (
        <p className="text-sm text-muted-foreground text-center">
          Maximum {maxImages} files reached. Remove some to add more.
        </p>
      )}

      <AlertDialog open={showDeleteDialog} onOpenChange={setShowDeleteDialog}>
        <AlertDialogContent>
          <AlertDialogHeader>
            {imageToDelete?.featured === true ? (
              <>
                <AlertDialogTitle>Cannot Delete Cover Image</AlertDialogTitle>
                <AlertDialogDescription>
                  This image is currently set as the cover. Please set another image as cover first
                  before deleting this image.
                </AlertDialogDescription>
              </>
            ) : (
              <>
                <AlertDialogTitle>Delete File</AlertDialogTitle>
                <AlertDialogDescription>
                  Are you sure you want to delete this file? This action cannot be undone and the
                  file will be permanently removed from the server.
                </AlertDialogDescription>
              </>
            )}
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>Cancel</AlertDialogCancel>
            {imageToDelete?.featured === true ? (
              <AlertDialogAction
                onClick={() => {
                  const firstOther = uploadedImages.find(
                    (img) => normalizeId(img) !== normalizeId(imageToDelete!),
                  );
                  if (firstOther) {
                    handleSetFeatured(firstOther);
                  }
                  setImageToDelete(null);
                  setShowDeleteDialog(false);
                }}
                className="bg-primary text-primary-foreground hover:bg-primary/90"
              >
                Set First Image as Cover
              </AlertDialogAction>
            ) : (
              <AlertDialogAction
                onClick={handleConfirmDelete}
                className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
              >
                Delete
              </AlertDialogAction>
            )}
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>

      <AlertDialog open={showBulkDeleteDialog} onOpenChange={setShowBulkDeleteDialog}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>
              Delete {bulkDeleteCount} {bulkDeleteCount === 1 ? "File" : "Files"}
            </AlertDialogTitle>
            <AlertDialogDescription>
              Are you sure you want to delete {bulkDeleteCount}{" "}
              {bulkDeleteCount === 1 ? "file" : "files"}? This action cannot be undone and the{" "}
              {bulkDeleteCount === 1 ? "file will" : "files will"} be permanently removed from the
              server.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>Cancel</AlertDialogCancel>
            <AlertDialogAction
              onClick={handleConfirmBulkDelete}
              className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
            >
              Delete {bulkDeleteCount} {bulkDeleteCount === 1 ? "File" : "Files"}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  );
}

export default GalleryUploader;
