import { useLexicalComposerContext } from "@lexical/react/LexicalComposerContext";
import { FORMAT_ELEMENT_COMMAND, INDENT_CONTENT_COMMAND, OUTDENT_CONTENT_COMMAND } from "lexical";
import {
  AlignLeft,
  AlignCenter,
  AlignRight,
  AlignJustify,
  ChevronDown,
  Indent,
  Outdent,
} from "lucide-react";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
  DropdownMenuShortcut,
} from "@/Components/ui/dropdown-menu";
import { Toggle } from "@/Components/ui/toggle";
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from "@/Components/ui/tooltip";

type AlignOption = {
  label: string;
  icon: React.ComponentType<{ className?: string }>;
  value: string;
  shortcut: string;
};

const ALIGN_OPTIONS: AlignOption[] = [
  { label: "Align Left", icon: AlignLeft, value: "left", shortcut: "Ctrl+Shift+L" },
  { label: "Align Center", icon: AlignCenter, value: "center", shortcut: "Ctrl+Shift+E" },
  { label: "Align Right", icon: AlignRight, value: "right", shortcut: "Ctrl+Shift+R" },
  { label: "Justify", icon: AlignJustify, value: "justify", shortcut: "Ctrl+Shift+J" },
];

export function ElementFormatDropdown({ elementFormat }: { elementFormat: string }) {
  const [editor] = useLexicalComposerContext();

  const formatElement = (value: string) => {
    editor.dispatchCommand(
      FORMAT_ELEMENT_COMMAND,
      value as "left" | "center" | "right" | "justify",
    );
  };

  const formatIndent = () => {
    editor.dispatchCommand(INDENT_CONTENT_COMMAND, undefined);
  };

  const formatOutdent = () => {
    editor.dispatchCommand(OUTDENT_CONTENT_COMMAND, undefined);
  };

  const CurrentIcon = ALIGN_OPTIONS.find((opt) => opt.value === elementFormat)?.icon ?? AlignLeft;

  return (
    <TooltipProvider>
      <Tooltip>
        <DropdownMenu>
          <TooltipTrigger
            render={
              <DropdownMenuTrigger
                render={
                  <Toggle size="sm" variant="outline" aria-label="Text alignment" className="gap-1">
                    <CurrentIcon className="h-4 w-4" />
                    <ChevronDown className="h-3 w-3 text-muted-foreground" />
                  </Toggle>
                }
              />
            }
          />
          <DropdownMenuContent className="w-auto min-w-[180px]">
            {ALIGN_OPTIONS.map((option) => (
              <DropdownMenuItem key={option.value} onClick={() => formatElement(option.value)}>
                <option.icon className="h-4 w-4" />
                {option.label}
                <DropdownMenuShortcut>{option.shortcut}</DropdownMenuShortcut>
              </DropdownMenuItem>
            ))}
            <DropdownMenuSeparator />
            <DropdownMenuItem onClick={formatOutdent}>
              <Outdent className="h-4 w-4" />
              Outdent
              <DropdownMenuShortcut>Ctrl+[</DropdownMenuShortcut>
            </DropdownMenuItem>
            <DropdownMenuItem onClick={formatIndent}>
              <Indent className="h-4 w-4" />
              Indent
              <DropdownMenuShortcut>Ctrl+]</DropdownMenuShortcut>
            </DropdownMenuItem>
          </DropdownMenuContent>
        </DropdownMenu>
        <TooltipContent>Alignment</TooltipContent>
      </Tooltip>
    </TooltipProvider>
  );
}
