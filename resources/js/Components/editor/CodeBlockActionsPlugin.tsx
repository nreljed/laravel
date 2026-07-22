import { useCallback, useEffect, useRef, useState } from "react";
import { useLexicalComposerContext } from "@lexical/react/LexicalComposerContext";
import { $getNodeByKey, $nodesOfType, type NodeKey } from "lexical";
import { createPortal } from "react-dom";
import { $isCodeNode, CodeNode } from "@lexical/code";
import { Copy, Check } from "lucide-react";
import { Button } from "@/Components/ui/button";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/Components/ui/select";
import { useFloatingPosition } from "./hooks/use-floating-position";

const LANGUAGES = [
  { label: "Plain Text", value: "plain" },
  { label: "JavaScript", value: "javascript" },
  { label: "TypeScript", value: "typescript" },
  { label: "HTML", value: "html" },
  { label: "CSS", value: "css" },
  { label: "PHP", value: "php" },
  { label: "Python", value: "python" },
  { label: "Java", value: "java" },
  { label: "C", value: "c" },
  { label: "C++", value: "cpp" },
  { label: "C#", value: "csharp" },
  { label: "Go", value: "go" },
  { label: "Rust", value: "rust" },
  { label: "Ruby", value: "ruby" },
  { label: "SQL", value: "sql" },
  { label: "Bash", value: "bash" },
  { label: "JSON", value: "json" },
  { label: "YAML", value: "yaml" },
  { label: "Markdown", value: "markdown" },
  { label: "XML", value: "xml" },
  { label: "Dockerfile", value: "dockerfile" },
];

function CodeBlockToolbar({ editor }: { editor: ReturnType<typeof useLexicalComposerContext>[0] }) {
  const { ref: toolbarRef, positionAtRect } = useFloatingPosition(4);
  const [codeBlocks, setCodeBlocks] = useState<
    Array<{ key: NodeKey; language: string | null | undefined }>
  >([]);
  const [copiedKey, setCopiedKey] = useState<NodeKey | null>(null);
  const [activeKey, setActiveKey] = useState<NodeKey | null>(null);
  const codeBlocksRef = useRef(codeBlocks);
  // eslint-disable-next-line react-hooks/refs
  codeBlocksRef.current = codeBlocks;

  useEffect(() => {
    const unregister = editor.registerUpdateListener(() => {
      editor.read(() => {
        const nodes = $nodesOfType(CodeNode);
        const blocks = nodes
          .filter((n) => $isCodeNode(n) && n.isAttached())
          .map((n) => ({
            key: n.getKey(),
            language: n.getLanguage(),
          }));
        setCodeBlocks(blocks);
      });
    });
    return unregister;
  }, [editor]);

  useEffect(() => {
    if (!activeKey) return;
    const el = editor.getElementByKey(activeKey);
    if (!el) return;
    positionAtRect(el.getBoundingClientRect(), { anchor: "top-left" });
  }, [activeKey, editor, positionAtRect]);

  useEffect(() => {
    if (!activeKey) return;
    const update = () => {
      const el = editor.getElementByKey(activeKey);
      if (el) positionAtRect(el.getBoundingClientRect(), { anchor: "top-left" });
    };
    /* eslint-disable unicorn/prefer-observer-apis */
    window.addEventListener("scroll", update, { capture: true });
    window.addEventListener("resize", update);
    /* eslint-enable unicorn/prefer-observer-apis */
    return () => {
      window.removeEventListener("scroll", update, true);
      window.removeEventListener("resize", update);
    };
  }, [activeKey, editor, positionAtRect]);

  const copyCode = useCallback(
    async (key: NodeKey) => {
      const el = editor.getElementByKey(key);
      if (el) {
        try {
          await navigator.clipboard.writeText(el.textContent ?? "");
          setCopiedKey(key);
          setTimeout(() => setCopiedKey(null), 2000);
        } catch {
          /* ignore */
        }
      }
    },
    [editor],
  );

  const setLanguage = useCallback(
    (key: NodeKey, language: string) => {
      editor.update(() => {
        const node = $getNodeByKey(key);
        if (node && $isCodeNode(node)) {
          node.setLanguage(language);
        }
      });
    },
    [editor],
  );

  useEffect(() => {
    const rootElement = editor.getRootElement();
    if (!rootElement) return;

    const handleMouseOver = (e: MouseEvent) => {
      const target = e.target as HTMLElement;
      const codeBlockEl = target.closest<HTMLElement>("code");
      if (!codeBlockEl || !rootElement.contains(codeBlockEl)) {
        setActiveKey(null);
        return;
      }
      for (const block of codeBlocksRef.current) {
        const el = editor.getElementByKey(block.key);
        if (el && (el === codeBlockEl || el.contains(codeBlockEl))) {
          setActiveKey(block.key);
          return;
        }
      }
      setActiveKey(null);
    };

    rootElement.addEventListener("mouseover", handleMouseOver);
    return () => rootElement.removeEventListener("mouseover", handleMouseOver);
  }, [editor]);

  useEffect(() => {
    if (!activeKey) return;
    const handleMouseDown = (e: MouseEvent) => {
      const el = toolbarRef.current;
      if (!el) return;
      if (el.contains(e.target as Node)) return;
      setActiveKey(null);
    };
    const handleKeyDown = (e: KeyboardEvent) => {
      if (e.key === "Escape") setActiveKey(null);
    };
    document.addEventListener("mousedown", handleMouseDown);
    document.addEventListener("keydown", handleKeyDown);
    return () => {
      document.removeEventListener("mousedown", handleMouseDown);
      document.removeEventListener("keydown", handleKeyDown);
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [activeKey]);

  if (codeBlocks.length === 0) return null;

  const activeBlock = activeKey ? codeBlocks.find((b) => b.key === activeKey) : null;

  return createPortal(
    <div
      ref={toolbarRef}
      className="pointer-events-auto fixed z-[100] flex items-center gap-1 rounded-md border bg-background/95 p-1 shadow-sm backdrop-blur-sm"
      style={{ display: activeBlock ? undefined : "none" }}
      contentEditable={false}
    >
      {activeBlock && (
        <>
          <Select
            value={activeBlock.language ?? "plain"}
            onValueChange={(v) => {
              if (v) setLanguage(activeBlock.key, v);
            }}
          >
            <SelectTrigger className="h-7 w-[120px] text-xs">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              {LANGUAGES.map((lang) => (
                <SelectItem key={lang.value} value={lang.value} className="text-xs">
                  {lang.label}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
          <Button
            variant="ghost"
            size="icon"
            className="h-7 w-7"
            onClick={() => copyCode(activeBlock.key)}
          >
            {copiedKey === activeBlock.key ? (
              <Check className="h-3.5 w-3.5 text-green-500" />
            ) : (
              <Copy className="h-3.5 w-3.5" />
            )}
          </Button>
        </>
      )}
    </div>,
    document.body,
  );
}

export function CodeBlockActionsPlugin() {
  const [editor] = useLexicalComposerContext();
  return <CodeBlockToolbar editor={editor} />;
}
