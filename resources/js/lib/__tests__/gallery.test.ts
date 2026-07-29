import { describe, it, expect } from "vitest";
import {
  isGalleryImageDone,
  isGalleryImageUploading,
  isGalleryImageError,
  getGalleryImageUrl,
  createGalleryImageFromFile,
  imagesToServerFormat,
  imagesFromServerFormat,
  type GalleryImage,
  type GalleryImageDone,
  type GalleryImageUploading,
  type GalleryImageError,
  type ServerGalleryImage,
} from "@/types/gallery";

describe("GalleryImage type guards", () => {
  const doneImage: GalleryImageDone = {
    id: "1",
    name: "test.jpg",
    size: 1024,
    type: "image/jpeg",
    status: "done",
    url: "/storage/test-posts/test.webp",
    path: "test-posts/test.webp",
  };

  const uploadingImage: GalleryImageUploading = {
    id: "2",
    name: "upload.jpg",
    size: 2048,
    type: "image/jpeg",
    status: "uploading",
    progress: 50,
    preview: "blob:http://localhost/preview",
  };

  const errorImage: GalleryImageError = {
    id: "3",
    name: "error.jpg",
    size: 512,
    type: "image/jpeg",
    status: "error",
    error: "Upload failed",
  };

  it("identifies done images", () => {
    expect(isGalleryImageDone(doneImage)).toBe(true);
    expect(isGalleryImageDone(uploadingImage)).toBe(false);
    expect(isGalleryImageDone(errorImage)).toBe(false);
  });

  it("identifies uploading images", () => {
    expect(isGalleryImageUploading(doneImage)).toBe(false);
    expect(isGalleryImageUploading(uploadingImage)).toBe(true);
    expect(isGalleryImageUploading(errorImage)).toBe(false);
  });

  it("identifies error images", () => {
    expect(isGalleryImageError(doneImage)).toBe(false);
    expect(isGalleryImageError(uploadingImage)).toBe(false);
    expect(isGalleryImageError(errorImage)).toBe(true);
  });

  it("gets image url from done image", () => {
    expect(getGalleryImageUrl(doneImage)).toBe("/storage/test-posts/test.webp");
  });

  it("gets image url from uploading image", () => {
    expect(getGalleryImageUrl(uploadingImage)).toBe("blob:http://localhost/preview");
  });

  it("gets empty string for error image without preview", () => {
    expect(getGalleryImageUrl(errorImage)).toBe("");
  });
});

describe("createGalleryImageFromFile", () => {
  it("creates uploading image from file", () => {
    const file = new File(["test"], "photo.jpg", { type: "image/jpeg" });
    const image = createGalleryImageFromFile(file);

    expect(image.status).toBe("uploading");
    expect(image.name).toBe("photo.jpg");
    expect(image.size).toBe(4);
    expect(image.type).toBe("image/jpeg");
    expect(image.progress).toBe(0);
    expect(image.preview).toContain("blob:");
  });

  it("uses provided id", () => {
    const file = new File(["test"], "photo.jpg", { type: "image/jpeg" });
    const image = createGalleryImageFromFile(file, "custom-id");

    expect(image.id).toBe("custom-id");
  });

  it("generates random id when not provided", () => {
    const file = new File(["test"], "photo.jpg", { type: "image/jpeg" });
    const image = createGalleryImageFromFile(file);

    expect(image.id).toMatch(/^[0-9a-f-]{36}$/);
  });
});

describe("Server format conversion", () => {
  const serverImages: ServerGalleryImage[] = [
    { url: "/storage/test-posts/img1.webp", alt: "Image 1", order: 0 },
    { url: "/storage/test-posts/img2.webp", alt: "Image 2", order: 1 },
  ];

  const doneImages: GalleryImageDone[] = [
    {
      id: "1",
      name: "Image 1",
      size: 1024,
      type: "image/*",
      status: "done",
      url: "/storage/test-posts/img1.webp",
      path: "test-posts/img1.webp",
    },
    {
      id: "2",
      name: "Image 2",
      size: 2048,
      type: "image/*",
      status: "done",
      url: "/storage/test-posts/img2.webp",
      path: "test-posts/img2.webp",
    },
  ];

  it("converts server format to internal format", () => {
    const internal = imagesFromServerFormat(serverImages);

    expect(internal).toHaveLength(2);
    const first = internal[0];
    expect(first).toBeDefined();
    expect(isGalleryImageDone(first!)).toBe(true);
    if (isGalleryImageDone(first!)) {
      expect(first!.url).toBe("/storage/test-posts/img1.webp");
      expect(first!.name).toBe("Image 1");
    }
  });

  it("converts internal format to server format", () => {
    const server = imagesToServerFormat(doneImages);

    expect(server).toHaveLength(2);
    expect(server[0]).toBeDefined();
    expect(server[0]!.url).toBe("/storage/test-posts/img1.webp");
    expect(server[0]!.order).toBe(0);
    expect(server[1]!.order).toBe(1);
  });

  it("filters out non-done images when converting to server format", () => {
    const first = doneImages[0];
    expect(first).toBeDefined();
    const mixed: GalleryImage[] = [
      first!,
      {
        id: "3",
        name: "uploading.jpg",
        size: 512,
        type: "image/jpeg",
        status: "uploading" as const,
        progress: 50,
        preview: "blob:preview",
      },
    ];

    const server = imagesToServerFormat(mixed);
    expect(server).toHaveLength(1);
  });

  it("handles empty arrays", () => {
    expect(imagesFromServerFormat([])).toEqual([]);
    expect(imagesToServerFormat([])).toEqual([]);
  });
});
