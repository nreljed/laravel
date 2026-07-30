import * as React from "react";
import { jsonHeaders } from "@/lib/gallery-utils";
import { route } from "ziggy-js";
import { Button } from "@/Components/ui/button";
import { Badge } from "@/Components/ui/badge";
import { Field, FieldLabel, FieldDescription } from "@/Components/ui/field";
import { SerpPreview } from "@/Components/shared/seo/SerpPreview";
import { SeoScore } from "@/Components/shared/seo/SeoScore";
import { KeywordSuggestions } from "@/Components/shared/seo/KeywordSuggestions";
import { SettingsAccordionItem } from "@/Components/shared/settings-accordion";
import { Search, Sparkles, Globe, AlertTriangle, Info } from "lucide-react";

interface SeoRecommendation {
  field: "title" | "meta_description" | "content" | "structure" | "keyword";
  severity: "critical" | "warning" | "info";
  message: string;
}

interface SeoData {
  title_options: string[];
  meta_description_options: string[];
  primary_keyword: string;
  secondary_keywords: string[];
  seo_score: number;
  recommendations: SeoRecommendation[];
}

interface SeoPanelProps {
  title: string;
  content: string;
  metaTitle: string;
  metaDescription: string;
  ogImage: string;
  keyword: string;
  savedSeoData?: SeoData | null;
  onChange: (_field: string, _value: string) => void;
  onSeoGenerated?: (_seoResponse: SeoData) => void;
  className?: string;
}

const TITLE_MAX = 60;
const DESC_MAX = 155;

const severityIcon = {
  critical: AlertTriangle,
  warning: AlertTriangle,
  info: Info,
} as const;

const severityColor = {
  critical: "text-red-500 dark:text-red-400",
  warning: "text-yellow-500 dark:text-yellow-400",
  info: "text-blue-500 dark:text-blue-400",
} as const;

