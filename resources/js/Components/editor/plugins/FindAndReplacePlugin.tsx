import { useCallback, useEffect, useRef, useState } from "react";
import { useLexicalComposerContext } from "@lexical/react/LexicalComposerContext";
import {
  $getRoot,
  $getNodeByKey,
  $isTextNode,
  $setSelection,
  $createRangeSelection,
  registerEventListener,
} from "lexical";
import { Button } from "@/Components/ui/button";
import { Input } from "@/Components/ui/input";
import {
  Dialog,
  DialogClose,
  DialogContent,
  DialogHeader,
  DialogTitle,
} from "@/Components/ui/dialog";
import { ArrowDown, ArrowUp, Replace, ReplaceAll, X } from "lucide-react";

interface FindMatch {
  key: string;
  start: number;
  end: number;
}

function $findAllMatches(searchText: string): FindMatch[] {
  if (!searchText) return [];
  const matches: FindMatch[] = [];
  const root = $getRoot();
  const lowerSearch = searchText.toLowerCase();

  const collectTextNodes = (
    node: ReturnType<typeof $getRoot>,
  ): Array<{ node: ReturnType<typeof $getRoot>; text: string; offset: number }> => {
    const result: Array<{ node: ReturnType<typeof $getRoot>; text: string; offset: number }> = [];
    let globalOffset = 0;
    const visit = (n: ReturnType<typeof $getRoot>) => {
      for (const child of n.getChildren()) {
        if ($isTextNode(child)) {
          const text = child.getTextContent();
          result.push({
            node: child as unknown as ReturnType<typeof $getRoot>,
            text,
            offset: globalOffset,
          });
          globalOffset += text.length;
        } else if ("getChildren" in child) {
          visit(child as unknown as ReturnType<typeof $getRoot>);
        }
      }
    };
    visit(node);
    return result;
  };

  const textNodes = collectTextNodes(root);
  const fullText = textNodes.map((t) => t.text).join("");

  const lowerFullText = fullText.toLowerCase();
  let searchIdx = 0;
  while (searchIdx < lowerFullText.length) {
    const pos = lowerFullText.indexOf(lowerSearch, searchIdx);
    if (pos === -1) break;
    const end = pos + searchText.length;

    let charCount = 0;
    for (const entry of textNodes) {
      const nodeStart = charCount;
      const nodeEnd = charCount + entry.text.length;
      if (nodeEnd > pos && nodeStart < end) {
        const matchStartInNode = Math.max(pos - nodeStart, 0);
        const matchEndInNode = Math.min(end - nodeStart, entry.text.length);
        matches.push({
          key: (entry.node as unknown as { getKey: () => string }).getKey(),
          start: matchStartInNode,
          end: matchEndInNode,
        });
      }
      charCount = nodeEnd;
    }
    searchIdx = pos + 1;
  }

  return matches;
}

