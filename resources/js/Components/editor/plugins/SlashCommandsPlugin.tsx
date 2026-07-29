import { useState, useCallback, useMemo } from "react";
import { useLexicalComposerContext } from "@lexical/react/LexicalComposerContext";
import {
  LexicalTypeaheadMenuPlugin,
  useBasicTypeaheadTriggerMatch,
} from "@lexical/react/LexicalTypeaheadMenuPlugin";
import type { TextNode } from "lexical";
import { createPortal } from "react-dom";
import { BlockOption, getBlockOptions } from "../shared/block-options";
import { BlockPickerMenu } from "../shared/block-picker-menu";

export function SlashCommandsPlugin(): React.ReactNode {
  const [editor] = useLexicalComposerContext();
  const [queryString, setQueryString] = useState<string | null>(null);
  const [dialogs, setDialogs] = useState<
    Array<{ id: number; title: string; render: (_onClose: () => void) => React.ReactNode }>
  >([]);

  const showModal = useCallback(
    (title: string, render: (_onClose: () => void) => React.ReactNode) => {
      const id = Date.now();
      setDialogs((prev) => [...prev, { id, title, render }]);
    },
    [],
  );

  const closeDialog = useCallback((id: number) => {
    setDialogs((prev) => prev.filter((d) => d.id !== id));
  }, []);

  const triggerMatch = useBasicTypeaheadTriggerMatch("/", {
    allowWhitespace: true,
    minLength: 0,
  });

  const options = useMemo(() => {
    const allOptions = getBlockOptions(editor, showModal);
    if (!queryString) {
      return allOptions;
    }
    const regex = new RegExp(queryString, "i");
    return allOptions.filter(
      (option) =>
        regex.test(option.title) || option.keywords.some((keyword) => regex.test(keyword)),
    );
  }, [editor, queryString, showModal]);

  const onSelectOption = useCallback(
    (
      selectedOption: BlockOption,
      nodeToRemove: TextNode | null,
      closeMenu: () => void,
      matchingString: string,
    ) => {
      editor.update(() => {
        nodeToRemove?.remove();
        selectedOption.onSelect(matchingString);
        closeMenu();
      });
    },
    [editor],
  );

  return (
    <>
      {dialogs.map((dialog) => (
        <div key={dialog.id}>{dialog.render(() => closeDialog(dialog.id))}</div>
      ))}
      <LexicalTypeaheadMenuPlugin<BlockOption>
        onQueryChange={setQueryString}
        onSelectOption={onSelectOption}
        triggerFn={triggerMatch}
        options={options}
        menuRenderFn={(
          anchorRef,
          { options: renderedOptions, selectedIndex, selectOptionAndCleanUp, setHighlightedIndex },
        ) =>
          anchorRef.current
            ? createPortal(
                <div
                  className="z-50 max-h-[300px] min-w-[200px] overflow-auto rounded-lg border bg-popover p-1 text-popover-foreground shadow-md"
                  style={{
                    position: "absolute",
                    left: anchorRef.current.getBoundingClientRect().left,
                    top: anchorRef.current.getBoundingClientRect().bottom + 4,
                  }}
                >
                  <BlockPickerMenu
                    options={renderedOptions}
                    highlightedIndex={selectedIndex ?? 0}
                    onHighlight={setHighlightedIndex}
                    onSelect={(option) => selectOptionAndCleanUp(option)}
                  />
                </div>,
                document.body,
              )
            : null
        }
      />
    </>
  );
}
