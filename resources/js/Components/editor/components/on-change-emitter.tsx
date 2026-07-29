import { useEffect, useRef } from "react";
import { useLexicalComposerContext } from "@lexical/react/LexicalComposerContext";
import { $generateHtmlFromNodes } from "@lexical/html";

export function OnChangeEmitter({ onChange }: { onChange: (_html: string) => void }) {
  const [editor] = useLexicalComposerContext();
  const isInitialLoad = useRef(true);
  const onChangeRef = useRef(onChange);
  // eslint-disable-next-line react-hooks/refs
  onChangeRef.current = onChange;
  const timerRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  useEffect(() => {
    return editor.registerUpdateListener(({ editorState }) => {
      if (isInitialLoad.current) {
        isInitialLoad.current = false;
        return;
      }
      if (timerRef.current !== null) {
        clearTimeout(timerRef.current);
      }
      timerRef.current = setTimeout(() => {
        timerRef.current = null;
        editorState.read(() => {
          const html = $generateHtmlFromNodes(editor, null);
          onChangeRef.current(html);
        });
      }, 100);
    });
  }, [editor]);

  useEffect(() => {
    return () => {
      if (timerRef.current === null) {
        return;
      }

      clearTimeout(timerRef.current);
      editor.read(() => {
        const html = $generateHtmlFromNodes(editor, null);
        onChangeRef.current(html);
      });
    };
  }, [editor]);

  return null;
}
