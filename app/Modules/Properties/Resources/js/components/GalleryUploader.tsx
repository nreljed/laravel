import { useState, useCallback, useRef } from "react";
import { router } from "@inertiajs/react";
import { Upload, X, Star, GripVertical, ImageIcon } from "lucide-react";
import { toast } from "sonner";
import { cn } from "@/lib/utils";
import { Button } from "@/Components/ui/button";
import { Input } from "@/Components/ui/input";
import { Card, CardContent } from "@/Components/ui/card";
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
  useSortable,
  verticalListSortingStrategy,
} from "@dnd-kit/sortable";
import { restrictToVerticalAxis } from "@dnd-kit/modifiers";

interface GalleryImage {
  id: number;
  url: string;
  thumbnail_url: string;
  alt_text: string | null;
  caption: string | null;
  is_featured: boolean;
  sort_order: number;
}

interface GalleryUploaderProps {
  propertyId: number;
  images: GalleryImage[];
  maxImages?: number;
}

function SortableImage({
  image,
  onDelete,
  onSetFeatured,
  onUpdateAlt,
}: {
  image: GalleryImage;
  onDelete: (id: number) => void;
  onSetFeatured: (id: number) => void;
  onUpdateAlt: (id: number, alt: string) => void;
}) {
  const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({ id: image.id });

  const style = transform
    ? { transform: `translate3d(${transform.x}px, ${transform.y}px, 0)`, transition }
    : undefined;

  return (
    <div
      ref={setNodeRef}
      style={style}
      className={cn(
        "group relative flex flex-col rounded-lg border bg-card overflow-hidden",
        isDragging && "z-50 opacity-80 shadow-lg",
        image.is_featured && "ring-2 ring-primary",
      )}
    >
      <div className="relative aspect-[4/3] overflow-hidden bg-muted">
        <img
          src={image.thumbnail_url || image.url}
          alt={image.alt_text || ""}
          className="h-full w-full object-cover"
        />
        <div className="absolute inset-0 flex items-start justify-between p-1 opacity-0 group-hover:opacity-100 transition-opacity">
          <button
            type="button"
            className="flex h-7 w-7 cursor-grab items-center justify-center rounded-md bg-background/80 text-foreground hover:bg-background"
            {...attributes}
            {...listeners}
          >
            <GripVertical className="h-4 w-4" />
          </button>
          <div className="flex gap-1">
            {!image.is_featured && (
              <button
                type="button"
                onClick={() => onSetFeatured(image.id)}
                className="flex h-7 w-7 items-center justify-center rounded-md bg-background/80 text-muted-foreground hover:bg-background hover:text-amber-500"
                title="Set as featured"
              >
                <Star className="h-4 w-4" />
              </button>
            )}
            <button
              type="button"
              onClick={() => onDelete(image.id)}
              className="flex h-7 w-7 items-center justify-center rounded-md bg-background/80 text-muted-foreground hover:bg-background hover:text-destructive"
              title="Delete image"
            >
              <X className="h-4 w-4" />
            </button>
          </div>
        </div>
        {image.is_featured && (
          <div className="absolute bottom-1 right-1 flex items-center gap-1 rounded-md bg-amber-500 px-1.5 py-0.5 text-[10px] font-medium text-white">
            <Star className="h-3 w-3 fill-current" />
            Featured
          </div>
        )}
      </div>
      <div className="p-2">
        <Input
          type="text"
          value={image.alt_text || ""}
          onChange={(e) => onUpdateAlt(image.id, e.target.value)}
          placeholder="Alt text"
          className="h-7 text-xs"
        />
      </div>
    </div>
  );
}

