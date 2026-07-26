import { useEffect } from "react";
import { useLexicalComposerContext } from "@lexical/react/LexicalComposerContext";
import {
  $createParagraphNode,
  $getNodeByKey,
  COMMAND_PRIORITY_EDITOR,
  KEY_ARROW_DOWN_COMMAND,
  KEY_ARROW_LEFT_COMMAND,
  KEY_ARROW_RIGHT_COMMAND,
  KEY_ARROW_UP_COMMAND,
  createCommand,
  type LexicalCommand,
  type NodeKey,
} from "lexical";
import {
  $insertNodeToNearestRoot,
  $onEscapeDown,
  $onEscapeUp,
  mergeRegister,
} from "@lexical/utils";
import {
  $createLayoutContainerNode,
  $isLayoutContainerNode,
  type ResponsiveMode,
  LayoutContainerNode,
} from "./LayoutContainerNode";
import { $createLayoutItemNode, $isLayoutItemNode, LayoutItemNode } from "./LayoutItemNode";

export const INSERT_LAYOUT_COMMAND: LexicalCommand<string> = createCommand<string>();

export type UpdateLayoutPayload = {
  nodeKey: NodeKey;
  template: string;
  responsive?: boolean;
};

export const UPDATE_LAYOUT_COMMAND: LexicalCommand<UpdateLayoutPayload> =
  createCommand<UpdateLayoutPayload>();

export type InsertLayoutPayload = {
  template: string;
  responsiveMode?: ResponsiveMode;
};

export const INSERT_LAYOUT_V2_COMMAND: LexicalCommand<InsertLayoutPayload> =
  createCommand<InsertLayoutPayload>();

export const TOGGLE_LAYOUT_RESPONSIVE_COMMAND: LexicalCommand<NodeKey> = createCommand<NodeKey>();

export const SET_LAYOUT_RESPONSIVE_MODE_COMMAND: LexicalCommand<{
  nodeKey: NodeKey;
  mode: ResponsiveMode;
}> = createCommand<{ nodeKey: NodeKey; mode: ResponsiveMode }>();

function getItemsCountFromTemplate(template: string): number {
  return template.trim().split(/\s+/).length;
}

function $fillLayoutItemIfEmpty(node: import("lexical").LexicalNode): void {
  if ($isLayoutItemNode(node) && node.isEmpty()) {
    node.append($createParagraphNode());
  }
}

function $removeIsolatedLayoutItem(node: import("lexical").LexicalNode): boolean {
  if (!$isLayoutItemNode(node)) return false;
  const parent = node.getParent();
  if (!$isLayoutContainerNode(parent)) {
    const children = node.getChildren();
    for (const child of children) {
      node.insertBefore(child);
    }
    node.remove();
    return true;
  }
  return false;
}

