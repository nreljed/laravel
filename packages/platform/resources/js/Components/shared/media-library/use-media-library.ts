/**
 * useMediaLibrary - shared data hook for the Media Library.
 *
 * Handles fetching, uploading, and deleting media items against the
 * `MediaController` JSON endpoints (`dashboard.media.*`). Used by both the
 * standalone page and the picker dialog.
 */
import * as React from "react";
import { toast } from "../../ui/toast";
import { getCsrfToken, jsonHeaders } from "../../../lib/gallery-utils";
import type {
  MediaItem,
  MediaListResponse,
  MediaRow,
  OrphanCleanupMode,
  OrphanScanReport,
} from "./types";
import { toMediaItem } from "./types";

interface UseMediaLibraryOptions {
  initialItems?: MediaItem[];
  initialTotal?: number;
  initialPages?: number;
  /**
   * Which module's uploads folder to scope to (e.g. "posts"). The module ALWAYS
   * supplies this — the shared media library never defaults to a specific
   * module, otherwise it would leak one module's folder into another's.
   */
  subdirectory: string;
}

export interface MediaLibraryApi {
  items: MediaItem[];
  total: number;
  totalPages: number;
  page: number;
  perPage: number;
  loading: boolean;
  uploading: boolean;
  search: string;
  setSearch: (s: string) => void;
  mimeType: string;
  setMimeType: (m: string) => void;
  setPage: (p: number) => void;
  refresh: () => Promise<void>;
  upload: (files: File[]) => Promise<MediaItem[]>;
  remove: (item: MediaItem) => Promise<boolean>;
  scanOrphans: () => Promise<OrphanScanReport | undefined>;
  cleanupOrphans: (mode: OrphanCleanupMode) => Promise<boolean>;
}

const PER_PAGE = 24;