export function GalleryUploader({ propertyId, images, maxImages = 20 }: GalleryUploaderProps) {
  const [items, setItems] = useState(images);
  const [uploading, setUploading] = useState(false);
  const fileInputRef = useRef<HTMLInputElement>(null);

  const sensors = useSensors(
    useSensor(PointerSensor, { activationConstraint: { distance: 8 } }),
    useSensor(KeyboardSensor, { coordinateGetter: sortableKeyboardCoordinates }),
  );

  const handleUpload = useCallback(
    (e: React.ChangeEvent<HTMLInputElement>) => {
      const files = e.target.files;
      if (!files?.length) return;

      if (items.length + files.length > maxImages) {
        toast.error(`Maximum ${maxImages} images allowed`);
        return;
      }

      setUploading(true);
      const formData = new FormData();
      formData.append("file", files[0]);

      router.post(route("dashboard.properties.upload-image", propertyId), formData, {
        preserveScroll: true,
        onSuccess: () => {
          router.reload({ only: ["property"] });
          toast.success("Image uploaded");
          setUploading(false);
        },
        onError: () => {
          toast.error("Upload failed");
          setUploading(false);
        },
      });

      if (fileInputRef.current) fileInputRef.current.value = "";
    },
    [propertyId, items.length, maxImages],
  );

  const handleDelete = useCallback(
    (imageId: number) => {
      router.delete(route("dashboard.properties.delete-image", [propertyId, imageId]), {
        preserveScroll: true,
        onSuccess: () => {
          setItems((prev) => prev.filter((img) => img.id !== imageId));
          toast.success("Image deleted");
        },
        onError: () => toast.error("Failed to delete image"),
      });
    },
    [propertyId],
  );

  const handleSetFeatured = useCallback(
    (imageId: number) => {
      router.post(route("dashboard.properties.set-featured-image", [propertyId, imageId]), {}, {
        preserveScroll: true,
        onSuccess: () => {
          setItems((prev) =>
            prev.map((img) => ({ ...img, is_featured: img.id === imageId })),
          );
          toast.success("Featured image updated");
        },
        onError: () => toast.error("Failed to update featured image"),
      });
    },
    [propertyId],
  );

  const handleUpdateAlt = useCallback(
    (imageId: number, alt: string) => {
      setItems((prev) => prev.map((img) => (img.id === imageId ? { ...img, alt_text: alt } : img)));
    },
    [],
  );

  const handleDragEnd = useCallback(
    (event: DragEndEvent) => {
      const { active, over } = event;
      if (!over || active.id === over.id) return;

      setItems((prev) => {
        const oldIndex = prev.findIndex((img) => img.id === active.id);
        const newIndex = prev.findIndex((img) => img.id === over.id);
        const reordered = arrayMove(prev, oldIndex, newIndex);
        const orderedIds = reordered.map((img) => img.id);

        router.post(route("dashboard.properties.reorder-images", propertyId), { ordered_ids: orderedIds }, {
          preserveScroll: true,
          onError: () => toast.error("Failed to reorder"),
        });

        return reordered;
      });
    },
    [propertyId],
  );

  return (
    <Card>
      <CardContent className="p-4">
        <div className="mb-3 flex items-center justify-between">
          <div className="flex items-center gap-2 text-sm font-medium">
            <ImageIcon className="h-4 w-4" />
            Gallery ({items.length}/{maxImages})
          </div>
          {items.length < maxImages && (
            <>
              <input
                ref={fileInputRef}
                type="file"
                accept="image/jpeg,image/png,image/webp"
                className="hidden"
                onChange={handleUpload}
              />
              <Button
                type="button"
                variant="outline"
                size="sm"
                disabled={uploading}
                onClick={() => fileInputRef.current?.click()}
              >
                <Upload className="mr-1 h-4 w-4" />
                {uploading ? "Uploading..." : "Upload"}
              </Button>
            </>
          )}
        </div>

        {items.length === 0 ? (
          <div
            className="flex cursor-pointer flex-col items-center gap-2 rounded-lg border-2 border-dashed p-8 text-center text-sm text-muted-foreground hover:border-primary/50"
            onClick={() => fileInputRef.current?.click()}
          >
            <Upload className="h-8 w-8" />
            Click to upload images
            <span className="text-xs">JPEG, PNG, WebP up to 5MB</span>
          </div>
        ) : (
          <DndContext
            sensors={sensors}
            collisionDetection={closestCenter}
            onDragEnd={handleDragEnd}
            modifiers={[restrictToVerticalAxis]}
          >
            <SortableContext items={items.map((img) => img.id)} strategy={verticalListSortingStrategy}>
              <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4">
                {items.map((image) => (
                  <SortableImage
                    key={image.id}
                    image={image}
                    onDelete={handleDelete}
                    onSetFeatured={handleSetFeatured}
                    onUpdateAlt={handleUpdateAlt}
                  />
                ))}
              </div>
            </SortableContext>
          </DndContext>
        )}
      </CardContent>
    </Card>
  );
}
