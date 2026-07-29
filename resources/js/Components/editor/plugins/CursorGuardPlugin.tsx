import { useLexicalComposerContext } from "@lexical/react/LexicalComposerContext";
import {
  $getTableCellNodeFromLexicalNode,
  $getTableNodeFromLexicalNodeOrThrow,
  $isTableNode,
} from "@lexical/table";
import {
  $getSelection,
  $isElementNode,
  $isRangeSelection,
  COMMAND_PRIORITY_HIGH,
  INSERT_PARAGRAPH_COMMAND,
  SELECTION_CHANGE_COMMAND,
} from "lexical";
import { useEffect, useRef } from "react";

export default function CursorGuardPlugin() {
  const [editor] = useLexicalComposerContext();
  const textInsertedRef = useRef(false);

  useEffect(() => {
    const unregisterParagraph = editor.registerCommand(
      INSERT_PARAGRAPH_COMMAND,
      () => {
        const selection = $getSelection();
        if ($isRangeSelection(selection) && selection.isCollapsed()) {
          const anchorCell = $getTableCellNodeFromLexicalNode(selection.anchor.getNode());
          if (!anchorCell) {
            textInsertedRef.current = true;
          }
        }
        return false;
      },
      COMMAND_PRIORITY_HIGH,
    );

    const unregisterSelectionChange = editor.registerCommand(
      SELECTION_CHANGE_COMMAND,
      () => {
        if (!textInsertedRef.current) {
          return false;
        }
        textInsertedRef.current = false;

        const selection = $getSelection();
        if (!$isRangeSelection(selection) || !selection.isCollapsed()) {
          return false;
        }

        const anchorCell = $getTableCellNodeFromLexicalNode(selection.anchor.getNode());
        if (!anchorCell) {
          return false;
        }

        try {
          const tableNode = $getTableNodeFromLexicalNodeOrThrow(anchorCell);
          const prevSibling = tableNode.getPreviousSibling();
          if (prevSibling && $isElementNode(prevSibling) && !$isTableNode(prevSibling)) {
            prevSibling.selectEnd();
            return true;
          }
        } catch {
          // Table node not found
        }

        return false;
      },
      COMMAND_PRIORITY_HIGH,
    );

    return () => {
      unregisterParagraph();
      unregisterSelectionChange();
    };
  }, [editor]);

  return null;
}
