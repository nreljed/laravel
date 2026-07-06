import React from "react";
import { router } from "@inertiajs/react";
import { cn } from "@/lib/utils";
import { Button } from "@/Components/ui/button";
import { Checkbox } from "@/Components/ui/checkbox";
import {
  Check,
  ExternalLink,
  Trash2,
  AlertTriangle,
  AlertCircle,
  CalendarPlus,
  CheckCircle2,
  XCircle,
  Pencil,
  UserPlus,
  Home,
  RefreshCw,
  MessageCircle,
  ShoppingCart,
  ArrowLeftRight,
  Package,
  FileText,
  Clock,
  ShieldCheck,
  KeyRound,
  Mail,
  Maximize2,
} from "lucide-react";

export interface StructuredNotification {
  id: string;
  type: string;
  title?: string;
  message?: string;
  link?: string;
  module?: string;
  severity?: Severity;
  is_read?: string;
  data?: Record<string, string>;
  read_at: string | null;
  created_at: string;
  created_at_relative?: string;
}

export type Severity = "info" | "warning" | "urgent" | "critical";

type TypeIconConfig = {
  icon: React.ComponentType<{ className?: string }>;
  color: string;
  label: string;
};

const typeConfig: Record<string, TypeIconConfig> = {
  "booking.created": { icon: CalendarPlus, color: "text-green-500", label: "New Booking" },
  "booking.confirmed": { icon: CheckCircle2, color: "text-blue-500", label: "Confirmed" },
  "booking.cancelled": { icon: XCircle, color: "text-red-500", label: "Cancelled" },
  "booking.modified": { icon: Pencil, color: "text-amber-500", label: "Modified" },
  "property.created": { icon: Home, color: "text-green-500", label: "New Property" },
  "property.updated": { icon: RefreshCw, color: "text-blue-500", label: "Updated" },
  "property.inquiry": { icon: MessageCircle, color: "text-purple-500", label: "Inquiry" },
  "order.placed": { icon: ShoppingCart, color: "text-green-500", label: "New Order" },
  "order.status_changed": { icon: ArrowLeftRight, color: "text-blue-500", label: "Status Changed" },
  low_stock: { icon: Package, color: "text-red-500", label: "Low Stock" },
  "post.published": { icon: FileText, color: "text-green-500", label: "Published" },
  "post.scheduled": { icon: Clock, color: "text-purple-500", label: "Scheduled" },
  "user.registered": { icon: UserPlus, color: "text-green-500", label: "New User" },
  "password.changed": { icon: ShieldCheck, color: "text-blue-500", label: "Password Changed" },
  "password.reset": { icon: KeyRound, color: "text-orange-500", label: "Password Reset" },
  "email.changed": { icon: Mail, color: "text-amber-500", label: "Email Changed" },
  "account.deleted": { icon: Trash2, color: "text-red-500", label: "Account Deleted" },
  "booking.guest_registered": {
    icon: UserPlus,
    color: "text-violet-500",
    label: "Guest Registered",
  },
};

function getTypeIcon(type: string) {
  if (Object.hasOwn(typeConfig, type)) return typeConfig[type];
  const match = Object.entries(typeConfig).find(([key]) => type.includes(key));
  return match ? match[1] : null;
}

const severityConfig: Record<Severity, { dot: string; icon: React.ReactNode }> = {
  info: { dot: "bg-primary", icon: null },
  warning: {
    dot: "bg-amber-500",
    icon: <AlertTriangle className="h-3 w-3 text-amber-500 dark:text-amber-400" />,
  },
  urgent: {
    dot: "bg-orange-500",
    icon: <AlertCircle className="h-3 w-3 text-orange-500 dark:text-orange-400" />,
  },
  critical: { dot: "bg-destructive", icon: <AlertTriangle className="h-3 w-3 text-destructive" /> },
};

function getSeverity(type: string, n?: StructuredNotification): Severity {
  if (n?.severity && ["info", "warning", "urgent", "critical"].includes(n.severity))
    return n.severity;
  if (type.includes("cancel") || type.includes("deleted") || type === "low_stock")
    return "critical";
  if (type.includes("inquiry") || type.includes("modified")) return "warning";
  if (type.includes("reset") || type.includes("changed")) return "urgent";
  return "info";
}

interface NotificationItemProps {
  notification: StructuredNotification;
  onMarkRead: (_id: string) => void;
  onDelete?: (_id: string) => void;
  onShowDetail?: (_id: string) => void;
  selected?: boolean;
  onSelect?: (_id: string) => void;
}

