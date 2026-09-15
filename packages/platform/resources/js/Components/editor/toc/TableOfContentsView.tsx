import { useCallback, useEffect, useMemo, useState } from "react";
import { useLexicalComposerContext } from "@lexical/react/LexicalComposerContext";
import { ChevronDown, ChevronRight, ListTree, Settings2 } from "lucide-react";
import { Button } from "../../ui/button";
import { Tooltip, TooltipContent, TooltipTrigger } from "../../ui/tooltip";
import { cn } from "../../../lib/utils";
import {
  $getHeadings,
  buildTocTree,
  HEADING_INDENT,
  HEADING_SIZE,
  type HeadingEntry,
  type TocTreeItem,
} from "./headings";
import { type TableOfContentsOptions } from "./TableOfContentsNode";
import { TableOfContentsSettingsDialog } from "./TableOfContentsSettingsDialog";

interface TableOfContentsViewProps {
  nodeKey: string;
  options: TableOfContentsOptions;
}

function TocListNode({
  items,
  numbered,
  scrollToHeading,
}: {
  items: TocTreeItem[];
  numbered: boolean;
  scrollToHeading: (_heading: TocTreeItem) => void;
}) {
  if (items.length === 0) {
    return null;
  }

  return (
    <ul className="space-y-0.5">
      {items.map((item) => (
        <li key={item.key}>
          <div className="flex items-start gap-1.5">
            {numbered && (
              <span className="mt-0.5 w-4 shrink-0 select-none text-right text-xs leading-relaxed text-muted-foreground tabular-nums">
                {item.number}
              </span>
            )}
            <button
              type="button"
              onClick={() => scrollToHeading(item)}
              className={cn(
                "text-left hover:underline",
                HEADING_INDENT[item.tag],
                HEADING_SIZE[item.tag],
                "text-foreground",
              )}
            >
              {item.text || item.tag.toUpperCase()}
            </button>
          </div>
          <TocListNode
            items={item.children}
            numbered={numbered}
            scrollToHeading={scrollToHeading}
          />
        </li>
      ))}
    </ul>
  );
}

/**
 * Live in-editor preview for the Table of Contents block node.
 *
 * Renders the heading outline derived from the document (h1-h6 in document
 * order), mirroring the nested outline the save-time content builder emits for
 * the public renderer (including the heading nesting and max-depth filtering).
 * Clicking an entry scrolls the corresponding heading into view inside the
 * editor canvas. The collapsible header previews the native
 * `details`/`summary` disclosure readers get, and the gear opens the
 * per-instance options dialog.
 */
export function TableOfContentsView({
  nodeKey,
  options: initialOptions,
}: TableOfContentsViewProps) {
  const [editor] = useLexicalComposerContext();
  const [headings, setHeadings] = useState<HeadingEntry[]>([]);
  const [options, setOptions] = useState<TableOfContentsOptions>(initialOptions);
  const [open, setOpen] = useState(!initialOptions.collapsed);
  const [settingsOpen, setSettingsOpen] = useState(false);

  useEffect(() => {
    const unregister = editor.registerUpdateListener(() => {
      editor.read(() => {
        setHeadings($getHeadings());
      });
    });
    editor.read(() => {
      setHeadings($getHeadings());
    });
    return unregister;
  }, [editor]);

  const tree = useMemo(() => buildTocTree(headings, options.depth), [headings, options.depth]);

  const handleOptionsSaved = useCallback((next: TableOfContentsOptions) => {
    setOptions(next);
    setOpen(!next.collapsed);
  }, []);

  const scrollToHeading = useCallback(
    (heading: TocTreeItem) => {
      editor.read(() => {
        const node = editor.getElementByKey(heading.key);
        if (node) {
          node.scrollIntoView({ behavior: "smooth", block: "center" });
        }
      });
    },
    [editor],
  );

  return (
    <div className="my-3 rounded-md border bg-background text-foreground">
      <div className="flex items-center gap-2 px-3 py-2.5">
        <button
          type="button"
          onClick={() => setOpen((value) => !value)}
          aria-label={open ? "Collapse table of contents" : "Expand table of contents"}
          aria-expanded={open}
          className="flex h-5 w-5 shrink-0 items-center justify-center rounded text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
        >
          {open ? <ChevronDown className="h-4 w-4" /> : <ChevronRight className="h-4 w-4" />}
        </button>
        <ListTree className="h-4 w-4 shrink-0 text-muted-foreground" />
        <span className="truncate text-sm font-semibold text-foreground">
          {options.title || "Table of Contents"}
        </span>
        <span
          className="hidden shrink-0 text-xs font-normal text-muted-foreground sm:inline"
          aria-hidden="true"
        >
          (auto-generated on save)
        </span>
        <Tooltip>
          <TooltipTrigger
            render={
              <Button
                variant="ghost"
                size="icon-sm"
                aria-label="Table of contents options"
                onClick={() => setSettingsOpen(true)}
              />
            }
          >
            <Settings2 className="h-4 w-4 text-muted-foreground" />
          </TooltipTrigger>
          <TooltipContent>Table of contents options</TooltipContent>
        </Tooltip>
      </div>

      {open && (
        <div className="px-3 pb-3">
          {tree.length === 0 ? (
            <p className="text-xs text-muted-foreground">
              Add a heading to populate this table of contents.
            </p>
          ) : (
            <div className="text-sm leading-relaxed">
              <TocListNode
                items={tree}
                numbered={options.numbered}
                scrollToHeading={scrollToHeading}
              />
            </div>
          )}
        </div>
      )}

      <TableOfContentsSettingsDialog
        open={settingsOpen}
        onOpenChange={setSettingsOpen}
        nodeKey={nodeKey}
        onSaved={handleOptionsSaved}
      />
    </div>
  );
}