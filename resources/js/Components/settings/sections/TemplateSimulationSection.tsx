import { useState, useCallback, useEffect } from "react";
import { Section } from "@/Components/shared/section";
import { router, usePage } from "@inertiajs/react";
import { toast } from "@/Components/ui/toast";
import {
  Eye,
  Send,
  FileText,
  Bug,
  CheckCircle2,
  XCircle,
  Monitor,
  Tablet,
  Smartphone,
} from "lucide-react";
import { Button } from "@/Components/ui/button";
import { Input } from "@/Components/ui/input";
import { Label } from "@/Components/ui/label";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/Components/ui/select";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/Components/ui/card";
import { Dialog, DialogContent, DialogHeader, DialogTitle } from "@/Components/ui/dialog";

import { cn } from "@/lib/utils";

interface EmailTemplate {
  label: string;
  group: string;
  sample_data: Record<string, unknown>;
}

interface MailDebug {
  success: boolean;
  mailer: string;
  host: string;
  port: string;
  encryption: string;
  username: string;
  from_email: string;
  from_name: string;
  to: string;
  subject: string;
  template: string;
  error: string | null;
}

export function TemplateSimulationSection({ asAccordion = false }: { asAccordion?: boolean }) {
  const flash = usePage().props.flash as Record<string, string | MailDebug> | undefined;
  const emailTemplates = (usePage().props as unknown as Record<string, unknown>).emailTemplates as
    Record<string, EmailTemplate> | undefined;

  const [selectedTemplate, setSelectedTemplate] = useState("");
  const [previewLoading, setPreviewLoading] = useState(false);
  const [dialogOpen, setDialogOpen] = useState(false);
  const [dialogHtml, setDialogHtml] = useState<string | null>(null);
  const [previewSize, setPreviewSize] = useState<"desktop" | "tablet" | "mobile">("desktop");
  const [testEmail, setTestEmail] = useState("");
  const [sending, setSending] = useState(false);
  const [debugLog, setDebugLog] = useState<MailDebug[]>([]);

  useEffect(() => {
    if (!emailTemplates || selectedTemplate) {
      return;
    }

    const keys = Object.keys(emailTemplates);
    // eslint-disable-next-line react-hooks/set-state-in-effect
    if (keys.length > 0) setSelectedTemplate(keys[0] ?? "");
  }, [emailTemplates, selectedTemplate]);

  useEffect(() => {
    const debug = flash?.mail_debug as MailDebug | undefined;
    if (debug) {
      // eslint-disable-next-line react-hooks/set-state-in-effect
      setDebugLog((prev) => [debug, ...prev].slice(0, 20));
    }
  }, [flash?.mail_debug]);

  if (flash?.status && typeof flash.status === "string")
    toast.add({ title: flash.status, type: "success" });

  const handlePreview = useCallback(async () => {
    if (!selectedTemplate) return;
    setPreviewLoading(true);
    setDialogHtml(null);
    try {
      const url = route("dashboard.admin.settings.email.template-preview", {
        template: selectedTemplate,
        fragment: "1",
      } as Record<string, unknown>);
      const response = await fetch(url);
      if (!response.ok) throw new Error("Failed to load preview");
      const html = await response.text();
      setDialogHtml(html);
      setDialogOpen(true);
    } catch {
      toast.add({ title: "Failed to load template preview", type: "error" });
    } finally {
      setPreviewLoading(false);
    }
  }, [selectedTemplate]);

  const handleSendTest = useCallback(() => {
    if (sending || !testEmail || !selectedTemplate) return;
    setSending(true);
    router.post(
      route("dashboard.admin.settings.email.template-test"),
      { template: selectedTemplate, to: testEmail },
      {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => setSending(false),
        onError: () => setSending(false),
      },
    );
  }, [selectedTemplate, testEmail, sending]);

  const handleSendSimpleTest = useCallback(() => {
    if (sending || !testEmail) return;
    setSending(true);
    router.post(
      route("dashboard.admin.settings.email.test"),
      { to: testEmail },
      {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => setSending(false),
        onError: () => setSending(false),
      },
    );
  }, [testEmail, sending]);

  const groupedTemplates: Record<string, Array<{ value: string; label: string }>> = {};

  if (emailTemplates) {
    for (const [key, tpl] of Object.entries(emailTemplates)) {
      const group = tpl.group || "Other";
      if (!Object.hasOwn(groupedTemplates, group)) groupedTemplates[group] = [];
      const gt = groupedTemplates[group];
      if (gt) gt.push({ value: key, label: tpl.label });
    }
  }

  return (
    <Section
      title="Email Template Simulation"
      description="Preview and test your email notification templates."
      icon={FileText}
      asAccordion={asAccordion}
    >
      <div className="grid gap-6">
        <div className="flex items-end gap-4">
          <div className="grid gap-2 flex-1">
            <Label htmlFor="template-select">Email Template</Label>
            <Select
              value={selectedTemplate}
              onValueChange={(value) => setSelectedTemplate(value ?? "")}
            >
              <SelectTrigger id="template-select" className="w-full">
                <SelectValue placeholder="Select a template..." />
              </SelectTrigger>
              <SelectContent>
                {Object.entries(groupedTemplates).map(([group, templates]) => (
                  <div key={group}>
                    <div className="px-2 py-1.5 text-xs font-medium text-muted-foreground">
                      {group}
                    </div>
                    {templates.map((tpl) => (
                      <SelectItem key={tpl.value} value={tpl.value}>
                        {tpl.label}
                      </SelectItem>
                    ))}
                  </div>
                ))}
              </SelectContent>
            </Select>
          </div>
          <Button
            variant="secondary"
            onClick={handlePreview}
            disabled={previewLoading || !selectedTemplate}
          >
            <Eye className="mr-2 h-4 w-4" />
            {previewLoading ? "Loading..." : "Preview"}
          </Button>
        </div>

        <Card>
          <CardHeader>
            <CardTitle className="text-sm font-medium">Send Test Email</CardTitle>
            <CardDescription className="text-xs">
              Send a sample of this template to an email address to verify delivery.
            </CardDescription>
          </CardHeader>
          <CardContent>
            <div className="flex items-end gap-3">
              <div className="grid gap-2 flex-1">
                <Label htmlFor="sim-test-email">Recipient Email</Label>
                <Input
                  id="sim-test-email"
                  type="email"
                  value={testEmail}
                  onChange={(e) => setTestEmail(e.target.value)}
                  placeholder="your@email.com"
                />
              </div>
              <Button
                onClick={handleSendTest}
                disabled={sending || !testEmail || !selectedTemplate}
              >
                <Send className="mr-2 h-4 w-4" />
                {sending ? "Sending..." : "Send Template"}
              </Button>
              <Button
                variant="outline"
                onClick={handleSendSimpleTest}
                disabled={sending || !testEmail}
              >
                <Bug className="mr-2 h-4 w-4" />
                Simple SMTP Test
              </Button>
            </div>
          </CardContent>
        </Card>

        <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
          <DialogContent
            className="max-w-[calc(100%-2rem)] sm:max-w-[90vw] sm:max-h-[90vh] overflow-y-auto"
            showCloseButton
          >
            <DialogHeader className="pr-10">
              <DialogTitle>
                Preview:{" "}
                {selectedTemplate
                  ? (emailTemplates?.[selectedTemplate]?.label ?? selectedTemplate)
                  : ""}
              </DialogTitle>
              <div className="flex items-center justify-center gap-1 rounded-md border p-0.5 w-fit mx-auto">
                {(["desktop", "tablet", "mobile"] as const).map((size) => {
                  const Icon =
                    size === "desktop" ? Monitor : size === "tablet" ? Tablet : Smartphone;
                  return (
                    <button
                      key={size}
                      type="button"
                      onClick={() => setPreviewSize(size)}
                      className={cn(
                        "rounded px-2 py-1 text-xs font-medium transition-colors",
                        previewSize === size
                          ? "bg-accent text-accent-foreground"
                          : "text-muted-foreground hover:text-foreground",
                      )}
                      aria-label={size.charAt(0).toUpperCase() + size.slice(1)}
                    >
                      <Icon className="h-3.5 w-3.5" />
                    </button>
                  );
                })}
              </div>
            </DialogHeader>
            {dialogHtml && (
              <div className="flex justify-center">
                <div
                  className={cn(
                    "rounded-md border bg-white dark:bg-gray-900 [&_img]:max-w-full overflow-hidden",
                    previewSize === "desktop" && "w-full",
                    previewSize === "tablet" && "w-[600px]",
                    previewSize === "mobile" && "w-[375px]",
                  )}
                  dangerouslySetInnerHTML={{ __html: dialogHtml }}
                />
              </div>
            )}
          </DialogContent>
        </Dialog>

        {debugLog.length > 0 && (
          <Card>
            <CardHeader className="flex flex-row items-center justify-between">
              <div>
                <CardTitle className="text-sm font-medium">Delivery Log</CardTitle>
                <CardDescription className="text-xs">
                  Last {debugLog.length} send attempts — newest first
                </CardDescription>
              </div>
              <Button variant="ghost" size="sm" onClick={() => setDebugLog([])}>
                Clear
              </Button>
            </CardHeader>
            <CardContent>
              <div className="space-y-2">
                {debugLog.map((entry, i) => (
                  <div
                    key={i}
                    className={cn(
                      "rounded-md border p-3 text-xs font-mono",
                      entry.success
                        ? "border-green-200 bg-green-50 dark:border-green-800 dark:bg-green-950/30"
                        : "border-red-200 bg-red-50 dark:border-red-800 dark:bg-red-950/30",
                    )}
                  >
                    <div className="flex items-center gap-2 mb-1.5">
                      {entry.success ? (
                        <CheckCircle2 className="h-3.5 w-3.5 text-green-600 dark:text-green-400" />
                      ) : (
                        <XCircle className="h-3.5 w-3.5 text-red-600 dark:text-red-400" />
                      )}
                      <span
                        className={cn(
                          "font-semibold",
                          entry.success
                            ? "text-green-700 dark:text-green-300"
                            : "text-red-700 dark:text-red-300",
                        )}
                      >
                        {entry.success ? "Delivered" : "Failed"}
                      </span>
                      <span className="text-muted-foreground">—</span>
                      <span className="text-muted-foreground">{entry.template}</span>
                      <span className="text-muted-foreground">→</span>
                      <span className="text-foreground">{entry.to}</span>
                    </div>
                    <div className="grid grid-cols-[auto_1fr] gap-x-3 gap-y-0.5 text-muted-foreground">
                      <span>Subject:</span>
                      <span className="text-foreground">{entry.subject}</span>
                      <span>Mailer:</span>
                      <span className="text-foreground">{entry.mailer}</span>
                      <span>SMTP Host:</span>
                      <span className="text-foreground">
                        {entry.host}:{entry.port}
                      </span>
                      <span>Encryption:</span>
                      <span className="text-foreground">{entry.encryption}</span>
                      <span>Username:</span>
                      <span className="text-foreground">{entry.username}</span>
                      <span>From:</span>
                      <span className="text-foreground">
                        {entry.from_name} &lt;{entry.from_email}&gt;
                      </span>
                    </div>
                    {entry.error && (
                      <div className="mt-1.5 rounded bg-red-100 p-1.5 text-red-700 dark:bg-red-900/40 dark:text-red-300">
                        Error: {entry.error}
                      </div>
                    )}
                  </div>
                ))}
              </div>
            </CardContent>
          </Card>
        )}
      </div>
    </Section>
  );
}
