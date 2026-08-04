import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { ImageInsertDialog } from "../ImageInsertDialog";
import { INSERT_IMAGE_COMMAND } from "../ImageNode";
import { uploadImageToServer } from "../ImagePlugin";

const dispatchCommand = vi.fn();

vi.mock("@lexical/react/LexicalComposerContext", () => ({
  useLexicalComposerContext: () => [{ dispatchCommand }],
}));

vi.mock("../ImagePlugin", () => ({
  uploadImageToServer: vi.fn(),
}));

vi.mock("@/Components/ui/toast", () => ({
  toast: { add: vi.fn() },
}));

describe("ImageInsertDialog", () => {
  beforeEach(() => {
    vi.stubGlobal("route", (name: string) => ({ toString: () => `/mock/${name}` }));
    vi.mocked(uploadImageToServer).mockReset();
    dispatchCommand.mockReset();
  });

  afterEach(() => {
    vi.unstubAllGlobals();
  });

  it("inserts an image from a URL", async () => {
    const user = userEvent.setup();
    const onOpenChange = vi.fn();
    render(<ImageInsertDialog open onOpenChange={onOpenChange} />);

    await user.type(
      screen.getByPlaceholderText("https://example.com/image.jpg"),
      "https://example.com/photo.jpg",
    );
    await user.type(screen.getByLabelText("Alt text"), "A photo");

    await user.click(screen.getByRole("button", { name: "Insert" }));

    expect(dispatchCommand).toHaveBeenCalledWith(INSERT_IMAGE_COMMAND, {
      src: "https://example.com/photo.jpg",
      altText: "A photo",
    });
    expect(onOpenChange).toHaveBeenCalledWith(false);
  });

  it("uploads a file and inserts the returned URL", async () => {
    const user = userEvent.setup();
    vi.mocked(uploadImageToServer).mockResolvedValue({
      url: "/storage/posts/abc.webp",
      path: "posts/abc.webp",
    });

    render(<ImageInsertDialog open onOpenChange={vi.fn()} />);

    await user.click(screen.getByRole("tab", { name: "Upload" }));

    const fileInput = document.querySelector('input[type="file"]') as HTMLInputElement;
    expect(fileInput).not.toBeNull();
    await user.upload(fileInput, new File(["fake"], "photo.jpg", { type: "image/jpeg" }));

    await waitFor(() => {
      expect(dispatchCommand).toHaveBeenCalledWith(INSERT_IMAGE_COMMAND, {
        src: "/storage/posts/abc.webp",
        altText: "photo.jpg",
      });
    });
    expect(uploadImageToServer).toHaveBeenCalledTimes(1);
  });

  it("does not insert an empty URL", async () => {
    const user = userEvent.setup();
    render(<ImageInsertDialog open onOpenChange={vi.fn()} />);

    const insert = screen.getByRole("button", { name: "Insert" });
    expect(insert).toBeDisabled();

    await user.click(insert);

    expect(dispatchCommand).not.toHaveBeenCalled();
  });
});
