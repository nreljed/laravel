import { useEffect } from "react";
import { useLexicalComposerContext } from "@lexical/react/LexicalComposerContext";
import {
  FORMAT_TEXT_COMMAND,
  INDENT_CONTENT_COMMAND,
  OUTDENT_CONTENT_COMMAND,
  $getSelection,
  $isRangeSelection,
  $isTextNode,
  registerEventListener,
} from "lexical";

export function KeyboardShortcutsPlugin() {
  const [editor] = useLexicalComposerContext();

  useEffect(() => {
    const handleKeyDown = (event: KeyboardEvent) => {
      const { ctrlKey, metaKey, shiftKey, key } = event;
      const mod = ctrlKey || metaKey;

      if (mod && shiftKey && key === "-") {
        event.preventDefault();
        editor.dispatchCommand(FORMAT_TEXT_COMMAND, "subscript");
        return;
      }

      if (mod && shiftKey && key === "=") {
        event.preventDefault();
        editor.dispatchCommand(FORMAT_TEXT_COMMAND, "superscript");
        return;
      }

      if (mod && key === "\\") {
        event.preventDefault();
        editor.update(() => {
          const selection = $getSelection();
          if (!$isRangeSelection(selection)) return;
          const nodes = selection.getNodes();
          for (const node of nodes) {
            if (!$isTextNode(node)) {
              continue;
            }

            node.setFormat(0);
            node.setStyle("");
          }
        });
        return;
      }

      if (!mod && !shiftKey && key === "Tab") {
        event.preventDefault();
        editor.dispatchCommand(INDENT_CONTENT_COMMAND, undefined);
        return;
      }

      if (!mod && shiftKey && key === "Tab") {
        event.preventDefault();
        editor.dispatchCommand(OUTDENT_CONTENT_COMMAND, undefined);
        return;
      }
    };

    return registerEventListener(document, "keydown", handleKeyDown);
  }, [editor]);

  return null;
}
