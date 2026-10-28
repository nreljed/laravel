export type GalleryImageStatus = "uploading" | "processing" | "done" | "error";

interface GalleryImageBase {
  id: string;
  name: string;
  size: number;
  type: string;
  status: GalleryImageStatus;
  progress?: number;
  attachment_id?: number;
}

export interface GalleryImageUploading extends GalleryImageBase {
  status: "uploading" | "processing";
  preview: string;
}

export interface GalleryImageDone extends GalleryImageBase {
  status: "done";
  url: string;
  path: string;
  preview?: string;
}

export interface GalleryImageError extends GalleryImageBase {
  status: "error";
  error: string;
  preview?: string;
}

export type GalleryImage = GalleryImageUploading | GalleryImageDone | GalleryImageError;

export function isGalleryImageDone(image: GalleryImage): image is GalleryImageDone {
  return image.status === "done";
}

export function isGalleryImageUploading(image: GalleryImage): image is GalleryImageUploading {
  return image.status === "uploading" || image.status === "processing";
}

export function isGalleryImageError(image: GalleryImage): image is GalleryImageError {
  return image.status === "error";
}

export function getGalleryImageUrl(image: GalleryImage): string {
  if (isGalleryImageDone(image)) {
    return image.url;
  }
  if (image.preview) {
    return image.preview;
  }
  return "";
}

export function createGalleryImageFromFile(file: File, id?: string): GalleryImageUploading {
  return {
    id: id ?? crypto.randomUUID(),
    name: file.name,
    size: file.size,
    type: file.type,
    status: "uploading",
    progress: 0,
    preview: URL.createObjectURL(file),
  };
}

export interface ServerGalleryImage {
  url: string;
  alt?: string;
  caption?: string;
  order?: number;
  attachment_id?: number;
}

export function toServerFormat(image: GalleryImageDone): ServerGalleryImage {
  return {
    url: image.url,
    alt: image.name,
    order: image.progress,
    attachment_id: image.attachment_id,
  };
}

export function fromServerFormat(serverImage: ServerGalleryImage, index: number): GalleryImageDone {
  return {
    id: crypto.randomUUID(),
    name: serverImage.alt || `Image ${index + 1}`,
    size: 0,
    type: "image/*",
    status: "done",
    url: serverImage.url,
    path: serverImage.url.replace(/^\/storage\//, ""),
    preview: serverImage.url,
    attachment_id: serverImage.attachment_id,
  };
}

export function imagesToServerFormat(images: GalleryImage[]): ServerGalleryImage[] {
  return images
    .filter(isGalleryImageDone)
    .map(toServerFormat)
    .map((img, i) => ({ ...img, order: i }));
}

export function imagesFromServerFormat(serverImages: ServerGalleryImage[]): GalleryImage[] {
  return serverImages.map(fromServerFormat);
}
