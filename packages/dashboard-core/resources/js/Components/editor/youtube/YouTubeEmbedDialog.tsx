import { useState } from "react";
import { useLexicalComposerContext } from "@lexical/react/LexicalComposerContext";
import { YouTubeEmbedConfig } from "./YouTubeNode";
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogFooter,
} from "@/Components/ui/dialog";
import { Button } from "@/Components/ui/button";
import { Input } from "@/Components/ui/input";
import { Label } from "@/Components/ui/label";
import { toast } from "@/Components/ui/toast";

interface YouTubeEmbedDialogProps {
  open: boolean;
  onOpenChange: (_open: boolean) => void;
}

export function YouTubeEmbedDialog({ open, onOpenChange }: YouTubeEmbedDialogProps) {
  const [editor] = useLexicalComposerContext();
  const [url, setUrl] = useState("");

  const insertEmbed = () => {
    const result = YouTubeEmbedConfig.parseUrl(url.trim());
    if (!result) {
      toast.add({ title: "Enter a valid YouTube URL", type: "error" });
      return;
    }
    YouTubeEmbedConfig.insertNode(editor, result);
    setUrl("");
    onOpenChange(false);
  };

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-md">
        <DialogHeader>
          <DialogTitle>Insert YouTube Video</DialogTitle>
        </DialogHeader>

        <div className="space-y-2">
          <Label htmlFor="youtube-url">YouTube URL</Label>
          <Input
            id="youtube-url"
            placeholder="https://www.youtube.com/watch?v=..."
            value={url}
            onChange={(e) => setUrl(e.target.value)}
            onKeyDown={(e) => {
              if (e.key === "Enter") insertEmbed();
            }}
          />
          <p className="text-xs text-muted-foreground">
            Supports watch links, short links (youtu.be), and embed URLs.
          </p>
        </div>

        <DialogFooter>
          <Button variant="outline" onClick={() => onOpenChange(false)}>
            Cancel
          </Button>
          <Button onClick={insertEmbed} disabled={!url.trim()}>
            Insert
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
