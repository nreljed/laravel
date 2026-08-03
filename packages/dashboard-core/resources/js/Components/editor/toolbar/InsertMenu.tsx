import { useState } from "react";
import { useLexicalComposerContext } from "@lexical/react/LexicalComposerContext";
import { $getSelection, $isRangeSelection } from "lexical";
import { $isLinkNode, TOGGLE_LINK_COMMAND } from "@lexical/link";
import { $findMatchingParent } from "@lexical/utils";
import { INSERT_HORIZONTAL_RULE_COMMAND } from "@lexical/extension";
import {
  Link,
  ImagePlus,
  Table,
  Columns3,
  Plus,
  Minus,
  ChevronDown,
  Clapperboard,
} from "lucide-react";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/Components/ui/dropdown-menu";
import { Button } from "@/Components/ui/button";
import { ImageInsertDialog } from "../image";
import { InsertTableDialog } from "../table";
import { InsertLayoutDialog } from "../layout";
import { YouTubeEmbedDialog } from "../youtube/YouTubeEmbedDialog";

export function InsertMenu() {
  const [editor] = useLexicalComposerContext();
  const [imageDialogOpen, setImageDialogOpen] = useState(false);
  const [tableDialogOpen, setTableDialogOpen] = useState(false);
  const [layoutDialogOpen, setLayoutDialogOpen] = useState(false);
  const [youtubeDialogOpen, setYoutubeDialogOpen] = useState(false);

  const formatLink = () => {
    editor.update(() => {
      const selection = $getSelection();
      if (!$isRangeSelection(selection)) return;
      const anchorNode = selection.anchor.getNode();
      const linkParent = $findMatchingParent(anchorNode, (node) => $isLinkNode(node));
      if (linkParent) {
        editor.dispatchCommand(TOGGLE_LINK_COMMAND, null);
      } else {
        editor.dispatchCommand(TOGGLE_LINK_COMMAND, "https://");
      }
    });
  };

  const insertHorizontalRule = () => {
    editor.dispatchCommand(INSERT_HORIZONTAL_RULE_COMMAND, undefined);
  };

  return (
    <>
      <DropdownMenu>
        <DropdownMenuTrigger
          render={
            <Button type="button" size="sm" variant="outline" aria-label="Insert" className="gap-1">
              <Plus className="h-4 w-4" />
              <span className="hidden sm:inline">Insert</span>
              <ChevronDown className="h-3 w-3 text-muted-foreground" />
            </Button>
          }
        />
        <DropdownMenuContent className="w-auto min-w-[160px]">
          <DropdownMenuItem onClick={formatLink}>
            <Link className="h-4 w-4" />
            Link
          </DropdownMenuItem>
          <DropdownMenuItem onClick={() => setImageDialogOpen(true)}>
            <ImagePlus className="h-4 w-4" />
            Image
          </DropdownMenuItem>
          <DropdownMenuItem onClick={() => setYoutubeDialogOpen(true)}>
            <Clapperboard className="h-4 w-4" />
            YouTube Video
          </DropdownMenuItem>
          <DropdownMenuItem onClick={insertHorizontalRule}>
            <Minus className="h-4 w-4" />
            Horizontal Rule
          </DropdownMenuItem>
          <DropdownMenuSeparator />
          <DropdownMenuItem onClick={() => setTableDialogOpen(true)}>
            <Table className="h-4 w-4" />
            Table
          </DropdownMenuItem>
          <DropdownMenuItem onClick={() => setLayoutDialogOpen(true)}>
            <Columns3 className="h-4 w-4" />
            Columns Layout
          </DropdownMenuItem>
        </DropdownMenuContent>
      </DropdownMenu>
      <ImageInsertDialog open={imageDialogOpen} onOpenChange={setImageDialogOpen} />
      <InsertTableDialog open={tableDialogOpen} onOpenChange={setTableDialogOpen} />
      <InsertLayoutDialog open={layoutDialogOpen} onOpenChange={setLayoutDialogOpen} />
      <YouTubeEmbedDialog open={youtubeDialogOpen} onOpenChange={setYoutubeDialogOpen} />
    </>
  );
}
