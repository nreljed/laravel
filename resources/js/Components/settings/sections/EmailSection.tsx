import { Section, SectionField } from "@/Components/section";
import { Input } from "@/Components/ui/input";
import { Switch } from "@/Components/ui/switch";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/Components/ui/select";
import { router, usePage } from "@inertiajs/react";
import { toast } from "sonner";
import { useState, useEffect } from "react";
import { Mail, Send, Shield, Info } from "lucide-react";
import { Button } from "@/Components/ui/button";
import { Label } from "@/Components/ui/label";
import { Badge } from "@/Components/ui/badge";
import { Tooltip, TooltipContent, TooltipTrigger } from "@/Components/ui/tooltip";

interface EmailSettings {
  [key: string]: string | number | boolean | undefined;
  mailer: string;
  smtp_host: string;
  smtp_port: number;
  smtp_encryption: string;
  smtp_auth: boolean;
  auto_tls: boolean;
  smtp_username: string;
  smtp_password: string;
  from_email: string;
  from_name: string;
  reply_to_email: string;
  return_path: string;
  use_from_as_return_path: boolean;
  force_from_email: boolean;
  force_from_name: boolean;
  smtp_allow_self_signed: boolean;
  has_encrypted_password?: boolean;
}

export function EmailSection({ asAccordion = false }: { asAccordion?: boolean }) {
  const flash = usePage().props.flash as Record<string, string> | undefined;
  const [saving, setSaving] = useState(false);
  const [sending, setSending] = useState(false);
  const [settings, setSettings] = useState<EmailSettings>({
    mailer: "smtp",
    smtp_host: "",
    smtp_port: 587,
    smtp_encryption: "tls",
    smtp_auth: true,
    auto_tls: true,
    smtp_username: "",
    smtp_password: "",
    from_email: "",
    from_name: "",
    reply_to_email: "",
    return_path: "",
    use_from_as_return_path: false,
    force_from_email: true,
    force_from_name: true,
    smtp_allow_self_signed: false,
    has_encrypted_password: false,
  });
  const [testEmail, setTestEmail] = useState("");

  if (flash?.status) toast.success(flash.status);

  useEffect(() => {
    router.reload({
      only: ["emailSettings"],
      onSuccess: (page) => {
        const data = (page.props as unknown as Record<string, unknown>).emailSettings as
          EmailSettings | undefined;
        if (data) setSettings(data);
      },
    });
  }, []);

  const handleChange = (field: string, value: string | boolean) => {
    setSettings((prev) => ({ ...prev, [field]: value }));
  };

  const handleSave = (e: React.FormEvent) => {
    e.preventDefault();
    if (saving) return;
    setSaving(true);
    router.put(
      route("dashboard.admin.settings.email.update"),
      // eslint-disable-next-line @typescript-eslint/no-explicit-any
      settings as any,
      {
        preserveScroll: true,
        onSuccess: () => {
          toast.success("Email settings saved.");
          setSaving(false);
        },
        onError: () => {
          toast.error("Failed to save email settings.");
          setSaving(false);
        },
      },
    );
  };

  const handleSendTest = () => {
    if (sending) return;
    setSending(true);
    router.post(
      route("dashboard.admin.settings.email.test"),
      { to: testEmail },
      {
        preserveScroll: true,
        onSuccess: () => {
          toast.success("Test email sent.");
          setSending(false);
        },
        onError: () => {
          toast.error("Failed to send test email.");
          setSending(false);
        },
      },
    );
  };

  return (
    <Section
      title="SMTP Configuration"
      description="Configure your outgoing mail server. Only SMTP is supported."
      icon={Mail}
      asAccordion={asAccordion}
      onSubmit={handleSave}
      loading={saving}
      submitLabel="Save SMTP Settings"
    >
      <div className="space-y-8">
        <div>
          <h4 className="text-sm font-medium mb-1">Sender Information</h4>
          <p className="text-xs text-muted-foreground mb-4">
            These details appear in the "From" field of every outgoing email.
          </p>
          <div className="grid grid-cols-2 gap-4">
            <SectionField
              label="From Email"
              htmlFor="from_email"
              description="The email address emails are sent from."
            >
              <Input
                id="from_email"
                type="email"
                value={settings.from_email}
                onChange={(e) => handleChange("from_email", e.target.value)}
                placeholder="noreply@example.com"
              />
            </SectionField>
            <SectionField
              label="From Name"
              htmlFor="from_name"
              description="A recognizable name for recipients."
            >
              <Input
                id="from_name"
                value={settings.from_name}
                onChange={(e) => handleChange("from_name", e.target.value)}
                placeholder="Your Company"
              />
            </SectionField>
          </div>
          <div className="flex items-center gap-4 mt-2">
            <label className="flex items-center gap-2 cursor-pointer">
              <Switch
                checked={settings.force_from_email}
                onCheckedChange={(v) => handleChange("force_from_email", v)}
              />
              <span className="text-sm">Force From Email</span>
            </label>
            <Tooltip>
              <TooltipTrigger>
                <Info className="h-3.5 w-3.5 text-muted-foreground" />
              </TooltipTrigger>
              <TooltipContent>
                Override the sender email even if the sending service provides one.
              </TooltipContent>
            </Tooltip>
            <label className="flex items-center gap-2 cursor-pointer">
              <Switch
                checked={settings.force_from_name}
                onCheckedChange={(v) => handleChange("force_from_name", v)}
              />
              <span className="text-sm">Force From Name</span>
            </label>
            <Tooltip>
              <TooltipTrigger>
                <Info className="h-3.5 w-3.5 text-muted-foreground" />
              </TooltipTrigger>
              <TooltipContent>
                Override the sender name even if the sending service provides one.
              </TooltipContent>
            </Tooltip>
          </div>
        </div>

        <div>
          <h4 className="text-sm font-medium mb-1">SMTP Server</h4>
          <p className="text-xs text-muted-foreground mb-4">
            Your SMTP provider's connection details.
          </p>
          <div className="grid grid-cols-3 gap-4">
            <SectionField label="SMTP Host" htmlFor="smtp_host" description="e.g. smtp.gmail.com">
              <Input
                id="smtp_host"
                value={settings.smtp_host}
                onChange={(e) => handleChange("smtp_host", e.target.value)}
                placeholder="smtp.example.com"
              />
            </SectionField>
            <SectionField label="Port" htmlFor="smtp_port" description="587 (TLS) or 465 (SSL).">
              <Input
                id="smtp_port"
                type="number"
                value={settings.smtp_port}
                onChange={(e) => handleChange("smtp_port", String(parseInt(e.target.value) || 587))}
              />
            </SectionField>
            <SectionField
              label="Encryption"
              htmlFor="smtp_encryption"
              description="TLS is recommended."
            >
              <Select
                value={settings.smtp_encryption}
                onValueChange={(v) => handleChange("smtp_encryption", v)}
              >
                <SelectTrigger id="smtp_encryption">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="tls">TLS (recommended)</SelectItem>
                  <SelectItem value="ssl">SSL</SelectItem>
                  <SelectItem value="none">None</SelectItem>
                </SelectContent>
              </Select>
            </SectionField>
          </div>
        </div>

        <div>
          <h4 className="text-sm font-medium mb-1">Authentication</h4>
          <p className="text-xs text-muted-foreground mb-4">Credentials for your SMTP server.</p>
          <div className="grid grid-cols-2 gap-4">
            <SectionField
              label="SMTP Username"
              htmlFor="smtp_username"
              description="Your SMTP account username or email."
            >
              <Input
                id="smtp_username"
                value={settings.smtp_username}
                onChange={(e) => handleChange("smtp_username", e.target.value)}
                placeholder="user@example.com"
              />
            </SectionField>
            <SectionField
              label="SMTP Password"
              htmlFor="smtp_password"
              description={
                settings.has_encrypted_password
                  ? "Stored encrypted. Leave empty to keep current password."
                  : "Your SMTP account password."
              }
            >
              <div className="relative">
                <Input
                  id="smtp_password"
                  type="password"
                  value={settings.smtp_password === "********" ? "" : settings.smtp_password}
                  onChange={(e) => handleChange("smtp_password", e.target.value)}
                  placeholder={
                    settings.smtp_password === "********"
                      ? "Password set (leave empty to keep)"
                      : ""
                  }
                />
                {settings.has_encrypted_password && (
                  <Badge
                    variant="outline"
                    className="absolute right-2 top-1/2 -translate-y-1/2 h-5 gap-1 text-xs pointer-events-none"
                  >
                    <Shield className="h-3 w-3" />
                    Encrypted
                  </Badge>
                )}
              </div>
            </SectionField>
          </div>
          <div className="flex items-center gap-4 mt-2">
            <label className="flex items-center gap-2 cursor-pointer">
              <Switch
                checked={settings.smtp_auth}
                onCheckedChange={(v) => handleChange("smtp_auth", v)}
              />
              <span className="text-sm">Use Authentication</span>
            </label>
            <Tooltip>
              <TooltipTrigger>
                <Info className="h-3.5 w-3.5 text-muted-foreground" />
              </TooltipTrigger>
              <TooltipContent>
                Disable if your SMTP server does not require login credentials.
              </TooltipContent>
            </Tooltip>
            <label className="flex items-center gap-2 cursor-pointer">
              <Switch
                checked={settings.auto_tls}
                onCheckedChange={(v) => handleChange("auto_tls", v)}
              />
              <span className="text-sm">Auto TLS</span>
            </label>
            <Tooltip>
              <TooltipTrigger>
                <Info className="h-3.5 w-3.5 text-muted-foreground" />
              </TooltipTrigger>
              <TooltipContent>
                Automatically upgrade to TLS if the server supports it.
              </TooltipContent>
            </Tooltip>
            <label className="flex items-center gap-2 cursor-pointer">
              <Switch
                checked={settings.smtp_allow_self_signed}
                onCheckedChange={(v) => handleChange("smtp_allow_self_signed", v)}
              />
              <span className="text-sm">Allow Self-Signed</span>
            </label>
            <Tooltip>
              <TooltipTrigger>
                <Info className="h-3.5 w-3.5 text-muted-foreground" />
              </TooltipTrigger>
              <TooltipContent>
                Skip CA verification when the SMTP server uses a self-signed or internal
                certificate.
              </TooltipContent>
            </Tooltip>
          </div>
        </div>

        <div>
          <h4 className="text-sm font-medium mb-1">Advanced</h4>
          <p className="text-xs text-muted-foreground mb-4">
            Optional reply-to and bounce handling.
          </p>
          <div className="grid grid-cols-2 gap-4">
            <SectionField
              label="Reply-To Email"
              htmlFor="reply_to_email"
              description="Optional: replies go here instead of From address."
            >
              <Input
                id="reply_to_email"
                type="email"
                value={settings.reply_to_email}
                onChange={(e) => handleChange("reply_to_email", e.target.value)}
                placeholder="support@example.com"
              />
            </SectionField>
            <SectionField
              label="Return-Path (Bounce)"
              htmlFor="return_path"
              description="Optional: bounced emails go here."
            >
              <Input
                id="return_path"
                type="email"
                value={settings.return_path}
                onChange={(e) => handleChange("return_path", e.target.value)}
                placeholder="bounces@example.com"
              />
            </SectionField>
          </div>
          <div className="flex items-center gap-4 mt-2">
            <label className="flex items-center gap-2 cursor-pointer">
              <Switch
                checked={settings.use_from_as_return_path}
                onCheckedChange={(v) => handleChange("use_from_as_return_path", v)}
              />
              <span className="text-sm">Use From Email as Return-Path</span>
            </label>
            <Tooltip>
              <TooltipTrigger>
                <Info className="h-3.5 w-3.5 text-muted-foreground" />
              </TooltipTrigger>
              <TooltipContent>
                Automatically use the From address as the bounce address.
              </TooltipContent>
            </Tooltip>
          </div>
        </div>

        <div className="border-t border-border pt-6">
          <div className="flex items-center gap-2 text-sm font-medium mb-1">
            <Send className="h-4 w-4" />
            Send Test Email
          </div>
          <p className="text-xs text-muted-foreground mb-3">
            Send a test email to verify your SMTP configuration before using it in production.
          </p>
          <div className="flex items-end gap-3">
            <div className="grid gap-2 flex-1">
              <Label htmlFor="test_email">Recipient Email</Label>
              <Input
                id="test_email"
                type="email"
                value={testEmail}
                onChange={(e) => setTestEmail(e.target.value)}
                placeholder="your@email.com"
              />
            </div>
            <Button variant="secondary" onClick={handleSendTest} disabled={sending}>
              {sending ? "Sending..." : "Send Test"}
            </Button>
          </div>
        </div>
      </div>
    </Section>
  );
}
