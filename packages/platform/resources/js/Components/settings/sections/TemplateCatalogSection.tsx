import { usePage } from "@inertiajs/react";
import { Section } from "../../shared/section";
import { Badge } from "../../ui/badge";
import { Button } from "../../ui/button";
import { Card, CardContent } from "../../ui/card";
import { Eye, Inbox, ListChecks, Mail, ShieldAlert, TriangleAlert, Zap } from "lucide-react";

interface CatalogEntry {
  type_key: string | null;
  label: string;
  description: string;
  group: string;
  template_key: string | null;
  template_label: string | null;
  has_template: boolean;
  default_channels: string[];
  default_enabled: boolean;
  required: boolean;
  severity: string;
}

interface TemplateCatalogSectionProps {
  asAccordion?: boolean;
  selectedTemplate?: string;
  onSelectTemplate?: (templateKey: string) => void;
}

const SEVERITY_VARIANT: Record<string, "destructive" | "secondary" | "outline"> = {
  critical: "destructive",
  warning: "secondary",
  info: "outline",
};

function severityVariant(severity: string) {
  return SEVERITY_VARIANT[severity] ?? "outline";
}

function groupEntries(entries: CatalogEntry[]): Record<string, CatalogEntry[]> {
  return entries.reduce<Record<string, CatalogEntry[]>>((groups, entry) => {
    const group = entry.group || "Other";
    (groups[group] ??= []).push(entry);

    return groups;
  }, {});
}

export function TemplateCatalogSection({
  asAccordion,
  selectedTemplate,
  onSelectTemplate,
}: TemplateCatalogSectionProps) {
  const catalog = (usePage().props.emailCatalog as CatalogEntry[] | undefined) ?? [];

  if (catalog.length === 0) {
    return null;
  }

  const grouped = groupEntries(catalog);

  return (
    <Section
      title="Email Catalog"
      description="Every notification type and the email it sends. Select a template to preview it below."
      icon={ListChecks}
      asAccordion={asAccordion}
    >
      <div className="grid gap-6">
        {Object.entries(grouped).map(([group, entries]) => (
          <Card key={group}>
            <CardContent className="space-y-0 divide-y p-0">
              {entries.map((entry) => {
                const previewable = Boolean(entry.template_key && entry.has_template);
                const isSelected = Boolean(entry.template_key) && entry.template_key === selectedTemplate;

                return (
                  <div
                    key={entry.type_key ?? `template:${entry.template_key}`}
                    className="flex flex-col gap-3 p-4 sm:flex-row sm:items-start sm:justify-between"
                  >
                    <div className="min-w-0 space-y-1.5">
                      <div className="flex flex-wrap items-center gap-2">
                        <p className="text-sm font-medium">{entry.label}</p>
                        {entry.type_key && (
                          <code className="rounded bg-muted px-1.5 py-0.5 font-mono text-xs text-muted-foreground">
                            {entry.type_key}
                          </code>
                        )}
                      </div>
                      <p className="text-xs text-muted-foreground">{entry.description}</p>
                      {entry.template_key && (
                        <p className="text-xs text-muted-foreground">
                          Template:{" "}
                          <span className="font-mono text-foreground">{entry.template_key}</span>
                          {entry.template_label ? ` — ${entry.template_label}` : ""}
                        </p>
                      )}
                    </div>

                    <div className="flex shrink-0 flex-wrap items-center gap-1.5">
                      {entry.default_channels.map((channel) => (
                        <Badge key={channel} variant="outline" className="gap-1 text-xs">
                          {channel === "email" ? (
                            <Mail className="h-3 w-3" />
                          ) : (
                            <Inbox className="h-3 w-3" />
                          )}
                          {channel === "email" ? "Email" : "In-app"}
                        </Badge>
                      ))}

                      {entry.required && (
                        <Badge variant="secondary" className="gap-1 text-xs">
                          <ShieldAlert className="h-3 w-3" />
                          Required
                        </Badge>
                      )}

                      {entry.severity !== "info" && (
                        <Badge variant={severityVariant(entry.severity)} className="gap-1 text-xs">
                          <TriangleAlert className="h-3 w-3" />
                          {entry.severity}
                        </Badge>
                      )}

                      {!entry.type_key && (
                        <Badge variant="outline" className="gap-1 text-xs">
                          <Zap className="h-3 w-3" />
                          Not wired
                        </Badge>
                      )}

                      {!entry.has_template && entry.template_key && (
                        <Badge variant="destructive" className="text-xs">
                          Missing template
                        </Badge>
                      )}

                      {previewable && onSelectTemplate && (
                        <Button
                          variant={isSelected ? "default" : "secondary"}
                          size="sm"
                          className="ml-1"
                          onClick={() => onSelectTemplate(entry.template_key as string)}
                        >
                          <Eye className="mr-1.5 h-3.5 w-3.5" />
                          {isSelected ? "Selected" : "Preview"}
                        </Button>
                      )}
                    </div>
                  </div>
                );
              })}
            </CardContent>
          </Card>
        ))}
      </div>
    </Section>
  );
}

export type { CatalogEntry };

export default TemplateCatalogSection;
