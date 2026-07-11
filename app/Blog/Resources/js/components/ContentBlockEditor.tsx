import { GripVertical, Plus, Trash2, Image, Type, Code, Hash, Minus, Layout } from "lucide-react";
import { Button } from "@/Components/ui/button";
import { Input } from "@/Components/ui/input";
import { Textarea } from "@/Components/ui/textarea";
import { Label } from "@/Components/ui/label";
import {
  Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from "@/Components/ui/select";

interface ContentBlock {
  id?: number;
  type: string;
  content: string | null;
  media_url: string | null;
  media_alt: string | null;
  settings: Record<string, unknown> | null;
  order: number;
}

interface Props {
  blocks: ContentBlock[];
  onChange: (blocks: ContentBlock[]) => void;
}

const blockIcons: Record<string, React.ReactNode> = {
  rich_text: <Type className="h-4 w-4" />,
  image: <Image className="h-4 w-4" />,
  gallery: <Layout className="h-4 w-4" />,
  embed: <Code className="h-4 w-4" />,
  code: <Code className="h-4 w-4" />,
  separator: <Minus className="h-4 w-4" />,
  heading: <Hash className="h-4 w-4" />,
};

const blockLabels: Record<string, string> = {
  rich_text: "Rich Text",
  image: "Image",
  gallery: "Gallery",
  embed: "Embed",
  code: "Code",
  separator: "Separator",
  heading: "Heading",
};

export default function ContentBlockEditor({ blocks, onChange }: Props) {
  const addBlock = (type: string) => {
    const newBlock: ContentBlock = {
      type,
      content: "",
      media_url: null,
      media_alt: null,
      settings: null,
      order: blocks.length,
    };
    onChange([...blocks, newBlock]);
  };

  const updateBlock = (index: number, field: string, value: unknown) => {
    const updated = blocks.map((block, i) =>
      i === index ? { ...block, [field]: value } : block,
    );
    onChange(updated);
  };

  const removeBlock = (index: number) => {
    onChange(blocks.filter((_, i) => i !== index));
  };

  const moveBlock = (index: number, direction: -1 | 1) => {
    const target = index + direction;
    if (target < 0 || target >= blocks.length) return;
    const updated = [...blocks];
    [updated[index], updated[target]] = [updated[target], updated[index]];
    onChange(updated);
  };

  return (
    <div className="space-y-3">
      <Label>Content Blocks</Label>

      {blocks.map((block, index) => (
        <div key={index} className="rounded-lg border p-3 space-y-3">
          <div className="flex items-center justify-between">
            <div className="flex items-center gap-2">
              <GripVertical className="h-4 w-4 text-muted-foreground" />
              {blockIcons[block.type]}
              <span className="text-sm font-medium">{blockLabels[block.type] || block.type}</span>
            </div>
            <div className="flex items-center gap-1">
              <Select
                value={block.type}
                onValueChange={(v) => updateBlock(index, "type", v)}
              >
                <SelectTrigger className="w-32 h-7 text-xs">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  {Object.entries(blockLabels).map(([key, label]) => (
                    <SelectItem key={key} value={key}>{label}</SelectItem>
                  ))}
                </SelectContent>
              </Select>
              <Button variant="ghost" size="icon-sm" onClick={() => moveBlock(index, -1)} disabled={index === 0}>
                <span className="text-xs">▲</span>
              </Button>
              <Button variant="ghost" size="icon-sm" onClick={() => moveBlock(index, 1)} disabled={index === blocks.length - 1}>
                <span className="text-xs">▼</span>
              </Button>
              <Button variant="ghost" size="icon-sm" onClick={() => removeBlock(index)}>
                <Trash2 className="h-3.5 w-3.5 text-destructive" />
              </Button>
            </div>
          </div>

          {(block.type === "rich_text" || block.type === "code") && (
            <Textarea
              rows={4}
              placeholder={block.type === "code" ? "Enter code..." : "Enter content (HTML supported)..."}
              value={block.content || ""}
              onChange={(e) => updateBlock(index, "content", e.target.value)}
            />
          )}

          {block.type === "heading" && (
            <Input
              placeholder="Heading text..."
              value={block.content || ""}
              onChange={(e) => updateBlock(index, "content", e.target.value)}
            />
          )}

          {block.type === "image" && (
            <div className="space-y-2">
              <Input
                placeholder="Image URL..."
                value={block.media_url || ""}
                onChange={(e) => updateBlock(index, "media_url", e.target.value)}
              />
              <Input
                placeholder="Alt text..."
                value={block.media_alt || ""}
                onChange={(e) => updateBlock(index, "media_alt", e.target.value)}
              />
            </div>
          )}

          {block.type === "embed" && (
            <Input
              placeholder="Embed URL (YouTube, Vimeo, etc.)..."
              value={block.content || ""}
              onChange={(e) => updateBlock(index, "content", e.target.value)}
            />
          )}

          {block.type === "gallery" && (
            <Input
              placeholder="Image URLs (comma-separated)..."
              value={block.content || ""}
              onChange={(e) => updateBlock(index, "content", e.target.value)}
            />
          )}

          {block.type === "separator" && (
            <p className="text-xs text-muted-foreground">Horizontal rule separator (no content needed)</p>
          )}
        </div>
      ))}

      <div className="flex flex-wrap gap-2">
        <Select onValueChange={(v) => addBlock(v)}>
          <SelectTrigger className="w-36">
            <Plus className="mr-1 h-3 w-3" />
            <SelectValue placeholder="Add block" />
          </SelectTrigger>
          <SelectContent>
            {Object.entries(blockLabels).map(([key, label]) => (
              <SelectItem key={key} value={key}>{label}</SelectItem>
            ))}
          </SelectContent>
        </Select>
      </div>
    </div>
  );
}
