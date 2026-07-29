import React from "react";
import { Sheet, SheetTrigger, SheetContent, SheetHeader, SheetTitle } from "@/Components/ui/sheet";
import { Button } from "@/Components/ui/button";
import { ScrollArea } from "@/Components/ui/scroll-area";
import { Separator } from "@/Components/ui/separator";
import { X, CheckCheck, Bell, BellOff, Eye } from "lucide-react";
import { Link } from "@inertiajs/react";
import { NotificationItem } from "@/Components/notifications/NotificationItem";
import type { StructuredNotification } from "@/lib/notifications";

type Notification = StructuredNotification;

interface NotificationPanelProps {
  open: boolean;
  onOpenChange: (_open: boolean) => void;
  notifications: Notification[];
  unreadCount: number;
  loading: boolean;
  onMarkRead: (_id: string) => void;
  onMarkAllRead: () => void;
  onDelete: (_id: string) => void;
  children: React.ReactNode;
}

export function NotificationPanel({
  open,
  onOpenChange,
  notifications,
  unreadCount,
  loading,
  onMarkRead,
  onMarkAllRead,
  onDelete,
  children,
}: NotificationPanelProps) {
  return (
    <Sheet open={open} onOpenChange={onOpenChange}>
      <SheetTrigger render={<span />}>{children}</SheetTrigger>
      <SheetContent
        side="right"
        className="w-[90vw] sm:max-w-md p-0 flex flex-col overflow-hidden"
        showCloseButton={false}
      >
        <SheetHeader className="px-4 pt-4 pb-0">
          <div className="flex items-center justify-between">
            <div className="flex items-center gap-2">
              <Bell className="size-4 text-muted-foreground" />
              <SheetTitle className="text-base">Notifications</SheetTitle>
              {unreadCount > 0 && (
                <span className="flex min-h-5 min-w-5 items-center justify-center rounded-full bg-destructive px-1.5 text-[11px] font-medium leading-none text-destructive-foreground">
                  {unreadCount > 99 ? "99+" : unreadCount}
                </span>
              )}
            </div>
            <Button
              variant="ghost"
              size="icon"
              className="size-7 rounded-xs"
              onClick={() => onOpenChange(false)}
              aria-label="Close"
            >
              <X className="size-4" />
            </Button>
          </div>
        </SheetHeader>
        <Separator />
        {loading && notifications.length === 0 ? (
          <div className="flex-1 flex items-center justify-center py-8">
            <div className="h-5 w-5 animate-spin rounded-full border-2 border-muted-foreground border-t-transparent" />
          </div>
        ) : notifications.length === 0 ? (
          <div className="flex-1 flex flex-col items-center justify-center gap-2 py-8 text-center">
            <BellOff className="h-8 w-8 text-muted-foreground" />
            <p className="text-sm text-muted-foreground">No notifications</p>
          </div>
        ) : (
          <ScrollArea className="flex-1 w-full min-h-0">
            <div className="divide-y divide-border w-full">
              {notifications.map((notification) => (
                <NotificationItem
                  key={notification.id}
                  notification={notification}
                  onMarkRead={onMarkRead}
                  onDelete={onDelete}
                />
              ))}
            </div>
          </ScrollArea>
        )}
        <div className="shrink-0 border-t border-border px-3 py-2 flex items-center justify-between gap-2">
          <Link
            href={route("dashboard.notifications")}
            className="inline-flex items-center gap-1.5 text-xs text-muted-foreground hover:text-foreground transition-colors"
            onClick={() => onOpenChange(false)}
          >
            <Eye className="size-3" />
            View all
          </Link>
          {unreadCount > 0 && (
            <Button
              variant="ghost"
              size="sm"
              className="h-auto px-2 py-1 text-xs"
              onClick={onMarkAllRead}
            >
              <CheckCheck className="mr-1 size-3" />
              Mark all read
            </Button>
          )}
        </div>
      </SheetContent>
    </Sheet>
  );
}
