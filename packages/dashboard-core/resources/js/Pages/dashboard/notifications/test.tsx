import { useState, useCallback, useMemo } from "react";
import { Head, Link, router, usePage } from "@inertiajs/react";
import type { PageProps } from "@/types";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Button } from "@/Components/ui/button";
import { Input } from "@/Components/ui/input";
import { Textarea } from "@/Components/ui/textarea";
import { Label } from "@/Components/ui/label";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/Components/ui/select";
import { Separator } from "@/Components/ui/separator";
import { SettingsAccordion } from "@/Components/shared/settings-accordion";
import { SystemStatusCards } from "@/Pages/dashboard/notifications/system-status-cards";
import { QuickSendSection } from "@/Pages/dashboard/notifications/quick-send-section";
import {
  Bell,
  BellDot,
  FlaskConical,
  Send,
  Users,
  Trash2,
  Loader2,
  CheckCircle2,
  AlertTriangle,
  Mail,
  Settings,
  ExternalLink,
  ListChecks,
  History,
  X,
} from "lucide-react";

interface TestResult {
  id: string;
  ok: boolean;
  text: string;
  timestamp: string;
  type: string;
}

const TYPE_OPTIONS = [
  { value: "test", label: "Test (generic)" },
  { value: "user.registered", label: "User Registered" },
  { value: "password.changed", label: "Password Changed" },
  { value: "email.changed", label: "Email Changed" },
];

const SEVERITY_OPTIONS = [
  { value: "info", label: "Info" },
  { value: "warning", label: "Warning" },
  { value: "urgent", label: "Urgent" },
  { value: "critical", label: "Critical" },
];

