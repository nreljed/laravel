import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { YouTubeEmbedDialog } from "../YouTubeEmbedDialog";
import { YouTubeEmbedConfig } from "../YouTubeNode";

const update = vi.fn();

vi.mock("@lexical/react/LexicalComposerContext", () => ({
  useLexicalComposerContext: () => [{ update }],
}));

vi.mock("@/Components/ui/toast", () => ({
  toast: { add: vi.fn() },
}));

import { toast } from "@/Components/ui/toast";

describe("YouTubeEmbedConfig.parseUrl", () => {
  it.each([
    "https://www.youtube.com/watch?v=dQw4w9WgXcQ",
    "https://youtu.be/dQw4w9WgXcQ",
    "https://www.youtube.com/embed/dQw4w9WgXcQ",
    "https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ",
  ])("matches %s", (url) => {
    expect(YouTubeEmbedConfig.parseUrl(url)).toEqual({ id: "dQw4w9WgXcQ", url });
  });

  it("rejects non-YouTube URLs", () => {
    expect(YouTubeEmbedConfig.parseUrl("https://vimeo.com/123456789")).toBeNull();
    expect(YouTubeEmbedConfig.parseUrl("not a url")).toBeNull();
  });
});

describe("YouTubeEmbedDialog", () => {
  beforeEach(() => {
    update.mockReset();
    vi.mocked(toast.add).mockReset();
  });

  afterEach(() => {
    vi.unstubAllGlobals();
  });

  it("inserts a YouTube embed from a URL", async () => {
    const user = userEvent.setup();
    const onOpenChange = vi.fn();
    render(<YouTubeEmbedDialog open onOpenChange={onOpenChange} />);

    await user.type(
      screen.getByPlaceholderText("https://www.youtube.com/watch?v=..."),
      "https://www.youtube.com/watch?v=dQw4w9WgXcQ",
    );
    await user.click(screen.getByRole("button", { name: "Insert" }));

    expect(update).toHaveBeenCalledTimes(1);
    expect(onOpenChange).toHaveBeenCalledWith(false);
  });

  it("shows an error toast for an invalid URL", async () => {
    const user = userEvent.setup();
    render(<YouTubeEmbedDialog open onOpenChange={vi.fn()} />);

    await user.type(
      screen.getByPlaceholderText("https://www.youtube.com/watch?v=..."),
      "vimeo.com/123",
    );
    await user.click(screen.getByRole("button", { name: "Insert" }));

    expect(update).not.toHaveBeenCalled();
    expect(toast.add).toHaveBeenCalledWith(expect.objectContaining({ type: "error" }));
  });

  it("does not insert an empty URL", async () => {
    const user = userEvent.setup();
    render(<YouTubeEmbedDialog open onOpenChange={vi.fn()} />);

    const insert = screen.getByRole("button", { name: "Insert" });
    expect(insert).toBeDisabled();

    await user.click(insert);

    expect(update).not.toHaveBeenCalled();
  });
});
