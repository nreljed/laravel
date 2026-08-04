import { useCallback, useEffect, useMemo, useRef, useState } from "react";
import { useDropzone } from "react-dropzone";
import {
  DndContext,
  closestCenter,
  KeyboardSensor,
  PointerSensor,
  useSensor,
  useSensors,
  type DragEndEvent,
} from "@dnd-kit/core";
import {
  arrayMove,
  SortableContext,
  sortableKeyboardCoordinates,
  rectSortingStrategy,
} from "@dnd-kit/sortable";
import { toast } from "@/Components/ui/toast";
import { Upload, Loader2, Trash2, CheckSquare, Square, X, SquareCheck } from "lucide-react";
import { Button } from "@/Components/ui/button";
import { cn } from "@/lib/utils";
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
import { SortableImage } from "./gallery-sortable-image";
import { useImageUpload, type UploadResult } from "@/hooks/use-image-upload";
import { GALLERY_MAX_SIZE, formatFileSize } from "@/lib/gallery-utils";
import {
  type GalleryImage,
  type GalleryImageDone,
  type ServerGalleryImage,
  isGalleryImageDone,
  isGalleryImageUploading,
  createGalleryImageFromFile,
  imagesToServerFormat,
  imagesFromServerFormat,
} from "@/types/gallery";

export type { GalleryImage, GalleryImageDone, ServerGalleryImage } from "@/types/gallery";
export type { UploadResult } from "@/hooks/use-image-upload";

type GalleryUploaderBaseProps = {
  maxImages?: number;
  compress?: boolean;
  label?: string;
  description?: string;
  className?: string;
};

export interface GalleryUploaderUrlProps extends GalleryUploaderBaseProps {
  images: ServerGalleryImage[];
  onChange: (_images: ServerGalleryImage[]) => void;
  uploadUrl: string;
  deleteUrl: string;
}

export interface GalleryUploaderCallbackProps extends GalleryUploaderBaseProps {
  value: GalleryImage[];
  onChange: (_images: GalleryImage[]) => void;
  onUpload: (
    file: File,
    onProgress: (pct: number) => void,
    signal: AbortSignal,
    pendingId: string,
  ) => Promise<UploadResult>;
  onDelete?: (image: GalleryImage) => Promise<void>;
  onSetFeatured?: (image: GalleryImage) => Promise<void>;
  onReorder?: (images: GalleryImage[]) => Promise<void>;
}

export type GalleryUploaderProps = GalleryUploaderUrlProps | GalleryUploaderCallbackProps;

