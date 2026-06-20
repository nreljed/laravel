export const BOOKING_STATUS = {
  Pending: "pending" as const,
  Confirmed: "confirmed" as const,
  CheckedIn: "checked_in" as const,
  CheckedOut: "checked_out" as const,
  Cancelled: "cancelled" as const,
} as const;

export type BookingStatus = (typeof BOOKING_STATUS)[keyof typeof BOOKING_STATUS];

export const STATUS_LABELS: Record<string, string> = {
  [BOOKING_STATUS.Pending]: "Pending",
  [BOOKING_STATUS.Confirmed]: "Confirmed",
  [BOOKING_STATUS.CheckedIn]: "Checked In",
  [BOOKING_STATUS.CheckedOut]: "Checked Out",
  [BOOKING_STATUS.Cancelled]: "Cancelled",
};

export const ALL_STATUSES: readonly BookingStatus[] = Object.values(BOOKING_STATUS);

export const ACTIVE_STATUSES: readonly BookingStatus[] = [
  BOOKING_STATUS.Pending,
  BOOKING_STATUS.Confirmed,
  BOOKING_STATUS.CheckedIn,
];

export const TERMINAL_STATUSES: readonly BookingStatus[] = [
  BOOKING_STATUS.CheckedOut,
  BOOKING_STATUS.Cancelled,
];

export const CONFIRMED_GROUP: readonly BookingStatus[] = [
  BOOKING_STATUS.Confirmed,
  BOOKING_STATUS.CheckedIn,
];

export interface BookingStatusConfig {
  value: string;
  label: string;
  color: "default" | "secondary" | "destructive" | "outline";
}

export const BOOKING_STATUSES: BookingStatusConfig[] = [
  { value: BOOKING_STATUS.Pending, label: "Pending", color: "secondary" },
  { value: BOOKING_STATUS.Confirmed, label: "Confirmed", color: "default" },
  { value: BOOKING_STATUS.CheckedIn, label: "Checked In", color: "default" },
  { value: BOOKING_STATUS.CheckedOut, label: "Checked Out", color: "outline" },
  { value: BOOKING_STATUS.Cancelled, label: "Cancelled", color: "destructive" },
];

export const STATUS_VARIANT: Record<string, "default" | "secondary" | "destructive" | "outline"> = {
  [BOOKING_STATUS.Pending]: "secondary",
  [BOOKING_STATUS.Confirmed]: "default",
  [BOOKING_STATUS.CheckedIn]: "default",
  [BOOKING_STATUS.CheckedOut]: "outline",
  [BOOKING_STATUS.Cancelled]: "destructive",
};

export const STATUS_BADGE: Record<string, string> = {
  [BOOKING_STATUS.Pending]: "bg-amber-50 text-amber-700 dark:bg-amber-900/50 dark:text-amber-300",
  [BOOKING_STATUS.Confirmed]: "bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200",
  [BOOKING_STATUS.CheckedIn]: "bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200",
  [BOOKING_STATUS.CheckedOut]: "bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200",
  [BOOKING_STATUS.Cancelled]: "bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200",
};

export const STATUS_BORDER_BADGE: Record<string, string> = {
  [BOOKING_STATUS.Pending]: "bg-amber-100 text-amber-800 dark:bg-amber-900/50 dark:text-amber-300 border-amber-200 dark:border-amber-800",
  [BOOKING_STATUS.Confirmed]: "bg-green-100 text-green-800 dark:bg-green-900/50 dark:text-green-200 border-green-200 dark:border-green-800",
  [BOOKING_STATUS.CheckedIn]: "bg-blue-100 text-blue-800 dark:bg-blue-900/50 dark:text-blue-200 border-blue-200 dark:border-blue-800",
  [BOOKING_STATUS.CheckedOut]: "bg-gray-100 text-gray-800 dark:bg-gray-800/50 dark:text-gray-200 border-gray-200 dark:border-gray-700",
  [BOOKING_STATUS.Cancelled]: "bg-red-100 text-red-800 dark:bg-red-900/50 dark:text-red-200 border-red-200 dark:border-red-800",
};

export const STATUS_DOT: Record<string, string> = {
  [BOOKING_STATUS.Pending]: "bg-amber-500 dark:bg-amber-400",
  [BOOKING_STATUS.Confirmed]: "bg-green-500 dark:bg-green-400",
  [BOOKING_STATUS.CheckedIn]: "bg-blue-500 dark:bg-blue-400",
  [BOOKING_STATUS.CheckedOut]: "bg-gray-500 dark:bg-gray-400",
  [BOOKING_STATUS.Cancelled]: "bg-red-500 dark:bg-red-400",
};

export const STATUS_BG: Record<string, string> = {
  [BOOKING_STATUS.Pending]: "bg-amber-400/10 dark:bg-amber-500/15",
  [BOOKING_STATUS.Confirmed]: "bg-green-400/10 dark:bg-green-500/15",
  [BOOKING_STATUS.CheckedOut]: "bg-gray-400/10 dark:bg-gray-500/15",
  [BOOKING_STATUS.CheckedIn]: "bg-blue-400/10 dark:bg-blue-500/15",
  [BOOKING_STATUS.Cancelled]: "bg-red-400/10 dark:bg-red-500/15",
};

export const PAYMENT_STATUS_BADGE: Record<string, string> = {
  paid: "bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300",
  unpaid: "bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300",
  pending: "bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300",
  failed: "bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300",
};

export function getStatusLabel(status: string): string {
  return STATUS_LABELS[status] ?? status.replace(/_/g, " ").replace(/\b\w/g, (l) => l.toUpperCase());
}

export function getStatusColor(status: string): "default" | "secondary" | "destructive" | "outline" {
  return STATUS_VARIANT[status] ?? "secondary";
}

export function isActiveStatus(status: string): boolean {
  return ACTIVE_STATUSES.includes(status as BookingStatus);
}

export function isTerminalStatus(status: string): boolean {
  return TERMINAL_STATUSES.includes(status as BookingStatus);
}

export function canTransition(from: string, to: string): boolean {
  const transitions: Record<string, string[]> = {
    [BOOKING_STATUS.Pending]: [BOOKING_STATUS.Confirmed, BOOKING_STATUS.Cancelled],
    [BOOKING_STATUS.Confirmed]: [BOOKING_STATUS.CheckedIn, BOOKING_STATUS.Cancelled],
    [BOOKING_STATUS.CheckedIn]: [BOOKING_STATUS.CheckedOut, BOOKING_STATUS.Cancelled],
    [BOOKING_STATUS.CheckedOut]: [],
    [BOOKING_STATUS.Cancelled]: [],
  };
  return transitions[from]?.includes(to) ?? false;
}

export function formatStatusLabel(status: string): string {
  return status.replace(/_/g, " ").replace(/\b\w/g, (l) => l.toUpperCase());
}
