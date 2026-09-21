import { useCallback, useEffect, useState } from "react";
import { useLexicalComposerContext } from "@lexical/react/LexicalComposerContext";
import { $getRoot, $isTextNode } from "lexical";

function $getWordAndCharCount(): { words: number; characters: number } {
  const root = $getRoot();
  let text = "";
  const visit = (node: ReturnType<typeof $getRoot>) => {
    for (const child of node.getChildren()) {
      if ($isTextNode(child)) {
        text += child.getTextContent();
      } else if ("getChildren" in child) {
        visit(child as ReturnType<typeof $getRoot>);
      }
    }
  };
  visit(root);

  const trimmed = text.trim();
  const words = trimmed === "" ? 0 : trimmed.split(/\s+/).length;
  const characters = [...text].length;

  return { words, characters };
}

function WordCountPluginInner() {
  const [editor] = useLexicalComposerContext();
  const [counts, setCounts] = useState({ words: 0, characters: 0 });

  const updateCounts = useCallback(() => {
    editor.read(() => {
      setCounts($getWordAndCharCount());
    });
  }, [editor]);

  useEffect(() => {
    return editor.registerUpdateListener(() => {
      updateCounts();
    });
  }, [editor, updateCounts]);

  useEffect(() => {
    updateCounts();
  }, [updateCounts]);

  return (
    <span className="text-xs text-muted-foreground tabular-nums">
      {counts.words} {counts.words === 1 ? "word" : "words"} &middot; {counts.characters}{" "}
      {counts.characters === 1 ? "character" : "characters"}
    </span>
  );
}

export function WordCountPlugin() {
  return <WordCountPluginInner />;
}
