import { useCallback, useState } from "react";
import { processImage, getCsrfToken } from "@/lib/gallery-utils";

interface UseImageUploadOptions {
  uploadUrl?: string;
  deleteUrl?: string;
  compress?: boolean;
  maxRetries?: number;
  enabled?: boolean;
}

export interface UploadResult {
  url: string;
  path: string;
  attachment_id?: number;
}

interface UseImageUploadReturn {
  upload: (_file: File, _signal?: AbortSignal) => Promise<UploadResult>;
  remove: (_path: string) => Promise<void>;
  retry: (_file: File, _signal?: AbortSignal) => Promise<UploadResult>;
  isUploading: boolean;
  error: string | null;
  clearError: () => void;
}

export function useImageUpload({
  uploadUrl,
  deleteUrl,
  compress = true,
  maxRetries = 2,
  enabled = true,
}: UseImageUploadOptions): UseImageUploadReturn {
  const [isUploading, setIsUploading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const clearError = useCallback(() => {
    setError(null);
  }, []);

  const upload = useCallback(
    async (file: File, signal?: AbortSignal): Promise<UploadResult> => {
      if (!enabled || !uploadUrl) {
        throw new Error("Upload is not enabled for this image picker");
      }

      setIsUploading(true);
      setError(null);

      try {
        const processedFile = compress ? await processImage(file) : file;

        const formData = new FormData();
        formData.append("file", processedFile);

        const res = await fetch(uploadUrl, {
          method: "POST",
          body: formData,
          signal,
          headers: {
            "X-Requested-With": "XMLHttpRequest",
            "X-CSRF-TOKEN": getCsrfToken(),
          },
        });

        if (!res.ok) {
          throw new Error(`Upload failed: ${res.status}`);
        }

        const data = await res.json();
        return { url: data.url, path: data.path, attachment_id: data.attachment_id };
      } catch (error_) {
        const message = error_ instanceof Error ? error_.message : "Upload failed";
        setError(message);
        throw error_;
      } finally {
        setIsUploading(false);
      }
    },
    [uploadUrl, compress, enabled],
  );

  const remove = useCallback(
    async (path: string): Promise<void> => {
      if (!enabled || !deleteUrl) {
        return;
      }

      try {
        await fetch(deleteUrl, {
          method: "DELETE",
          body: JSON.stringify({ path }),
          headers: {
            "Content-Type": "application/json",
            "X-Requested-With": "XMLHttpRequest",
            "X-CSRF-TOKEN": getCsrfToken(),
          },
        });
      } catch (error_) {
        console.error("Failed to delete image:", error_);
        throw error_;
      }
    },
    [deleteUrl, enabled],
  );

  const retry = useCallback(
    async (file: File, signal?: AbortSignal): Promise<UploadResult> => {
      let lastError: Error | null = null;

      for (let attempt = 0; attempt <= maxRetries; attempt++) {
        try {
          return await upload(file, signal);
        } catch (error_) {
          lastError = error_ instanceof Error ? error_ : new Error("Upload failed");
          if (signal?.aborted) {
            throw lastError;
          }
          if (attempt < maxRetries) {
            await new Promise((resolve) => setTimeout(resolve, 1000 * (attempt + 1)));
          }
        }
      }

      throw lastError;
    },
    [upload, maxRetries],
  );

  return {
    upload,
    remove,
    retry,
    isUploading,
    error,
    clearError,
  };
}
