import { useEffect } from "react";
import { useLexicalComposerContext } from "@lexical/react/LexicalComposerContext";
import {
  FORMAT_TEXT_COMMAND,
  INDENT_CONTENT_COMMAND,
  OUTDENT_CONTENT_COMMAND,
  $getSelection,
  $isRangeSelection,
  $isTextNode,
  $createParagraphNode,
  registerEventListener,
} from "lexical";
import {
  INSERT_ORDERED_LIST_COMMAND,
  INSERT_UNORDERED_LIST_COMMAND,
  INSERT_CHECK_LIST_COMMAND,
} from "@lexical/list";
import { $createHeadingNode, $createQuoteNode } from "@lexical/rich-text";
import { $createCodeNode } from "@lexical/code";
import { $setBlocksType } from "@lexical/selection";

export function KeyboardShortcutsPlugin() {
  const [editor] = useLexicalComposerContext();

  useEffect(() => {
    const handleKeyDown = (event: KeyboardEvent) => {
      const { ctrlKey, metaKey, shiftKey, altKey, key } = event;
      const mod = ctrlKey || metaKey;

      // --- Block Format: Ctrl+Alt+<number> ---
      if (mod && altKey && key === "0") {
        event.preventDefault();
        editor.update(() => {
          const selection = $getSelection();
          if ($isRangeSelection(selection)) {
            $setBlocksType(selection, () => $createParagraphNode());
          }
        });
        return;
      }

      if (mod && altKey && key === "1") {
        event.preventDefault();
        editor.update(() => {
          const selection = $getSelection();
          if ($isRangeSelection(selection)) {
            $setBlocksType(selection, () => $createHeadingNode("h1"));
          }
        });
        return;
      }

      if (mod && altKey && key === "2") {
        event.preventDefault();
        editor.update(() => {
          const selection = $getSelection();
          if ($isRangeSelection(selection)) {
            $setBlocksType(selection, () => $createHeadingNode("h2"));
          }
        });
        return;
      }

      if (mod && altKey && key === "3") {
        event.preventDefault();
        editor.update(() => {
          const selection = $getSelection();
          if ($isRangeSelection(selection)) {
            $setBlocksType(selection, () => $createHeadingNode("h3"));
          }
        });
        return;
      }

      // --- Lists: Ctrl+Shift+<number/letter> ---
      if (mod && shiftKey && key === "7") {
        event.preventDefault();
        editor.dispatchCommand(INSERT_ORDERED_LIST_COMMAND, undefined);
        return;
      }

      if (mod && shiftKey && key === "8") {
        event.preventDefault();
        editor.dispatchCommand(INSERT_UNORDERED_LIST_COMMAND, undefined);
        return;
      }

      if (mod && shiftKey && key === "9") {
        event.preventDefault();
        editor.dispatchCommand(INSERT_CHECK_LIST_COMMAND, undefined);
        return;
      }

      // --- Quote: Ctrl+Shift+Q ---
      if (ctrlKey && shiftKey && key === "q") {
        event.preventDefault();
        editor.update(() => {
          const selection = $getSelection();
          if ($isRangeSelection(selection)) {
            $setBlocksType(selection, () => $createQuoteNode());
          }
        });
        return;
      }

      // --- Code Block: Ctrl+Alt+C ---
      if (mod && altKey && key === "c") {
        event.preventDefault();
        editor.update(() => {
          const selection = $getSelection();
          if ($isRangeSelection(selection)) {
            if (selection.isCollapsed()) {
              $setBlocksType(selection, () => $createCodeNode());
            } else {
              const textContent = selection.getTextContent();
              const codeNode = $createCodeNode();
              selection.insertNodes([codeNode]);
              selection.insertRawText(textContent);
            }
          }
        });
        return;
      }

      // --- Text Format: Ctrl+Shift+<key> ---
      if (mod && shiftKey && key === "1") {
        event.preventDefault();
        editor.dispatchCommand(FORMAT_TEXT_COMMAND, "lowercase");
        return;
      }

      if (mod && shiftKey && key === "2") {
        event.preventDefault();
        editor.dispatchCommand(FORMAT_TEXT_COMMAND, "uppercase");
        return;
      }

      if (mod && shiftKey && key === "3") {
        event.preventDefault();
        editor.dispatchCommand(FORMAT_TEXT_COMMAND, "capitalize");
        return;
      }

      if (mod && shiftKey && key === "x") {
        event.preventDefault();
        editor.dispatchCommand(FORMAT_TEXT_COMMAND, "strikethrough");
        return;
      }

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
