import { useState, useEffect, useCallback, useRef } from "react";
import { usePage, router } from "@inertiajs/react";

interface Notification {
  id: string;
  type: string;
  data: Record<string, string>;
  read_at: string | null;
  created_at: string;
}

interface UseNotificationsResult {
  unreadCount: number;
  notifications: Notification[];
  loading: boolean;
  fetch: () => void;
  markRead: (id: string) => void;
  markAllRead: () => void;
  deleteNotification: (id: string) => void;
}

export function useNotifications(): UseNotificationsResult {
  const { props } = usePage();
  const latestNotifications = (props.latestNotifications as Notification[]) ?? [];
  const notificationUnreadCount = (props.notification_unread_count as number) ?? 0;

  const [unreadCount, setUnreadCount] = useState(notificationUnreadCount);
  const [notifications, setNotifications] = useState<Notification[]>(latestNotifications);
  const [loading, setLoading] = useState(false);
  const lastCountRef = useRef(notificationUnreadCount);
  const soundEnabledRef = useRef(true);

  useEffect(() => {
    setUnreadCount(notificationUnreadCount);
    setNotifications(latestNotifications);
    if (notificationUnreadCount > lastCountRef.current && soundEnabledRef.current) {
      playNotificationSound();
    }
    lastCountRef.current = notificationUnreadCount;
  }, [notificationUnreadCount, latestNotifications]);

  const fetch = useCallback(() => {
    setLoading(true);
    router.reload({
      only: ["latestNotifications", "notification_unread_count"],
      onFinish: () => setLoading(false),
    });
  }, []);

  const markRead = useCallback(
    (id: string) => {
      setNotifications((prev) =>
        prev.map((n) => (n.id === id ? { ...n, read_at: new Date().toISOString() } : n)),
      );
      setUnreadCount((prev) => Math.max(0, prev - 1));
      router.put(
        route("dashboard.notifications.mark-read") as string,
        { id },
        { preserveScroll: true },
      );
    },
    [],
  );

  const markAllRead = useCallback(() => {
    setNotifications((prev) => prev.map((n) => ({ ...n, read_at: new Date().toISOString() })));
    setUnreadCount(0);
    router.post(
      route("dashboard.notifications.mark-all-read") as string,
      {},
      { preserveScroll: true },
    );
  }, []);

  const deleteNotification = useCallback(
    (id: string) => {
      const wasUnread = notifications.some((n) => n.id === id && !n.read_at);
      setNotifications((prev) => prev.filter((n) => n.id !== id));
      if (wasUnread) {
        setUnreadCount((prev) => Math.max(0, prev - 1));
      }
      router.delete(
        route("dashboard.notifications.destroy", { id }) as string,
        { preserveScroll: true },
      );
    },
    [notifications],
  );

  useEffect(() => {
    const interval = setInterval(() => {
      router.reload({ only: ["latestNotifications", "notification_unread_count"] });
    }, 20000);
    return () => clearInterval(interval);
  }, []);

  return {
    unreadCount,
    notifications,
    loading,
    fetch,
    markRead,
    markAllRead,
    deleteNotification,
  };
}

function playNotificationSound(): void {
  try {
    const AudioContextClass =
      window.AudioContext ||
      ((window as unknown as Record<string, unknown>).webkitAudioContext as typeof AudioContext);
    if (!AudioContextClass) return;
    const ctx = new AudioContextClass();
    const oscillator = ctx.createOscillator();
    const gain = ctx.createGain();
    oscillator.connect(gain);
    gain.connect(ctx.destination);
    oscillator.frequency.value = 520;
    oscillator.type = "sine";
    gain.gain.setValueAtTime(0.15, ctx.currentTime);
    gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.25);
    oscillator.start(ctx.currentTime);
    oscillator.stop(ctx.currentTime + 0.25);
    oscillator.onended = () => ctx.close();
  } catch {
    // audio not supported
  }
}
