import { useMemo } from "react";
import { useLexicalComposerContext } from "@lexical/react/LexicalComposerContext";
import {
  $getSelection,
  $isRangeSelection,
  DELETE_WORD_COMMAND,
  DELETE_LINE_COMMAND,
  type LexicalNode,
} from "lexical";
import {
  NodeContextMenuPlugin,
  NodeContextMenuOption,
  NodeContextMenuSeparator,
} from "@lexical/react/LexicalNodeContextMenuPlugin";
import { $isLayoutContainerNode, type LayoutContainerNode } from "../layout/LayoutContainerNode";
import { $isLayoutItemNode, type LayoutItemNode } from "../layout/LayoutItemNode";
import { $addColumnToLayout, $removeColumnFromLayout } from "../layout/ColumnsPlugin";

function $getSelectedText(): string {
  const selection = $getSelection();
  if (!$isRangeSelection(selection)) return "";
  return selection.getTextContent();
}

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

export function ContextMenuWrapper() {
  const [editor] = useLexicalComposerContext();
  const items = useMemo(
    () => [
      new NodeContextMenuOption("Cut", {
        $showOn: () => $isRangeSelection($getSelection()),
        $onSelect: async () => {
          const text = editor.read($getSelectedText);
          if (text) {
            await navigator.clipboard.writeText(text);
          }
          editor.update($deleteSelection);
        },
      }),
      new NodeContextMenuOption("Copy", {
        $showOn: () => $isRangeSelection($getSelection()),
        $onSelect: async () => {
          const text = editor.read($getSelectedText);
          if (text) {
            await navigator.clipboard.writeText(text);
          }
        },
      }),
      new NodeContextMenuSeparator(),
      new NodeContextMenuOption("Delete word", {
        $showOn: () => $isRangeSelection($getSelection()),
        $onSelect: () => {
          editor.dispatchCommand(DELETE_WORD_COMMAND, true);
        },
      }),
      new NodeContextMenuOption("Delete line", {
        $showOn: () => $isRangeSelection($getSelection()),
        $onSelect: () => {
          editor.dispatchCommand(DELETE_LINE_COMMAND, true);
        },
      }),

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
    [editor],
  );
  return (
    <NodeContextMenuPlugin
      items={items}
      className="z-50 min-w-32 overflow-hidden rounded-md bg-popover p-1 text-popover-foreground shadow-md ring-1 ring-foreground/10"
      itemClassName="relative flex cursor-default items-center gap-2 rounded-sm px-2 py-1.5 text-sm outline-hidden select-none focus:bg-accent focus:text-accent-foreground w-full text-left"
      separatorClassName="-mx-1 my-1 h-px bg-border"
    />
  );
}
