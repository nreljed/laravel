import { useCallback, useEffect, useRef, useState } from "react";
import { LexicalExtensionComposer } from "@lexical/react/LexicalExtensionComposer";
import { ContentEditable } from "@lexical/react/LexicalContentEditable";
import { MarkdownShortcutPlugin } from "@lexical/react/LexicalMarkdownShortcutPlugin";
import { CharacterLimitPlugin } from "@lexical/react/LexicalCharacterLimitPlugin";
import { LexicalAutoEmbedPlugin, AutoEmbedOption } from "@lexical/react/LexicalAutoEmbedPlugin";
import { defineExtension, configExtension } from "lexical";
import { RichTextExtension } from "@lexical/rich-text";
import { HistoryExtension } from "@lexical/history";
import { ListExtension, CheckListExtension } from "@lexical/list";
import { LinkExtension, AutoLinkExtension, createLinkMatcherWithRegExp } from "@lexical/link";
import { TableExtension } from "@lexical/table";
import { CodeShikiExtension, ShikiTokenizer } from "@lexical/code-shiki";
import { OverflowExtension } from "@lexical/overflow";
import { HorizontalRuleExtension, AutoFocusExtension } from "@lexical/extension";
import { DragonExtension } from "@lexical/dragon";
import { TRANSFORMERS } from "@lexical/markdown";
import { type ElementNode } from "lexical";
import { cn } from "@/lib/utils";
import { editorTheme } from "./editor-theme";
import { EditorToolbar } from "./toolbar";
import { KeyboardShortcutsPlugin } from "./plugins/KeyboardShortcutsPlugin";
import { FindAndReplacePlugin } from "./plugins/FindAndReplacePlugin";
import { CodeBlockActionsPlugin } from "./plugins/CodeBlockActionsPlugin";
import { SlashCommandsPlugin } from "./plugins/SlashCommandsPlugin";
import { WordCountPlugin } from "./plugins/WordCountPlugin";
import DraggableBlockPlugin from "./plugins/DraggableBlockPlugin";
import CursorGuardPlugin from "./plugins/CursorGuardPlugin";
import { ImageNode, ImagePlugin } from "./image";
import { YouTubeNode, YouTubeEmbedConfig } from "./youtube/YouTubeNode";
import { LayoutContainerNode, LayoutItemNode, ColumnsPlugin, LayoutControlsPlugin } from "./layout";

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

const editorExtension = defineExtension({
  name: "[root]",
  namespace: "RichTextEditor",
  nodes: () => [ImageNode, YouTubeNode, LayoutContainerNode, LayoutItemNode],
  dependencies: [
    RichTextExtension,
    HistoryExtension,
    ListExtension,
    CheckListExtension,
    configExtension(LinkExtension, { validateUrl, attributes: undefined }),
    configExtension(AutoLinkExtension, {
      matchers: AUTOLINK_MATCHERS,
      excludeParents: AUTOLINK_EXCLUDE_PARENTS,
    }),
    configExtension(TableExtension, {
      hasHorizontalScroll: true,
      hasCellMerge: true,
      hasCellBackgroundColor: true,
      hasTabHandler: true,
      hasNestedTables: false,
    }),
    configExtension(CodeShikiExtension, {
      tokenizer: {
        ...ShikiTokenizer,
        defaultTheme: "github-dark",
        // Never stamp an unlabelled code block with the fallback language.
        // Otherwise imported legacy blocks (no `data-language`) get silently
        // corrupted to "javascript" and re-saved with the wrong language.
        defaultLanguage: null,
      },
    }),
    OverflowExtension,
    HorizontalRuleExtension,
    configExtension(AutoFocusExtension, { defaultSelection: "rootStart", disabled: false }),
    DragonExtension,
  ],
  theme: editorTheme,
});

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
    <LexicalExtensionComposer extension={editorExtension} contentEditable={null}>
      <div className={cn("relative min-w-0 rounded-lg border bg-background shadow-xs", className)}>
        {!compact && <EditorToolbar />}
        <div
          ref={scrollContainerRef}
          className="relative overflow-auto px-4 py-3 focus-within:ring-2 focus-within:ring-ring/20"
          style={{ minHeight, maxHeight }}
        >
          <div ref={anchorCallbackRef} className={cn("relative", showDraggable && "pl-12")}>
            <ContentEditable
              className="outline-none"
              aria-placeholder={placeholder}
              aria-label="Rich text editor"
              role="textbox"
              aria-multiline="true"
              placeholder={
                <div
                  className={cn(
                    "pointer-events-none absolute text-muted-foreground select-none",
                    showDraggable ? "left-[52px] top-[3px]" : "left-4 top-3",
                  )}
                >
                  {placeholder}
                </div>
              }
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
      <CursorGuardPlugin />
      <ImagePlugin />
      <ColumnsPlugin />
      <LayoutControlsPlugin />
      <MarkdownShortcutPlugin transformers={TRANSFORMERS} />
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
    </LexicalExtensionComposer>
  );
}
