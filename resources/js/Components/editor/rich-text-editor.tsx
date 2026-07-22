import { useCallback, useEffect, useRef, useState } from "react";
import { LexicalComposer } from "@lexical/react/LexicalComposer";
import { RichTextPlugin } from "@lexical/react/LexicalRichTextPlugin";
import { ContentEditable } from "@lexical/react/LexicalContentEditable";
import { HistoryPlugin } from "@lexical/react/LexicalHistoryPlugin";
import { ListPlugin } from "@lexical/react/LexicalListPlugin";
import { CheckListPlugin } from "@lexical/react/LexicalCheckListPlugin";
import { LinkPlugin } from "@lexical/react/LexicalLinkPlugin";
import { AutoLinkPlugin, createLinkMatcherWithRegExp } from "@lexical/react/LexicalAutoLinkPlugin";
import { TablePlugin } from "@lexical/react/LexicalTablePlugin";
import { AutoFocusPlugin } from "@lexical/react/LexicalAutoFocusPlugin";
import { MarkdownShortcutPlugin } from "@lexical/react/LexicalMarkdownShortcutPlugin";
import { HorizontalRulePlugin } from "@lexical/react/LexicalHorizontalRulePlugin";
import { HorizontalRuleNode } from "@lexical/react/LexicalHorizontalRuleNode";
import { CharacterLimitPlugin } from "@lexical/react/LexicalCharacterLimitPlugin";
import { LexicalAutoEmbedPlugin, AutoEmbedOption } from "@lexical/react/LexicalAutoEmbedPlugin";
import { LexicalErrorBoundary } from "@lexical/react/LexicalErrorBoundary";
import { HeadingNode, QuoteNode } from "@lexical/rich-text";
import { ListNode, ListItemNode } from "@lexical/list";
import { LinkNode, AutoLinkNode } from "@lexical/link";
import { CodeNode, CodeHighlightNode } from "@lexical/code";
import { TableCellNode, TableNode, TableRowNode } from "@lexical/table";
import { OverflowNode } from "@lexical/overflow";
import { YouTubeNode } from "./youtube";
import { YouTubeEmbedConfig } from "./youtube/YouTubeNode";
import { ImageNode, ImagePlugin } from "./image";
import { LayoutContainerNode, LayoutItemNode, ColumnsPlugin, LayoutControlsPlugin } from "./layout";
import { TRANSFORMERS } from "@lexical/markdown";
import { type ElementNode } from "lexical";
import { cn } from "@/lib/utils";
import { editorTheme } from "./editor-theme";
import { EditorToolbar } from "./toolbar";
import { KeyboardShortcutsPlugin } from "./KeyboardShortcutsPlugin";
import { FindAndReplacePlugin } from "./FindAndReplacePlugin";
import { CodeBlockActionsPlugin } from "./CodeBlockActionsPlugin";
import { SlashCommandsPlugin } from "./SlashCommandsPlugin";
import { WordCountPlugin } from "./WordCountPlugin";
import DraggableBlockPlugin from "./DraggableBlockPlugin";
import CursorGuardPlugin from "./CursorGuardPlugin";

import { CodeHighlightPlugin } from "./components/code-highlight-plugin";
import { OnChangeEmitter } from "./components/on-change-emitter";
import { HtmlLoader } from "./components/html-loader";
import { FloatingPlugins } from "./components/floating-plugins";
import { ContextMenuWrapper } from "./components/context-menu-wrapper";