export function FindAndReplacePlugin() {
  const [editor] = useLexicalComposerContext();
  const [open, setOpen] = useState(false);
  const [searchText, setSearchText] = useState("");
  const [replaceText, setReplaceText] = useState("");
  const [currentIndex, setCurrentIndex] = useState(0);
  const [matches, setMatches] = useState<FindMatch[]>([]);
  const inputRef = useRef<HTMLInputElement>(null);

  const updateMatches = useCallback(
    (search: string) => {
      editor.read(() => {
        const results = $findAllMatches(search);
        setMatches(results);
      });
    },
    [editor],
  );

  useEffect(() => {
    updateMatches(searchText);
    // eslint-disable-next-line react-hooks/set-state-in-effect
    setCurrentIndex(0);
  }, [searchText, updateMatches]);

  useEffect(() => {
    if (open) {
      // eslint-disable-next-line @eslint-react/web-api-no-leaked-timeout -- one-shot focus after mount
      setTimeout(() => inputRef.current?.focus(), 50);
    }
  }, [open]);

  useEffect(() => {
    const handleKeyDown = (e: KeyboardEvent) => {
      if ((e.ctrlKey || e.metaKey) && e.key === "f") {
        e.preventDefault();
        setOpen(true);
      }
      if (open && e.key === "Escape") {
        e.preventDefault();
        setOpen(false);
      }
    };
    const handleCustomEvent = () => setOpen(true);
    const cleanupKeydown = registerEventListener(document, "keydown", handleKeyDown);
    // eslint-disable-next-line @eslint-react/web-api-no-leaked-event-listener -- paired removeEventListener in the cleanup below
    addEventListener("lexical-find-replace", handleCustomEvent);
    return () => {
      cleanupKeydown();
      removeEventListener("lexical-find-replace", handleCustomEvent);
    };
  }, [open]);

  const selectMatch = useCallback(
    (match: FindMatch) => {
      editor.update(() => {
        const node = $getNodeByKey(match.key);
        if (!$isTextNode(node)) {
          const fallback = $getRoot().getChildren()[0];
          if (!$isTextNode(fallback)) return;
          const selection = $createRangeSelection();
          selection.setTextNodeRange(fallback, 0, fallback, 0);
          $setSelection(selection);
          return;
        }
        const selection = $createRangeSelection();
        selection.setTextNodeRange(node, match.start, node, match.end);
        $setSelection(selection);
      });
    },
    [editor],
  );

  const goToNext = useCallback(() => {
    if (matches.length === 0) return;
    const next = (currentIndex + 1) % matches.length;
    setCurrentIndex(next);
    const match = matches[next];
    if (match) selectMatch(match);
  }, [currentIndex, matches, selectMatch]);

  const goToPrev = useCallback(() => {
    if (matches.length === 0) return;
    const prev = (currentIndex - 1 + matches.length) % matches.length;
    setCurrentIndex(prev);
    const match = matches[prev];
    if (match) selectMatch(match);
  }, [currentIndex, matches, selectMatch]);

  const replaceCurrent = useCallback(() => {
    if (matches.length === 0) return;
    editor.update(() => {
      const match = matches[currentIndex];
      if (!match) return;
      const node = $getNodeByKey(match.key);
      if (!$isTextNode(node)) return;
      const fullText = node.getTextContent();
      const before = fullText.slice(0, match.start);
      const after = fullText.slice(match.end);
      node.setTextContent(before + replaceText + after);
    });
    setTimeout(() => {
      updateMatches(searchText);
    }, 0);
  }, [currentIndex, matches, replaceText, searchText, editor, updateMatches]);

  const replaceAll = useCallback(() => {
    if (matches.length === 0) return;
    editor.update(() => {
      for (let i = matches.length - 1; i >= 0; i--) {
        const match = matches[i];
        if (!match) continue;
        const node = $getNodeByKey(match.key);
        if (!$isTextNode(node)) continue;
        const fullText = node.getTextContent();
        const before = fullText.slice(0, match.start);
        const after = fullText.slice(match.end);
        node.setTextContent(before + replaceText + after);
      }
    });
    setTimeout(() => {
      updateMatches(searchText);
    }, 0);
  }, [matches, replaceText, searchText, editor, updateMatches]);

  const matchCount = matches.length;
  const hasMatches = matchCount > 0;

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogContent className="max-w-sm gap-0 p-0" showCloseButton={false}>
        <DialogHeader className="border-b px-4 py-3">
          <div className="flex items-center justify-between">
            <DialogTitle className="text-sm font-medium">Find &amp; Replace</DialogTitle>
            <DialogClose render={<Button variant="ghost" size="icon" className="h-6 w-6" />}>
              <X className="h-4 w-4" />
            </DialogClose>
          </div>
        </DialogHeader>
        <div className="space-y-3 p-4">
          <div className="flex items-center gap-2">
            <Input
              ref={inputRef}
              placeholder="Find..."
              value={searchText}
              onChange={(e) => setSearchText(e.target.value)}
              className="h-8 text-sm"
              onKeyDown={(e) => {
                if (e.key !== "Enter") {
                  return;
                }

                e.preventDefault();
                goToNext();
              }}
            />
            <span className="shrink-0 text-xs text-muted-foreground tabular-nums">
              {hasMatches ? `${currentIndex + 1}/${matchCount}` : "0 results"}
            </span>
            <Button
              variant="ghost"
              size="icon"
              className="h-8 w-8 shrink-0"
              disabled={!hasMatches}
              onClick={goToPrev}
            >
              <ArrowUp className="h-4 w-4" />
            </Button>
            <Button
              variant="ghost"
              size="icon"
              className="h-8 w-8 shrink-0"
              disabled={!hasMatches}
              onClick={goToNext}
            >
              <ArrowDown className="h-4 w-4" />
            </Button>
          </div>
          <div className="flex items-center gap-2">
            <Input
              placeholder="Replace with..."
              value={replaceText}
              onChange={(e) => setReplaceText(e.target.value)}
              className="h-8 text-sm"
              onKeyDown={(e) => {
                if (e.key !== "Enter") {
                  return;
                }

                e.preventDefault();
                replaceCurrent();
              }}
            />
            <Button
              variant="ghost"
              size="icon"
              className="h-8 w-8 shrink-0"
              disabled={!hasMatches}
              onClick={replaceCurrent}
              title="Replace current"
            >
              <Replace className="h-4 w-4" />
            </Button>
            <Button
              variant="ghost"
              size="icon"
              className="h-8 w-8 shrink-0"
              disabled={!hasMatches}
              onClick={replaceAll}
              title="Replace all"
            >
              <ReplaceAll className="h-4 w-4" />
            </Button>
          </div>
        </div>
      </DialogContent>
    </Dialog>
  );
}