export const NotificationItem = React.memo(function NotificationItem({
  notification: n,
  onMarkRead,
  onDelete,
  onShowDetail,
  selected,
  onSelect,
}: NotificationItemProps) {
  const isUnread = n.is_read === "0" || !n.read_at;
  const id = n.id;
  const type = n.type
    .replace("App\\Notifications\\", "")
    .replace("Notification", "")
    .replaceAll(/([a-z])([A-Z])/g, "$1.$2")
    .toLowerCase();
  const severity = getSeverity(type, n);
  const config = severityConfig[severity];
  const typeIcon = getTypeIcon(type);

  const title = n.title || n.data?.title || n.data?.subject || n.data?.message || "Notification";
  const message = n.message || n.data?.message || n.data?.body || "";
  const link = n.link || n.data?.link || n.data?.url || "";
  const createdAt = n.created_at_relative || n.created_at;

  const handleClick = () => {
    if (isUnread) onMarkRead(id);
    if (link) {
      router.visit(link);
    }
  };

  return (
    <div
      className={cn(
        "group relative flex items-start gap-3 px-4 py-3 transition-colors overflow-hidden min-w-0",
        isUnread ? "bg-muted/50" : "bg-background",
        selected && "bg-accent/50",
        link && "cursor-pointer hover:bg-muted/80 dark:hover:bg-muted/40",
      )}
      onClick={handleClick}
      role={link ? "button" : undefined}
      tabIndex={link ? 0 : undefined}
      onKeyDown={(e) => {
        if (!(link && (e.key === "Enter" || e.key === " "))) {
          return;
        }

        e.preventDefault();
        handleClick();
      }}
    >
      {onSelect && (
        <div className="mt-1 shrink-0" onClick={(e) => e.stopPropagation()}>
          <Checkbox
            checked={selected}
            onCheckedChange={() => onSelect(id)}
            aria-label={`Select notification ${id}`}
          />
        </div>
      )}
      {!typeIcon && (
        <div className="relative mt-1.5 shrink-0">
          <div className={cn("h-2 w-2 rounded-full", isUnread ? config.dot : "bg-transparent")} />
        </div>
      )}
      <div className="flex-1 min-w-0 w-full overflow-hidden">
        {typeIcon && (
          <div className="flex items-center gap-1.5 mb-1 w-full">
            <span
              className={cn(
                "inline-flex items-center gap-1 rounded-sm px-1.5 py-0.5 text-[10px] font-medium leading-none bg-muted truncate max-w-full",
                typeIcon.color,
              )}
            >
              <typeIcon.icon
                className={cn("h-3 w-3 shrink-0", isUnread ? "opacity-100" : "opacity-40")}
              />
              <span className="truncate">{typeIcon.label}</span>
            </span>
          </div>
        )}
        <div className="flex items-center gap-1.5 overflow-hidden w-full">
          <p
            className={cn(
              "text-sm truncate min-w-0",
              isUnread ? "font-semibold text-foreground" : "text-muted-foreground",
            )}
          >
            {title}
          </p>
        </div>
        {message && (
          <p
            className={cn(
              "mt-0.5 text-xs line-clamp-2 break-words",
              isUnread ? "text-foreground/80" : "text-muted-foreground",
            )}
          >
            {message}
          </p>
        )}
        <p className="mt-1 text-[11px] text-muted-foreground/60">
          {new Date(createdAt).toLocaleString()}
        </p>
      </div>
      <div className="flex shrink-0 gap-1 opacity-0 group-hover:opacity-100 transition-opacity max-sm:opacity-100 max-sm:flex">
        {onShowDetail && (
          <Button
            variant="ghost"
            size="icon"
            className="h-6 w-6"
            onClick={(e) => {
              e.stopPropagation();
              onShowDetail(id);
            }}
            title="View details"
          >
            <Maximize2 className="size-3" />
          </Button>
        )}
        {isUnread && (
          <Button
            variant="ghost"
            size="icon"
            className="h-6 w-6"
            onClick={(e) => {
              e.stopPropagation();
              onMarkRead(id);
            }}
            title="Mark as read"
          >
            <Check className="size-3" />
          </Button>
        )}
        {onDelete && (
          <Button
            variant="ghost"
            size="icon"
            className="h-6 w-6 text-muted-foreground hover:text-destructive"
            onClick={(e) => {
              e.stopPropagation();
              onDelete(id);
            }}
            title="Delete"
          >
            <Trash2 className="size-3" />
          </Button>
        )}
        {link && <ExternalLink className="h-3 w-3 text-muted-foreground self-center" />}
      </div>
    </div>
  );
});
