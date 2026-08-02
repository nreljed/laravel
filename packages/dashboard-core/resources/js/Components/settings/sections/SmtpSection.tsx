import { useState } from "react";
import { router, usePage } from "@inertiajs/react";
import { toast } from "@/Components/ui/toast";
import { Section, SectionField } from "@/Components/shared/section";
import { Input } from "@/Components/ui/input";
import { Switch } from "@/Components/ui/switch";
import { Label } from "@/Components/ui/label";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/Components/ui/select";
import { Server } from "lucide-react";

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
    smtp_username: (emailSettings.smtp_username as string) ?? "",
    smtp_password: (emailSettings.smtp_password as string) ?? "",
    from_email: (emailSettings.from_email as string) ?? "",
    from_name: (emailSettings.from_name as string) ?? "",
    reply_to_email: (emailSettings.reply_to_email as string) ?? "",
    return_path: (emailSettings.return_path as string) ?? "",
    force_from_email: Boolean(emailSettings.force_from_email ?? true),
    force_from_name: Boolean(emailSettings.force_from_name ?? true),
  });
  const [loading, setLoading] = useState(false);
  const [errors, setErrors] = useState<Record<string, string>>({});

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (loading) return;
    setLoading(true);
    setErrors({});

    const payload = {
      ...data,
      smtp_port: Number(data.smtp_port) || 587,
      smtp_auth: data.smtp_auth ? "1" : "0",
      force_from_email: data.force_from_email ? "1" : "0",
      force_from_name: data.force_from_name ? "1" : "0",
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

      {data.mailer === "smtp" && (
        <>
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <SectionField label="SMTP Host" htmlFor="smtp_host" description="e.g. smtp.gmail.com">
              <Input
                id="smtp_host"
                value={data.smtp_host}
                onChange={(e) => setData((p) => ({ ...p, smtp_host: e.target.value }))}
                placeholder="smtp.example.com"
              />
            </SectionField>
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
          </div>

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
                    ? "Leave empty to keep current password"
                    : undefined
                }
              >
                <Input
                  id="smtp_password"
                  type="password"
                  value={data.smtp_password}
                  onChange={(e) => setData((p) => ({ ...p, smtp_password: e.target.value }))}
                  autoComplete="off"
                  placeholder={data.smtp_password === "********" ? "••••••••" : "Enter password"}
                />
              </SectionField>
            </div>
          )}
        </>
      )}

      <div className="border-t border-border pt-4 mt-2">
        <h4 className="text-sm font-medium mb-3">Sender Information</h4>
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
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
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
            description="Bounce/return address"
          >
            <Input
              id="return_path"
              type="email"
              value={data.return_path}
              onChange={(e) => setData((p) => ({ ...p, return_path: e.target.value }))}
            />
          </SectionField>
        </div>
        <div className="flex items-center gap-6 mt-4">
          <div className="flex items-center gap-2">
            <Switch
              id="force_from_email"
              checked={data.force_from_email}
              onCheckedChange={(c) => setData((p) => ({ ...p, force_from_email: c }))}
            />
            <Label htmlFor="force_from_email" className="cursor-pointer font-normal text-sm">
              Force From Email
            </Label>
          </div>
          <div className="flex items-center gap-2">
            <Switch
              id="force_from_name"
              checked={data.force_from_name}
              onCheckedChange={(c) => setData((p) => ({ ...p, force_from_name: c }))}
            />
            <Label htmlFor="force_from_name" className="cursor-pointer font-normal text-sm">
              Force From Name
            </Label>
          </div>
        </div>
      </div>
    </Section>
  );
}
