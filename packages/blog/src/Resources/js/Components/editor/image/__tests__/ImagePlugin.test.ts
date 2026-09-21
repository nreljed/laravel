import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { deleteImageFromServer, uploadImageToServer } from "../ImagePlugin";

describe("uploadImageToServer", () => {
  const fetchMock = vi.fn();

  beforeEach(() => {
    vi.stubGlobal("route", (name: string) => ({ toString: () => `/mock/${name}` }));
    vi.stubGlobal("fetch", fetchMock);
    document.head.innerHTML = '<meta name="csrf-token" content="csrf-token-123">';
  });

  afterEach(() => {
    vi.unstubAllGlobals();
    fetchMock.mockReset();
  });

  it("POSTs the file to the upload route with CSRF headers", async () => {
    fetchMock.mockResolvedValue({
      ok: true,
      json: async () => ({ url: "/storage/posts/abc.webp", path: "posts/abc.webp" }),
    });

    const result = await uploadImageToServer(
      new File(["data"], "photo.jpg", { type: "image/jpeg" }),
    );

    expect(fetchMock).toHaveBeenCalledTimes(1);
    const [url, init] = fetchMock.mock.calls[0]!;
    expect(String(url)).toBe("/mock/dashboard.posts.upload-image");
    expect(init.method).toBe("POST");
    expect(init.headers["X-Requested-With"]).toBe("XMLHttpRequest");
    expect(init.headers["X-CSRF-TOKEN"]).toBe("csrf-token-123");
    expect(init.body).toBeInstanceOf(FormData);
    expect(result).toEqual({ url: "/storage/posts/abc.webp", path: "posts/abc.webp" });
  });

  it("throws when the upload fails", async () => {
    fetchMock.mockResolvedValue({ ok: false });

    await expect(
      uploadImageToServer(new File(["data"], "photo.jpg", { type: "image/jpeg" })),
    ).rejects.toThrow("Upload failed");
  });
});

describe("deleteImageFromServer", () => {
  afterEach(() => {
    vi.unstubAllGlobals();
  });

  it("skips URLs that are not local storage files", async () => {
    const fetchMock = vi.fn();
    vi.stubGlobal("fetch", fetchMock);

    await deleteImageFromServer("https://example.com/photo.jpg");

    expect(fetchMock).not.toHaveBeenCalled();
  });

  it("DELETEs the storage path with the right body", async () => {
    const fetchMock = vi.fn().mockResolvedValue({ ok: true });
    vi.stubGlobal("route", (name: string) => ({ toString: () => `/mock/${name}` }));
    vi.stubGlobal("fetch", fetchMock);
    document.head.innerHTML = '<meta name="csrf-token" content="csrf-token-123">';

    await deleteImageFromServer("/storage/posts/abc.webp");

    const [url, init] = fetchMock.mock.calls[0]!;
    expect(String(url)).toBe("/mock/dashboard.posts.delete-image");
    expect(init.method).toBe("DELETE");
    expect(JSON.parse(init.body)).toEqual({ path: "posts/abc.webp" });
  });
});