function SeoPanel({
  title,
  content,
  metaTitle,
  metaDescription,
  keyword,
  savedSeoData,
  onChange,
  onSeoGenerated,
  className,
}: SeoPanelProps) {
  const [seoData, setSeoData] = React.useState<SeoData | null>(savedSeoData ?? null);
  const [loading, setLoading] = React.useState(false);
  const [error, setError] = React.useState<string | null>(null);
  const [isOpen, setIsOpen] = React.useState(true);

  const titleLength = metaTitle.length;
  const descLength = metaDescription.length;

  const handleGenerate = React.useCallback(async () => {
    setLoading(true);
    setError(null);

    try {
      const res = await fetch(route("dashboard.posts.seo.generate") as string, {
        method: "POST",
        headers: jsonHeaders(),
        body: JSON.stringify({
          title,
          content,
          keyword: keyword || undefined,
        }),
      });

      if (!res.ok) {
        let errorMessage = "Failed to generate SEO data.";
        try {
          const errData = await res.json();
          if (errData?.message) errorMessage = errData.message;
        } catch {
          // response was not JSON
        }
        throw new Error(errorMessage);
      }

      const responseData = await res.json();
      const data: SeoData = responseData.seo;
      setSeoData(data);
      onSeoGenerated?.(data);

      if (!metaTitle && data.title_options.length > 0 && data.title_options[0]) {
        onChange("meta_title", data.title_options[0]);
      }
      if (
        !metaDescription &&
        data.meta_description_options.length > 0 &&
        data.meta_description_options[0]
      ) {
        onChange("meta_description", data.meta_description_options[0]);
      }
    } catch (error_) {
      const message = error_ instanceof Error ? error_.message : "Failed to generate SEO data.";
      setError(message);
    } finally {
      setLoading(false);
    }
  }, [title, content, keyword, metaTitle, metaDescription, onChange, onSeoGenerated]);

  const handleSelectTitle = React.useCallback(
    (option: string) => {
      onChange("meta_title", option);
    },
    [onChange],
  );

  const handleSelectDescription = React.useCallback(
    (option: string) => {
      onChange("meta_description", option);
    },
    [onChange],
  );

  const handleSelectKeyword = React.useCallback(
    (kw: string) => {
      onChange("keyword", kw);
    },
    [onChange],
  );

  return (
    <SettingsAccordionItem
      value="seo-metadata"
      icon={Search}
      title="SEO & Metadata"
      description="Search engine optimization settings"
      className={className}
      isOpen={isOpen}
      onOpenChange={setIsOpen}
    >
      <div className="flex flex-col gap-4 sm:flex-row">
        <Field className="flex-1">
          <FieldLabel>Target Keyword</FieldLabel>
          <div className="flex gap-2">
            <input
              type="text"
              value={keyword}
              onChange={(e) => onChange("keyword", e.target.value)}
              placeholder="e.g. cost management software"
              className="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs transition-colors placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
            />
            <Button
              type="button"
              variant="outline"
              size="sm"
              onClick={handleGenerate}
              disabled={loading || !title}
            >
              {loading ? (
                <span className="flex items-center gap-1.5">
                  <span className="h-3 w-3 animate-spin rounded-full border-2 border-current border-t-transparent" />
                  Generating...
                </span>
              ) : (
                <span className="flex items-center gap-1.5">
                  <Sparkles className="h-3.5 w-3.5" />
                  Generate with AI
                </span>
              )}
            </Button>
          </div>
          <FieldDescription>Leave empty and AI will suggest the best keyword</FieldDescription>
        </Field>
      </div>

      {error && (
        <div className="rounded-md bg-destructive/10 p-3 text-sm text-destructive">{error}</div>
      )}

      {seoData && (
        <div className="flex flex-wrap items-center gap-4">
          <SeoScore score={seoData.seo_score} size="lg" />
          <div className="space-y-1">
            <p className="text-sm font-medium">SEO Score: {seoData.seo_score}/100</p>
            <KeywordSuggestions
              keywords={[seoData.primary_keyword, ...seoData.secondary_keywords]}
              activeKeyword={keyword}
              onSelect={handleSelectKeyword}
            />
          </div>
        </div>
      )}

      <div className="grid gap-6 lg:grid-cols-2">
        <div className="space-y-4">
          <Field>
            <FieldLabel>
              Meta Title
              <span
                className={`ml-2 text-xs tabular-nums ${titleLength > TITLE_MAX ? "text-red-500 dark:text-red-400" : "text-muted-foreground"}`}
              >
                {titleLength}/{TITLE_MAX}
              </span>
            </FieldLabel>
            <input
              type="text"
              value={metaTitle}
              onChange={(e) => onChange("meta_title", e.target.value)}
              placeholder="Enter meta title..."
              maxLength={70}
              className="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs transition-colors placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
            />
            {seoData?.title_options && seoData.title_options.length > 0 && (
              <div className="space-y-1.5">
                <p className="text-xs text-muted-foreground">AI Suggestions:</p>
                <div className="flex flex-col gap-1">
                  {seoData.title_options.map((option, i) => (
                    <button
                      key={`title-${option}-${i}`}
                      type="button"
                      onClick={() => handleSelectTitle(option)}
                      className={`flex items-center justify-between rounded-md px-2 py-1.5 text-left text-sm transition-colors hover:bg-accent ${metaTitle === option ? "bg-primary/10 ring-1 ring-primary" : ""}`}
                    >
                      <span className="truncate mr-2">{option}</span>
                      <span className="shrink-0 text-xs text-muted-foreground">
                        {option.length}
                      </span>
                    </button>
                  ))}
                </div>
              </div>
            )}
          </Field>

          <Field>
            <FieldLabel>
              Meta Description
              <span
                className={`ml-2 text-xs tabular-nums ${descLength > DESC_MAX ? "text-red-500" : "text-muted-foreground"}`}
              >
                {descLength}/{DESC_MAX}
              </span>
            </FieldLabel>
            <textarea
              value={metaDescription}
              onChange={(e) => onChange("meta_description", e.target.value)}
              placeholder="Enter meta description..."
              maxLength={200}
              rows={3}
              className="flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs transition-colors placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring min-h-[80px] resize-none"
            />
            {seoData?.meta_description_options && seoData.meta_description_options.length > 0 && (
              <div className="space-y-1.5">
                <p className="text-xs text-muted-foreground">AI Suggestions:</p>
                <div className="flex flex-col gap-1">
                  {seoData.meta_description_options.map((option, i) => (
                    <button
                      key={`desc-${option}-${i}`}
                      type="button"
                      onClick={() => handleSelectDescription(option)}
                      className={`flex items-center justify-between rounded-md px-2 py-1.5 text-left text-sm transition-colors hover:bg-accent ${metaDescription === option ? "bg-primary/10 ring-1 ring-primary" : ""}`}
                    >
                      <span className="truncate mr-2">{option}</span>
                      <span className="shrink-0 text-xs text-muted-foreground">
                        {option.length}
                      </span>
                    </button>
                  ))}
                </div>
              </div>
            )}
          </Field>
        </div>

        <div className="space-y-4">
          <Field>
            <FieldLabel>
              <Globe className="h-4 w-4" />
              SERP Preview
            </FieldLabel>
            <div className="space-y-3">
              <SerpPreview title={metaTitle} metaDescription={metaDescription} variant="desktop" />
              <SerpPreview title={metaTitle} metaDescription={metaDescription} variant="mobile" />
            </div>
          </Field>

          {seoData?.recommendations && seoData.recommendations.length > 0 && (
            <Field>
              <FieldLabel>Recommendations</FieldLabel>
              <div className="space-y-2">
                {seoData.recommendations.map((rec, i) => {
                  const Icon = severityIcon[rec.severity];
                  return (
                    <div
                      key={`rec-${rec.field}-${i}`}
                      className="flex items-start gap-2 rounded-md border border-border p-2.5 text-sm"
                    >
                      <Icon className={`h-4 w-4 shrink-0 mt-0.5 ${severityColor[rec.severity]}`} />
                      <div className="min-w-0 flex-1">
                        <div className="flex items-center gap-2">
                          <Badge variant="outline" className="text-[10px] uppercase">
                            {rec.field}
                          </Badge>
                        </div>
                        <p className="text-muted-foreground mt-0.5">{rec.message}</p>
                      </div>
                    </div>
                  );
                })}
              </div>
            </Field>
          )}
        </div>
      </div>
    </SettingsAccordionItem>
  );
}

export { SeoPanel };
export type { SeoData, SeoRecommendation };
