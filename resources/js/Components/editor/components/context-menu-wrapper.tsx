import { useEffect, useMemo, useState } from "react";
import { useLexicalComposerContext } from "@lexical/react/LexicalComposerContext";
import {
  $getSelection,
  $isRangeSelection,
  COMMAND_PRIORITY_LOW,
  CAN_UNDO_COMMAND,
  CAN_REDO_COMMAND,
  UNDO_COMMAND,
  REDO_COMMAND,
  CUT_COMMAND,
  COPY_COMMAND,
  PASTE_COMMAND,
  $getRoot,
  type LexicalNode,
} from "lexical";
import {
  NodeContextMenuPlugin,
  NodeContextMenuOption,
  NodeContextMenuSeparator,
} from "@lexical/react/LexicalNodeContextMenuPlugin";
import { $isLinkNode, TOGGLE_LINK_COMMAND } from "@lexical/link";
import {
  Scissors,
  Copy,
  ClipboardPaste,
  Undo2,
  Redo2,
  Trash2,
  Check,
  Link2Off,
  type LucideIcon,
} from "lucide-react";
import { $isLayoutContainerNode, type LayoutContainerNode } from "../layout/LayoutContainerNode";
import { $isLayoutItemNode, type LayoutItemNode } from "../layout/LayoutItemNode";
import { $addColumnToLayout, $removeColumnFromLayout } from "../layout/ColumnsPlugin";

function $deleteSelection(): void {
  const selection = $getSelection();
  if (!$isRangeSelection(selection)) return;
  selection.removeText();
}

function $findNearestLayoutItem(node: LexicalNode): LayoutItemNode | null {
  let current: LexicalNode | null = node;
  while (current != null) {
    if ($isLayoutItemNode(current)) {
      return current;
    }
    current = current.getParent();
  }
  return null;
}

function $findLayoutContainerFromNode(node: LexicalNode): LayoutContainerNode | null {
  const layoutItem = $findNearestLayoutItem(node);
  if (layoutItem) {
    const parent = layoutItem.getParent();
    if ($isLayoutContainerNode(parent)) {
      return parent;
    }
  }
  return null;
}

function $findLayoutContext(): { container: LayoutContainerNode; item: LayoutItemNode } | null {
  const selection = $getSelection();
  if (!$isRangeSelection(selection)) return null;
  const anchor = selection.anchor.getNode();
  const item = $findNearestLayoutItem(anchor);
  if (!item) return null;
  const parent = item.getParent();
  if (!$isLayoutContainerNode(parent)) return null;
  return { container: parent, item };
}

function $isCursorOnLink(): boolean {
  const selection = $getSelection();
  if (!$isRangeSelection(selection)) return false;
  const node = selection.anchor.getNode();
  return $isLinkNode(node) || $isLinkNode(node.getParent());
}

function MenuIcon({ Icon, size = 16 }: { Icon: LucideIcon; size?: number }) {
  return <Icon size={size} className="shrink-0 opacity-70" />;
}

