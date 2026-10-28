/**
 * MediaPickerDialog - shared dialog for selecting media from the user's library.
 *
 * Used by images/featured selectors and editor flows to reuse existing uploads
 * instead of re-uploading (with an optional direct upload).
 */
import * as React from "react";
import { Button } from "../../ui/button";
import { Input } from "../../ui/input";
import {
  Dialog,
  DialogClose,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "../../ui/dialog";
import { ScrollArea } from "../../ui/scroll-area";
import { formatFileSize } from "../../../lib/gallery-utils";
import { MediaGrid } from "./MediaGrid";
import { useMediaLibrary } from "./use-media-library";
import { UploadCloud, Search, Check, X } from "lucide-react";

/**
 * File-type filters offered by the picker toolbar.
 * Values map to MIME types.
 */
const FILE_TYPE_FILTERS = [
  { label: "All", value: "" },
  { label: "Images", value: "image/" },
  { label: "JPEG", value: "image/jpeg" },
  { label: "PNG", value: "image/png" },
  { label: "GIF", value: "image/gif" },
  { label: "WebP", value: "image/webp" },
  { label: "SVG", value: "image/svg" },
] as const;

export interface MediaPickerDialogProps {
  open: boolean;
  onOpenChange: (_open: boolean) => void;
  onSelect: (_items: { id: number; url: string; filename?: string }[]) => void;
  multiple?: boolean;
  subdirectory: string;
}

export function MediaPickerDialog({
  open,
  onOpenChange,
  onSelect,
  multiple = false,
  subdirectory,
}: MediaPickerDialogProps) {
  const {
    items,
    total,
    totalPages,
    page,
    loading,
    uploading,
    upload,
    search,
    setSearch,
    mimeType,
    setMimeType,
    setPage,
    refresh,
  } = useMediaLibrary({
    subdirectory,
  });

  const [selected, setSelected] = React.useState<Set<number>>(new Set());
  const fileInputRef = React.useRef<HTMLInputElement>(null);

  const hasMore = page < totalPages;

  const handleOpenChange = (next: boolean): void => {
    if (next) {
      setSelected(new Set());
      refresh();
    }
    onOpenChange(next);
  };

  const selectedItems = items.filter((i) => selected.has(i.id));

  const toggleSelect = (id: number): void => {
    setSelected((prev) => {
      const next = new Set(prev);
      if (next.has(id)) {
        next.delete(id);
        return next;
      }
      if (!multiple) next.clear();
      next.add(id);
      return next;
    });
  };

  const handleConfirm = (): void => {
    if (selectedItems.length === 0) return;
    onSelect(
      selectedItems.map((i) => ({
        id: i.id,
        url: i.url,
        filename: i.filename,
      })),
    );
    onOpenChange(false);
  };

  const handleUpload = async (files: FileList | File[] | null): Promise<void> => {
    if (!files || files.length === 0) return;
    const uploaded = await upload([...files]);
    await refresh();
    // Auto-select newly uploaded images.
    setSelected((prev) => {
      const next = new Set(prev);
      for (const it of uploaded) next.add(it.id);
      return next;
    });
    if (fileInputRef.current) fileInputRef.current.value = "";
  };

  return (
    <Dialog open={open} onOpenChange={handleOpenChange}>
      <DialogContent
        className="sm:max-w-3xl p-0 gap-0 overflow-hidden"
        showCloseButton={false}
      >
        <DialogHeader className="flex-row items-center justify-between border-b border-border px-6 py-4">
          <DialogTitle className="text-foreground">Media Library</DialogTitle>
          <DialogClose className="inline-flex h-8 w-8 items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-accent hover:text-foreground">
            <X />
            <span className="sr-only">Close</span>
          </DialogClose>
        </DialogHeader>

        <div className="space-y-3 px-6 py-4">
          {/* Toolbar: search + upload */}
          <div className="flex items-center gap-2">
            <div className="relative flex-1">
              <Search className="absolute left-2.5 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
              <Input
                placeholder="Search media..."
                value={search}
                onChange={(e) => setSearch(e.target.value)}
                className="pl-8"
              />
            </div>
            <input
              ref={fileInputRef}
              type="file"
              accept="image/*"
              multiple
              className="hidden"
              onChange={(e) => handleUpload(e.target.files)}
            />
            <Button
              type="button"
              variant="outline"
              onClick={() => fileInputRef.current?.click()}
              disabled={uploading}
            >
              <UploadCloud className="mr-2 h-4 w-4" />
              {uploading ? "Uploading..." : "Upload"}
            </Button>
          </div>

          {/* File type filter */}
          <div className="flex flex-wrap items-center gap-1.5">
            {FILE_TYPE_FILTERS.map((f) => (
              <Button
                key={f.value}
                type="button"
                size="sm"
                variant={mimeType === f.value ? "default" : "outline"}
                onClick={() => setMimeType(f.value)}
                className="h-7 px-2.5 text-xs"
              >
                {f.label}
              </Button>
            ))}
          </div>

          {/* Grid */}
          <ScrollArea className="h-[50vh]">
            <MediaGrid
              items={items}
              loading={loading}
              selected={selected}
              selectable
              onToggleSelect={toggleSelect}
            />
          </ScrollArea>

          {/* Pagination + count */}
          <div className="flex items-center justify-between">
            <span className="text-sm text-muted-foreground">
              {total > 0 ? `${Math.min(items.length, total)} of ${total} shown` : "No media found"}
            </span>
            {hasMore && (
              <Button
                type="button"
                variant="ghost"
                size="sm"
                onClick={() => setPage(page + 1)}
                disabled={loading}
              >
                Load more
              </Button>
            )}
          </div>

          {/* Selected count */}
          {selectedItems.length > 0 && (
            <div className="text-sm text-foreground flex items-center gap-1">
              <Check className="h-4 w-4 text-primary" />
              {selectedItems.length} selected
              {selectedItems.length === 1 && (
                <span className="text-muted-foreground">
                  {" "}
                  · {formatFileSize(selectedItems[0]!.size)}
                </span>
              )}
            </div>
          )}
        </div>

        <DialogFooter className="rounded-b-xl border-t border-border px-6 py-4">
          <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
            Cancel
          </Button>
          <Button type="button" onClick={handleConfirm} disabled={selectedItems.length === 0}>
            Insert{selectedItems.length > 0 ? ` (${selectedItems.length})` : ""}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}