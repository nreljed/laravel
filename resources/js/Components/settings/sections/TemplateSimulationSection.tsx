import { useState, useCallback, useEffect } from "react";
import { Section } from "@/Components/section";
import { router, usePage } from "@inertiajs/react";
import { toast } from "sonner";
import { Eye, Send, FileText, Bug, CheckCircle2, XCircle } from "lucide-react";
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
  const [previewHtml, setPreviewHtml] = useState<string | null>(null);
  const [previewLoading, setPreviewLoading] = useState(false);
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

  if (flash?.status && typeof flash.status === "string") toast.success(flash.status);

  const handlePreview = useCallback(() => {
    if (!selectedTemplate) return;
    setPreviewLoading(true);
    router.get(
      route("dashboard.admin.settings.email.template-preview", {
        template: selectedTemplate,
      } as Record<string, unknown>),
      {},
      {
        preserveScroll: true,
        preserveState: true,
        onSuccess: (page) => {
          const html = (page.props as unknown as Record<string, unknown>).previewHtml as
            string | undefined;
          if (html) setPreviewHtml(html);
          else toast.error("Failed to load template preview");
          setPreviewLoading(false);
        },
        onError: () => {
          toast.error("Failed to load template preview");
          setPreviewLoading(false);
        },
      },
    );
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
            <Select value={selectedTemplate} onValueChange={setSelectedTemplate}>
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

        {previewHtml && (
          <Card>
            <CardHeader>
              <CardTitle className="text-sm font-medium">Preview</CardTitle>
            </CardHeader>
            <CardContent>
              <div
                className="rounded-md border bg-white p-4 dark:bg-gray-900 [&_img]:max-w-full"
                dangerouslySetInnerHTML={{ __html: previewHtml }}
              />
            </CardContent>
          </Card>
        )}

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
                        <CheckCircle2 className="h-3.5 w-3.5 text-green-600" />
                      ) : (
                        <XCircle className="h-3.5 w-3.5 text-red-600" />
                      )}
                      <span
                        className={cn(
                          "font-semibold",
                          entry.success ? "text-green-700" : "text-red-700",
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
