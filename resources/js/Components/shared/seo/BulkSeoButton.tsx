import * as React from "react";
import { jsonHeaders } from "@/lib/gallery-utils";
import { route } from "ziggy-js";
import { Button } from "@/Components/ui/button";
import { Sparkles, Loader2 } from "lucide-react";

interface BulkSeoButtonProps {
  selectedIds: number[];
  onComplete?: () => void;
  className?: string;
}

function BulkSeoButton({ selectedIds, onComplete, className }: BulkSeoButtonProps) {
  const [loading, setLoading] = React.useState(false);
  const [result, setResult] = React.useState<{ message: string; count: number } | null>(null);

  const handleBulkGenerate = React.useCallback(async () => {
    if (selectedIds.length === 0) return;

    setLoading(true);
    setResult(null);

    try {
      const res = await fetch(route("dashboard.posts.seo.bulk") as string, {
        method: "POST",
        headers: jsonHeaders(),
        body: JSON.stringify({ post_ids: selectedIds }),
      });
      const data = await res.json();
      setResult(data);
      onComplete?.();
    } catch {
      setResult({ message: "Failed to start bulk SEO generation.", count: 0 });
    } finally {
      setLoading(false);
    }
  }, [selectedIds, onComplete]);

  return (
    <div className={className}>
      <Button
        type="button"
        variant="outline"
        size="sm"
        onClick={handleBulkGenerate}
        disabled={loading || selectedIds.length === 0}
      >
        {loading ? (
          <Loader2 className="h-3.5 w-3.5 animate-spin" />
        ) : (
          <Sparkles className="h-3.5 w-3.5" />
        )}
        {loading ? "Generating..." : `Generate SEO (${selectedIds.length})`}
      </Button>
      {result && <p className="mt-1 text-xs text-muted-foreground">{result.message}</p>}
    </div>
  );
}

export { BulkSeoButton };
