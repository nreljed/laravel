import { useState, useEffect, useCallback, useRef } from "react";
import { usePage, router } from "@inertiajs/react";
import axios from "axios";

export type Severity = "info" | "warning" | "urgent" | "critical";

export interface Notification {
  id: string;
  type: string;
  title: string;
  message: string;
  link: string;
  module: string;
  is_read: string;
  severity: Severity;
  data: Record<string, string>;
  read_at: string | null;
  created_at: string;
  created_at_relative: string;
}

interface UseNotificationsResult {
  unreadCount: number;
  recentNotifications: Notification[];
  allNotifications: Notification[];
  loading: boolean;
  fetchRecent: () => Promise<void>;
  fetchAll: (_limit?: number, _offset?: number) => Promise<void>;
  markRead: (_id: string) => Promise<void>;
  markAllRead: () => Promise<void>;
  deleteNotification: (_id: string) => Promise<void>;
  triggerRefresh: () => void;
  notificationPermission: NotificationPermission | "unsupported";
  requestNotificationPermission: () => Promise<void>;
}

const POLL_INTERVAL_ACTIVE = 10_000;
const POLL_INTERVAL_IDLE = 30_000;
const POLL_INTERVAL_STANDBY = 60_000;
const IDLE_THRESHOLD_1 = 120_000;
const IDLE_THRESHOLD_2 = 300_000;
const CHANNEL_NAME = "app-notifications";
const MAX_CONSECUTIVE_ERRORS = 3;

const getAudioContext: () => AudioContext | null = (() => {
  let ctx: AudioContext | null = null;
  return () => {
    if (!ctx || ctx.state === "closed") {
      try {
        ctx = new (
          globalThis.AudioContext ||
          ((globalThis as unknown as Record<string, unknown>)
            .webkitAudioContext as typeof AudioContext)
        )();
      } catch {
        // audio not supported
      }
    }
    return ctx;
  };
})();

function playNotificationSound(): void {
  const ctx = getAudioContext();
  if (!ctx) return;
  try {
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
  } catch {
    // audio not supported
  }
}

function showDesktopNotification(title: string, body: string, link?: string): void {
  if (!("Notification" in globalThis) || Notification.permission !== "granted") return;
  try {
    const n = new Notification(title, { body });
    if (link) {
      n.addEventListener("click", () => {
        window.focus();
        location.assign(link);
      });
    }
  } catch {
    // silently fail
  }
}

