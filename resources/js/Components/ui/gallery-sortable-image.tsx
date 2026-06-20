import * as React from "react";
import { useSortable } from "@dnd-kit/sortable";
import { CSS } from "@dnd-kit/utilities";
import { Trash2, Star, GripVertical, Loader2, Check } from "lucide-react";
import { Badge } from "@/Components/ui/badge";
import { getImageUrl } from "@/lib/gallery-utils";

export interface GalleryImage {
  id: string | number;
  name?: string | null;
  filename?: string | null;
  url?: string;
  url_raw?: string;
  urlRaw?: string;
}

interface SortableImageProps {
  image: GalleryImage;
  onRemove: (_image: GalleryImage) => void;
  onSetFeatured: (_image: GalleryImage) => void;
  isUploading?: boolean;
  isFeatured?: boolean;
  enableDragHandle?: boolean;
  enableDrag?: boolean;
  onConfirmDelete?: (_image: GalleryImage) => void;
  enableFeatured?: boolean;
  isDeleting?: boolean;
  selectionMode?: boolean;
  isSelected?: boolean;
  onToggleSelect?: (_id: string | number) => void;
}

const SortableImage = React.memo(function SortableImage({
  image,
  onRemove,
  onSetFeatured,
  isUploading,
  isFeatured,
  enableDragHandle = false,
  enableDrag = true,
  onConfirmDelete,
  enableFeatured = true,
  isDeleting = false,
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

  const imageUrl = getImageUrl(image);
  const shouldUseHandle = enableDragHandle;

  return (
    <div
      ref={setNodeRef}
      style={style}
      {...(enableDrag && !shouldUseHandle && !selectionMode ? { ...attributes, ...listeners } : {})}
      className={`group relative aspect-square rounded-lg overflow-hidden bg-muted select-none touch-none ${
        isFeatured ? "border-3 border-yellow-500" : "border-2 border-transparent"
      } ${
        isDragging
          ? "ring-2 ring-primary shadow-lg cursor-grabbing"
          : enableDrag && !selectionMode
            ? "cursor-grab"
            : selectionMode
              ? "cursor-pointer"
              : ""
      }`}
      onClick={selectionMode ? () => onToggleSelect?.(image.id) : undefined}
    >
      {imageUrl ? (
        <img
          src={imageUrl}
          alt={image.name || image.filename || "Gallery image"}
          className="size-full object-cover"
          draggable="false"
          onError={(e) => {
            (e.target as HTMLElement).style.display = "none";
          }}
        />
      ) : (
        <div className="size-full flex items-center justify-center bg-muted">
          <span className="text-xs text-muted-foreground">No preview</span>
        </div>
      )}

      {isDeleting && (
        <div className="absolute inset-0 bg-black/50 flex items-center justify-center z-40">
          <Loader2 className="size-6 text-white animate-spin" />
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
          className="absolute bottom-1 right-1 p-1 cursor-grab z-20"
          onClick={(e) => e.stopPropagation()}
        >
          <GripVertical className="size-4 text-white drop-shadow-md" />
        </div>
      )}

      {enableFeatured && isFeatured && !selectionMode && (
        <div className="absolute top-2 left-2 z-30">
          <Badge
            variant="default"
            className="gap-1 bg-yellow-500 text-white border-yellow-400 hover:bg-yellow-600"
          >
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
            onSetFeatured(image);
          }}
          onPointerDown={(e) => e.stopPropagation()}
          onPointerUp={(e) => e.stopPropagation()}
          disabled={isUploading || isDeleting}
          className="absolute top-2 left-2 rounded-full bg-black/50 p-1 opacity-100 hover:bg-black/70 disabled:opacity-50 pointer-events-auto z-30"
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
            if (onConfirmDelete) {
              onConfirmDelete(image);
            } else {
              onRemove(image);
            }
          }}
          onPointerDown={(e) => e.stopPropagation()}
          onPointerUp={(e) => e.stopPropagation()}
          disabled={isUploading || isDeleting}
          className="absolute top-2 right-2 rounded-full bg-destructive p-1 opacity-100 hover:bg-destructive/90 disabled:opacity-50 pointer-events-auto z-30"
        >
          <Trash2 className="size-3 text-white" />
        </button>
      )}
    </div>
  );
});

export { SortableImage };
export default SortableImage;