export function useMediaLibrary(options: UseMediaLibraryOptions): MediaLibraryApi {
  const { initialItems = [], initialTotal = 0, initialPages = 1, subdirectory } = options;

  const [items, setItems] = React.useState<MediaItem[]>(initialItems);
  const [total, setTotal] = React.useState(initialTotal);
  const [totalPages, setTotalPages] = React.useState(initialPages);
  const [page, setPage] = React.useState(1);
  const [perPage, setPerPage] = React.useState(PER_PAGE);
  const [search, setSearch] = React.useState("");
  const [mimeType, setMimeType] = React.useState("");
  const [loading, setLoading] = React.useState(false);
  const [uploading, setUploading] = React.useState(false);
  const searchTimeout = React.useRef<number | undefined>(undefined);

  const fetchItems = React.useCallback(
    async (currentPage: number, query = "", filterMime = "") => {
      setLoading(true);
      try {
        const url = new URL(route("dashboard.media.index") as string, window.location.origin);
        url.searchParams.set("page", String(currentPage));
        url.searchParams.set("per_page", String(PER_PAGE));
        url.searchParams.set("folder", subdirectory);
        if (query) url.searchParams.set("search", query);
        if (filterMime) url.searchParams.set("mime_type", filterMime);

        const res = await fetch(url.toString(), {
          method: "GET",
          headers: { Accept: "application/json" },
        });
        const json = (await res.json()) as MediaListResponse &
          ({ message?: string } | Record<string, unknown>);
        if ("data" in json && Array.isArray(json.data)) {
          // Load-more appends paginated results; page 1 replaces the list.
          setItems((prev) => {
            if (currentPage <= 1 || prev.length === 0) return json.data.map(toMediaItem);
            const ids = new Set(prev.map((i) => i.id));
            return [...prev, ...json.data.map(toMediaItem).filter((i) => !ids.has(i.id))];
          });
          setTotal(json.total);
          setTotalPages(json.totalPages);
          setPerPage(json.perPage);
        } else if (import.meta.env.DEV) {
          console.error("[MediaLibrary] load error:", (json as { message?: string }).message);
        }
      } catch (error) {
        if (import.meta.env.DEV) console.error("[MediaLibrary] load error:", error);
      } finally {
        setLoading(false);
      }
    },
    // subdirectory changes scope, so re-fetch when it changes.
    [subdirectory],
  );

  const refresh = React.useCallback(async () => {
    await fetchItems(1, search, mimeType);
  }, [fetchItems, search, mimeType]);

  React.useEffect(() => {
    // Scope change (subdirectory/mime type): reset to page 1 and refetch.
    setPage(1);
    fetchItems(1, search, mimeType);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [subdirectory, mimeType]);

  // Load-more: fetch the next page when page advances (page 1 is handled by the
  // scope effect above on mount).
  React.useEffect(() => {
    if (page === 1) return;
    fetchItems(page, search, mimeType);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [page]);

  // Debounced search (fires on the search term only — filter changes refetch
  // immediately via the scope effect).
  React.useEffect(() => {
    if (searchTimeout.current) window.clearTimeout(searchTimeout.current);
    searchTimeout.current = window.setTimeout(() => {
      setPage(1);
      fetchItems(1, search, mimeType);
    }, 350);
    return () => {
      if (searchTimeout.current) window.clearTimeout(searchTimeout.current);
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [search]);

  const upload = React.useCallback(
    async (files: File[]): Promise<MediaItem[]> => {
      if (files.length === 0) return [];
      setUploading(true);
      const uploaded: MediaItem[] = [];
      try {
        for (const file of files) {
          const formData = new FormData();
          formData.append("file", file);
          formData.append("folder", subdirectory);
          const res = await fetch(route("dashboard.media.upload") as string, {
            method: "POST",
            headers: { "X-CSRF-TOKEN": getCsrfToken(), Accept: "application/json" },
            body: formData,
          });
          const json = (await res.json()) as
            | { success: true; data: MediaRow }
            | { success: false; message?: string };
          if (json.success && json.data?.url) {
            const item = toMediaItem(json.data);
            uploaded.push(item);
            setItems((prev) => [item, ...prev]);
            setTotal((prev) => prev + 1);
          } else if (!json.success) {
            toast.add({ title: json.message || "Upload failed", type: "error" });
          }
        }
        if (uploaded.length > 0) {
          setTotalPages((prev) =>
            Math.max(1, Math.ceil((prev * perPage + uploaded.length) / perPage)),
          );
        }
      } catch (error) {
        if (import.meta.env.DEV) console.error("[MediaLibrary] upload error:", error);
        toast.add({ title: "Upload failed", type: "error" });
      } finally {
        setUploading(false);
      }
      return uploaded;
    },
    [perPage, subdirectory],
  );

  const remove = React.useCallback(
    async (item: MediaItem): Promise<boolean> => {
      try {
        const res = await fetch(route("dashboard.media.destroy") as string, {
          method: "DELETE",
          headers: jsonHeaders(),
          body: JSON.stringify({ path: item.path, folder: item.folder }),
        });
        const json = (await res.json()) as { success?: boolean; message?: string };
        if (json.success) {
          setItems((prev) => prev.filter((i) => i.id !== item.id));
          setTotal((prev) => Math.max(0, prev - 1));
          toast.add({ title: "Media deleted", type: "success" });
          return true;
        }
        toast.add({ title: json.message || "Failed to delete", type: "error" });
        return false;
      } catch (error) {
        if (import.meta.env.DEV) console.error("[MediaLibrary] delete error:", error);
        toast.add({ title: "Failed to delete", type: "error" });
        return false;
      }
    },
    [subdirectory],
  );

  const scanOrphans = React.useCallback(async (): Promise<OrphanScanReport | undefined> => {
    try {
      const res = await fetch(route("dashboard.media.scan") as string, {
        method: "POST",
        headers: jsonHeaders(),
        body: JSON.stringify({ folder: subdirectory }),
      });
      const json = (await res.json()) as OrphanScanReport & { message?: string };
      if (res.ok) return json;
      toast.add({ title: json.message || "Scan failed", type: "error" });
      return undefined;
    } catch (error) {
      if (import.meta.env.DEV) console.error("[MediaLibrary] scan error:", error);
      toast.add({ title: "Scan failed", type: "error" });
      return undefined;
    }
  }, [subdirectory]);

  const cleanupOrphans = React.useCallback(
    async (mode: OrphanCleanupMode): Promise<boolean> => {
      try {
        const res = await fetch(route("dashboard.media.cleanup") as string, {
          method: "POST",
          headers: jsonHeaders(),
          body: JSON.stringify({ folder: subdirectory, mode }),
        });
        const json = (await res.json()) as {
          success?: boolean;
          removed_records?: number;
          removed_files?: number;
          message?: string;
        };
        if (json.success) {
          const parts: string[] = [];
          if (json.removed_records) parts.push(`${json.removed_records} orphaned records`);
          if (json.removed_files) parts.push(`${json.removed_files} orphaned files`);
          toast.add({
            title: parts.length > 0 ? `Cleaned up ${parts.join(" and ")}` : "Nothing to clean up",
            type: json.removed_records || json.removed_files ? "success" : "info",
          });
          return true;
        }
        toast.add({ title: json.message || "Cleanup failed", type: "error" });
        return false;
      } catch (error) {
        if (import.meta.env.DEV) console.error("[MediaLibrary] cleanup error:", error);
        toast.add({ title: "Cleanup failed", type: "error" });
        return false;
      }
    },
    [subdirectory],
  );

  return {
    items,
    total,
    totalPages,
    page,
    perPage,
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
  };
}