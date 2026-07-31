import { useEffect, useCallback } from "react";
import { useLexicalComposerContext } from "@lexical/react/LexicalComposerContext";
import {
  $insertNodes,
  COMMAND_PRIORITY_LOW,
  PASTE_COMMAND,
  DROP_COMMAND,
  DRAGSTART_COMMAND,
} from "lexical";
import { toast } from "@/Components/ui/toast";
import { $createImageNode, INSERT_IMAGE_COMMAND, ImageNode, type ImagePayload } from "./ImageNode";

export async function uploadImageToServer(file: File): Promise<{ url: string; path: string }> {
  const formData = new FormData();
  formData.append("file", file);
  const csrfToken =
    document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") || "";
  const res = await fetch(route("dashboard.posts.upload-image").toString(), {
    method: "POST",
    body: formData,
    headers: { "X-Requested-With": "XMLHttpRequest", "X-CSRF-TOKEN": csrfToken },
  });
  if (!res.ok) throw new Error("Upload failed");
  const data = await res.json();
  return data;
}

export async function deleteImageFromServer(src: string): Promise<void> {
  if (!src.startsWith("/storage/")) return;
  const path = src.replace(/^\/storage\//, "");
  const csrfToken =
    document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") || "";
  const res = await fetch(route("dashboard.posts.delete-image").toString(), {
    method: "DELETE",
    body: JSON.stringify({ path }),
    headers: {
      "Content-Type": "application/json",
      "X-Requested-With": "XMLHttpRequest",
      "X-CSRF-TOKEN": csrfToken,
    },
  });
  if (!res.ok) throw new Error("Delete failed");
}

function extractImageFiles(dataTransfer: DataTransfer): File[] {
  const files: File[] = [];
  for (let i = 0; i < dataTransfer.items.length; i++) {
    const item = dataTransfer.items[i];
    if (item && item.kind === "file") {
      const file = item.getAsFile();
      if (file && file.type.startsWith("image/")) {
        files.push(file);
      }
    }
  }
  return files;
}

export function ImagePlugin(): null {
  const [editor] = useLexicalComposerContext();

  useEffect(() => {
    if (!editor.hasNodes([ImageNode])) {
      console.warn("ImagePlugin: ImageNode is not registered on the editor.");
    }
  }, [editor]);

  const insertImage = useCallback(
    (payload: ImagePayload) => {
      editor.update(() => {
        const node = $createImageNode(payload);
        $insertNodes([node]);
      });
    },
    [editor],
  );

  const handleUpload = useCallback(
    async (files: File[]) => {
      for (const file of files) {
        try {
          const { url } = await uploadImageToServer(file);
          insertImage({ src: url, altText: file.name });
        } catch {
          toast.add({ title: `Failed to upload ${file.name}`, type: "error" });
        }
      }
    },
    [insertImage],
  );

  useEffect(() => {
    return editor.registerCommand<ImagePayload>(
      INSERT_IMAGE_COMMAND,
      (payload) => {
        insertImage(payload);
        return true;
      },
      COMMAND_PRIORITY_LOW,
    );
  }, [editor, insertImage]);

  useEffect(() => {
    return editor.registerCommand(
      PASTE_COMMAND,
      (event) => {
        if (!(event instanceof ClipboardEvent)) return false;
        const files = event.clipboardData ? extractImageFiles(event.clipboardData) : [];
        if (files.length > 0) {
          event.preventDefault();
          handleUpload(files);
          return true;
        }
        return false;
      },
      COMMAND_PRIORITY_LOW,
    );
  }, [editor, handleUpload]);

  useEffect(() => {
    return editor.registerCommand<DragEvent>(
      DROP_COMMAND,
      (event) => {
        const files = event.dataTransfer ? extractImageFiles(event.dataTransfer) : [];
        if (files.length > 0) {
          event.preventDefault();
          handleUpload(files);
          return true;
        }
        return false;
      },
      COMMAND_PRIORITY_LOW,
    );
  }, [editor, handleUpload]);

  useEffect(() => {
    return editor.registerCommand<DragEvent>(
      DRAGSTART_COMMAND,
      (event) => {
        const files = event.dataTransfer ? extractImageFiles(event.dataTransfer) : [];
        if (files.length > 0) {
          event.preventDefault();
          return true;
        }
        return false;
      },
      COMMAND_PRIORITY_LOW,
    );
  }, [editor]);

  return null;
}