export default function NotificationsTest() {
  const { props } = usePage<
    PageProps<{
      emailConfigured: boolean;
      fromEmail: string;
      fromName: string;
      notificationTableExists: boolean;
      emailTemplates: { value: string; label: string; group: string }[];
    }>
  >();

  const {
    emailConfigured = false,
    fromEmail = "",
    fromName = "",
    notificationTableExists = false,
    emailTemplates = [],
  } = props;

  const [type, setType] = useState("test");
  const [title, setTitle] = useState("Test Notification");
  const [message, setMessage] = useState("This is a test notification from the development tools.");
  const [link, setLink] = useState("");
  const [severity, setSeverity] = useState("info");
  const [alsoSendEmail, setAlsoSendEmail] = useState(false);
  const [selectedTemplate, setSelectedTemplate] = useState("test");
  const [customEmail, setCustomEmail] = useState("");

  const [sending, setSending] = useState<string | null>(null);
  const [result, setResult] = useState<TestResult | null>(null);
  const [resultHistory, setResultHistory] = useState<TestResult[]>([]);
  const [showHistory, setShowHistory] = useState(false);

  const addResult = useCallback((r: TestResult) => {
    setResult(r);
    setResultHistory((prev) => [r, ...prev].slice(0, 20));
  }, []);

  const resetForm = () => {
    setType("test");
    setTitle("Test Notification");
    setMessage("This is a test notification from the development tools.");
    setLink("");
    setSeverity("info");
    setAlsoSendEmail(false);
  };

  const sendToMe = async (overrides?: Record<string, string>) => {
    setSending("me");
    setResult(null);
    const now = new Date();
    try {
      const body: Record<string, string> = {
        type: overrides?.type ?? type,
        title: overrides?.title ?? title,
        message: overrides?.message ?? message,
        link: overrides?.link ?? link,
        severity: overrides?.severity ?? severity,
      };
      if (overrides?.module) {
        body.module = overrides.module;
      }
      if (alsoSendEmail || overrides?.sendEmail) {
        body.channels = JSON.stringify(["in_app", "email"]);
      }
      const data = await new Promise<Record<string, unknown>>((resolve, reject) => {
        router.post(route("dashboard.admin.notifications.test-send"), body, {
          preserveState: true,
          preserveScroll: true,
          onSuccess: (page: { props: Record<string, unknown> }) => resolve(page.props),
          onError: (errors) => reject(errors),
        });
      });
      const msg = data.success
        ? `Sent! ID: ${data.id}${data.emailQueued ? " + email queued" : ""}`
        : `Error: ${data.error || "unknown"}`;
      addResult({
        id: `notif-${Date.now()}`,
        ok: !!data.success,
        text: msg,
        timestamp: now.toISOString(),
        type: overrides?.type ?? type,
      });
    } catch {
      addResult({
        id: `notif-${Date.now()}`,
        ok: false,
        text: "Network error",
        timestamp: now.toISOString(),
        type: "",
      });
    } finally {
      setSending(null);
    }
  };

  const sendToAll = async () => {
    setSending("all");
    setResult(null);
    const now = new Date();
    try {
      const data = await new Promise<Record<string, unknown>>((resolve, reject) => {
        router.post(
          route("dashboard.admin.notifications.test-send-all"),
          { type, title, message, link, severity },
          {
            preserveState: true,
            preserveScroll: true,
            onSuccess: (page: { props: Record<string, unknown> }) => resolve(page.props),
            onError: (errors) => reject(errors),
          },
        );
      });
      const msg = data.success ? `Sent to ${data.count} users!` : `Error: ${data.error}`;
      addResult({
        id: `all-${Date.now()}`,
        ok: !!data.success,
        text: msg,
        timestamp: now.toISOString(),
        type: "broadcast",
      });
    } catch {
      addResult({
        id: `all-${Date.now()}`,
        ok: false,
        text: "Network error",
        timestamp: now.toISOString(),
        type: "broadcast",
      });
    } finally {
      setSending(null);
    }
  };

  const clearMine = async () => {
    setSending("clear");
    setResult(null);
    const now = new Date();
    try {
      const data = await new Promise<Record<string, unknown>>((resolve, reject) => {
        router.delete(route("dashboard.admin.notifications.test-clear"), {
          preserveState: true,
          preserveScroll: true,
          onSuccess: (page: { props: Record<string, unknown> }) => resolve(page.props),
          onError: (errors) => reject(errors),
        });
      });
      const msg = data.success !== false ? "All notifications cleared" : "Error clearing";
      addResult({
        id: `clear-${Date.now()}`,
        ok: data.success !== false,
        text: msg,
        timestamp: now.toISOString(),
        type: "clear",
      });
    } catch {
      addResult({
        id: `clear-${Date.now()}`,
        ok: false,
        text: "Network error",
        timestamp: now.toISOString(),
        type: "clear",
      });
    } finally {
      setSending(null);
    }
  };

  const sendTestEmail = async () => {
    setSending("email");
    setResult(null);
    const now = new Date();
    try {
      const payload: Record<string, unknown> = {};
      if (customEmail) payload.to = customEmail;
      await new Promise<void>((resolve, reject) => {
        // eslint-disable-next-line @typescript-eslint/no-explicit-any
        router.post(route("dashboard.admin.settings.email.test"), payload as any, {
          preserveState: true,
          preserveScroll: true,
          onSuccess: () => resolve(),
          onError: (errors) => reject(errors),
        });
      });
      addResult({
        id: `email-${Date.now()}`,
        ok: true,
        text: "Test email sent!",
        timestamp: now.toISOString(),
        type: "email-test",
      });
    } catch {
      addResult({
        id: `email-${Date.now()}`,
        ok: false,
        text: "Failed to send test email",
        timestamp: now.toISOString(),
        type: "email-test",
      });
    } finally {
      setSending(null);
    }
  };

  const sendTemplateEmail = async () => {
    setSending("template");
    setResult(null);
    const now = new Date();
    try {
      const payload: Record<string, unknown> = { template: selectedTemplate };
      if (customEmail) payload.email = customEmail;
      await new Promise<void>((resolve, reject) => {
        // eslint-disable-next-line @typescript-eslint/no-explicit-any
        router.post(route("dashboard.admin.settings.email.template-test"), payload as any, {
          preserveState: true,
          preserveScroll: true,
          onSuccess: () => resolve(),
          onError: (errors) => reject(errors),
        });
      });
      addResult({
        id: `tmpl-${Date.now()}`,
        ok: true,
        text: "Template email sent!",
        timestamp: now.toISOString(),
        type: "template",
      });
    } catch {
      addResult({
        id: `tmpl-${Date.now()}`,
        ok: false,
        text: "Template email send failed",
        timestamp: now.toISOString(),
        type: "template",
      });
    } finally {
      setSending(null);
    }
  };

  const groupedTemplates = useMemo(() => {
    const groups: Record<string, { value: string; label: string }[]> = {};
    for (const t of emailTemplates) {
      (groups[t.group] ??= []).push(t);
    }
    return groups;
  }, [emailTemplates]);

  return (
    <AuthenticatedLayout header="Development" maxWidth="max-w-4xl">
      <Head title="Notification Test" />

      <div className="space-y-6">
        <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
          <div className="flex items-center gap-3">
            <div className="rounded-lg bg-primary/10 p-2">
              <FlaskConical className="h-5 w-5 text-primary" />
            </div>
            <div>
              <h2 className="text-lg font-semibold text-foreground">
                Notification & Email Test Console
              </h2>
              <p className="text-sm text-muted-foreground">
                Comprehensive testing tools for the notification and email delivery system
              </p>
            </div>
          </div>
          <div className="flex items-center gap-2 shrink-0">
            <Link href={route("dashboard.notifications")}>
              <Button variant="outline" size="sm">
                <ListChecks className="mr-1.5 h-3.5 w-3.5" />
                My Notifications
              </Button>
            </Link>
            <Link href={route("dashboard.admin.settings.email")}>
              <Button variant="outline" size="sm">
                <Settings className="mr-1.5 h-3.5 w-3.5" />
                Email Settings
              </Button>
            </Link>
          </div>
        </div>

        <SystemStatusCards
          notificationTableExists={notificationTableExists}
          emailConfigured={emailConfigured}
          fromEmail={fromEmail}
        />

        <SettingsAccordion
          defaultOpen={true}
          items={[
            {
              value: "custom-form",
              icon: Send,
              title: "Send Custom Notification",
              description: "Compose and send a test notification to yourself or all users.",
              content: (
                <div className="space-y-4">
                  <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div className="space-y-2">
                      <Label htmlFor="type">Type</Label>
                      <Select value={type} onValueChange={(value) => setType(value ?? "")}>
                        <SelectTrigger id="type">
                          <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                          {TYPE_OPTIONS.map((o) => (
                            <SelectItem key={o.value} value={o.value}>
                              {o.label}
                            </SelectItem>
                          ))}
                        </SelectContent>
                      </Select>
                    </div>
                    <div className="space-y-2">
                      <Label htmlFor="severity">Severity</Label>
                      <Select value={severity} onValueChange={(value) => setSeverity(value ?? "")}>
                        <SelectTrigger id="severity">
                          <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                          {SEVERITY_OPTIONS.map((o) => (
                            <SelectItem key={o.value} value={o.value}>
                              {o.label}
                            </SelectItem>
                          ))}
                        </SelectContent>
                      </Select>
                    </div>
                  </div>
                  <div className="space-y-2">
                    <Label htmlFor="title">Title</Label>
                    <Input id="title" value={title} onChange={(e) => setTitle(e.target.value)} />
                  </div>
                  <div className="space-y-2">
                    <Label htmlFor="message">Message</Label>
                    <Textarea
                      id="message"
                      value={message}
                      onChange={(e) => setMessage(e.target.value)}
                      rows={3}
                    />
                  </div>
                  <div className="space-y-2">
                    <Label htmlFor="link">Link (optional)</Label>
                    <Input
                      id="link"
                      value={link}
                      onChange={(e) => setLink(e.target.value)}
                      placeholder="/dashboard/somewhere"
                    />
                  </div>
                  <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <label className="flex items-center gap-2 text-sm cursor-pointer text-foreground">
                      <input
                        type="checkbox"
                        checked={alsoSendEmail}
                        onChange={(e) => setAlsoSendEmail(e.target.checked)}
                        className="h-4 w-4 rounded border-border"
                      />
                      <Mail className="h-3.5 w-3.5 text-muted-foreground" />
                      Also send as email (if template exists)
                    </label>
                    <div className="flex flex-wrap gap-2">
                      <Button onClick={() => sendToMe()} disabled={sending !== null}>
                        {sending === "me" ? (
                          <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                        ) : (
                          <Bell className="mr-2 h-4 w-4" />
                        )}
                        Send to me
                      </Button>
                      <Button variant="secondary" onClick={sendToAll} disabled={sending !== null}>
                        {sending === "all" ? (
                          <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                        ) : (
                          <Users className="mr-2 h-4 w-4" />
                        )}
                        Send to all users
                      </Button>
                      <Button variant="outline" onClick={resetForm} disabled={sending !== null}>
                        Reset
                      </Button>
                      <Button variant="destructive" onClick={clearMine} disabled={sending !== null}>
                        {sending === "clear" ? (
                          <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                        ) : (
                          <Trash2 className="mr-2 h-4 w-4" />
                        )}
                        Clear mine
                      </Button>
                    </div>
                  </div>
                </div>
              ),
            },
            {
              value: "email-testing",
              icon: Mail,
              title: "Email Testing",
              description: emailConfigured
                ? `Send test emails from ${fromEmail}`
                : "Email is not configured — go to Email Settings to set it up.",
              variant: emailConfigured ? ("default" as const) : ("destructive" as const),
              content: (
                <div className="space-y-4">
                  <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div className="space-y-2">
                      <Label htmlFor="custom-email">Send to (optional)</Label>
                      <Input
                        id="custom-email"
                        type="email"
                        value={customEmail}
                        onChange={(e) => setCustomEmail(e.target.value)}
                        placeholder="your@email.com (defaults to you)"
                      />
                    </div>
                    <div className="space-y-2">
                      <Label htmlFor="template">Template</Label>
                      <Select
                        value={selectedTemplate}
                        onValueChange={(value) => setSelectedTemplate(value ?? "")}
                      >
                        <SelectTrigger id="template">
                          <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                          {Object.entries(groupedTemplates).map(([group, templates]) => (
                            <div key={group}>
                              {Object.keys(groupedTemplates).length > 1 && (
                                <div className="px-2 py-1.5 text-[11px] font-medium text-muted-foreground uppercase tracking-wider">
                                  {group}
                                </div>
                              )}
                              {templates.map((t) => (
                                <SelectItem key={t.value} value={t.value}>
                                  {t.label}
                                </SelectItem>
                              ))}
                            </div>
                          ))}
                        </SelectContent>
                      </Select>
                    </div>
                  </div>
                  <div className="flex flex-wrap gap-2">
                    <Button onClick={sendTestEmail} disabled={sending !== null} variant="default">
                      {sending === "email" ? (
                        <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                      ) : (
                        <Mail className="mr-2 h-4 w-4" />
                      )}
                      Send Test Email
                    </Button>
                    <Button
                      onClick={sendTemplateEmail}
                      disabled={sending !== null}
                      variant="secondary"
                    >
                      {sending === "template" ? (
                        <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                      ) : (
                        <ExternalLink className="mr-2 h-4 w-4" />
                      )}
                      Send Template Preview
                    </Button>
                  </div>
                  {emailConfigured && (
                    <p className="text-xs text-muted-foreground">
                      From:{" "}
                      <span className="font-medium text-foreground">
                        {fromName ? `${fromName} <${fromEmail}>` : fromEmail}
                      </span>
                    </p>
                  )}
                </div>
              ),
            },
            {
              value: "quick-send",
              icon: BellDot,
              title: "Quick Send by Module",
              description: "One-click buttons to send realistic notifications grouped by module.",
              content: <QuickSendSection sending={sending} onSend={sendToMe} />,
            },
          ]}
        />

        {result && (
          <div
            className={`flex items-start gap-3 rounded-lg border p-4 text-sm ${result.ok ? "border-green-500/20 bg-green-500/5 text-green-700 dark:border-green-400/20 dark:bg-green-400/10 dark:text-green-400" : "border-red-500/20 bg-red-500/5 text-red-700 dark:border-red-400/20 dark:bg-red-400/10 dark:text-red-400"}`}
          >
            <div className="mt-0.5 shrink-0">
              {result.ok ? (
                <CheckCircle2 className="h-4 w-4" />
              ) : (
                <AlertTriangle className="h-4 w-4" />
              )}
            </div>
            <div className="flex-1 min-w-0">
              <p className="font-medium">{result.text}</p>
              <p className="text-xs mt-0.5 opacity-70">
                {new Date(result.timestamp).toLocaleTimeString()} &middot; {result.type}
              </p>
            </div>
          </div>
        )}

        {resultHistory.length > 1 && (
          <div className="rounded-xl border bg-card">
            <button
              type="button"
              onClick={() => setShowHistory(!showHistory)}
              className="flex w-full items-center justify-between px-4 py-3 text-sm font-medium text-foreground hover:bg-muted/50 transition-colors"
            >
              <div className="flex items-center gap-2">
                <History className="h-4 w-4 text-muted-foreground" />
                Test History ({resultHistory.length})
              </div>
              <span className="text-muted-foreground text-xs">{showHistory ? "Hide" : "Show"}</span>
            </button>
            {showHistory && (
              <div className="border-t border-border divide-y divide-border">
                {resultHistory.map((r) => (
                  <div key={r.id} className="flex items-center gap-3 px-4 py-2 text-xs">
                    <div
                      className={`shrink-0 ${r.ok ? "text-green-600 dark:text-green-400" : "text-red-600 dark:text-red-400"}`}
                    >
                      {r.ok ? <CheckCircle2 className="h-3 w-3" /> : <X className="h-3 w-3" />}
                    </div>
                    <span className="text-foreground flex-1 truncate">{r.text}</span>
                    <span className="text-muted-foreground shrink-0">
                      {new Date(r.timestamp).toLocaleTimeString()}
                    </span>
                    <span className="text-muted-foreground shrink-0">{r.type}</span>
                  </div>
                ))}
              </div>
            )}
          </div>
        )}

        <Separator />
        <p className="text-xs text-muted-foreground text-center">
          These tools are only visible to administrators. In-app notifications respect each user's
          channel preferences; emails use the configured SMTP settings.
        </p>
      </div>
    </AuthenticatedLayout>
  );
}
