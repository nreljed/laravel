import { useEffect, useState } from "react";
import { useLexicalComposerContext } from "@lexical/react/LexicalComposerContext";
import { $getNodeByKey, type NodeKey } from "lexical";
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "../../ui/dialog";
import { Button } from "../../ui/button";
import { Input } from "../../ui/input";
import { Label } from "../../ui/label";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "../../ui/select";
import { Switch } from "../../ui/switch";
import { TOC_DEPTH_VALUES, type TableOfContentsOptions } from "./TableOfContentsNode";
import { $isTableOfContentsNode } from "./TableOfContentsNode";

interface TableOfContentsSettingsDialogProps {
  open: boolean;
  onOpenChange: (_open: boolean) => void;
  nodeKey: NodeKey | null;
  onSaved: (_options: TableOfContentsOptions) => void;
}

function OptionRow({
  label,
  description,
  checked,
  onCheckedChange,
}: {
  label: string;
  description: string;
  checked: boolean;
  onCheckedChange: (_checked: boolean) => void;
}) {
  return (
    <div className="flex items-center justify-between gap-3">
      <div className="min-w-0">
        <Label className="text-foreground">{label}</Label>
        <p className="text-xs leading-tight text-muted-foreground">{description}</p>
      </div>
      <Switch checked={checked} onCheckedChange={onCheckedChange} size="sm" />
    </div>
  );
}

/**
 * Per-instance settings dialog for a Table of Contents block.
 *
 * Mirrors the image block's edit dialog: reads the current options from the
 * Lexical node when opened, keeps edits in local state, and only writes them
 * back to the node on Save (Cancel discards). Keeps the block preview clean —
 * no inline form inside the document.
 */
export function TableOfContentsSettingsDialog({
  open,
  onOpenChange,
  nodeKey,
  onSaved,
}: TableOfContentsSettingsDialogProps) {
  const [editor] = useLexicalComposerContext();
  const [options, setOptions] = useState<TableOfContentsOptions | null>(null);

  useEffect(() => {
    if (!open || nodeKey == null) return;
    editor.read(() => {
      const node = $getNodeByKey(nodeKey);
      if ($isTableOfContentsNode(node)) {
        setOptions(node.getOptions());
      }
    });
  }, [open, nodeKey, editor]);

  const patch = (partial: Partial<TableOfContentsOptions>) => {
    setOptions((current) => (current ? { ...current, ...partial } : current));
  };

  const handleSave = () => {
    if (nodeKey == null || options == null) return;
    editor.update(() => {
      const node = $getNodeByKey(nodeKey);
      if ($isTableOfContentsNode(node)) {
        node.setOptions(options);
      }
    });
    onSaved(options);
    onOpenChange(false);
  };

  const handleCancel = () => {
    onOpenChange(false);
  };

  return (
    <Dialog
      open={open}
      onOpenChange={(isOpen) => {
        if (!isOpen) handleCancel();
      }}
    >
      <DialogContent className="sm:max-w-md">
        <DialogHeader>
          <DialogTitle>Table of contents options</DialogTitle>
        </DialogHeader>

        {options && (
          <div className="space-y-4 py-2">
            <div className="space-y-2">
              <Label htmlFor="toc-title-input">Title</Label>
              <Input
                id="toc-title-input"
                type="text"
                placeholder="Table of Contents"
                value={options.title}
                onChange={(e) => patch({ title: e.target.value })}
              />
            </div>

            <div className="flex items-center justify-between gap-3">
              <div className="min-w-0">
                <Label className="text-foreground">Max depth</Label>
                <p className="text-xs leading-tight text-muted-foreground">
                  Deepest heading level to include
                </p>
              </div>
              <Select
                value={String(options.depth)}
                onValueChange={(value) => {
                  const depth = Number(value ?? "");
                  if (TOC_DEPTH_VALUES.includes(depth as (typeof TOC_DEPTH_VALUES)[number])) {
                    patch({ depth });
                  }
                }}
              >
                <SelectTrigger size="sm" className="min-w-28">
                  <SelectValue>
                    <span className="text-foreground">
                      {options.depth === 6 ? "All (H6)" : `H${options.depth}`}
                    </span>
                  </SelectValue>
                </SelectTrigger>
                <SelectContent align="end">
                  {TOC_DEPTH_VALUES.map((depth) => (
                    <SelectItem key={depth} value={String(depth)}>
                      <span className="text-foreground">
                        {depth === 6 ? "All levels (H2–H6)" : `Down to H${depth}`}
                      </span>
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>

            <OptionRow
              label="Collapsed by default"
              description="Readers start with the outline collapsed"
              checked={options.collapsed}
              onCheckedChange={(checked) => patch({ collapsed: checked })}
            />
            <OptionRow
              label="Show numbers"
              description="Number each heading hierarchically"
              checked={options.numbered}
              onCheckedChange={(checked) => patch({ numbered: checked })}
            />
            <OptionRow
              label="Smooth scroll"
              description="Animate jumps to headings"
              checked={options.smooth}
              onCheckedChange={(checked) => patch({ smooth: checked })}
            />
            <OptionRow
              label="Highlight active section"
              description="Scroll-spy highlight while reading"
              checked={options.spy}
              onCheckedChange={(checked) => patch({ spy: checked })}
            />

            <p className="pt-1 text-xs text-muted-foreground">
              Options apply to this block and render on the frontend when you save.
            </p>
          </div>
        )}

        <DialogFooter>
          <Button variant="outline" onClick={handleCancel}>
            Cancel
          </Button>
          <Button onClick={handleSave} disabled={options == null}>
            Save
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}