export default function ColumnsPlugin(): null {
  const [editor] = useLexicalComposerContext();

  useEffect(() => {
    if (!editor.hasNodes([LayoutContainerNode]) || !editor.hasNodes([LayoutItemNode])) {
      console.warn(
        "ColumnsPlugin: LayoutContainerNode or LayoutItemNode is not registered on the editor.",
      );
    }

    return mergeRegister(
      editor.registerCommand(
        INSERT_LAYOUT_COMMAND,
        (template) => {
          editor.update(() => {
            const container = $createLayoutContainerNode(template);
            const itemsCount = getItemsCountFromTemplate(template);

            for (let i = 0; i < itemsCount; i++) {
              container.append($createLayoutItemNode().append($createParagraphNode()));
            }

            $insertNodeToNearestRoot(container);
            container.selectStart();
          });

          return true;
        },
        COMMAND_PRIORITY_EDITOR,
      ),

      editor.registerCommand(
        UPDATE_LAYOUT_COMMAND,
        (payload) => {
          editor.update(() => {
            const containerNode = $getNodeByKey(payload.nodeKey);
            if (!$isLayoutContainerNode(containerNode)) {
              return;
            }

            const prevItems = containerNode.getChildren().filter($isLayoutItemNode);
            const prevItemsCount = prevItems.length;
            const newItemsCount = getItemsCountFromTemplate(payload.template);

            if (newItemsCount > prevItemsCount) {
              for (let i = prevItemsCount; i < newItemsCount; i++) {
                containerNode.append($createLayoutItemNode().append($createParagraphNode()));
              }
            } else if (newItemsCount < prevItemsCount) {
              for (let i = prevItemsCount - 1; i >= newItemsCount; i--) {
                prevItems[i]?.remove();
              }
            }

            containerNode.setTemplateColumns(payload.template);
            if (payload.responsive !== undefined) {
              containerNode.setResponsive(payload.responsive);
            }
          });

          return true;
        },
        COMMAND_PRIORITY_EDITOR,
      ),

      editor.registerCommand(
        INSERT_LAYOUT_V2_COMMAND,
        (payload) => {
          editor.update(() => {
            const container = $createLayoutContainerNode(
              payload.template,
              payload.responsiveMode ?? "full",
            );
            const itemsCount = getItemsCountFromTemplate(payload.template);

            for (let i = 0; i < itemsCount; i++) {
              container.append($createLayoutItemNode().append($createParagraphNode()));
            }

            $insertNodeToNearestRoot(container);
            container.selectStart();
          });

          return true;
        },
        COMMAND_PRIORITY_EDITOR,
      ),

      editor.registerCommand(
        TOGGLE_LAYOUT_RESPONSIVE_COMMAND,
        (nodeKey) => {
          editor.update(() => {
            const containerNode = $getNodeByKey(nodeKey);
            if (!$isLayoutContainerNode(containerNode)) {
              return;
            }
            const current = containerNode.getResponsiveMode();
            containerNode.setResponsiveMode(current === "none" ? "full" : "none");
          });

          return true;
        },
        COMMAND_PRIORITY_EDITOR,
      ),

      editor.registerCommand(
        SET_LAYOUT_RESPONSIVE_MODE_COMMAND,
        (payload) => {
          editor.update(() => {
            const containerNode = $getNodeByKey(payload.nodeKey);
            if (!$isLayoutContainerNode(containerNode)) {
              return;
            }
            containerNode.setResponsiveMode(payload.mode);
          });

          return true;
        },
        COMMAND_PRIORITY_EDITOR,
      ),

      // Arrow key navigation to escape out of layouts
      editor.registerCommand(
        KEY_ARROW_DOWN_COMMAND,
        (event) => $onEscapeDown($isLayoutContainerNode, event),
        COMMAND_PRIORITY_EDITOR,
      ),
      editor.registerCommand(
        KEY_ARROW_LEFT_COMMAND,
        (event) => $onEscapeUp($isLayoutContainerNode, event),
        COMMAND_PRIORITY_EDITOR,
      ),
      editor.registerCommand(
        KEY_ARROW_UP_COMMAND,
        (event) => $onEscapeUp($isLayoutContainerNode, event),
        COMMAND_PRIORITY_EDITOR,
      ),
      editor.registerCommand(
        KEY_ARROW_RIGHT_COMMAND,
        (event) => $onEscapeDown($isLayoutContainerNode, event),
        COMMAND_PRIORITY_EDITOR,
      ),

      // Structure-enforcing transforms
      editor.registerNodeTransform(LayoutItemNode, (node) => {
        const isRemoved = $removeIsolatedLayoutItem(node);
        if (!isRemoved) {
          $fillLayoutItemIfEmpty(node);
        }
      }),
      editor.registerNodeTransform(LayoutContainerNode, (node) => {
        const children = node.getChildren();
        if (!children.every($isLayoutItemNode)) {
          for (const child of children) {
            node.insertBefore(child);
          }
          node.remove();
        }
      }),
    );
  }, [editor]);

  return null;
}

export function $getLayoutContainerNodeForNode(
  node: import("lexical").LexicalNode,
): import("lexical").ElementNode | null {
  let current: import("lexical").LexicalNode | null = node;
  while (current != null) {
    if ($isLayoutContainerNode(current)) {
      return current;
    }
    current = current.getParent();
  }
  return null;
}

export function $addColumnToLayout(containerNode: LayoutContainerNode): void {
  const prevColumns = containerNode.getTemplateColumns().trim().split(/\s+/);
  const newTemplate = [...prevColumns, "1fr"].join(" ");
  containerNode.setTemplateColumns(newTemplate);
  containerNode.append($createLayoutItemNode().append($createParagraphNode()));
}

export function $removeColumnFromLayout(
  containerNode: LayoutContainerNode,
  columnNode: LayoutItemNode,
): void {
  const children = containerNode.getChildren().filter($isLayoutItemNode);
  if (children.length <= 1) {
    containerNode.remove();
    return;
  }
  const index = children.indexOf(columnNode);
  columnNode.remove();
  const prevColumns = containerNode.getTemplateColumns().trim().split(/\s+/);
  if (index !== -1 && index < prevColumns.length) {
    prevColumns.splice(index, 1);
  }
  containerNode.setTemplateColumns(prevColumns.join(" "));
}

export function $moveLayoutColumn(
  containerNode: LayoutContainerNode,
  fromIndex: number,
  toIndex: number,
): void {
  const children = containerNode.getChildren().filter($isLayoutItemNode);
  if (fromIndex < 0 || fromIndex >= children.length) return;
  const clampedTo = Math.max(0, Math.min(toIndex, children.length - 1));
  if (fromIndex === clampedTo) return;

  const moved = children[fromIndex];
  if (!moved) return;
  children.splice(fromIndex, 1);
  children.splice(clampedTo, 0, moved);

  const template = children.map(() => "1fr").join(" ");
  containerNode.setTemplateColumns(template);

  for (const child of containerNode.getChildren()) {
    child.remove();
  }
  for (const child of children) {
    containerNode.append(child);
  }
}
