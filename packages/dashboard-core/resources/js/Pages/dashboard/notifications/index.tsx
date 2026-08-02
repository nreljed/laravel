import { useState, useMemo, useCallback } from "react";
import { Head, router } from "@inertiajs/react";
import { toast } from "@/Components/ui/toast";
import {
  Bell,
  BellOff,
  CheckCheck,
  Check,
  Trash2,
  X,
  Loader2,
  Search,
  User,
  FileText,
} from "lucide-react";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Button } from "@/Components/ui/button";
import { Input } from "@/Components/ui/input";
import { Tabs, TabsList, TabsTrigger } from "@/Components/ui/tabs";
import {
  Accordion,
  AccordionContent,
  AccordionItem,
  AccordionTrigger,
} from "@/Components/ui/accordion";
import { cn } from "@/lib/utils";
import { NotificationItem } from "@/Components/notifications/NotificationItem";
import { NotificationDetailDialog } from "@/Components/notifications/NotificationDetailDialog";
import type { StructuredNotification } from "@/lib/notifications";

interface NotificationsPageProps {
  notifications: {
    data: StructuredNotification[];
    total: number;
    current_page: number;
    last_page: number;
  };
  unreadCount: number;
  filter: string;
  typeFilter: string;
}

function getDateGroup(dateStr: string): string {
  const date = new Date(dateStr.replace(" ", "T"));
  const now = new Date();
  const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
  const yesterday = new Date(today);
  yesterday.setDate(yesterday.getDate() - 1);
  const startOfWeek = new Date(today);
  startOfWeek.setDate(today.getDate() - today.getDay());
  const startOfLastWeek = new Date(startOfWeek);
  startOfLastWeek.setDate(startOfWeek.getDate() - 7);

  if (date >= today) return "Today";
  if (date >= yesterday) return "Yesterday";
  if (date >= startOfWeek) return "This Week";
  if (date >= startOfLastWeek) return "Last Week";
  return "Older";
}

function inferTypeGroup(type: string): string {
  const t = type.toLowerCase();
  if (
    t.includes("account") ||
    t.includes("user") ||
    t.includes("password") ||
    t.includes("email") ||
    t.includes("register") ||
    t.includes("profile")
  )
    return "account";
  if (t.includes("post") || t.includes("page") || t.includes("content") || t.includes("comment"))
    return "content";
  return "other";
}

const READ_FILTERS = [
  { value: "all", label: "All" },
  { value: "unread", label: "Unread" },
  { value: "read", label: "Read" },
];

const DEFAULT_OPEN_GROUPS = new Set(["Today", "Yesterday"]);

const TYPE_FILTERS = [
  { value: "all", label: "All", icon: Bell },
  { value: "account", label: "Account", icon: User },
  { value: "content", label: "Content", icon: FileText },
];

