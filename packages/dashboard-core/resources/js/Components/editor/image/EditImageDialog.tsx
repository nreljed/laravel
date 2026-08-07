import { useEffect, useState, useRef } from "react";
import { useLexicalComposerContext } from "@lexical/react/LexicalComposerContext";
import { $getNodeByKey, type NodeKey } from "lexical";
import { $isImageNode } from "./ImageNode";
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
import { uploadImageToServer, deleteImageFromServer } from "./ImagePlugin";
import { toast } from "@/Components/ui/toast";
import { Upload } from "lucide-react";

interface EditImageDialogProps {
  open: boolean;
  onOpenChange: (_open: boolean) => void;
  nodeKey: NodeKey | null;
}

export function EditImageDialog({ open, onOpenChange, nodeKey }: EditImageDialogProps) {
  const [editor] = useLexicalComposerContext();
  const [src, setSrc] = useState("");
  const [altText, setAltText] = useState("");
  const [uploading, setUploading] = useState(false);
  const fileInputRef = useRef<HTMLInputElement>(null);

  useEffect(() => {
    if (!open || nodeKey == null) return;
    editor.read(() => {
      const node = $getNodeByKey(nodeKey);
      if (node && $isImageNode(node)) {
        setSrc(node.getSrc());
        setAltText(node.getAltText());
      }
    });
  }, [open, nodeKey, editor]);

  const handleSave = () => {
    if (nodeKey == null) return;
    editor.update(() => {
      const node = $getNodeByKey(nodeKey);
      if (node && $isImageNode(node)) {
        node.setSrc(src.trim());
        node.setAltText(altText.trim());
      }
    });
    onOpenChange(false);
  };

  const handleReplaceFile = async (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file) return;
    const oldSrc = src;
    setUploading(true);
    try {
      const { url } = await uploadImageToServer(file);
      setSrc(url);
      if (oldSrc && oldSrc.startsWith("/storage/")) {
        try {
          await deleteImageFromServer(oldSrc);
        } catch {
          /*
          ignore
          */
        }
      }
    } catch {
      toast.add({ title: "Failed to upload image", type: "error" });
    } finally {
      setUploading(false);
      if (fileInputRef.current) fileInputRef.current.value = "";
    }
  };

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-md">
        <DialogHeader>
          <DialogTitle>Edit Image</DialogTitle>
        </DialogHeader>

        <div className="space-y-4 py-2">
          <div className="space-y-2">
            <Label htmlFor="edit-image-url">Image URL</Label>
            <Input
              id="edit-image-url"
              placeholder="https://example.com/image.jpg"
              value={src}
              onChange={(e) => setSrc(e.target.value)}
            />
          </div>

          <div className="space-y-2">
            <Label>Or replace with file</Label>
            <input
              ref={fileInputRef}
              type="file"
              accept="image/*"
              className="hidden"
              onChange={handleReplaceFile}
            />
            <Button
              variant="outline"
              className="w-full"
              onClick={() => fileInputRef.current?.click()}
              disabled={uploading}
            >
              <Upload className="mr-2 h-4 w-4" />
              {uploading ? "Uploading..." : "Replace with file"}
            </Button>
          </div>

          <div className="space-y-2">
            <Label htmlFor="edit-image-alt">Alt text</Label>
            <Input
              id="edit-image-alt"
              placeholder="Describe the image"
              value={altText}
              onChange={(e) => setAltText(e.target.value)}
            />
          </div>
        </div>

        <DialogFooter>
          <Button variant="outline" onClick={() => onOpenChange(false)}>
            Cancel
          </Button>
          <Button onClick={handleSave} disabled={!src.trim()}>
            Save
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