export function GalleryUploader(props: GalleryUploaderProps) {
  const { maxImages = 20, compress = true, label, description, className } = props;
  const isCallbackMode = "value" in props;

  const onChange = props.onChange;
  const uploadUrl = "value" in props ? undefined : props.uploadUrl;
  const deleteUrl = "value" in props ? undefined : props.deleteUrl;
  const onUpload = "value" in props ? props.onUpload : undefined;
  const onDelete = "value" in props ? props.onDelete : undefined;
  const onSetFeatured = "value" in props ? props.onSetFeatured : undefined;
  const onReorder = "value" in props ? props.onReorder : undefined;
  const serverImages = "value" in props ? [] : props.images;
  const value = "value" in props ? props.value : undefined;

  const [internalImages, setInternalImages] = useState<GalleryImage[]>(() =>
    isCallbackMode ? (value ?? []) : imagesFromServerFormat(serverImages),
  );
  const [failedImages, setFailedImages] = useState<Map<string, File>>(new Map());
  const [selectedIds, setSelectedIds] = useState<Set<string>>(new Set());
  const [selectionMode, setSelectionMode] = useState(false);
  const [deleteConfirmImage, setDeleteConfirmImage] = useState<GalleryImage | null>(null);
  const [deleteConfirmBulk, setDeleteConfirmBulk] = useState(false);
  const mountedRef = useRef(true);
  const { upload, remove, retry } = useImageUpload({
    uploadUrl,
    deleteUrl,
    compress,
    enabled: !isCallbackMode,
  });

  useEffect(() => {
    mountedRef.current = true;
    return () => {
      mountedRef.current = false;
    };
  }, []);

  const emitChange = useCallback(
    (images: GalleryImage[]) => {
      if (!mountedRef.current) return;
      if (isCallbackMode) {
        (onChange as (images: GalleryImage[]) => void)(images);
      } else {
        (onChange as (images: ServerGalleryImage[]) => void)(imagesToServerFormat(images));
      }
    },
    [onChange, isCallbackMode],
  );

  const sensors = useSensors(
    useSensor(PointerSensor, { activationConstraint: { distance: 8 } }),
    useSensor(KeyboardSensor, { coordinateGetter: sortableKeyboardCoordinates }),
  );

  const sortableIds = useMemo(() => internalImages.map((img) => img.id), [internalImages]);

  const updateImageProgress = useCallback((id: string, progress: number) => {
    setInternalImages((prev) =>
      prev.map((img) =>
        img.id === id && isGalleryImageUploading(img) ? { ...img, progress } : img,
      ),
    );
  }, []);

  const markImageDone = useCallback(
    (id: string, result: GalleryImageDone) => {
      setInternalImages((prev) => {
        const updated = prev.map((img) =>
          img.id === id
            ? {
                ...result,
                id,
                name: img.name,
                size: img.size,
                type: img.type,
                preview: isGalleryImageUploading(img) ? img.preview : undefined,
              }
            : img,
        );
        emitChange(updated);
        return updated;
      });
    },
    [emitChange],
  );

  const markImageError = useCallback((id: string, error: string, file: File) => {
    setFailedImages((prev) => new Map(prev).set(id, file));
    setInternalImages((prev) => {
      const updated = prev.map((img) =>
        img.id === id
          ? {
              ...img,
              status: "error" as const,
              error,
            }
          : img,
      );
      return updated;
    });
  }, []);

  const doUpload = useCallback(
    async (file: File, pendingId: string, signal: AbortSignal): Promise<UploadResult> => {
      if (onUpload) {
        return onUpload(file, (pct) => updateImageProgress(pendingId, pct), signal, pendingId);
      }
      return upload(file, signal);
    },
    [onUpload, upload, updateImageProgress],
  );

  const doRemove = useCallback(
    async (image: GalleryImage): Promise<void> => {
      if (onDelete) {
        await onDelete(image);
        return;
      }
      if (isGalleryImageDone(image)) {
        await remove(image.path);
      }
    },
    [onDelete, remove],
  );

  const doRetry = useCallback(
    async (file: File, pendingId: string): Promise<UploadResult> => {
      if (onUpload) {
        return onUpload(
          file,
          (pct) => updateImageProgress(pendingId, pct),
          new AbortController().signal,
          pendingId,
        );
      }
      return retry(file);
    },
    [onUpload, retry, updateImageProgress],
  );

  const onDrop = useCallback(
    async (acceptedFiles: File[]) => {
      const remainingSlots = maxImages - internalImages.length;
      const filesToUpload = acceptedFiles.slice(0, remainingSlots);

      if (filesToUpload.length < acceptedFiles.length) {
        toast.add({ title: `Only ${remainingSlots} more images allowed.`, type: "warning" });
      }

      const newUploading = filesToUpload.map((f) => createGalleryImageFromFile(f));
      if (mountedRef.current) {
        setInternalImages((prev) => [...prev, ...newUploading]);
      }

      const controller = new AbortController();

      const uploadPromises = filesToUpload.map(async (file, index) => {
        const stableId = newUploading[index]!.id;

        try {
          updateImageProgress(stableId, 10);

          const result = await doUpload(file, stableId, controller.signal);

          updateImageProgress(stableId, 100);

          if (mountedRef.current) {
            markImageDone(stableId, {
              id: stableId,
              name: file.name,
              size: file.size,
              type: file.type,
              status: "done",
              url: result.url,
              path: result.path,
              attachment_id: result.attachment_id,
            });
          }
          return result;
        } catch (error) {
          if (controller.signal.aborted) return null;
          console.error(`Failed to upload ${file.name}:`, error);
          toast.add({ title: `Failed to upload ${file.name}`, type: "error" });
          if (mountedRef.current) {
            markImageError(
              stableId,
              error instanceof Error ? error.message : "Upload failed",
              file,
            );
          }
          return null;
        }
      });

      await Promise.all(uploadPromises);

      const successCount = filesToUpload.length - failedImages.size;
      if (successCount > 0) {
        toast.add({ title: `${successCount} image(s) uploaded.`, type: "success" });
      }
    },
    [
      internalImages.length,
      maxImages,
      doUpload,
      updateImageProgress,
      markImageDone,
      markImageError,
      failedImages.size,
    ],
  );

  const { getRootProps, getInputProps, isDragActive } = useDropzone({
    onDrop,
    accept: { "image/*": [".png", ".jpg", ".jpeg", ".gif", ".webp", ".avif", ".bmp"] },
    maxSize: GALLERY_MAX_SIZE,
    disabled: internalImages.length >= maxImages,
  });

  const confirmRemove = useCallback(
    async (image: GalleryImage) => {
      setDeleteConfirmImage(null);

      if (isGalleryImageDone(image)) {
        try {
          await doRemove(image);
        } catch {
          // Best-effort delete
        }
      }

      if (isGalleryImageUploading(image) && image.preview) {
        URL.revokeObjectURL(image.preview);
      }

      setFailedImages((prev) => {
        const next = new Map(prev);
        next.delete(image.id);
        return next;
      });

      setSelectedIds((prev) => {
        const next = new Set(prev);
        next.delete(image.id);
        return next;
      });

      setInternalImages((prev) => {
        const updated = prev.filter((img) => img.id !== image.id);
        emitChange(updated);
        return updated;
      });

      toast.add({ title: "Image removed.", type: "success" });
    },
    [doRemove, emitChange],
  );

  const handleRemove = useCallback((image: GalleryImage) => {
    setDeleteConfirmImage(image);
  }, []);

  const handleRetry = useCallback(
    async (image: GalleryImage) => {
      const file = failedImages.get(image.id);
      if (!file) return;

      setFailedImages((prev) => {
        const next = new Map(prev);
        next.delete(image.id);
        return next;
      });

      if (mountedRef.current) {
        setInternalImages((prev) =>
          prev.map((img) =>
            img.id === image.id
              ? {
                  id: img.id,
                  name: img.name,
                  size: img.size,
                  type: img.type,
                  status: "uploading" as const,
                  progress: 0,
                  preview: isGalleryImageUploading(img) ? img.preview : "",
                }
              : img,
          ),
        );
      }

      try {
        updateImageProgress(image.id, 10);
        const result = await doRetry(file, image.id);
        updateImageProgress(image.id, 100);

        if (mountedRef.current) {
          markImageDone(image.id, {
            id: image.id,
            name: file.name,
            size: file.size,
            type: file.type,
            status: "done",
            url: result.url,
            path: result.path,
          });
          toast.add({ title: `${file.name} uploaded.`, type: "success" });
        }
      } catch (error) {
        if (mountedRef.current) {
          markImageError(image.id, error instanceof Error ? error.message : "Upload failed", file);
          toast.add({ title: `Failed to upload ${file.name}`, type: "error" });
        }
      }
    },
    [failedImages, doRetry, updateImageProgress, markImageDone, markImageError],
  );

  const handleSetFeatured = useCallback(
    (image: GalleryImage) => {
      setInternalImages((prev) => {
        const idx = prev.findIndex((img) => img.id === image.id);
        if (idx <= 0) return prev;

        const moved = prev[idx]!;
        const rest = prev.filter((img) => img.id !== image.id);
        const updated = [moved, ...rest].map((img, i) => ({ ...img, order: i }));
        emitChange(updated);
        return updated;
      });

      if (onSetFeatured) {
        void onSetFeatured(image);
      } else {
        toast.add({ title: "Cover image updated.", type: "success" });
      }
    },
    [emitChange, onSetFeatured],
  );

  const handleDragEnd = useCallback(
    (event: DragEndEvent) => {
      const { active, over } = event;
      if (!over || active.id === over.id) return;

      const oldIndex = internalImages.findIndex((img) => img.id === active.id);
      const newIndex = internalImages.findIndex((img) => img.id === over.id);

      if (oldIndex !== -1 && newIndex !== -1) {
        const reordered = arrayMove(internalImages, oldIndex, newIndex).map((img, i) => ({
          ...img,
          order: i,
        }));
        emitChange(reordered);
        if (onReorder) {
          void onReorder(reordered);
        }
      }
    },
    [internalImages, emitChange, onReorder],
  );

  const handleToggleSelect = useCallback((id: string) => {
    setSelectedIds((prev) => {
      const next = new Set(prev);
      if (next.has(id)) {
        next.delete(id);
      } else {
        next.add(id);
      }
      return next;
    });
  }, []);

  const handleSelectAll = useCallback(() => {
    setSelectedIds(new Set(internalImages.map((img) => img.id)));
  }, [internalImages]);

  const handleDeselectAll = useCallback(() => {
    setSelectedIds(new Set());
  }, []);

  const confirmBulkDelete = useCallback(async () => {
    setDeleteConfirmBulk(false);
    const toDelete = internalImages.filter((img) => selectedIds.has(img.id));
    if (toDelete.length === 0) return;

    const serverDeletes = toDelete.filter(isGalleryImageDone);
    await Promise.allSettled(serverDeletes.map((img) => doRemove(img)));

    for (const img of toDelete) {
      if (isGalleryImageUploading(img) && img.preview) {
        URL.revokeObjectURL(img.preview);
      }
    }

    setInternalImages((prev) => {
      const updated = prev.filter((img) => !selectedIds.has(img.id));
      emitChange(updated);
      return updated;
    });

    setSelectedIds(new Set());
    setSelectionMode(false);
    toast.add({ title: `${toDelete.length} image(s) removed.`, type: "success" });
  }, [internalImages, selectedIds, doRemove, emitChange]);

  const handleBulkDelete = useCallback(() => {
    if (selectedIds.size === 0) return;
    setDeleteConfirmBulk(true);
  }, [selectedIds.size]);

  const handleCancelSelection = useCallback(() => {
    setSelectedIds(new Set());
    setSelectionMode(false);
  }, []);

  return (
    <div className={cn("space-y-4", className)}>
      {(label || description) && (
        <div className="space-y-1">
          {label && <p className="text-sm font-medium leading-none">{label}</p>}
          {description && <p className="text-sm text-muted-foreground">{description}</p>}
        </div>
      )}

      {selectionMode && (
        <div className="flex items-center justify-between rounded-lg border bg-muted/50 p-2">
          <div className="flex items-center gap-2">
            <Button
              type="button"
              variant="ghost"
              size="sm"
              onClick={
                selectedIds.size === internalImages.length ? handleDeselectAll : handleSelectAll
              }
            >
              {selectedIds.size === internalImages.length ? (
                <>
                  <Square className="mr-1.5 size-4" />
                  Deselect All
                </>
              ) : (
                <>
                  <SquareCheck className="mr-1.5 size-4" />
                  Select All
                </>
              )}
            </Button>
            <span className="text-sm text-muted-foreground">
              {selectedIds.size} of {internalImages.length} selected
            </span>
          </div>
          <div className="flex items-center gap-2">
            <Button
              type="button"
              variant="destructive"
              size="sm"
              disabled={selectedIds.size === 0}
              onClick={handleBulkDelete}
            >
              <Trash2 className="mr-1.5 size-4" />
              Delete ({selectedIds.size})
            </Button>
            <Button type="button" variant="ghost" size="sm" onClick={handleCancelSelection}>
              <X className="mr-1.5 size-4" />
              Cancel
            </Button>
          </div>
        </div>
      )}

      <DndContext sensors={sensors} collisionDetection={closestCenter} onDragEnd={handleDragEnd}>
        <SortableContext items={sortableIds} strategy={rectSortingStrategy}>
          <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">
            {internalImages.map((image, index) => (
              <SortableImage
                key={image.id}
                image={image}
                onRemove={handleRemove}
                onRetry={handleRetry}
                onSetFeatured={handleSetFeatured}
                isFeatured={index === 0}
                enableDragHandle
                enableDrag
                enableFeatured
                selectionMode={selectionMode}
                isSelected={selectedIds.has(image.id)}
                onToggleSelect={handleToggleSelect}
              />
            ))}
          </div>
        </SortableContext>
      </DndContext>

      {internalImages.length < maxImages && !selectionMode && (
        <div
          {...getRootProps()}
          className={`border-2 border-dashed rounded-lg p-6 text-center cursor-pointer transition-colors ${
            isDragActive
              ? "border-primary bg-primary/5"
              : "border-muted-foreground/25 hover:border-primary/50"
          }`}
        >
          <input {...getInputProps()} />
          <Upload className="mx-auto h-8 w-8 text-muted-foreground mb-2" />
          <p className="text-sm text-muted-foreground">
            {isDragActive ? "Drop images here..." : "Drag & drop images, or click to select"}
          </p>
          <p className="text-xs text-muted-foreground mt-1">
            {internalImages.length}/{maxImages} images · Max {formatFileSize(GALLERY_MAX_SIZE)} each
          </p>
        </div>
      )}

      {internalImages.some(isGalleryImageUploading) && (
        <div className="flex items-center gap-2 text-sm text-muted-foreground">
          <Loader2 className="h-4 w-4 animate-spin" />
          Uploading...
        </div>
      )}

      {!selectionMode && internalImages.length > 1 && (
        <div className="flex justify-end">
          <Button type="button" variant="outline" size="sm" onClick={() => setSelectionMode(true)}>
            <CheckSquare className="mr-1.5 size-4" />
            Select
          </Button>
        </div>
      )}

      <AlertDialog
        open={!!deleteConfirmImage}
        onOpenChange={(open) => !open && setDeleteConfirmImage(null)}
      >
        <AlertDialogContent size="sm">
          <AlertDialogHeader>
            <AlertDialogTitle>Remove image?</AlertDialogTitle>
            <AlertDialogDescription>
              {deleteConfirmImage
                ? `Are you sure you want to remove "${deleteConfirmImage.name}"? This action cannot be undone.`
                : ""}
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>Cancel</AlertDialogCancel>
            <AlertDialogAction
              variant="destructive"
              onClick={() => {
                if (deleteConfirmImage) {
                  confirmRemove(deleteConfirmImage);
                }
              }}
            >
              Remove
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>

      <AlertDialog
        open={deleteConfirmBulk}
        onOpenChange={(open) => !open && setDeleteConfirmBulk(false)}
      >
        <AlertDialogContent size="sm">
          <AlertDialogHeader>
            <AlertDialogTitle>Remove {selectedIds.size} image(s)?</AlertDialogTitle>
            <AlertDialogDescription>
              Are you sure you want to remove {selectedIds.size} selected image(s)? This action
              cannot be undone.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>Cancel</AlertDialogCancel>
            <AlertDialogAction variant="destructive" onClick={confirmBulkDelete}>
              Remove All
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  );
}
