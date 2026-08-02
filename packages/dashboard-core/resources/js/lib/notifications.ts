export type Severity = "info" | "warning" | "urgent" | "critical";

export interface Notification {
  id: string;
  type: string;
  title: string;
  message: string;
  link: string;
  module: string;
  is_read: boolean;
  severity: Severity;
  data: Record<string, string>;
  read_at: string | null;
  created_at: string;
  created_at_relative: string;
}

export interface StructuredNotification {
  id: string;
  type: string;
  title?: string;
  message?: string;
  link?: string;
  feature?: string;
  severity?: Severity;
  is_read?: boolean;
  data?: Record<string, string>;
  read_at: string | null;
  created_at: string;
  created_at_relative?: string;
}

export const SEVERITY_COLORS: Record<Severity, string> = {
  info: "bg-primary/10 text-primary",
  warning: "bg-amber-500/10 text-amber-600 dark:text-amber-400",
  urgent: "bg-orange-500/10 text-orange-600 dark:text-orange-400",
  critical: "bg-destructive/10 text-destructive",
};

export const SEVERITY_LABELS: Record<Severity, string> = {
  info: "Info",
  warning: "Warning",
  urgent: "Urgent",
  critical: "Critical",
};

export function normalizeNotificationType(type: string): string {
  return type
    .replace("Nreljed\\DashboardCore\\Notifications\\", "")
    .replace("App\\Notifications\\", "")
    .replace("Notification", "")
    .replaceAll(/([a-z])([A-Z])/g, "$1.$2")
    .toLowerCase();
}

export function getSeverity(type: string, n?: { severity?: Severity }): Severity {
  if (n?.severity && ["info", "warning", "urgent", "critical"].includes(n.severity))
    return n.severity;
  if (type.includes("cancel") || type.includes("deleted")) return "critical";
  if (type.includes("inquiry") || type.includes("modified")) return "warning";
  if (type.includes("reset") || type.includes("changed")) return "urgent";
  return "info";
}

const TYPE_LABELS: Record<string, string> = {
  "post.published": "Post Published",
  "post.scheduled": "Post Scheduled",
  "user.registered": "New User",
  "password.changed": "Password Changed",
  "password.reset": "Password Reset",
  "email.changed": "Email Changed",
  "account.deleted": "Account Deleted",
};

export function getTypeLabel(type: string): string {
  const normalized = normalizeNotificationType(type);
  return TYPE_LABELS[normalized] || normalized;
}
