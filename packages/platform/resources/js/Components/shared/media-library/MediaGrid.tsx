/**
 * MediaGrid - shared Media Library display, in grid or list mode.
 */
import { Image as ImageIcon } from "lucide-react";
import { Checkbox } from "../../ui/checkbox";
import { Skeleton } from "../../ui/skeleton";
import { formatFileSize } from "../../../lib/gallery-utils";
import type { MediaItem } from "./types";

export type MediaView = "grid" | "list";

export interface MediaGridProps {
  items: MediaItem[];
  loading?: boolean;
  selected?: Set<number>;
  selectable?: boolean;
  onToggleSelect?: (_id: number) => void;
  onClick?: (_item: MediaItem) => void;
  onDoubleClick?: (_item: MediaItem) => void;
  emptyText?: string;
  view?: MediaView;
}

export function MediaGrid({
  items,
  loading = false,
  selected = new Set(),
  selectable = false,
  onToggleSelect,
  onClick,
  onDoubleClick,
  emptyText = "No media found",
  view = "grid",
}: MediaGridProps) {
  if (loading) {
    return (
      <div className="grid grid-cols-4 sm:grid-cols-4 md:grid-cols-5 lg:grid-cols-6 gap-3">
        {Array.from({ length: 12 }, (_, i) => (
          <Skeleton key={i} className="aspect-square rounded-lg" />
        ))}
      </div>
    );
  }

  if (items.length === 0) {
    return (
      <div className="flex flex-col items-center justify-center py-16 text-center">
        <div className="p-4 rounded-full bg-muted mb-3">
          <ImageIcon className="h-8 w-8 text-muted-foreground" />
        </div>
        <p className="text-sm font-medium text-foreground">{emptyText}</p>
        <p className="text-xs text-muted-foreground mt-1">Upload images to build your library</p>
      </div>
    );
  }

  if (view === "list") {
    return (
      <div className="rounded-lg border overflow-hidden divide-y divide-border">
        <div className="grid grid-cols-12 gap-2 px-4 py-2 bg-muted text-xs font-medium text-muted-foreground">
          <span className="col-span-4">Image</span>
          <span className="col-span-3">Folder</span>
          <span className="col-span-2">Uploaded</span>
          <span className="col-span-2">Size</span>
          <span className="col-span-1" />
        </div>
        {items.map((item) => {
          const isSelected = selected.has(item.id);
          return (
            <div
              key={item.id}
              role={onClick || onDoubleClick ? "button" : undefined}
              tabIndex={onClick || onDoubleClick ? 0 : undefined}
              onClick={() => {
                if (onClick) onClick(item);
              }}
              onDoubleClick={() => {
                if (onDoubleClick) onDoubleClick(item);
              }}
              className={[
                "grid grid-cols-12 gap-2 items-center px-4 py-2 select-none",
                isSelected ? "bg-primary/10" : "hover:bg-accent/50",
                onClick || onDoubleClick ? "cursor-pointer" : "",
              ].join(" ")}
            >
              <div className="col-span-4 flex items-center gap-3 min-w-0">
                {selectable && onToggleSelect && (
                  <Checkbox
                    checked={isSelected}
                    onCheckedChange={() => onToggleSelect(item.id)}
                    onClick={(e) => e.stopPropagation()}
                    aria-label={`Select ${item.title || item.filename}`}
                  />
                )}
                <img
                  src={item.url}
                  alt={item.title || item.filename}
                  loading="lazy"
                  className="h-10 w-10 rounded object-cover shrink-0"
                />
                <div className="min-w-0">
                  <p className="text-sm font-medium text-foreground truncate">
                    {item.title || item.filename}
                  </p>
                  <p className="text-xs text-muted-foreground truncate">{item.filename}</p>
                </div>
              </div>
              <div className="col-span-3 min-w-0">
                <span className="text-sm text-muted-foreground truncate block">
                  {item.folder ?? "Unattached"}
                </span>
              </div>
              <span className="col-span-2 text-sm text-muted-foreground">
                {item.date ? new Date(item.date).toLocaleDateString() : "\u{2014}"}
              </span>
              <span className="col-span-2 text-sm text-muted-foreground">
                {formatFileSize(item.size)}
              </span>
              <span className="col-span-1" />
            </div>
          );
        })}
      </div>
    );
  }

  return (
    <div className="grid grid-cols-4 sm:grid-cols-4 md:grid-cols-5 lg:grid-cols-6 gap-3">
      {items.map((item) => {
        const isSelected = selected.has(item.id);
        return (
          <div
            key={item.id}
            role={onClick || onDoubleClick || (selectable && onToggleSelect) ? "button" : undefined}
            tabIndex={onClick || onDoubleClick || (selectable && onToggleSelect) ? 0 : undefined}
            onClick={() => {
              // In selection mode the whole tile toggles; otherwise delegate to
              // the consumer's optional click handler (e.g. preview).
              if (selectable && onToggleSelect) onToggleSelect(item.id);
              else if (onClick) onClick(item);
            }}
            onKeyDown={(e) => {
              if (e.key !== "Enter" && e.key !== " ") return;
              if (onClick || onDoubleClick || (selectable && onToggleSelect)) {
                e.preventDefault();
                if (selectable && onToggleSelect) onToggleSelect(item.id);
                else if (onClick) onClick(item);
              }
            }}
            onDoubleClick={() => {
              if (onDoubleClick) onDoubleClick(item);
            }}
            className={[
              "group relative aspect-square rounded-lg overflow-hidden border",
              "bg-muted select-none",
              isSelected ? "ring-2 ring-primary border-primary" : "hover:border-primary/50",
              onClick || onDoubleClick || (selectable && onToggleSelect) ? "cursor-pointer" : "",
            ].join(" ")}
          >
            <img
              src={item.url}
              alt={item.title || item.filename}
              loading="lazy"
              className="h-full w-full object-cover"
            />
            {selectable && onToggleSelect && (
              <Checkbox
                checked={isSelected}
                onCheckedChange={() => onToggleSelect(item.id)}
                className="absolute top-2 left-2 bg-background/80"
                onClick={(e) => e.stopPropagation()}
                aria-label={`Select ${item.title || item.filename}`}
              />
            )}
            {isSelected && <div className="absolute inset-0 bg-primary/15 pointer-events-none" />}
            <div className="absolute bottom-0 inset-x-0 p-2 bg-gradient-to-t from-black/70 to-transparent opacity-0 group-hover:opacity-100 transition-opacity">
              <p className="text-[11px] text-white truncate">{item.title || item.filename}</p>
              <p className="text-[10px] text-white/70 truncate">{formatFileSize(item.size)}</p>
            </div>
          </div>
        );
      })}
    </div>
  );
}