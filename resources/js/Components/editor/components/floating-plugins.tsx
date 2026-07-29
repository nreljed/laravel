import { useState, type RefObject } from "react";
import { type NodeKey } from "lexical";
import { FloatingLinkEditor, FloatingTextFormatPlugin } from "../floating";
import {
  TableActionMenuPlugin,
  TableCellResizerPlugin,
  TableHoverActionsPlugin,
  TableScrollShadowPlugin,
} from "../table";
import { ImageToolbarPlugin, EditImageDialog } from "../image";

export function FloatingPlugins({
  scrollContainerRef,
}: {
  scrollContainerRef: RefObject<HTMLDivElement | null>;
}) {
  const [editImageOpen, setEditImageOpen] = useState(false);
  const [editImageKey, setEditImageKey] = useState<NodeKey | null>(null);

  const handleEditImage = (nodeKey: NodeKey) => {
    setEditImageKey(nodeKey);
    setEditImageOpen(true);
  };

  return (
    <>
      <FloatingLinkEditor />
      <FloatingTextFormatPlugin />
      <TableActionMenuPlugin scrollContainerRef={scrollContainerRef} />
      <TableCellResizerPlugin scrollContainerRef={scrollContainerRef} />
      <TableHoverActionsPlugin scrollContainerRef={scrollContainerRef} />
      <TableScrollShadowPlugin />
      <ImageToolbarPlugin onEdit={handleEditImage} />
      <EditImageDialog
        open={editImageOpen}
        onOpenChange={setEditImageOpen}
        nodeKey={editImageKey}
      />
    </>
  );
}