export function ContextMenuWrapper() {
  const [editor] = useLexicalComposerContext();
  const [canUndo, setCanUndo] = useState(false);
  const [canRedo, setCanRedo] = useState(false);

  useEffect(() => {
    const undoReg = editor.registerCommand(
      CAN_UNDO_COMMAND,
      (payload) => {
        setCanUndo(payload);
        return false;
      },
      COMMAND_PRIORITY_LOW,
    );
    const redoReg = editor.registerCommand(
      CAN_REDO_COMMAND,
      (payload) => {
        setCanRedo(payload);
        return false;
      },
      COMMAND_PRIORITY_LOW,
    );
    return () => {
      undoReg();
      redoReg();
    };
  }, [editor]);

  const items = useMemo(
    () => [
      // --- Link context (playground pattern) ---
      new NodeContextMenuOption("Remove Link", {
        icon: <MenuIcon Icon={Link2Off} />,
        $showOn: () => $isCursorOnLink(),
        $onSelect: () => editor.dispatchCommand(TOGGLE_LINK_COMMAND, null),
      }),
      new NodeContextMenuSeparator({
        $showOn: () => $isCursorOnLink(),
      }),

      // --- Clipboard (Lexical built-in commands) ---
      new NodeContextMenuOption("Cut", {
        icon: <MenuIcon Icon={Scissors} />,
        $onSelect: () => editor.dispatchCommand(CUT_COMMAND, null),
      }),
      new NodeContextMenuOption("Copy", {
        icon: <MenuIcon Icon={Copy} />,
        $onSelect: () => editor.dispatchCommand(COPY_COMMAND, null),
      }),
      new NodeContextMenuOption("Paste", {
        icon: <MenuIcon Icon={ClipboardPaste} />,
        $onSelect: async () => {
          try {
            const clipboardItems = await navigator.clipboard.read();
            const item = clipboardItems[0];
            if (!item) return;
            const data = new DataTransfer();
            for (const type of item.types) {
              const blob = await item.getType(type);
              const dataString = await blob.text();
              data.setData(type, dataString);
            }
            const event = new ClipboardEvent("paste", { clipboardData: data });
            editor.dispatchCommand(PASTE_COMMAND, event);
          } catch {
            document.execCommand("paste");
          }
        },
      }),
      new NodeContextMenuOption("Paste as Plain Text", {
        $showOn: () => {
          const selection = $getSelection();
          return $isRangeSelection(selection) || selection === null;
        },
        $onSelect: async () => {
          try {
            const text = await navigator.clipboard.readText();
            const data = new DataTransfer();
            data.setData("text/plain", text);
            const event = new ClipboardEvent("paste", { clipboardData: data });
            editor.dispatchCommand(PASTE_COMMAND, event);
          } catch {
            // Clipboard read failed
          }
        },
      }),

      // --- History ---
      new NodeContextMenuSeparator(),
      new NodeContextMenuOption("Undo", {
        icon: <MenuIcon Icon={Undo2} />,
        disabled: !canUndo,
        $onSelect: () => editor.dispatchCommand(UNDO_COMMAND, undefined),
      }),
      new NodeContextMenuOption("Redo", {
        icon: <MenuIcon Icon={Redo2} />,
        disabled: !canRedo,
        $onSelect: () => editor.dispatchCommand(REDO_COMMAND, undefined),
      }),

      // --- Edit ---
      new NodeContextMenuSeparator(),
      new NodeContextMenuOption("Delete", {
        icon: <MenuIcon Icon={Trash2} />,
        $showOn: () => {
          const selection = $getSelection();
          return $isRangeSelection(selection) && !selection.isCollapsed();
        },
        $onSelect: () => editor.update($deleteSelection),
      }),
      new NodeContextMenuOption("Select All", {
        icon: <MenuIcon Icon={Check} />,
        $onSelect: () => editor.update(() => $getRoot().select()),
      }),

      // --- Layout context ---
      new NodeContextMenuSeparator({
        $showOn: (node: LexicalNode) => !!$findLayoutContainerFromNode(node),
      }),
      new NodeContextMenuOption("Add column", {
        $showOn: (node: LexicalNode) => !!$findLayoutContainerFromNode(node),
        $onSelect: () => {
          const ctx = editor.read($findLayoutContext);
          if (ctx) {
            editor.update(() => {
              $addColumnToLayout(ctx.container);
            });
          }
        },
      }),
      new NodeContextMenuOption("Remove column", {
        $showOn: (node: LexicalNode) => {
          const container = $findLayoutContainerFromNode(node);
          return !!container && container.getChildren().filter($isLayoutItemNode).length > 1;
        },
        $onSelect: () => {
          const ctx = editor.read($findLayoutContext);
          if (ctx && ctx.container.getChildren().filter($isLayoutItemNode).length > 1) {
            editor.update(() => {
              $removeColumnFromLayout(ctx.container, ctx.item);
            });
          }
        },
      }),
      new NodeContextMenuOption("Remove layout", {
        $showOn: (node: LexicalNode) => !!$findLayoutContainerFromNode(node),
        $onSelect: () => {
          const ctx = editor.read($findLayoutContext);
          if (!ctx) return;
          editor.update(() => {
            const children = ctx.container.getChildren().filter($isLayoutItemNode);
            for (const item of children) {
              for (const child of item.getChildren()) {
                ctx.container.insertBefore(child);
              }
              item.remove();
            }
            ctx.container.remove();
          });
        },
      }),
    ],
    [editor, canUndo, canRedo],
  );

  return (
    <NodeContextMenuPlugin
      items={items}
      className="z-50 min-w-48 overflow-hidden rounded-md border bg-popover p-1 text-popover-foreground shadow-md"
      itemClassName="relative flex cursor-default items-center gap-2 rounded-sm px-2 py-1.5 text-sm outline-hidden select-none w-full text-left focus:bg-accent focus:text-accent-foreground data-[disabled]:pointer-events-none data-[disabled]:opacity-50"
      separatorClassName="-mx-1 my-1 h-px bg-border"
    />
  );
}
