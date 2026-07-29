import { useCallback, useEffect, useState } from "react";
import { useLexicalComposerContext } from "@lexical/react/LexicalComposerContext";
import { $getRoot, $isElementNode, type LexicalNode } from "lexical";
import { $isHeadingNode, type HeadingTagType } from "@lexical/rich-text";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/Components/ui/select";
import { List } from "lucide-react";

interface HeadingEntry {
  key: string;
  tag: HeadingTagType;
  text: string;
  index: number;
}

function $getHeadings(): HeadingEntry[] {
  const headings: HeadingEntry[] = [];
  const root = $getRoot();
  const children = root.getChildren();
  let index = 0;

  const collect = (node: LexicalNode) => {
    if ($isHeadingNode(node)) {
      headings.push({
        key: node.getKey(),
        tag: node.getTag(),
        text: node.getTextContent(),
        index,
      });
      index++;
    } else if ($isElementNode(node)) {
      for (const child of node.getChildren()) {
        collect(child);
      }
    }
  };

  for (const child of children) {
    collect(child);
  }

  return headings;
}

const HEADING_INDENT: Record<HeadingTagType, string> = {
  h1: "pl-0",
  h2: "pl-3",
  h3: "pl-6",
  h4: "pl-9",
  h5: "pl-12",
  h6: "pl-15",
};

const HEADING_SIZE: Record<HeadingTagType, string> = {
  h1: "font-bold text-base",
  h2: "font-semibold text-sm",
  h3: "font-medium text-sm",
  h4: "text-sm",
  h5: "text-xs",
  h6: "text-xs",
};

export function TableOfContentsPlugin() {
  const [editor] = useLexicalComposerContext();
  const [headings, setHeadings] = useState<HeadingEntry[]>([]);

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

  const scrollToHeading = useCallback(
    (value: string | null) => {
      if (!value) return;
      editor.read(() => {
        const node = editor.getElementByKey(value);
        if (node) {
          node.scrollIntoView({ behavior: "smooth", block: "center" });
          node.focus({ preventScroll: true });
        }
      });
    },
    [editor],
  );

  if (headings.length === 0) return null;

  return (
    <Select onValueChange={scrollToHeading}>
      <SelectTrigger className="h-8 w-full max-w-[200px]">
        <List className="mr-2 h-4 w-4 shrink-0" />
        <SelectValue placeholder="Table of Contents" />
      </SelectTrigger>
      <SelectContent>
        {headings.map((h) => (
          <SelectItem key={h.key} value={h.key} className={HEADING_INDENT[h.tag]}>
            <span className={HEADING_SIZE[h.tag]}>{h.text || h.tag.toUpperCase()}</span>
          </SelectItem>
        ))}
      </SelectContent>
    </Select>
  );
}
