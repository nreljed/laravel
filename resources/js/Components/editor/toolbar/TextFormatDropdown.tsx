import { useLexicalComposerContext } from "@lexical/react/LexicalComposerContext";
import { FORMAT_TEXT_COMMAND, $getSelection, $isRangeSelection, $isTextNode } from "lexical";
import {
  CaseLower,
  CaseUpper,
  CaseSensitive,
  Strikethrough,
  Subscript as SubscriptIcon,
  Superscript as SuperscriptIcon,
  Highlighter,
  RemoveFormatting,
  MoreHorizontal,
  ChevronDown,
} from "lucide-react";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuShortcut,
  DropdownMenuTrigger,
} from "@/Components/ui/dropdown-menu";
import { Toggle } from "@/Components/ui/toggle";
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from "@/Components/ui/tooltip";

interface TextFormatDropdownProps {
  isStrikethrough: boolean;
  isSubscript: boolean;
  isSuperscript: boolean;
  isLowercase: boolean;
  isUppercase: boolean;
  isCapitalize: boolean;
  isHighlight: boolean;
}

function formatClearFormatting(editor: ReturnType<typeof useLexicalComposerContext>[0]) {
  editor.update(() => {
    const selection = $getSelection();
    if (!$isRangeSelection(selection)) return;
    const nodes = selection.getNodes();
    for (const node of nodes) {
      if (!$isTextNode(node)) {
        continue;
      }
      node.setFormat(0);
      node.setStyle("");
    }
  });
}

export function TextFormatDropdown({
  isStrikethrough,
  isSubscript,
  isSuperscript,
  isLowercase,
  isUppercase,
  isCapitalize,
  isHighlight,
}: TextFormatDropdownProps) {
  const [editor] = useLexicalComposerContext();

  const formatText = (format: string) => {
    editor.dispatchCommand(FORMAT_TEXT_COMMAND, format as never);
  };

  const isActive =
    isStrikethrough ||
    isSubscript ||
    isSuperscript ||
    isLowercase ||
    isUppercase ||
    isCapitalize ||
    isHighlight;

  return (
    <TooltipProvider>
      <Tooltip>
        <DropdownMenu>
          <TooltipTrigger
            render={
              <DropdownMenuTrigger
                render={
                  <Toggle
                    size="sm"
                    variant="outline"
                    aria-label="More text formatting"
                    pressed={isActive}
                    className="gap-1"
                  >
                    <MoreHorizontal className="h-4 w-4" />
                    <ChevronDown className="h-3 w-3 text-muted-foreground" />
                  </Toggle>
                }
              />
            }
          />
          <DropdownMenuContent className="w-56">
            <DropdownMenuItem onClick={() => formatText("strikethrough")}>
              <Strikethrough className="h-4 w-4" />
              Strikethrough
              <DropdownMenuShortcut>Ctrl+Shift+X</DropdownMenuShortcut>
            </DropdownMenuItem>
            <DropdownMenuItem onClick={() => formatText("subscript")}>
              <SubscriptIcon className="h-4 w-4" />
              Subscript
              <DropdownMenuShortcut>Ctrl+,</DropdownMenuShortcut>
            </DropdownMenuItem>
            <DropdownMenuItem onClick={() => formatText("superscript")}>
              <SuperscriptIcon className="h-4 w-4" />
              Superscript
              <DropdownMenuShortcut>Ctrl+.</DropdownMenuShortcut>
            </DropdownMenuItem>
            <DropdownMenuItem onClick={() => formatText("highlight")}>
              <Highlighter className="h-4 w-4" />
              Highlight
            </DropdownMenuItem>

            <DropdownMenuSeparator />

            <DropdownMenuItem onClick={() => formatText("lowercase")}>
              <CaseLower className="h-4 w-4" />
              Lowercase
              <DropdownMenuShortcut>Ctrl+Shift+1</DropdownMenuShortcut>
            </DropdownMenuItem>
            <DropdownMenuItem onClick={() => formatText("uppercase")}>
              <CaseUpper className="h-4 w-4" />
              Uppercase
              <DropdownMenuShortcut>Ctrl+Shift+2</DropdownMenuShortcut>
            </DropdownMenuItem>
            <DropdownMenuItem onClick={() => formatText("capitalize")}>
              <CaseSensitive className="h-4 w-4" />
              Capitalize
              <DropdownMenuShortcut>Ctrl+Shift+3</DropdownMenuShortcut>
            </DropdownMenuItem>

            <DropdownMenuSeparator />

            <DropdownMenuItem onClick={() => formatClearFormatting(editor)}>
              <RemoveFormatting className="h-4 w-4" />
              Clear Formatting
              <DropdownMenuShortcut>Ctrl+\</DropdownMenuShortcut>
            </DropdownMenuItem>
          </DropdownMenuContent>
        </DropdownMenu>
        <TooltipContent>Text Formatting</TooltipContent>
      </Tooltip>
    </TooltipProvider>
  );
}
