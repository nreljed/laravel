/**
 * OrphanCleanupDialog - lets a user reconcile the media library folder against
 * its records.
 *
 * Backed by `dashboard.media.scan` (report) and `dashboard.media.cleanup`
 * (remove missing records / unrecorded files). Non-managers never see orphan
 * files from the backend, so their "files" row stays disabled.
 */
import { Button } from "../../ui/button";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "../../ui/dialog";
import { formatFileSize } from "../../../lib/gallery-utils";
import { cn } from "../../../lib/utils";
import {
  FolderX,
  ImageOff,
  LoaderCircle,
  RefreshCw,
  ScanSearch,
  Trash2,
} from "lucide-react";
import type { OrphanCleanupMode, OrphanScanReport } from "./types";

interface OrphanCleanupDialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  report: OrphanScanReport | null;
  scanning: boolean;
  cleaning: boolean;
  onScan: () => void;
  onCleanup: (mode: OrphanCleanupMode) => void;
}

const MAX_INLINE_PATHS = 3;

function PathPreview({ paths }: { paths: string[] }) {
  if (paths.length === 0) return null;

  const visible = paths.slice(0, MAX_INLINE_PATHS);
  const overflow = paths.length - visible.length;

  return (
    <ul className="mt-2 space-y-1">
      {visible.map((path) => (
        <li
          key={path}
          className="truncate rounded-md border bg-muted/40 px-2 py-1 font-mono text-[11px] text-muted-foreground"
          title={path}
        >
          {path}
        </li>
      ))}
      {overflow > 0 && (
        <li className="px-2 py-0.5 text-xs text-muted-foreground">
          +{overflow} more&hellip;
        </li>
      )}
    </ul>
  );
}

export function OrphanCleanupDialog({
  open,
  onOpenChange,
  report,
  scanning,
  cleaning,
  onScan,
  onCleanup,
}: OrphanCleanupDialogProps) {
  const missing = report?.totals.missing_records ?? 0;
  const orphanFiles = report?.totals.orphan_files ?? 0;
  const nothingToClean = scanning || cleaning || (missing === 0 && orphanFiles === 0);

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-lg">
        <DialogHeader>
          <DialogTitle className="flex items-center gap-2">
            <ScanSearch className="h-4 w-4" />
            Orphaned media
          </DialogTitle>
          <DialogDescription>
            Reconcile files on disk with their{" "}
            {report?.folder ? `"${report.folder}" folder` : "media library"}{" "}
            records. Orphans are safe to remove.
          </DialogDescription>
        </DialogHeader>

        {scanning ? (
          <div className="flex items-center gap-2 py-6 text-sm text-muted-foreground">
            <LoaderCircle className="h-4 w-4 animate-spin" />
            Scanning for orphaned media&hellip;
          </div>
        ) : report ? (
          <div className="space-y-3">
            <div className={cn("flex gap-3 rounded-lg border p-3", missing > 0 && "bg-destructive/5")}>
              <ImageOff className="mt-0.5 h-4 w-4 shrink-0 text-muted-foreground" />
              <div className="min-w-0 flex-1">
                <div className="flex items-center gap-2">
                  <span className="text-sm font-medium">Missing records</span>
                  <span className="rounded-full bg-muted px-2 py-0.5 text-xs tabular-nums">
                    {missing}
                  </span>
                </div>
                <p className="mt-0.5 text-xs text-muted-foreground">
                  Media entries whose file is already gone from disk.
                </p>
                <PathPreview paths={(report.records ?? []).map((r) => r.path)} />
              </div>
              <Button
                type="button"
                variant="outline"
                size="sm"
                disabled={missing === 0 || cleaning}
                onClick={() => onCleanup("records")}
              >
                Remove records
              </Button>
            </div>

            <div className={cn("flex gap-3 rounded-lg border p-3", orphanFiles > 0 && "bg-destructive/5")}>
              <FolderX className="mt-0.5 h-4 w-4 shrink-0 text-muted-foreground" />
              <div className="min-w-0 flex-1">
                <div className="flex items-center gap-2">
                  <span className="text-sm font-medium">Orphan files</span>
                  <span className="rounded-full bg-muted px-2 py-0.5 text-xs tabular-nums">
                    {orphanFiles}
                  </span>
                </div>
                <p className="mt-0.5 text-xs text-muted-foreground">
                  Files on disk with no record, e.g.{" "}
                  {report.orphan_files.length > 0
                    ? formatFileSize(report.orphan_files[0]?.size ?? 0)
                    : "leftover uploads"}
                  .
                </p>
                <PathPreview paths={(report.orphan_files ?? []).map((f) => f.path)} />
              </div>
              <Button
                type="button"
                variant="outline"
                size="sm"
                disabled={orphanFiles === 0 || cleaning}
                onClick={() => onCleanup("files")}
              >
                Delete files
              </Button>
            </div>

            {missing === 0 && orphanFiles === 0 && (
              <p className="px-1 text-sm text-emerald-600 dark:text-emerald-500">
                All clean — no orphaned media found in this folder.
              </p>
            )}
          </div>
        ) : (
          <div className="py-6 text-sm text-muted-foreground">
            No scan results yet — run a scan to reconcile this folder.
          </div>
        )}

        <DialogFooter>
          <Button
            type="button"
            variant="outline"
            onClick={onScan}
            disabled={scanning || cleaning}
          >
            <RefreshCw className="mr-2 h-4 w-4" />
            Scan again
          </Button>
          <Button
            type="button"
            onClick={() => onCleanup("both")}
            disabled={nothingToClean}
          >
            {cleaning ? (
              <>
                <LoaderCircle className="mr-2 h-4 w-4 animate-spin" />
                Cleaning&hellip;
              </>
            ) : (
              <>
                <Trash2 className="mr-2 h-4 w-4" />
                Remove all orphans
              </>
            )}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}