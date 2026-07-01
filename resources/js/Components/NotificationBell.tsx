import React from "react";
import { Bell } from "lucide-react";
import { Button } from "@/Components/ui/button";
import { useNotifications } from "@/hooks/use-notifications";
import { NotificationPanel } from "@/Components/notifications/NotificationPanel";

export default function NotificationBell() {
  const [open, setOpen] = React.useState(false);
  const {
    unreadCount,
    recentNotifications: notifications,
    loading,
    markRead,
    markAllRead,
    deleteNotification,
  } = useNotifications();

  const handleOpenChange = (isOpen: boolean) => {
    setOpen(isOpen);
  };

  return (
    <NotificationPanel
      open={open}
      onOpenChange={handleOpenChange}
      notifications={notifications}
      unreadCount={unreadCount}
      loading={loading}
      onMarkRead={markRead}
      onMarkAllRead={markAllRead}
      onDelete={deleteNotification}
    >
      <Button
        variant="ghost"
        size="icon"
        className="relative"
        aria-label={`Notifications${unreadCount > 0 ? ` (${unreadCount} unread)` : ""}`}
      >
        <Bell className="size-5" />
        {unreadCount > 0 && (
          <span className="absolute -top-1.5 -right-1.5 flex min-h-5 min-w-5 items-center justify-center rounded-full bg-destructive px-1.5 text-[11px] font-medium leading-none text-destructive-foreground">
            {unreadCount > 99 ? "99+" : unreadCount}
          </span>
        )}
      </Button>
    </NotificationPanel>
  );
}