export function useNotifications(): UseNotificationsResult {
  const { props } = usePage();
  const initialNotifications = (props.latestNotifications ?? []) as Notification[];
  const initialCount = (props.notificationUnreadCount as number) ?? 0;
  const [unreadCount, setUnreadCount] = useState(initialCount);
  const [recentNotifications, setRecentNotifications] =
    useState<Notification[]>(initialNotifications);
  const [allNotifications, setAllNotifications] = useState<Notification[]>([]);
  const [loading, setLoading] = useState(false);
  const [notificationPermission, setNotificationPermission] = useState<
    NotificationPermission | "unsupported"
  >("Notification" in globalThis ? Notification.permission : "unsupported");
  const timerRef = useRef<ReturnType<typeof setTimeout> | null>(null);
  const countEtagRef = useRef<string | null>(null);
  const channelRef = useRef<BroadcastChannel | null>(null);
  const lastCountRef = useRef(initialCount);
  const initialCountRef = useRef(initialCount);
  const soundEnabledRef = useRef(localStorage.getItem("notification_sound_enabled") !== "false");
  const lastInteractionRef = useRef(0);
  const pollIntervalRef = useRef(POLL_INTERVAL_ACTIVE);
  const fetchGenRef = useRef(0);
  const consecutiveErrorsRef = useRef(0);

  const broadcast = useCallback((count: number) => {
    if (channelRef.current) {
      channelRef.current.postMessage({ type: "unread", count });
    }
  }, []);

  const fetchList = useCallback(
    async (background = false) => {
      const gen = fetchGenRef.current;
      if (!background) setLoading(true);
      try {
        const response = await axios.get(route("api.notifications.recent"));
        if (gen !== fetchGenRef.current) return;
        const data = response.data;
        setRecentNotifications(data.notifications ?? []);
        const c = data.unread_count ?? 0;
        if (c > lastCountRef.current && soundEnabledRef.current) {
          playNotificationSound();
          if (document.visibilityState === "hidden") {
            const latest = (data.notifications ?? [])[0];
            if (latest) showDesktopNotification(latest.title, latest.message || "", latest.link);
          }
        }
        lastCountRef.current = c;
        setUnreadCount(c);
        broadcast(c);
      } catch {
        // silently fail
      } finally {
        if (gen === fetchGenRef.current && !background) setLoading(false);
      }
    },
    [broadcast],
  );

  const pollCount = useCallback(async () => {
    const gen = fetchGenRef.current;

    try {
      const response = await axios.get(route("api.notifications.unread-count"));
      if (gen !== fetchGenRef.current) return;
      consecutiveErrorsRef.current = 0;
      const data = response.data;
      const c = data.count ?? 0;
      if (c > lastCountRef.current && soundEnabledRef.current) {
        playNotificationSound();
        if (document.visibilityState === "hidden") {
          showDesktopNotification(
            `${c} new notification${c > 1 ? "s" : ""}`,
            "You have unread notifications",
            "/dashboard/notifications",
          );
        }
      }
      lastCountRef.current = c;
      setUnreadCount(c);
      fetchList(true);
    } catch {
      consecutiveErrorsRef.current += 1;
    }
  }, [fetchList]);

  const getAdaptiveInterval = useCallback((): number => {
    const errors = consecutiveErrorsRef.current;
    if (errors > MAX_CONSECUTIVE_ERRORS) {
      return Math.min(POLL_INTERVAL_STANDBY * (1 << (errors - MAX_CONSECUTIVE_ERRORS)), 300_000);
    }
    const idleTime = Date.now() - lastInteractionRef.current;
    if (idleTime < IDLE_THRESHOLD_1) return POLL_INTERVAL_ACTIVE;
    if (idleTime < IDLE_THRESHOLD_2) return POLL_INTERVAL_IDLE;
    return POLL_INTERVAL_STANDBY;
  }, []);

  const startPollingRef = useRef<() => void>(undefined);

  const startPolling = useCallback(() => {
    if (timerRef.current) clearTimeout(timerRef.current);
    pollCount();
    const next = getAdaptiveInterval();
    pollIntervalRef.current = next;
    timerRef.current = setTimeout(() => startPollingRef.current?.(), next);
  }, [pollCount, getAdaptiveInterval]);

  useEffect(() => {
    startPollingRef.current = startPolling;
  }, [startPolling]);

  const stopPolling = useCallback(() => {
    if (!timerRef.current) {
      return;
    }

    clearTimeout(timerRef.current);
    timerRef.current = null;
  }, []);

  const triggerRefresh = useCallback(() => {
    countEtagRef.current = null;
    fetchGenRef.current += 1;
    pollCount();
  }, [pollCount]);

  const fetchRecent = useCallback(async () => {
    await fetchList();
  }, [fetchList]);

  const fetchAll = useCallback(
    async (limit = 20, offset = 0) => {
      const skipLoading = initialCountRef.current === 0;
      if (!skipLoading) setLoading(true);
      try {
        const response = await axios.get(route("api.notifications", { limit, offset }));
        const data = response.data;
        setAllNotifications(data.notifications ?? []);
        setUnreadCount(data.unread_count ?? 0);
        lastCountRef.current = data.unread_count ?? 0;
        broadcast(data.unread_count ?? 0);
      } catch {
        // silently fail
      } finally {
        if (!skipLoading) setLoading(false);
      }
    },
    [broadcast],
  );

  const markRead = useCallback(
    async (id: string) => {
      fetchGenRef.current += 1;
      setRecentNotifications((prev) => prev.map((n) => (n.id === id ? { ...n, is_read: "1" } : n)));
      setAllNotifications((prev) => prev.map((n) => (n.id === id ? { ...n, is_read: "1" } : n)));
      setUnreadCount((prev) => Math.max(0, prev - 1));
      countEtagRef.current = null;
      try {
        await axios.post(route("api.notifications.mark-read"), { id });
        triggerRefresh();
      } catch {
        await triggerRefresh();
      }
    },
    [triggerRefresh],
  );

  const markAllRead = useCallback(async () => {
    fetchGenRef.current += 1;
    setRecentNotifications((prev) => prev.map((n) => ({ ...n, is_read: "1" })));
    setAllNotifications((prev) => prev.map((n) => ({ ...n, is_read: "1" })));
    setUnreadCount(0);
    lastCountRef.current = 0;
    countEtagRef.current = null;
    try {
      await axios.post(route("api.notifications.mark-all-read"), {});
      triggerRefresh();
    } catch {
      await triggerRefresh();
    }
  }, [triggerRefresh]);

  const deleteNotification = useCallback(
    async (id: string) => {
      fetchGenRef.current += 1;
      setRecentNotifications((prev) => prev.filter((n) => n.id !== id));
      setAllNotifications((prev) => prev.filter((n) => n.id !== id));
      setUnreadCount((prev) => Math.max(0, prev - 1));
      lastCountRef.current = Math.max(0, lastCountRef.current - 1);
      countEtagRef.current = null;
      try {
        await axios.delete(route("api.notifications.destroy", { id }));
        triggerRefresh();
      } catch {
        await triggerRefresh();
      }
    },
    [triggerRefresh],
  );

  const requestNotificationPermission = useCallback(async () => {
    if (!("Notification" in globalThis)) return;
    if (Notification.permission === "granted") {
      setNotificationPermission("granted");
      return;
    }
    if (Notification.permission === "denied") return;
    const result = await Notification.requestPermission();
    setNotificationPermission(result);
  }, []);

  // Visibility changes — pause/resume polling
  useEffect(() => {
    const handleVisibility = () => {
      if (document.visibilityState === "visible") {
        lastInteractionRef.current = Date.now();
        countEtagRef.current = null;
        stopPolling();
        pollCount();
        startPolling();
      } else {
        stopPolling();
      }
    };
    document.addEventListener("visibilitychange", handleVisibility);
    return () => document.removeEventListener("visibilitychange", handleVisibility);
  }, [pollCount, startPolling, stopPolling]);

  // Track user interaction for adaptive intervals
  useEffect(() => {
    lastInteractionRef.current = Date.now();
    const handleInteraction = () => {
      lastInteractionRef.current = Date.now();
    };
    addEventListener("mousemove", handleInteraction, { passive: true });
    addEventListener("click", handleInteraction, { passive: true });
    addEventListener("keydown", handleInteraction, { passive: true });
    addEventListener("touchstart", handleInteraction, { passive: true });
    return () => {
      removeEventListener("mousemove", handleInteraction);
      removeEventListener("click", handleInteraction);
      removeEventListener("keydown", handleInteraction);
      removeEventListener("touchstart", handleInteraction);
    };
  }, []);

  // BroadcastChannel for cross-tab sync
  useEffect(() => {
    try {
      const bc = new BroadcastChannel(CHANNEL_NAME);
      channelRef.current = bc;
      bc.addEventListener("message", (event) => {
        if (event.data?.type !== "unread") {
          return;
        }

        lastCountRef.current = event.data.count;
        setUnreadCount(event.data.count);
      });
    } catch {
      // not supported
    }
    return () => {
      if (!channelRef.current) {
        return;
      }

      channelRef.current.close();
      channelRef.current = null;
    };
  }, []);

  // Refresh on Inertia navigation finish
  useEffect(() => {
    const remove = router.on("finish", () => {
      countEtagRef.current = null;
      fetchGenRef.current += 1;
      pollCount();
      fetchList();
    });
    return remove;
  }, [pollCount, fetchList]);

  // Start polling on mount, stop on unmount
  useEffect(() => {
    if (document.visibilityState === "visible") {
      const id = setTimeout(startPolling, 0);
      return () => {
        clearTimeout(id);
        stopPolling();
      };
    }
    return stopPolling;
  }, [startPolling, stopPolling]);

  return {
    unreadCount,
    recentNotifications,
    allNotifications,
    loading,
    fetchRecent,
    fetchAll,
    markRead,
    markAllRead,
    deleteNotification,
    triggerRefresh,
    notificationPermission,
    requestNotificationPermission,
  };
}
