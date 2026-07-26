import { Badge } from "@/Components/ui/badge";
import { cn } from "@/lib/utils";
import { Hash } from "lucide-react";

interface KeywordSuggestionsProps {
  keywords: string[];
  activeKeyword?: string;
  onSelect?: (_keyword: string) => void;
  className?: string;
}

function KeywordSuggestions({
  keywords,
  activeKeyword,
  onSelect,
  className,
}: KeywordSuggestionsProps) {
  if (keywords.length === 0) {
    return null;
  }

  return (
    <div className={cn("space-y-2", className)}>
      <p className="text-xs font-medium text-muted-foreground uppercase tracking-wide">Keywords</p>
      <div className="flex flex-wrap gap-1.5">
        {keywords.map((keyword) => (
          <Badge
            key={keyword}
            variant={activeKeyword === keyword ? "default" : "outline"}
            className={cn(
              "cursor-pointer transition-colors",
              activeKeyword !== keyword && "hover:bg-accent",
            )}
            onClick={() => onSelect?.(keyword)}
          >
            <Hash className="h-3 w-3" />
            {keyword}
          </Badge>
        ))}
      </div>
    </div>
  );
}

export { KeywordSuggestions };
