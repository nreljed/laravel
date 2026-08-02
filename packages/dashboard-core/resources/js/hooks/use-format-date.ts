import { usePage } from "@inertiajs/react";
import { formatDate, formatDateTime } from "@/lib/utils";

export function useFormatDate() {
  const props = usePage().props as Record<string, unknown>;
  const formatting = (props.formattingSettings as Record<string, string>) || {};
  const dateFormat = formatting.date_format || "Y-m-d";
  const timeFormat = formatting.time_format || "H:i";

  return {
    dateFormat,
    timeFormat,
    formatDate: (date: Parameters<typeof formatDate>[0], fallback?: string) =>
      formatDate(date, dateFormat, fallback),
    formatDateTime: (date: Parameters<typeof formatDateTime>[0], fallback?: string) =>
      formatDateTime(date, dateFormat, timeFormat, fallback),
  };
}
