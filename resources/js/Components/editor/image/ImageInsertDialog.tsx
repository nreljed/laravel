import { useState, useRef } from "react";
import { useLexicalComposerContext } from "@lexical/react/LexicalComposerContext";
import { INSERT_IMAGE_COMMAND, type ImagePayload } from "./ImageNode";
import { uploadImageToServer } from "./ImagePlugin";
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
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/Components/ui/tabs";
import { toast } from "@/Components/ui/toast";
import { ImagePlus, Upload, LinkIcon } from "lucide-react";

interface ImageInsertDialogProps {
  open: boolean;
  onOpenChange: (_open: boolean) => void;
}

export function ImageInsertDialog({ open, onOpenChange }: ImageInsertDialogProps) {
  const [editor] = useLexicalComposerContext();
  const [tab, setTab] = useState<"url" | "upload">("url");
  const [url, setUrl] = useState("");
  const [altText, setAltText] = useState("");
  const [uploading, setUploading] = useState(false);
  const fileInputRef = useRef<HTMLInputElement>(null);

  const insertFromUrl = () => {
    if (!url.trim()) return;
    const payload: ImagePayload = { src: url.trim(), altText: altText.trim() || "Image" };
    editor.dispatchCommand(INSERT_IMAGE_COMMAND, payload);
    resetAndClose();
  };

  const insertFromFile = async (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file) return;
    setUploading(true);
    try {
      const { url: uploadedUrl } = await uploadImageToServer(file);
      const payload: ImagePayload = { src: uploadedUrl, altText: altText.trim() || file.name };
      editor.dispatchCommand(INSERT_IMAGE_COMMAND, payload);
      resetAndClose();
    } catch {
      toast.add({ title: "Failed to upload image", type: "error" });
    } finally {
      setUploading(false);
      if (fileInputRef.current) fileInputRef.current.value = "";
    }
  };

  const resetAndClose = () => {
    setUrl("");
    setAltText("");
    setTab("url");
    onOpenChange(false);
  };

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-md">
        <DialogHeader>
          <DialogTitle>Insert Image</DialogTitle>
        </DialogHeader>

        <Tabs value={tab} onValueChange={(v) => setTab(v as "url" | "upload")}>
          <TabsList className="w-full">
            <TabsTrigger value="url" className="flex-1 gap-2">
              <LinkIcon className="h-4 w-4" />
              URL
            </TabsTrigger>
            <TabsTrigger value="upload" className="flex-1 gap-2">
              <Upload className="h-4 w-4" />
              Upload
            </TabsTrigger>
          </TabsList>

          <TabsContent value="url" className="space-y-2">
            <Label htmlFor="image-url">Image URL</Label>
            <Input
              id="image-url"
              placeholder="https://example.com/image.jpg"
              value={url}
              onChange={(e) => setUrl(e.target.value)}
              onKeyDown={(e) => {
                if (e.key === "Enter") insertFromUrl();
              }}
            />
          </TabsContent>

          <TabsContent value="upload" className="space-y-2">
            <Label>Choose file</Label>
            <input
              ref={fileInputRef}
              type="file"
              accept="image/*"
              className="hidden"
              onChange={insertFromFile}
            />
            <Button
              variant="outline"
              className="w-full"
              onClick={() => fileInputRef.current?.click()}
              disabled={uploading}
            >
              <ImagePlus className="mr-2 h-4 w-4" />
              {uploading ? "Uploading..." : "Select image"}
            </Button>
          </TabsContent>

          <div className="space-y-2">
            <Label htmlFor="image-alt">Alt text</Label>
            <Input
              id="image-alt"
              placeholder="Describe the image"
              value={altText}
              onChange={(e) => setAltText(e.target.value)}
            />
          </div>
        </Tabs>

        <DialogFooter>
          <Button variant="outline" onClick={resetAndClose}>
            Cancel
          </Button>
          {tab === "url" && (
            <Button onClick={insertFromUrl} disabled={!url.trim()}>
              Insert
            </Button>
          )}
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