const URL_MATCHER = createLinkMatcherWithRegExp(
  /((https?:\/\/(www\.)?)|(www\.))[-a-zA-Z0-9@:%._+~#=]{1,256}\.[a-zA-Z0-9()]{1,6}\b([-a-zA-Z0-9()@:%_+.~#?&//=]*)/u,
);

const EMAIL_MATCHER = createLinkMatcherWithRegExp(
  /[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/u,
);

const AUTOLINK_MATCHERS = [URL_MATCHER, EMAIL_MATCHER];

function validateUrl(url: string): boolean {
  try {
    new URL(url);
    return true;
  } catch {
    return (
      /^https?:\/\//i.test(url) || /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/u.test(url)
    );
  }
}

const AUTOLINK_EXCLUDE_PARENTS = [
  (parent: ElementNode) => parent.getType() === "link",
  (parent: ElementNode) => parent.getType() === "autolink",
];

const initialConfig = {
  namespace: "RichTextEditor",
  theme: editorTheme,
  nodes: [
    HeadingNode,
    QuoteNode,
    ListNode,
    ListItemNode,
    LinkNode,
    AutoLinkNode,
    CodeNode,
    CodeHighlightNode,
    TableNode,
    TableRowNode,
    TableCellNode,
    ImageNode,
    YouTubeNode,
    LayoutContainerNode,
    LayoutItemNode,
    HorizontalRuleNode,
    OverflowNode,
  ],
  onError: (error: Error) => {
    console.error("[Lexical]", error);
  },
};

export interface RichTextEditorProps {
  value: string;
  onChange: (_html: string) => void;
  placeholder?: string;
  compact?: boolean;
  className?: string;
  maxLength?: number;
  minHeight?: string;
  maxHeight?: string;
  showWordCount?: boolean;
}

export function RichTextEditor({
  value,
  onChange,
  placeholder = "Start writing...",
  compact = false,
  className,
  maxLength,
  minHeight = "200px",
  maxHeight = "600px",
  showWordCount = true,
}: RichTextEditorProps) {
  const scrollContainerRef = useRef<HTMLDivElement>(null);
  const [anchorElem, setAnchorElem] = useState<HTMLDivElement | null>(null);
  const anchorCallbackRef = useCallback((node: HTMLDivElement | null) => {
    setAnchorElem(node);
  }, []);

  const [isSmallWidthViewport, setIsSmallWidthViewport] = useState(
    () => typeof window !== "undefined" && matchMedia("(max-width: 1025px)").matches,
  );

  useEffect(() => {
    const mql = matchMedia("(max-width: 1025px)");
    const handler = (e: MediaQueryListEvent) => setIsSmallWidthViewport(e.matches);
    mql.addEventListener("change", handler);
    return () => mql.removeEventListener("change", handler);
  }, []);

  const showDraggable = !isSmallWidthViewport && !compact;

  return (
    <LexicalComposer initialConfig={initialConfig}>
      <div className={cn("relative rounded-lg border bg-background shadow-xs", className)}>
        {!compact && <EditorToolbar />}
        <div
          ref={scrollContainerRef}
          className="relative overflow-auto px-4 py-3 focus-within:ring-2 focus-within:ring-ring/20"
          style={{ minHeight, maxHeight }}
        >
          <div ref={anchorCallbackRef} className={cn("relative", showDraggable && "pl-12")}>
            <RichTextPlugin
              contentEditable={
                <ContentEditable
                  className="outline-none"
                  aria-placeholder={placeholder}
                  aria-label="Rich text editor"
                  role="textbox"
                  aria-multiline="true"
                  placeholder={
                    <div
                      className={cn(
                        "pointer-events-none absolute top-3 text-muted-foreground select-none",
                        showDraggable ? "left-[52px]" : "left-4",
                      )}
                    >
                      {placeholder}
                    </div>
                  }
                />
              }
              ErrorBoundary={LexicalErrorBoundary}
            />
          </div>
          <FloatingPlugins scrollContainerRef={scrollContainerRef} />
        </div>
        {showWordCount && !compact && (
          <div className="flex items-center justify-end border-t px-4 py-1.5">
            <WordCountPlugin />
          </div>
        )}
      </div>
      <HtmlLoader html={value} />
      <OnChangeEmitter onChange={onChange} />
      <HistoryPlugin />
      <ListPlugin />
      <CheckListPlugin />
      <LinkPlugin validateUrl={validateUrl} />
      <AutoLinkPlugin matchers={AUTOLINK_MATCHERS} excludeParents={AUTOLINK_EXCLUDE_PARENTS} />
      <TablePlugin hasHorizontalScroll hasCellMerge />
      <CursorGuardPlugin />
      <AutoFocusPlugin defaultSelection="rootStart" />
      <ImagePlugin />
      <ColumnsPlugin />
      <LayoutControlsPlugin />
      <CodeHighlightPlugin />
      <MarkdownShortcutPlugin transformers={TRANSFORMERS} />
      <HorizontalRulePlugin />
      <LexicalAutoEmbedPlugin
        embedConfigs={[YouTubeEmbedConfig]}
        onOpenEmbedModalForConfig={() => {}}
        getMenuOptions={(_config, embedFn, dismissFn) => [
          new AutoEmbedOption("Embed", { onSelect: embedFn }),
          new AutoEmbedOption("Dismiss", { onSelect: dismissFn }),
        ]}
      />
      <ContextMenuWrapper />
      <KeyboardShortcutsPlugin />
      <FindAndReplacePlugin />
      <CodeBlockActionsPlugin />
      <SlashCommandsPlugin />
      {showDraggable && <DraggableBlockPlugin anchorElem={anchorElem ?? undefined} />}
      {maxLength != null && (
        <CharacterLimitPlugin
          charset="UTF-8" // eslint-disable-line unicorn/text-encoding-identifier-case -- Lexical plugin requires "UTF-8"
          maxLength={maxLength}
          renderer={({ remainingCharacters }) => (
            <span
              className={cn(
                "text-xs tabular-nums",
                remainingCharacters < 0 ? "text-destructive font-medium" : "text-muted-foreground",
              )}
            >
              {remainingCharacters}
            </span>
          )}
        />
      )}
    </LexicalComposer>
  );
}
