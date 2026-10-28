import { useState } from "react";
import { router, usePage } from "@inertiajs/react";
import { toast } from "../../ui/toast";
import { Section, SectionField } from "../../shared/section";
import { SettingsAccordionItem } from "../../shared/settings-accordion";
import { Input } from "../../ui/input";
import { Switch } from "../../ui/switch";
import { Label } from "../../ui/label";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "../../ui/select";
import { Badge } from "../../ui/badge";
import { Mail, Server, Settings as SettingsIcon, Eye, EyeOff, ShieldCheck } from "lucide-react";

interface SmtpSectionProps {
  asAccordion?: boolean;
}

export function SmtpSection({ asAccordion }: SmtpSectionProps) {
  const page = usePage();
  const emailSettings = (page.props.emailSettings ?? {}) as Record<string, unknown>;

  const [data, setData] = useState({
    mailer: (emailSettings.mailer as string) ?? "smtp",
    smtp_host: (emailSettings.smtp_host as string) ?? "",
    smtp_port: String(emailSettings.smtp_port ?? "587"),
    smtp_encryption: (emailSettings.smtp_encryption as string) ?? "tls",
    smtp_auth: Boolean(emailSettings.smtp_auth ?? true),
    auto_tls: Boolean(emailSettings.auto_tls ?? true),
    smtp_username: (emailSettings.smtp_username as string) ?? "",
    smtp_password: (emailSettings.smtp_password as string) ?? "",
    from_email: (emailSettings.from_email as string) ?? "",
    from_name: (emailSettings.from_name as string) ?? "",
    reply_to_email: (emailSettings.reply_to_email as string) ?? "",
    return_path: (emailSettings.return_path as string) ?? "",
    use_from_as_return_path: Boolean(emailSettings.use_from_as_return_path ?? false),
    force_from_email: Boolean(emailSettings.force_from_email ?? true),
    force_from_name: Boolean(emailSettings.force_from_name ?? true),
  });
  const [loading, setLoading] = useState(false);
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [showPassword, setShowPassword] = useState(false);
  const hasEncryptedPassword = emailSettings.has_encrypted_password === true;

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (loading) return;
    setLoading(true);
    setErrors({});

    const payload = {
      ...data,
      smtp_port: Number(data.smtp_port) || 587,
      smtp_auth: data.smtp_auth ? "1" : "0",
      auto_tls: data.auto_tls ? "1" : "0",
      force_from_email: data.force_from_email ? "1" : "0",
      force_from_name: data.force_from_name ? "1" : "0",
      use_from_as_return_path: data.use_from_as_return_path ? "1" : "0",
    };

    router.put(route("dashboard.admin.settings.email.update"), payload, {
      preserveScroll: true,
      preserveState: true,
      onSuccess: () => {
        toast.add({ title: "SMTP settings saved.", type: "success" });
        setLoading(false);
      },
      onError: (errs) => {
        setErrors(errs);
        toast.add({ title: "Failed to save SMTP settings.", type: "error" });
        setLoading(false);
      },
    });
  };

  return (
    <Section
      title="SMTP Configuration"
      description="Configure the mail server used for outgoing emails."
      icon={Server}
      onSubmit={handleSubmit}
      loading={loading}
      fieldErrors={errors as unknown as Record<string, string[]>}
      submitLabel="Save SMTP Settings"
      asAccordion={asAccordion}
      accordionDefaultOpen
    >
      <div className="md:col-span-2">
        <div className="flex flex-wrap items-center gap-2">
          {hasEncryptedPassword && (
            <Badge
              variant="secondary"
              className="gap-1 bg-warning/20 text-warning-foreground text-xs font-medium"
            >
              <ShieldCheck className="h-3 w-3" />
              SMTP password is encrypted at rest
            </Badge>
          )}
        </div>
      </div>

      <div className="md:col-span-2">
        <SettingsAccordionItem value="sender" icon={Mail} title="Sender Settings" defaultOpen>
          <div className="space-y-4">
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <SectionField
                label="SMTP From Email"
                htmlFor="from_email"
                description="Sender email address"
              >
                <Input
                  id="from_email"
                  type="email"
                  value={data.from_email}
                  onChange={(e) => setData((p) => ({ ...p, from_email: e.target.value }))}
                  placeholder="noreply@example.com"
                />
              </SectionField>
              <SectionField
                label="SMTP From Name"
                htmlFor="from_name"
                description="Sender display name"
              >
                <Input
                  id="from_name"
                  value={data.from_name}
                  onChange={(e) => setData((p) => ({ ...p, from_name: e.target.value }))}
                  placeholder="My App"
                />
              </SectionField>
            </div>

            <div className="flex items-center gap-2">
              <Switch
                id="force_from_email"
                checked={data.force_from_email}
                onCheckedChange={(c) => setData((p) => ({ ...p, force_from_email: c }))}
              />
              <div className="flex flex-col">
                <Label htmlFor="force_from_email" className="cursor-pointer text-sm font-medium">
                  Force From Email
                </Label>
                <p className="text-xs text-muted-foreground">
                  Override the sender email address on all outgoing messages.
                </p>
              </div>
            </div>

            <div className="flex items-center gap-2">
              <Switch
                id="force_from_name"
                checked={data.force_from_name}
                onCheckedChange={(c) => setData((p) => ({ ...p, force_from_name: c }))}
              />
              <div className="flex flex-col">
                <Label htmlFor="force_from_name" className="cursor-pointer text-sm font-medium">
                  Force From Name
                </Label>
                <p className="text-xs text-muted-foreground">
                  Override the sender name on all outgoing messages.
                </p>
              </div>
            </div>
          </div>
        </SettingsAccordionItem>
      </div>

      <div className="md:col-span-2">
        <SettingsAccordionItem value="smtp" icon={Server} title="SMTP Configuration" defaultOpen>
          <div className="space-y-4">
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <SectionField label="Mailer" htmlFor="mailer" description="Mail driver to use">
                <Select
                  value={data.mailer}
                  onValueChange={(v) => setData((p) => ({ ...p, mailer: v ?? "" }))}
                >
                  <SelectTrigger id="mailer">
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="smtp">SMTP</SelectItem>
                    <SelectItem value="sendmail">Sendmail</SelectItem>
                    <SelectItem value="mail">PHP Mail</SelectItem>
                    <SelectItem value="log">Log (Testing)</SelectItem>
                  </SelectContent>
                </Select>
              </SectionField>
              <SectionField label="SMTP Host" htmlFor="smtp_host" description="e.g. smtp.gmail.com">
                <Input
                  id="smtp_host"
                  value={data.smtp_host}
                  onChange={(e) => setData((p) => ({ ...p, smtp_host: e.target.value }))}
                  placeholder="smtp.example.com"
                />
              </SectionField>
            </div>

            {data.mailer === "smtp" && (
              <>
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <SectionField
                    label="SMTP Port"
                    htmlFor="smtp_port"
                    description="Common: 587 (TLS) or 465 (SSL)"
                  >
                    <Input
                      id="smtp_port"
                      type="number"
                      min={1}
                      max={65_535}
                      value={data.smtp_port}
                      onChange={(e) => setData((p) => ({ ...p, smtp_port: e.target.value }))}
                    />
                  </SectionField>
                  <SectionField label="Encryption" htmlFor="smtp_encryption">
                    <Select
                      value={data.smtp_encryption}
                      onValueChange={(v) => setData((p) => ({ ...p, smtp_encryption: v ?? "" }))}
                    >
                      <SelectTrigger id="smtp_encryption">
                        <SelectValue />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem value="tls">TLS</SelectItem>
                        <SelectItem value="ssl">SSL</SelectItem>
                        <SelectItem value="none">NONE</SelectItem>
                      </SelectContent>
                    </Select>
                  </SectionField>
                </div>

                <SectionField label="Authentication" htmlFor="smtp_auth">
                  <div className="flex items-center gap-2">
                    <Switch
                      id="smtp_auth"
                      checked={data.smtp_auth}
                      onCheckedChange={(c) => setData((p) => ({ ...p, smtp_auth: c }))}
                    />
                    <Label htmlFor="smtp_auth" className="cursor-pointer font-normal">
                      {data.smtp_auth ? "Required" : "Not required"}
                    </Label>
                  </div>
                </SectionField>

                <SectionField
                  label="Auto TLS"
                  htmlFor="auto_tls"
                  description="Automatically handle TLS when the server does not advertise it"
                >
                  <div className="flex items-center gap-2">
                    <Switch
                      id="auto_tls"
                      checked={data.auto_tls}
                      onCheckedChange={(c) => setData((p) => ({ ...p, auto_tls: c }))}
                    />
                    <Label htmlFor="auto_tls" className="cursor-pointer font-normal">
                      {data.auto_tls ? "Enabled (recommended)" : "Disabled"}
                    </Label>
                  </div>
                </SectionField>

                {data.smtp_auth && (
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <SectionField label="SMTP Username" htmlFor="smtp_username">
                      <Input
                        id="smtp_username"
                        value={data.smtp_username}
                        onChange={(e) => setData((p) => ({ ...p, smtp_username: e.target.value }))}
                        autoComplete="off"
                      />
                    </SectionField>
                    <SectionField
                      label="SMTP Password"
                      htmlFor="smtp_password"
                      description={
                        data.smtp_password === "********"
                          ? "Leave empty to keep the current password."
                          : undefined
                      }
                    >
                      <div className="relative">
                        <Input
                          id="smtp_password"
                          type={showPassword ? "text" : "password"}
                          value={data.smtp_password}
                          onChange={(e) => setData((p) => ({ ...p, smtp_password: e.target.value }))}
                          autoComplete="off"
                          placeholder={
                            data.smtp_password === "********"
                              ? "•••••••• (unchanged)"
                              : "Enter password"
                          }
                          className="pr-10"
                        />
                        <button
                          type="button"
                          className="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                          onClick={() => setShowPassword((s) => !s)}
                          aria-label={showPassword ? "Hide password" : "Show password"}
                        >
                          {showPassword ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                        </button>
                      </div>
                      {hasEncryptedPassword && (
                        <p className="text-xs text-muted-foreground mt-1">
                          Encrypted with Laravel's application key before storing.
                        </p>
                      )}
                    </SectionField>
                  </div>
                )}
              </>
            )}
          </div>
        </SettingsAccordionItem>
      </div>

      <div className="md:col-span-2">
        <SettingsAccordionItem value="advanced" icon={SettingsIcon} title="Advanced">
          <div className="space-y-4">
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <SectionField
                label="Reply-To Email"
                htmlFor="reply_to_email"
                description="Optional reply-to address"
              >
                <Input
                  id="reply_to_email"
                  type="email"
                  value={data.reply_to_email}
                  onChange={(e) => setData((p) => ({ ...p, reply_to_email: e.target.value }))}
                  placeholder="support@example.com"
                />
              </SectionField>
              <SectionField
                label="Return Path"
                htmlFor="return_path"
                description={
                  data.use_from_as_return_path
                    ? "Uses the From Email as the bounce address"
                    : "Bounce/return address"
                }
              >
                <Input
                  id="return_path"
                  type="email"
                  value={data.return_path}
                  onChange={(e) => setData((p) => ({ ...p, return_path: e.target.value }))}
                  disabled={data.use_from_as_return_path}
                />
              </SectionField>
            </div>

            <div className="flex items-center justify-between gap-4 rounded-md border p-3">
              <div className="flex flex-col">
                <Label htmlFor="use_from_as_return_path" className="cursor-pointer text-sm font-medium">
                  Use From Email as Return-Path
                </Label>
                <span className="text-xs text-muted-foreground">
                  Automatically set the return-path (bounce address) to match the From Email.
                </span>
              </div>
              <Switch
                id="use_from_as_return_path"
                checked={data.use_from_as_return_path}
                onCheckedChange={(c) => setData((p) => ({ ...p, use_from_as_return_path: c }))}
              />
            </div>
          </div>
        </SettingsAccordionItem>
      </div>
    </Section>
  );
}