/**
 * Media Library - shared types.
 *
 * `MediaRow` matches the backend JSON shape (raw `media` table row from
 * `MediaController`). `MediaItem` is the normalized UI shape used by the grid,
 * table, and picker.
 */

/** Raw row as returned by the backend (`MediaController::index` / `upload`). */
export interface MediaRow {
  id: number;
  user_id: number;
  folder: string;
  path: string;
  url: string;
  filename: string;
  title: string | null;
  mime_type: string;
  size: number;
  width?: number | null;
  height?: number | null;
  created_at: string;
  updated_at: string;
}

export interface MediaItem {
  id: number;
  url: string;
  filename: string;
  title: string;
  mime_type: string;
  size: number;
  date: string;
  width?: number | null;
  height?: number | null;
  uploaded_by: number;
  /** Relative uploads path (e.g. "posts/2026/09/foo.webp"). Used for deletion. */
  path?: string;
  /** Module folder scope this item lives in (e.g. "posts", "media-library"). */
  folder?: string;
}

export function toMediaItem(row: MediaRow): MediaItem {
  return {
    id: row.id,
    url: row.url,
    filename: row.filename,
    title: row.title ?? row.filename,
    mime_type: row.mime_type,
    size: row.size,
    date: row.created_at,
    width: row.width ?? null,
    height: row.height ?? null,
    uploaded_by: row.user_id,
    path: row.path,
    folder: row.folder,
  };
}

export interface MediaListResponse {
  data: MediaRow[];
  total: number;
  totalPages: number;
  page: number;
  perPage: number;
}

/** A `media` row whose physical file is missing on disk. */
export interface OrphanRecord {
  id: number;
  path: string;
  filename: string;
  size: number;
}

/** A file on disk with no matching `media` row. */
export interface OrphanFile {
  path: string;
  filename: string;
  size: number;
}

/** Response of `dashboard.media.scan`. */
export interface OrphanScanReport {
  folder: string;
  records: OrphanRecord[];
  orphan_files: OrphanFile[];
  totals: { missing_records: number; orphan_files: number };
}

export type OrphanCleanupMode = "records" | "files" | "both";

export interface MediaQueryParams {
  page?: number;
  perPage?: number;
  search?: string;
  mimeType?: string;
}