export default function NotificationsPage({
  notifications: notifsData,
  unreadCount,
  filter: initialFilter,
  typeFilter: initialTypeFilter,
}: NotificationsPageProps) {
  const [readFilter, setReadFilter] = useState(initialFilter);
  const [typeFilter, setTypeFilter] = useState(initialTypeFilter || "all");
  const [searchQuery, setSearchQuery] = useState("");
  const [selectedIds, setSelectedIds] = useState<Set<string>>(new Set());
  const [loading, setLoading] = useState(false);
  const [detailNotification, setDetailNotification] = useState<StructuredNotification | null>(null);

  const [allNotifications, setAllNotifications] = useState(notifsData.data);
  const { current_page, last_page } = notifsData;

  const isUnread = (n: StructuredNotification) => !n.is_read && !n.read_at;

  const filtered = useMemo(() => {
    const getDataValue = (n: StructuredNotification, ...keys: string[]) => {
      if (!n.data) return "";
      for (const key of keys) {
        if (Object.hasOwn(n.data, key)) return n.data[key] as string;
      }
      return "";
    };
    const getTitle = (n: StructuredNotification) =>
      n.title || getDataValue(n, "title", "subject", "message");
    const getMessage = (n: StructuredNotification) =>
      n.message || getDataValue(n, "message", "body");

    let result = allNotifications;
    if (readFilter === "unread") {
      result = result.filter((n) => isUnread(n));
    } else if (readFilter === "read") {
      result = result.filter((n) => !isUnread(n));
    }
    if (typeFilter !== "all") {
      result = result.filter((n) => inferTypeGroup(n.type) === typeFilter);
    }
    if (searchQuery.trim()) {
      const q = searchQuery.toLowerCase();
      result = result.filter(
        (n) =>
          getTitle(n).toLowerCase().includes(q) ||
          getMessage(n).toLowerCase().includes(q) ||
          n.type.toLowerCase().includes(q),
      );
    }
    return result;
  }, [allNotifications, readFilter, typeFilter, searchQuery]);

  const grouped = useMemo(() => {
    const groups: Record<string, typeof filtered> = {};
    for (const n of filtered) {
      const dateGroup = getDateGroup(n.created_at);
      if (!Object.hasOwn(groups, dateGroup)) groups[dateGroup] = [];
      groups[dateGroup]!.push(n);
    }
    return groups;
  }, [filtered]);

  const toggleSelect = useCallback((id: string) => {
    setSelectedIds((prev) => {
      const next = new Set(prev);
      if (next.has(id)) next.delete(id);
      else next.add(id);
      return next;
    });
  }, []);

  const allVisibleSelected = filtered.length > 0 && filtered.every((n) => selectedIds.has(n.id));

  const selectAllVisible = () => setSelectedIds(new Set(filtered.map((n) => n.id)));

  const clearSelection = () => setSelectedIds(new Set());

  const changeFilter = (value: string) => {
    setReadFilter(value);
    setSelectedIds(new Set());
    router.visit(route("dashboard.notifications", { filter: value, type_filter: typeFilter }), {
      preserveScroll: true,
      preserveState: true,
    });
  };

  const changeTypeFilter = (value: string) => {
    setTypeFilter(value);
    setSelectedIds(new Set());
    router.visit(route("dashboard.notifications", { filter: readFilter, type_filter: value }), {
      preserveScroll: true,
      preserveState: true,
    });
  };

  const markAsRead = async (id: string) => {
    try {
      await new Promise<void>((resolve, reject) => {
        router.post(
          route("api.notifications.mark-read"),
          { id },
          {
            preserveState: true,
            preserveScroll: true,
            onSuccess: () => resolve(),
            onError: () => reject(),
          },
        );
      });
      setAllNotifications((prev) =>
        prev.map((n) =>
          n.id === id ? { ...n, is_read: true, read_at: new Date().toISOString() } : n,
        ),
      );
      toast.add({ title: "Marked as read", type: "success" });
    } catch {
      toast.add({ title: "Failed to mark as read", type: "error" });
    }
  };

  const markAllAsRead = async () => {
    try {
      await new Promise<void>((resolve, reject) => {
        router.post(
          route("api.notifications.mark-all-read"),
          {},
          {
            preserveState: true,
            preserveScroll: true,
            onSuccess: () => resolve(),
            onError: () => reject(),
          },
        );
      });
      setAllNotifications((prev) =>
        prev.map((n) => ({ ...n, is_read: true, read_at: new Date().toISOString() })),
      );
      setSelectedIds(new Set());
      toast.add({ title: "All notifications marked as read", type: "success" });
    } catch {
      toast.add({ title: "Failed to mark all as read", type: "error" });
    }
  };

  const deleteNotification = async (id: string) => {
    try {
      await new Promise<void>((resolve, reject) => {
        router.delete(route("api.notifications.destroy", { id }), {
          preserveState: true,
          preserveScroll: true,
          onSuccess: () => resolve(),
          onError: () => reject(),
        });
      });
      setAllNotifications((prev) => prev.filter((n) => n.id !== id));
      setSelectedIds(new Set());
      toast.add({ title: "Notification deleted", type: "success" });
    } catch {
      toast.add({ title: "Failed to delete notification", type: "error" });
    }
  };

  const batchMarkRead = async () => {
    const ids = [...selectedIds];
    if (ids.length === 0) return;
    clearSelection();
    try {
      await new Promise<void>((resolve, reject) => {
        router.post(
          route("api.notifications.mark-bulk"),
          { ids },
          {
            preserveState: true,
            preserveScroll: true,
            onSuccess: () => resolve(),
            onError: () => reject(),
          },
        );
      });
      setAllNotifications((prev) =>
        prev.map((n) =>
          ids.includes(n.id) ? { ...n, is_read: true, read_at: new Date().toISOString() } : n,
        ),
      );
      toast.add({ title: "Selected notifications marked as read", type: "success" });
    } catch {
      toast.add({ title: "Failed to mark selected as read", type: "error" });
    }
  };

  const batchDelete = async () => {
    const ids = [...selectedIds];
    if (ids.length === 0) return;
    clearSelection();
    try {
      await new Promise<void>((resolve, reject) => {
        router.post(
          route("api.notifications.delete-bulk"),
          { ids },
          {
            preserveState: true,
            preserveScroll: true,
            onSuccess: () => resolve(),
            onError: () => reject(),
          },
        );
      });
      setAllNotifications((prev) => prev.filter((n) => !ids.includes(n.id)));
      toast.add({ title: "Selected notifications deleted", type: "success" });
    } catch {
      toast.add({ title: "Failed to delete selected notifications", type: "error" });
    }
  };

  const showDetail = useCallback(
    (id: string) => {
      const n = allNotifications.find((n) => n.id === id);
      if (n) setDetailNotification(n);
    },
    [allNotifications],
  );

  const loadMore = () => {
    if (current_page >= last_page) return;
    setLoading(true);
    router.get(
      route("dashboard.notifications", {
        filter: readFilter,
        type_filter: typeFilter,
        page: current_page + 1,
      }),
      {},
      {
        preserveScroll: true,
        preserveState: true,
        onFinish: () => setLoading(false),
      },
    );
  };

  const hasMore = current_page < last_page;

  return (
    <AuthenticatedLayout header="Notifications">
      <Head title="Notifications" />

      <div className="mx-auto max-w-4xl space-y-6">
        <div className="flex items-center justify-between gap-4 flex-wrap">
          <div className="flex items-center gap-2">
            <Bell className="size-5 text-muted-foreground" />
            <h2 className="text-lg font-semibold">Notifications</h2>
            {unreadCount > 0 && (
              <span className="rounded-full bg-destructive px-2 py-0.5 text-xs font-medium text-destructive-foreground">
                {unreadCount} unread
              </span>
            )}
          </div>
          {unreadCount > 0 && (
            <Button variant="outline" size="sm" onClick={markAllAsRead}>
              <CheckCheck className="mr-2 size-4" />
              Mark all as read
            </Button>
          )}
        </div>

        <Tabs value={readFilter} onValueChange={changeFilter}>
          <TabsList>
            {READ_FILTERS.map((rf) => (
              <TabsTrigger key={rf.value} value={rf.value}>
                {rf.label}
              </TabsTrigger>
            ))}
          </TabsList>
        </Tabs>

        <Tabs value={typeFilter} onValueChange={changeTypeFilter}>
          <TabsList className="flex-wrap">
            {TYPE_FILTERS.map((tf) => {
              const Icon = tf.icon;
              return (
                <TabsTrigger key={tf.value} value={tf.value} className="gap-1.5">
                  <Icon className="size-3.5" />
                  {tf.label}
                </TabsTrigger>
              );
            })}
          </TabsList>
        </Tabs>

        <div className="relative">
          <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground pointer-events-none" />
          <Input
            placeholder="Search notifications..."
            value={searchQuery}
            onChange={(e) => setSearchQuery(e.target.value)}
            className="pl-9"
          />
        </div>

        {selectedIds.size > 0 && (
          <div className="flex items-center gap-2 px-4 py-2 rounded-md bg-accent/50 border">
            <span className="text-sm font-medium">{selectedIds.size} selected</span>
            <div className="flex-1" />
            <Button variant="ghost" size="sm" onClick={batchMarkRead}>
              <Check className="mr-1 size-3" />
              Mark read
            </Button>
            <Button
              variant="ghost"
              size="sm"
              onClick={batchDelete}
              className="text-destructive hover:text-destructive"
            >
              <Trash2 className="mr-1 size-3" />
              Delete
            </Button>
            <Button variant="ghost" size="sm" onClick={clearSelection}>
              <X className="mr-1 size-3" />
              Deselect
            </Button>
          </div>
        )}

        {filtered.length > 0 && (
          <div className="flex items-center gap-2 px-1 py-1">
            <button
              onClick={allVisibleSelected ? clearSelection : selectAllVisible}
              className="inline-flex items-center gap-1.5 text-xs text-muted-foreground hover:text-foreground transition-colors"
            >
              <div
                className={cn(
                  "size-4 rounded border flex items-center justify-center",
                  allVisibleSelected ? "border-primary bg-primary" : "border-muted-foreground/40",
                )}
              >
                {allVisibleSelected && <Check className="size-3 text-primary-foreground" />}
              </div>
              {allVisibleSelected ? "Deselect all" : `Select all ${filtered.length}`}
            </button>
          </div>
        )}

        {loading && filtered.length === 0 ? (
          <div className="flex items-center justify-center py-16">
            <Loader2 className="size-6 animate-spin text-muted-foreground" />
          </div>
        ) : filtered.length === 0 ? (
          <div className="flex flex-col items-center justify-center gap-2 py-16 text-center">
            <BellOff className="size-10 text-muted-foreground" />
            <p className="text-sm text-muted-foreground">
              {readFilter === "unread" ? "No unread notifications" : "No notifications yet"}
            </p>
          </div>
        ) : (
          <div className="space-y-2">
            {Object.entries(grouped).map(([dateGroup, items]) => (
              <div key={dateGroup} className="rounded-xl border bg-card">
                <Accordion defaultValue={DEFAULT_OPEN_GROUPS.has(dateGroup) ? [dateGroup] : []}>
                  <AccordionItem value={dateGroup} className="border-0">
                    <AccordionTrigger className="px-4 py-3 hover:no-underline hover:bg-muted/50 transition-colors">
                      <div className="flex items-center gap-2 flex-1 min-w-0">
                        <span className="text-sm font-medium">{dateGroup}</span>
                        <span className="text-xs text-muted-foreground">
                          {items.length} {items.length === 1 ? "notification" : "notifications"}
                        </span>
                      </div>
                    </AccordionTrigger>
                    <AccordionContent className="pb-0">
                      <div className="border-t divide-y">
                        {items.map((notification) => (
                          <NotificationItem
                            key={notification.id}
                            notification={notification}
                            onMarkRead={markAsRead}
                            onDelete={deleteNotification}
                            onShowDetail={showDetail}
                            selected={selectedIds.has(notification.id)}
                            onSelect={toggleSelect}
                          />
                        ))}
                      </div>
                    </AccordionContent>
                  </AccordionItem>
                </Accordion>
              </div>
            ))}

            {hasMore && (
              <div className="flex justify-center pt-2 pb-4">
                <Button variant="outline" size="sm" onClick={loadMore} disabled={loading}>
                  {loading ? (
                    <>
                      <Loader2 className="mr-2 size-3 animate-spin" />
                      Loading...
                    </>
                  ) : (
                    "Load more"
                  )}
                </Button>
              </div>
            )}
          </div>
        )}
      </div>

      <NotificationDetailDialog
        notification={detailNotification}
        open={detailNotification !== null}
        onOpenChange={(open) => {
          if (!open) setDetailNotification(null);
        }}
        onMarkRead={markAsRead}
        onDelete={deleteNotification}
      />
    </AuthenticatedLayout>
  );
}
