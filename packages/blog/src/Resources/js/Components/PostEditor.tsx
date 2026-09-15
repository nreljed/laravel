import { Suspense, lazy } from "react";
import { Spinner } from "@nreljed/platform/Components/ui/spinner";
import { cn } from "@nreljed/platform/lib/utils";
import type { ViewportSize } from "./PostEditorToolbar";

// Lazy load Lexical editor
const Editor = lazy(async () => {
  const module = await import("@nreljed/platform/Components/editor");
  return { default: module.RichTextEditor };
});

const VIEWPORT_WIDTHS: Record<ViewportSize, string> = {
  desktop: "w-full",
  tablet: "mx-auto w-full max-w-[768px]",
  mobile: "mx-auto w-full max-w-[375px]",
};

interface PostEditorProps {
  value: string;
  onChange: (_html: string) => void;
  viewport: ViewportSize;
  showWordCount?: boolean;
}

function PostEditor({ value, onChange, viewport, showWordCount = false }: PostEditorProps) {
  return (
    <Suspense
      fallback={
        <div className="h-64 flex items-center justify-center border rounded-md">
          <Spinner /> Loading editor...
        </div>
      }
    >
      <div className={cn("transition-all duration-300", VIEWPORT_WIDTHS[viewport])}>
        <Editor value={value} onChange={onChange} showWordCount={showWordCount} />
      </div>
    </Suspense>
  );
}

export { PostEditor };
export type { PostEditorProps };