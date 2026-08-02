export const DEFAULT_IMAGE_CONFIG = {
  maxWidth: 1920,
  maxHeight: 1080,
  quality: 0.85,
  format: "image/webp",
} as const;

export const GALLERY_MAX_SIZE = 10 * 1024 * 1024;

export function getCsrfToken(): string {
  return document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") || "";
}

export function jsonHeaders(): Record<string, string> {
  return {
    "Content-Type": "application/json",
    "X-Requested-With": "XMLHttpRequest",
    "X-CSRF-TOKEN": getCsrfToken(),
  };
}

export function formatFileSize(bytes: number): string {
  if (bytes === 0) return "0 B";
  const units = ["B", "KB", "MB", "GB"];
  const i = Math.floor(Math.log(bytes) / Math.log(1024));
  return `${(bytes / Math.pow(1024, i)).toFixed(i > 0 ? 1 : 0)} ${units[i]}`;
}

interface ImageLike {
  url?: string;
  url_raw?: string;
  urlRaw?: string;
}

export function getImageUrl(image: ImageLike | null | undefined): string {
  if (!image) return "";
  return image.url || image.url_raw || image.urlRaw || "";
}

export function createPreview(file: File | null | undefined): string | null {
  if (!file) return null;
  return URL.createObjectURL(file);
}

export interface FileMetadata {
  name: string;
  size: number;
  type: string;
  preview: string;
}

export function getFileMetadata(file: File): FileMetadata {
  return {
    name: file.name,
    size: file.size,
    type: file.type,
    preview: URL.createObjectURL(file),
  };
}

export function formatDuration(ms: number): string {
  if (ms < 1000) return "< 1s";
  const seconds = Math.floor(ms / 1000);
  if (seconds < 60) return `${seconds}s`;
  const minutes = Math.floor(seconds / 60);
  const remainingSeconds = seconds % 60;
  return remainingSeconds > 0 ? `${minutes}m ${remainingSeconds}s` : `${minutes}m`;
}

function resolveQuality(width: number, height: number, baseQuality: number): number {
  const megapixels = (width * height) / (1024 * 1024);

  if (megapixels > 8) return Math.min(baseQuality - 0.15, 0.75);
  if (megapixels > 2) return Math.min(baseQuality - 0.05, 0.8);
  return baseQuality;
}

const getWebpSupported: () => Promise<boolean> = (() => {
  let cached: boolean | undefined;
  return async () => {
    if (cached === undefined) {
      cached = await detectWebPSupport();
    }
    return cached;
  };
})();

async function detectWebPSupport(): Promise<boolean> {
  const canvas = document.createElement("canvas");
  if (!canvas.toBlob) return false;

  return new Promise((resolve) => {
    canvas.toBlob((blob) => resolve(blob?.type === "image/webp"), "image/webp", 0.1);
  });
}

async function loadImageBitmap(file: File): Promise<ImageBitmap | null> {
  if (typeof createImageBitmap === "undefined") return null;
  try {
    return await createImageBitmap(file, { imageOrientation: "from-image" });
  } catch {
    return null;
  }
}

function loadImageElement(file: File): Promise<HTMLImageElement> {
  return new Promise((resolve, reject) => {
    const img = new Image();
    const url = URL.createObjectURL(file);
    img.addEventListener("load", () => {
      URL.revokeObjectURL(url);
      resolve(img);
    });
    img.addEventListener("error", () => {
      URL.revokeObjectURL(url);
      reject(new Error("Could not load image"));
    });
    img.src = url;
  });
}

export async function processImage(
  file: File,
  config: Partial<typeof DEFAULT_IMAGE_CONFIG> = DEFAULT_IMAGE_CONFIG,
): Promise<File> {
  const webpSupported = await getWebpSupported();

  let imageSource: ImageBitmap | HTMLImageElement;
  let width: number;
  let height: number;

  const bitmap = await loadImageBitmap(file);

  if (bitmap) {
    imageSource = bitmap;
    width = bitmap.width;
    height = bitmap.height;
  } else {
    const el = await loadImageElement(file);
    imageSource = el;
    width = el.naturalWidth;
    height = el.naturalHeight;
  }

  const maxWidth = config.maxWidth ?? DEFAULT_IMAGE_CONFIG.maxWidth;
  const maxHeight = config.maxHeight ?? DEFAULT_IMAGE_CONFIG.maxHeight;
  if (width > maxWidth || height > maxHeight) {
    const ratio = Math.min(maxWidth / width, maxHeight / height);
    width = Math.round(width * ratio);
    height = Math.round(height * ratio);
  }

  const canvas = document.createElement("canvas");
  canvas.width = width;
  canvas.height = height;

  const ctx = canvas.getContext("2d");
  if (!ctx) throw new Error("Could not get canvas context");

  ctx.imageSmoothingEnabled = true;
  ctx.imageSmoothingQuality = "high";
  ctx.drawImage(imageSource, 0, 0, width, height);

  if (bitmap) bitmap.close();

  const outputFormat = webpSupported
    ? (config.format ?? DEFAULT_IMAGE_CONFIG.format)
    : "image/jpeg";
  const ext = webpSupported ? "webp" : "jpg";

  const encodedQuality = resolveQuality(
    width,
    height,
    !webpSupported ? 0.8 : (config.quality ?? DEFAULT_IMAGE_CONFIG.quality),
  );

  const blob = await new Promise<Blob>((resolve, reject) => {
    canvas.toBlob(
      (blob) => {
        if (!blob) {
          reject(new Error("Could not convert image"));
          return;
        }
        resolve(blob);
      },
      outputFormat,
      encodedQuality,
    );
  });

  const originalName = file.name.replace(/\.[^/.]+$/, "");
  return new File([blob], `${originalName}.${ext}`, {
    type: outputFormat,
    lastModified: Date.now(),
  });
}
