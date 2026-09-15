/**
 * Media Library page for the Blog module.
 *
 * Auto-registered as `posts/media` via the `registerPostsPages()` glob. Uses the
 * shared media-library components scoped to the "posts" uploads folder.
 */
import { useRef, useState } from "react";
import { Head } from "@inertiajs/react";
import AuthenticatedLayout from "@nreljed/platform/Layouts/AuthenticatedLayout";
import { Button } from "@nreljed/platform/Components/ui/button";
import { Input } from "@nreljed/platform/Components/ui/input";
import {
  MediaGrid,
  type MediaView,
} from "@nreljed/platform/Components/shared/media-library/MediaGrid";
import { MediaDataTable } from "@nreljed/platform/Components/shared/media-library/MediaDataTable";
import { OrphanCleanupDialog } from "@nreljed/platform/Components/shared/media-library/OrphanCleanupDialog";
import { useMediaLibrary } from "@nreljed/platform/Components/shared/media-library/use-media-library";
import type { MediaItem, OrphanCleanupMode, OrphanScanReport } from "@nreljed/platform/Components/shared/media-library/types";
import { LayoutGrid, List, ScanSearch, Search, UploadCloud } from "lucide-react";

const FILE_TYPE_FILTERS = [
  { label: "All", value: "" },
  { label: "Images", value: "image/" },
  { label: "JPEG", value: "image/jpeg" },
  { label: "PNG", value: "image/png" },
  { label: "GIF", value: "image/gif" },
  { label: "WebP", value: "image/webp" },
  { label: "SVG", value: "image/svg" },
] as const;

export default function MediaLibraryPage() {
  const {
    items,
    total,
    totalPages,
    page,
    loading,
    uploading,
    search,
    setSearch,
    mimeType,
    setMimeType,
    setPage,
    refresh,
    upload,
    remove,
    scanOrphans,
    cleanupOrphans,
  } = useMediaLibrary({
    subdirectory: "posts",
  });

  const [view, setView] = useState<MediaView>("grid");
  const [scanOpen, setScanOpen] = useState(false);
  const [scanReport, setScanReport] = useState<OrphanScanReport | null>(null);
  const [scanning, setScanning] = useState(false);
  const [cleaning, setCleaning] = useState(false);
  const fileInputRef = useRef<HTMLInputElement>(null);

  const hasMore = page < totalPages;

  const handleScan = async (): Promise<void> => {
    setScanning(true);
    const report = await scanOrphans();
    if (report) setScanReport(report);
    setScanning(false);
  };

  const handleCleanup = async (mode: OrphanCleanupMode): Promise<void> => {
    setCleaning(true);
    const done = await cleanupOrphans(mode);
    if (done) {
      setScanReport(null);
      await refresh();
    }
    setCleaning(false);
  };

  const handleUpload = async (files: FileList | null): Promise<void> => {
    if (!files || files.length === 0) return;
    const uploaded = await upload([...files]);
    if (uploaded.length > 0) await refresh();
    if (fileInputRef.current) fileInputRef.current.value = "";
  };

  const handleDelete = async (item: MediaItem): Promise<boolean> => {
    return remove(item);
  };

  const handleBulkDelete = async (targets: MediaItem[]): Promise<void> => {
    for (const target of targets) {
      await remove(target);
    }
  };

  return (
    <AuthenticatedLayout header="Media Library">
      <Head title="Media Library" />

      <div className="space-y-4">
        <div className="flex flex-wrap items-center justify-between gap-3">
          <div>
            <h2 className="text-2xl font-bold tracking-tight">Media Library</h2>
            <p className="text-muted-foreground">
              Browse and manage images uploaded to posts.
            </p>
          </div>
          <div className="flex items-center gap-2">
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
              onClick={() => {
                setScanOpen(true);
                if (!scanReport) void handleScan();
              }}
            >
              <ScanSearch className="mr-2 h-4 w-4" />
              Scan
            </Button>
            <Button
              type="button"
              variant="outline"
              onClick={() => fileInputRef.current?.click()}
              disabled={uploading}
            >
              <UploadCloud className="mr-2 h-4 w-4" />
              {uploading ? "Uploading..." : "Upload"}
            </Button>
            <div className="flex items-center rounded-md border">
              <Button
                type="button"
                variant={view === "grid" ? "secondary" : "ghost"}
                size="icon-sm"
                onClick={() => setView("grid")}
                aria-label="Grid view"
              >
                <LayoutGrid className="h-4 w-4" />
              </Button>
              <Button
                type="button"
                variant={view === "list" ? "secondary" : "ghost"}
                size="icon-sm"
                onClick={() => setView("list")}
                aria-label="List view"
              >
                <List className="h-4 w-4" />
              </Button>
            </div>
          </div>
        </div>

        {/* Toolbar: search + file-type filter */}
        <div className="space-y-3">
          <div className="relative max-w-sm">
            <Search className="absolute left-2.5 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
            <Input
              placeholder="Search media..."
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              className="pl-8"
            />
          </div>
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
        </div>

        {view === "list" ? (
          <MediaDataTable
            items={items}
            loading={loading}
            onDelete={handleDelete}
            onBulkDelete={handleBulkDelete}
            searchKey={undefined}
          />
        ) : (
          <>
            <MediaGrid items={items} loading={loading} />
            {hasMore && (
              <div className="flex items-center justify-center">
                <Button
                  type="button"
                  variant="outline"
                  size="sm"
                  onClick={() => setPage(page + 1)}
                  disabled={loading}
                >
                  Load more
                </Button>
              </div>
            )}
          </>
        )}

        <div className="text-sm text-muted-foreground">
          {total > 0
            ? `${Math.min(items.length, total)} of ${total} media items`
            : !loading
              ? "No media found"
              : "Loading..."}
        </div>
      </div>

      <OrphanCleanupDialog
        open={scanOpen}
        onOpenChange={setScanOpen}
        report={scanReport}
        scanning={scanning}
        cleaning={cleaning}
        onScan={() => void handleScan()}
        onCleanup={(mode) => void handleCleanup(mode)}
      />
    </AuthenticatedLayout>
  );
}