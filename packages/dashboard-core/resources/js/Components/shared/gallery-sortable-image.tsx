import * as React from "react";
import { useSortable } from "@dnd-kit/sortable";
import { CSS } from "@dnd-kit/utilities";
import { Trash2, Star, GripVertical, Check, RotateCcw, AlertCircle } from "lucide-react";
import { Badge } from "@/Components/ui/badge";
import { Button } from "@/Components/ui/button";
import { Progress } from "@/Components/ui/progress";
import {
  type GalleryImage,
  isGalleryImageDone,
  isGalleryImageUploading,
  isGalleryImageError,
  getGalleryImageUrl,
} from "@/types/gallery";
import { formatFileSize } from "@/lib/gallery-utils";

interface SortableImageProps {
  image: GalleryImage;
  onRemove: (_image: GalleryImage) => void;
  onRetry?: (_image: GalleryImage) => void;
  onSetFeatured?: (_image: GalleryImage) => void;
  isFeatured?: boolean;
  enableDragHandle?: boolean;
  enableDrag?: boolean;
  enableFeatured?: boolean;
  selectionMode?: boolean;
  isSelected?: boolean;
  onToggleSelect?: (_id: string) => void;
}

const SortableImage = React.memo(function SortableImage({
  image,
  onRemove,
  onRetry,
  onSetFeatured,
  isFeatured,
  enableDragHandle = false,
  enableDrag = true,
  enableFeatured = true,
  selectionMode = false,
  isSelected = false,
  onToggleSelect,
}: SortableImageProps) {
  const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({
    id: image.id,
    disabled: !enableDrag || selectionMode,
  });

  const style = {
    transform: CSS.Transform.toString(transform),
    transition,
    zIndex: isDragging ? 10 : "auto",
    opacity: isDragging ? 0.8 : 1,
  };

  const imageUrl = getGalleryImageUrl(image);
  const isUploading = isGalleryImageUploading(image);
  const isDone = isGalleryImageDone(image);
  const isError = isGalleryImageError(image);

  return (
    <div
      ref={setNodeRef}
      style={style}
      {...(enableDrag && !enableDragHandle && !selectionMode
        ? { ...attributes, ...listeners }
        : {})}
      className={`group relative aspect-square rounded-lg overflow-hidden bg-muted select-none touch-none ${
        isFeatured
          ? "border-3 border-yellow-500 dark:border-yellow-400"
          : "border-2 border-transparent"
      } ${
        isDragging
          ? "ring-2 ring-primary shadow-lg cursor-grabbing"
          : enableDrag && !selectionMode
            ? "cursor-grab"
            : selectionMode
              ? "cursor-pointer"
              : ""
      }`}
      data-state={image.status}
      onClick={selectionMode ? () => onToggleSelect?.(image.id) : undefined}
    >
      {imageUrl ? (
        <img
          src={imageUrl}
          alt={image.name || "Gallery image"}
          className="absolute inset-0 size-full object-cover"
          draggable="false"
          onError={(e) => {
            (e.target as HTMLElement).style.display = "none";
          }}
        />
      ) : (
        <div className="absolute inset-0 flex items-center justify-center bg-muted">
          <span className="text-xs text-muted-foreground">No preview</span>
        </div>
      )}

      {isUploading && image.progress !== undefined && (
        <div className="absolute inset-x-0 bottom-0 bg-black/50 p-1.5">
          <Progress value={image.progress} className="h-1" />
          <p className="mt-1 text-[10px] text-white/80 text-center truncate">{image.name}</p>
        </div>
      )}

      {isDone && (
        <div className="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/70 to-transparent p-2 opacity-0 group-hover:opacity-100 transition-opacity">
          <p className="text-xs text-white truncate font-medium">{image.name}</p>
          <p className="text-[10px] text-white/70">{formatFileSize(image.size)}</p>
        </div>
      )}

      {isError && (
        <div className="absolute inset-0 bg-destructive/10 flex flex-col items-center justify-center gap-1 z-10">
          <AlertCircle className="size-6 text-destructive" />
          <span className="text-[10px] text-destructive font-medium">Failed</span>
          {onRetry && (
            <Button
              type="button"
              variant="outline"
              size="sm"
              className="h-6 text-[10px] px-2"
              onClick={(e) => {
                e.stopPropagation();
                onRetry(image);
              }}
              onPointerDown={(e) => e.stopPropagation()}
              onPointerUp={(e) => e.stopPropagation()}
            >
              <RotateCcw className="mr-1 size-2.5" />
              Retry
            </Button>
          )}
        </div>
      )}

      {selectionMode && (
        <div className="absolute top-2 left-2 z-30">
          <div
            className={`size-6 rounded-full border-2 flex items-center justify-center transition-colors ${
              isSelected ? "bg-primary border-primary" : "bg-black/50 border-white/70"
            }`}
          >
            {isSelected && <Check className="size-4 text-white" />}
          </div>
        </div>
      )}

      {enableDrag && enableDragHandle && !selectionMode && (
        <div
          {...attributes}
          {...listeners}
          className="absolute bottom-1 right-1 p-1 cursor-grab z-20 opacity-0 group-hover:opacity-100 transition-opacity"
          onClick={(e) => e.stopPropagation()}
          role="button"
          aria-label="Drag to reorder"
          tabIndex={0}
        >
          <GripVertical className="size-4 text-white drop-shadow-md" />
        </div>
      )}

      {enableFeatured && isFeatured && !selectionMode && (
        <div className="absolute top-2 left-2 z-30">
          <Badge variant="warning" className="gap-1">
            <Star className="size-3 fill-white text-white" />
            Cover
          </Badge>
        </div>
      )}

      {enableFeatured && !isFeatured && !selectionMode && (
        <button
          type="button"
          onClick={(e) => {
            e.stopPropagation();
            e.preventDefault();
            onSetFeatured?.(image);
          }}
          onPointerDown={(e) => e.stopPropagation()}
          onPointerUp={(e) => e.stopPropagation()}
          disabled={isUploading || isError}
          className="absolute top-2 left-2 rounded-full bg-black/50 p-1 opacity-0 group-hover:opacity-100 hover:bg-black/70 disabled:opacity-50 pointer-events-auto z-30 transition-opacity"
          title="Set as cover image"
        >
          <Star className="size-3 text-white" />
        </button>
      )}

      {!selectionMode && (
        <button
          type="button"
          onClick={(e) => {
            e.stopPropagation();
            e.preventDefault();
            onRemove(image);
          }}
          onPointerDown={(e) => e.stopPropagation()}
          onPointerUp={(e) => e.stopPropagation()}
          disabled={isUploading}
          className="absolute top-2 right-2 rounded-full bg-destructive p-1 opacity-0 group-hover:opacity-100 hover:bg-destructive/90 disabled:opacity-50 pointer-events-auto z-30 transition-opacity"
          aria-label={`Remove ${image.name}`}
        >
          <Trash2 className="size-3 text-white" />
        </button>
      )}
    </div>
  );
});

export { SortableImage };
