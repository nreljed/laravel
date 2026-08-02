import { sanitizeLexicalTables } from "@/lib/sanitize-lexical-tables";

interface ContentBlock {
  id?: number;
  type: string;
  content: string | null;
  media_url: string | null;
  media_alt: string | null;
  settings: Record<string, unknown> | null;
  order: number;
}

interface ContentBlockRendererProps {
  blocks: ContentBlock[];
}

function renderBlock(block: ContentBlock) {
  switch (block.type) {
    case "rich_text": {
      return (
        <div
          ref={(node) => {
            if (node) {
              sanitizeLexicalTables(node);
            }
          }}
          className="prose prose-neutral dark:prose-invert max-w-none"
          dangerouslySetInnerHTML={{ __html: block.content || "" }}
        />
      );
    }

    case "image": {
      return (
        <figure className="my-6">
          <img
            src={block.media_url || ""}
            alt={block.media_alt || ""}
            className="w-full rounded-lg object-cover"
            loading="lazy"
          />
          {block.media_alt && (
            <figcaption className="mt-2 text-center text-sm text-muted-foreground">
              {block.media_alt}
            </figcaption>
          )}
        </figure>
      );
    }

    case "gallery": {
      const urls = (block.content || "")
        .split(",")
        .map((u) => u.trim())
        .filter(Boolean);
      return (
        <div className="my-6 grid grid-cols-2 gap-4 sm:grid-cols-3">
          {urls.map((url, i) => (
            <img
              key={i}
              src={url}
              alt={block.media_alt || `Gallery image ${i + 1}`}
              className="w-full rounded-lg object-cover"
              loading="lazy"
            />
          ))}
        </div>
      );
    }

    case "embed": {
      return (
        <div className="my-6 aspect-video">
          <iframe
            src={block.content || ""}
            className="h-full w-full rounded-lg border"
            allowFullScreen
            loading="lazy"
          />
        </div>
      );
    }

    case "code": {
      return (
        <pre className="my-6 overflow-x-auto rounded-lg bg-muted p-4 text-sm">
          <code>{block.content}</code>
        </pre>
      );
    }

    case "separator": {
      return <hr className="my-8 border-border" />;
    }

    case "heading": {
      return <h2 className="my-6 text-2xl font-bold text-foreground">{block.content}</h2>;
    }

    default: {
      return null;
    }
  }
}

export default function ContentBlockRenderer({ blocks }: ContentBlockRendererProps) {
  if (!blocks || blocks.length === 0) return null;

  return (
    <div className="space-y-6">
      {blocks.map((block) => (
        <div key={block.id ?? block.order}>{renderBlock(block)}</div>
      ))}
    </div>
  );
}
