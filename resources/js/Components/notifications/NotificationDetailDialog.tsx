import { router } from "@inertiajs/react";
import { Check, Trash2, ExternalLink, CalendarDays } from "lucide-react";
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogDescription,
  DialogFooter,
} from "@/Components/ui/dialog";
import { Button } from "@/Components/ui/button";
import { Badge } from "@/Components/ui/badge";
import { Separator } from "@/Components/ui/separator";
import { getSeverity, getTypeLabel, SEVERITY_COLORS, SEVERITY_LABELS } from "@/lib/notifications";
import type { StructuredNotification } from "@/lib/notifications";

interface NotificationDetailDialogProps {
  notification: StructuredNotification | null;
  open: boolean;
  onOpenChange: (_open: boolean) => void;
  onMarkRead: (_id: string) => void;
  onDelete: (_id: string) => void;
}

export function NotificationDetailDialog({
  notification: n,
  open,
  onOpenChange,
  onMarkRead,
  onDelete,
}: NotificationDetailDialogProps) {
  if (!n) return null;

  const isUnread = !n.is_read && !n.read_at;
  const severity = getSeverity(n.type, n);
  const title = n.title || n.data?.title || n.data?.subject || n.data?.message || "Notification";
  const message = n.message || n.data?.message || n.data?.body || "";
  const link = n.link || n.data?.link || n.data?.url || "";
  const createdAt = n.created_at_relative || n.created_at;

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-lg">
        <DialogHeader>
          <div className="flex items-center gap-3 mb-1">
            <Badge variant="outline" className={SEVERITY_COLORS[severity]}>
              {SEVERITY_LABELS[severity]}
            </Badge>
            <Badge variant="outline">{getTypeLabel(n.type)}</Badge>
            {isUnread && <div className="h-2 w-2 rounded-full bg-primary shrink-0" />}
          </div>
          <DialogTitle className="text-lg">{title}</DialogTitle>
          {createdAt && (
            <div className="flex items-center gap-1.5 text-xs text-muted-foreground">
              <CalendarDays className="h-3 w-3" />
              {new Date(createdAt).toLocaleString()}
            </div>
          )}
        </DialogHeader>

        <Separator />

        <DialogDescription className="text-sm text-foreground/80 whitespace-pre-wrap leading-relaxed max-h-80 overflow-y-auto">
          {message || "No additional details."}
        </DialogDescription>

        <DialogFooter className="gap-2 sm:gap-0">
          {link && (
            <Button
              variant="default"
              size="sm"
              onClick={() => {
                onOpenChange(false);
                router.visit(link);
              }}
            >
              <ExternalLink className="mr-1.5 h-3.5 w-3.5" />
              View Details
            </Button>
          )}
          <div className="flex gap-2">
            {isUnread && (
              <Button
                variant="outline"
                size="sm"
                onClick={() => {
                  onMarkRead(n.id);
                  onOpenChange(false);
                }}
              >
                <Check className="mr-1.5 h-3.5 w-3.5" />
                Mark as read
              </Button>
            )}
            <Button
              variant="ghost"
              size="sm"
              className="text-muted-foreground hover:text-destructive"
              onClick={() => {
                onDelete(n.id);
                onOpenChange(false);
              }}
            >
              <Trash2 className="mr-1.5 h-3.5 w-3.5" />
              Delete
            </Button>
          </div>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
