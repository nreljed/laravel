import { useMemo, useState } from "react";
import { toast } from "../../ui/toast";
import { Section, SectionField } from "../../shared/section";
import { Button } from "../../ui/button";
import { Input } from "../../ui/input";
import { Textarea } from "../../ui/textarea";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "../../ui/select";
import { getCsrfToken } from "../../../lib/gallery-utils";
import { FlaskConical, Loader2, Mail, Send, Trash2, Users } from "lucide-react";

interface NotificationType {
  label: string;
  description?: string;
  default_channels?: string[];
  default_enabled?: boolean;
  required?: boolean;
  group?: string;
  severity?: string;
}

interface DevelopmentNotificationTestProps {
  notificationTableExists: boolean;
  notificationTypes: Record<string, Record<string, NotificationType>>;
}

const SEVERITY_OPTIONS = ["info", "warning", "urgent", "critical"] as const;

export function DevelopmentNotificationTest({
  notificationTableExists,
  notificationTypes,
}: DevelopmentNotificationTestProps) {
  const [type, setType] = useState("test");
  const [severity, setSeverity] = useState("info");
  const [title, setTitle] = useState("Development notification");
  const [message, setMessage] = useState("This is a test notification from the settings page.");
  const [alsoSendEmail, setAlsoSendEmail] = useState(false);
  const [sending, setSending] = useState<string | null>(null);
  const [result, setResult] = useState<{ ok: boolean; text: string } | null>(null);

  const typeOptions = useMemo(() => {
    const options: { value: string; label: string; group: string }[] = [
      { value: "test", label: "Test (generic)", group: "System" },
    ];
    for (const [group, types] of Object.entries(notificationTypes)) {
      for (const [key, cfg] of Object.entries(types)) {
        options.push({ value: key, label: cfg.label ?? key, group });
      }
    }
    return options;
  }, [notificationTypes]);

  const groupedTypes = useMemo(() => {
    const groups: Record<string, { value: string; label: string }[]> = {};
    for (const t of typeOptions) {
      (groups[t.group] ??= []).push(t);
    }
    return groups;
  }, [typeOptions]);

  const showResult = (ok: boolean, text: string) => {
    setResult({ ok, text });
    toast.add({ title: text, type: ok ? "success" : "error" });
  };

  const request = async (url: string, options: { method: string; body?: unknown }) => {
    const res = await fetch(url, {
      method: options.method,
      headers: {
        "Content-Type": "application/json",
        "X-Requested-With": "XMLHttpRequest",
        "X-CSRF-TOKEN": getCsrfToken(),
      },
      body: options.body !== undefined ? JSON.stringify(options.body) : undefined,
    });

    return { res, data: (await res.json().catch(() => ({}))) as Record<string, unknown> };
  };

  const payload = () => {
    const body: Record<string, string> = {
      type,
      title,
      message,
      severity,
      module: "core",
    };
    if (alsoSendEmail) {
      body.channels = JSON.stringify(["in_app", "email"]);
    }
    return body;
  };

  const sendToMe = async () => {
    if (sending !== null) return;
    setSending("me");
    setResult(null);
    try {
      const { res, data } = await request(route("dashboard.admin.notifications.test-send"), {
        method: "POST",
        body: payload(),
      });
      showResult(res.ok, res.ok ? `Sent!${data.emailQueued ? " + email queued" : ""}` : "Send failed");
    } catch {
      showResult(false, "Network error");
    } finally {
      setSending(null);
    }
  };

  const sendToAll = async () => {
    if (sending !== null) return;
    setSending("all");
    setResult(null);
    try {
      const { res, data } = await request(route("dashboard.admin.notifications.test-send-all"), {
        method: "POST",
        body: payload(),
      });
      showResult(res.ok, res.ok ? `Sent to ${data.count ?? 0} users!` : "Send failed");
    } catch {
      showResult(false, "Network error");
    } finally {
      setSending(null);
    }
  };

  const clearMine = async () => {
    if (sending !== null) return;
    setSending("clear");
    setResult(null);
    try {
      const { res } = await request(route("dashboard.admin.notifications.test-clear"), {
        method: "DELETE",
      });
      showResult(res.ok, res.ok ? "All notifications cleared" : "Clear failed");
    } catch {
      showResult(false, "Network error");
    } finally {
      setSending(null);
    }
  };

  return (
    <Section
      title="Development Notification Test"
      description={
        notificationTableExists
          ? "Send a test notification to yourself or all users via the existing test console endpoints."
          : "The notifications table has not been migrated yet — run `php artisan migrate` first."
      }
      icon={FlaskConical}
      asAccordion
      accordionDefaultOpen={false}
    >
      <div className="space-y-4">
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <SectionField label="Type" htmlFor="devtest-type">
            <Select value={type} onValueChange={(value) => setType(value ?? "test")}>
              <SelectTrigger id="devtest-type">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                {Object.entries(groupedTypes).map(([group, types]) => (
                  <div key={group}>
                    {Object.keys(groupedTypes).length > 1 && (
                      <div className="px-2 py-1.5 text-[11px] font-medium text-muted-foreground uppercase tracking-wider">
                        {group}
                      </div>
                    )}
                    {types.map((t) => (
                      <SelectItem key={t.value} value={t.value}>
                        {t.label}
                      </SelectItem>
                    ))}
                  </div>
                ))}
              </SelectContent>
            </Select>
          </SectionField>
          <SectionField label="Severity" htmlFor="devtest-severity">
            <Select value={severity} onValueChange={(value) => setSeverity(value ?? "info")}>
              <SelectTrigger id="devtest-severity">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                {SEVERITY_OPTIONS.map((s) => (
                  <SelectItem key={s} value={s}>
                    {s.charAt(0).toUpperCase() + s.slice(1)}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </SectionField>
        </div>
        <SectionField label="Title" htmlFor="devtest-title">
          <Input id="devtest-title" value={title} onChange={(e) => setTitle(e.target.value)} />
        </SectionField>
        <SectionField label="Message" htmlFor="devtest-message">
          <Textarea
            id="devtest-message"
            value={message}
            onChange={(e) => setMessage(e.target.value)}
            rows={2}
          />
        </SectionField>
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
            <Button onClick={sendToMe} disabled={sending !== null}>
              {sending === "me" ? (
                <Loader2 className="mr-2 h-4 w-4 animate-spin" />
              ) : (
                <Send className="mr-2 h-4 w-4" />
              )}
              Send to me
            </Button>
            <Button variant="secondary" onClick={sendToAll} disabled={sending !== null}>
              {sending === "all" ? (
                <Loader2 className="mr-2 h-4 w-4 animate-spin" />
              ) : (
                <Users className="mr-2 h-4 w-4" />
              )}
              Send to all
            </Button>
            <Button variant="outline" onClick={clearMine} disabled={sending !== null}>
              {sending === "clear" ? (
                <Loader2 className="mr-2 h-4 w-4 animate-spin" />
              ) : (
                <Trash2 className="mr-2 h-4 w-4" />
              )}
              Clear mine
            </Button>
          </div>
        </div>
        {result && (
          <div
            className={`flex items-start gap-3 rounded-lg border p-3 text-sm ${result.ok ? "border-green-500/20 bg-green-500/5 text-green-700 dark:border-green-400/20 dark:bg-green-400/10 dark:text-green-400" : "border-red-500/20 bg-red-500/5 text-red-700 dark:border-red-400/20 dark:bg-red-400/10 dark:text-red-400"}`}
          >
            <div className="flex-1 min-w-0">
              <p className="font-medium">{result.text}</p>
              <p className="text-xs mt-0.5 opacity-70">
                Type: {type} &middot; Severity: {severity}
              </p>
            </div>
          </div>
        )}
      </div>
    </Section>
  );
}