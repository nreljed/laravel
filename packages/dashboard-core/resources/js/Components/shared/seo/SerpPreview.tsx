import { cn } from "@/lib/utils";

interface SerpPreviewProps {
  title: string;
  metaDescription: string;
  url?: string;
  className?: string;
  variant?: "desktop" | "mobile";
}

function SerpPreview({
  title,
  metaDescription,
  url = "https://example.com/blog/",
  className,
  variant = "desktop",
}: SerpPreviewProps) {
  const displayTitle = title || "Page Title";
  const displayDescription = metaDescription || "Meta description will appear here...";

  const isMobile = variant === "mobile";
  const maxTitleWidth = isMobile ? 50 : 60;
  const maxDescWidth = isMobile ? 100 : 155;

  const titleTruncated = displayTitle.length > maxTitleWidth;
  const descTruncated = displayDescription.length > maxDescWidth;

  return (
    <div
      className={cn(
        "rounded-lg border border-border bg-white p-4 dark:bg-gray-900",
        isMobile ? "max-w-[320px]" : "max-w-[600px]",
        className,
      )}
    >
      <div className="space-y-1">
        <div className="flex items-center gap-2">
          <div className="h-6 w-6 rounded-full bg-gray-200 dark:bg-gray-700" />
          <div className="min-w-0 flex-1">
            <p className="truncate text-sm text-[#202124] dark:text-[#e8eaed]">{url}</p>
          </div>
        </div>

        <p
          className={cn(
            "text-lg font-medium leading-snug",
            "text-[#1a0dab] dark:text-[#8ab4f8]",
            titleTruncated && "text-red-600 dark:text-red-400",
          )}
        >
          {displayTitle}
          {titleTruncated && (
            <span className="ml-1 text-xs text-red-500 dark:text-red-400">
              ({displayTitle.length}/{maxTitleWidth})
            </span>
          )}
        </p>

        <p
          className={cn(
            "text-sm leading-relaxed",
            "text-[#4d5156] dark:text-[#969ba1]",
            descTruncated && "text-orange-600 dark:text-orange-400",
          )}
        >
          {displayDescription}
          {descTruncated && (
            <span className="ml-1 text-xs text-orange-500 dark:text-orange-400">
              ({displayDescription.length}/{maxDescWidth})
            </span>
          )}
        </p>
      </div>
    </div>
  );
}

export { SerpPreview };
