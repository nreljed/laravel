import React, { useState, useCallback, useRef } from "react";
import { usePage } from "@inertiajs/react";
import { toast } from "sonner";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Button } from "@/Components/ui/button";
import { Badge } from "@/Components/ui/badge";
import { Separator } from "@/Components/ui/separator";
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from "@/Components/ui/card";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/Components/ui/select";
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from "@/Components/ui/alert-dialog";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/Components/ui/tabs";
import {
  Breadcrumb,
  BreadcrumbItem,
  BreadcrumbLink,
  BreadcrumbList,
  BreadcrumbPage,
  BreadcrumbSeparator,
} from "@/Components/ui/breadcrumb";
import BookingWidget from "../components/BookingWidget";
import BookingForm from "../components/BookingForm";
import type { LogEntry, DateSelection } from "../components/BookingWidget";
import {
  Beaker,
  Building2,
  MapPin,
  DollarSign,
  ExternalLink,
  RotateCcw,
  Users,
  CheckCircle2,
  XCircle,
  AlertTriangle,
  Loader2,
  ChevronDown,
  ChevronRight,
  Calendar,
  Shield,
  PawPrint,
  Info,
  Home,
  CalendarCheck,
  Trash2,
  Mail,
  Activity,
  RefreshCw,
  Key,
} from "lucide-react";
import { BOOKING_STATUS } from "../lib/booking-status";

type LogEntryType = "http" | "trace";

interface LogEntryData {
  type?: LogEntryType;
  method: string;
  url: string;
  status: number;
  duration: number;
  summary: string;
  timestamp: string;
  serverMs?: number;
  timing?: Record<string, number>;
  debug?: string[];
  requestBody?: string;
  responseBody?: string;
  pipelineStage?: string;
}

function LogEntryComponent({ entry }: { entry: LogEntryData }) {
  const isTrace = entry.type === "trace";
  const isSuccess = entry.status >= 200 && entry.status < 300;
  const isError = entry.status >= 400;
  const durationColor =
    entry.duration <= 200
      ? "text-green-500"
      : entry.duration <= 500
        ? "text-amber-500"
        : entry.duration <= 1000
          ? "text-orange-500"
          : "text-red-500";
  const [expanded, setExpanded] = useState(false);

  if (isTrace) {
    return (
      <div className="border border-blue-300/40 dark:border-blue-800/40 rounded-lg font-mono text-xs bg-blue-500/[0.03]">
        <button
          type="button"
          onClick={() => setExpanded(!expanded)}
          className="w-full text-left p-3 space-y-1"
        >
          <div className="flex items-center gap-2 text-muted-foreground">
            <span className="text-[10px] shrink-0">{entry.timestamp}</span>
            <Badge
              variant="secondary"
              className="text-[10px] px-1 py-0 h-4 bg-blue-100 dark:bg-blue-900 text-blue-700 dark:text-blue-300"
            >
              TRACE
            </Badge>
            <span className="text-[10px] font-bold text-blue-600 dark:text-blue-400">
              {entry.pipelineStage || "stage"}
            </span>
            <span className="text-foreground/70 ml-1">{entry.summary}</span>
            <div className="ml-auto shrink-0">
              {expanded ? (
                <ChevronDown className="h-3 w-3" />
              ) : (
                <ChevronRight className="h-3 w-3" />
              )}
            </div>
          </div>
          <div className="text-muted-foreground break-all">{entry.url}</div>
        </button>
        {expanded && entry.responseBody && (
          <div className="px-3 pb-3 border-t pt-2">
            <span className="text-[10px] font-semibold text-muted-foreground">Context</span>
            <pre className="text-[10px] bg-muted/50 p-2 rounded mt-0.5 overflow-x-auto max-h-[300px] scrollbar-thin">
              {entry.responseBody}
            </pre>
          </div>
        )}
      </div>
    );
  }

  return (
    <div
      className={`border rounded-lg font-mono text-xs transition-colors ${
        isError
          ? "border-red-300 dark:border-red-800 bg-red-500/5"
          : isSuccess
            ? "border-green-300 dark:border-green-800"
            : "border-border"
      }`}
    >
      <button
        type="button"
        onClick={() => setExpanded(!expanded)}
        className="w-full text-left p-3 space-y-1"
      >
        <div className="flex items-center gap-2 text-muted-foreground">
          <span className="text-[10px] shrink-0">{entry.timestamp}</span>
          <Badge
            variant={isError ? "destructive" : isSuccess ? "default" : "secondary"}
            className="text-[10px] px-1 py-0 h-4"
          >
            {entry.method}
          </Badge>
          <span
            className={`font-bold tabular-nums ${isError ? "text-red-500" : isSuccess ? "text-green-500" : "text-amber-500"}`}
          >
            {entry.status}
          </span>
          {entry.pipelineStage && (
            <Badge
              variant="outline"
              className="text-[10px] px-1 py-0 h-4 text-blue-600 dark:text-blue-400 border-blue-300"
            >
              {entry.pipelineStage}
            </Badge>
          )}
          <span className={`ml-auto font-semibold tabular-nums ${durationColor}`}>
            {entry.duration}ms
          </span>
          {entry.serverMs !== undefined && (
            <span className="text-[10px] text-blue-500 tabular-nums" title="Server processing time">
              → {entry.serverMs}ms
            </span>
          )}
          <div className="shrink-0">
            {expanded ? <ChevronDown className="h-3 w-3" /> : <ChevronRight className="h-3 w-3" />}
          </div>
        </div>
        <div className="text-foreground break-all">{entry.url}</div>
        <div className="flex items-center gap-2">
          <span
            className={`break-all ${isError ? "text-red-600 dark:text-red-400" : "text-muted-foreground"}`}
          >
            {entry.summary}
          </span>
        </div>
      </button>

      {expanded && (
        <div className="px-3 pb-3 space-y-2 border-t pt-2">
          {entry.requestBody && (
            <div>
              <span className="text-[10px] font-semibold text-muted-foreground">Request Body</span>
              <pre className="text-[10px] bg-muted/50 p-2 rounded mt-0.5 overflow-x-auto max-h-[120px] scrollbar-thin">
                {entry.requestBody}
              </pre>
            </div>
          )}

          {entry.responseBody && (
            <div>
              <span className="text-[10px] font-semibold text-muted-foreground">
                Response Body
                {isError && <span className="text-red-400 ml-1">(raw)</span>}
              </span>
              <pre className="text-[10px] bg-muted/50 p-2 rounded mt-0.5 overflow-x-auto max-h-[300px] scrollbar-thin">
                {entry.responseBody}
              </pre>
            </div>
          )}

          {entry.debug && entry.debug.length > 0 && (
            <div>
              <span className="text-[10px] font-semibold text-muted-foreground">
                Server Debug ({entry.debug.length} steps)
              </span>
              <div className="mt-0.5 space-y-px">
                {entry.debug.map((line, i) => {
                  const isTiming = line.includes("ms");
                  const isErr =
                    line.toLowerCase().includes("error") || line.toLowerCase().includes("fail");
                  return (
                    <div
                      key={i}
                      className={`text-[10px] pl-2 py-px ${
                        isErr
                          ? "text-red-500 bg-red-500/5"
                          : isTiming
                            ? "text-blue-500"
                            : "text-muted-foreground"
                      }`}
                    >
                      {isErr ? "✖ " : isTiming ? "⏱ " : "· "}
                      {line}
                    </div>
                  );
                })}
              </div>
            </div>
          )}

          <div className="flex items-center gap-3 text-[10px] text-muted-foreground pt-1 border-t border-border/50">
            <span>Client: {entry.duration}ms</span>
            {entry.serverMs !== undefined && <span>Server: {entry.serverMs}ms</span>}
            {entry.serverMs !== undefined && (
              <span
                className={
                  entry.duration - entry.serverMs > 100 ? "text-amber-500" : "text-green-500"
                }
              >
                Network: {Math.max(0, entry.duration - entry.serverMs)}ms
              </span>
            )}
          </div>
        </div>
      )}
    </div>
  );
}

function formatBool(v: unknown) {
  if (v === true || v === "1" || v === 1) return "Yes";
  if (v === false || v === "0" || v === 0 || v === "" || v == null) return "No";
  return String(v);
}

function formatPolicy(v: string | null | undefined) {
  if (!v) return "\u{2014}";
  const map: Record<string, string> = {
    flexible: "Flexible \u{2013} free cancel 24h before",
    moderate: "Moderate \u{2013} free cancel 5 days before",
    limited: "Limited \u{2013} free cancel 14 days before",
    firm: "Firm \u{2013} free cancel 30 days before",
    strict: "Strict \u{2013} 50% refund 7+ days before",
    long_term: "Long Term",
    non_refundable: "Non-refundable",
  };
  return map[v] || v;
}

function formatSmoking(v: string | null | undefined) {
  if (!v || v === "not_allowed") return "Not allowed";
  if (v === "outside_only") return "Outside only";
  if (v === "anywhere") return "Anywhere";
  return v;
}

function formatCheckDays(days: string[] | null | undefined) {
  if (!days || days.length === 0) return "All days";
  return days.map((d: string) => d).join(", ");
}

function DetailRow({
  label,
  children,
  className = "",
  multiline = false,
  status,
}: {
  label: string;
  children: React.ReactNode;
  className?: string;
  multiline?: boolean;
  status?: "ok" | "error" | "warn" | "info";
}) {
  if (multiline) {
    return (
      <div className={`py-1 ${className}`}>
        <span className="text-xs text-muted-foreground block">{label}</span>
        <span className="text-xs font-medium block mt-0.5 whitespace-pre-wrap break-words">
          {children || "\u{2014}"}
        </span>
      </div>
    );
  }

  const statusIcon =
    status === "ok" ? (
      <CheckCircle2 className="h-3 w-3 shrink-0 text-green-600 dark:text-green-400" />
    ) : status === "error" ? (
      <XCircle className="h-3 w-3 shrink-0 text-red-600 dark:text-red-400" />
    ) : status === "warn" ? (
      <AlertTriangle className="h-3 w-3 shrink-0 text-amber-500 dark:text-amber-400" />
    ) : null;

  return (
    <div className={`flex items-center justify-between gap-2 py-1 ${className}`}>
      <span className="text-xs text-muted-foreground shrink-0">{label}</span>
      <span className="text-xs font-medium text-right flex items-center gap-1">
        {statusIcon}
        {children || "\u{2014}"}
      </span>
    </div>
  );
}

function SectionPanel({
  title,
  icon: Icon,
  defaultOpen = true,
  children,
}: {
  title: string;
  icon?: React.ComponentType<{ className?: string }>;
  defaultOpen?: boolean;
  children: React.ReactNode;
}) {
  const [open, setOpen] = useState(defaultOpen);
  return (
    <div className="border rounded-lg">
      <button
        type="button"
        onClick={() => setOpen(!open)}
        className="w-full flex items-center gap-2 px-3 py-2 text-xs font-semibold text-muted-foreground hover:text-foreground transition-colors"
      >
        {Icon && <Icon className="h-3.5 w-3.5" />}
        <span className="flex-1 text-left">{title}</span>
        {open ? <ChevronDown className="h-3 w-3" /> : <ChevronRight className="h-3 w-3" />}
      </button>
      {open && <div className="px-3 pb-2 space-y-0.5 border-t pt-2">{children}</div>}
    </div>
  );
}

function fmt(val: unknown): string {
  if (val == null || val === "") return "\u{2014}";
  if (typeof val === "boolean") return val ? "Yes" : "No";
  if (Array.isArray(val)) return val.length > 0 ? val.join(", ") : "\u{2014}";
  if (typeof val === "number") return String(val);
  return String(val);
}

function BookingConfigPanel({ settings }: { settings: Record<string, unknown> }) {
  if (!settings) return null;

  const s = settings;

  const sections: React.ReactNode[] = [
    <SectionPanel key="pricing" title="Pricing & Fees" icon={DollarSign}>
      <DetailRow label="Nightly rate" status={Number(s.nightly_rate) > 0 ? "ok" : "error"}>
        {fmt(s.nightly_rate)}
      </DetailRow>
      <DetailRow label="Base guests" status={Number(s.base_guests) > 0 ? "ok" : "error"}>
        {fmt(s.base_guests)}
      </DetailRow>
      <DetailRow label="Extra guest fee">
        {s.extra_guest_fee ? `${fmt(s.extra_guest_fee)} /guest/night` : "\u{2014}"}
      </DetailRow>
      <DetailRow label="Cleaning fee">{fmt(s.cleaning_fee)}</DetailRow>
      <DetailRow label="Service fee">{fmt(s.service_fee)}</DetailRow>
      <DetailRow label="Service fee type">{fmt(s.service_fee_type)}</DetailRow>
      <DetailRow label="Weekend days">
        {Array.isArray(s.weekend_days) && s.weekend_days.length > 0
          ? (s.weekend_days as string[]).map((d: string) => d.slice(0, 3)).join(", ")
          : "All days"}
      </DetailRow>
      <DetailRow label="Weekend multiplier">{fmt(s.weekend_multiplier)}</DetailRow>
      <DetailRow label="Pet fee">{fmt(s.pet_fee)}</DetailRow>
      <DetailRow label="Pet deposit">{fmt(s.pet_deposit)}</DetailRow>
      <DetailRow label="Damage deposit">{fmt(s.damage_deposit_amount)}</DetailRow>
      <DetailRow label="Early check-in fee">{fmt(s.early_check_in_fee)}</DetailRow>
      <DetailRow label="Late check-out fee">{fmt(s.late_check_out_fee)}</DetailRow>
      <DetailRow label="Include checkout day">{formatBool(s.include_checkout_day)}</DetailRow>
    </SectionPanel>,
    <SectionPanel key="availability" title="Availability Rules" icon={Calendar}>
      <DetailRow label="Min nights" status={Number(s.min_nights) >= 1 ? "ok" : "error"}>
        {fmt(s.min_nights)}
      </DetailRow>
      <DetailRow label="Max nights">{fmt(s.max_nights)}</DetailRow>
      <DetailRow label="Advance notice (days)">{fmt(s.advance_notice_days)}</DetailRow>
      <DetailRow label="Preparation days">{fmt(s.preparation_days)}</DetailRow>
      <DetailRow label="Earliest bookable">{fmt(s.earliest_bookable_date)}</DetailRow>
      <DetailRow label="Latest bookable">{fmt(s.latest_bookable_date)}</DetailRow>
    </SectionPanel>,
    <SectionPanel key="checkin" title="Check-in / Check-out" icon={CalendarCheck}>
      <DetailRow label="Check-in time" status={s.check_in_time ? "ok" : "warn"}>
        {fmt(s.check_in_time) || "Not set"}
      </DetailRow>
      <DetailRow label="Check-out time" status={s.check_out_time ? "ok" : "warn"}>
        {fmt(s.check_out_time) || "Not set"}
      </DetailRow>
      <DetailRow label="Allow early check-in">{formatBool(s.allow_early_check_in)}</DetailRow>
      <DetailRow label="Allow late check-out">{formatBool(s.allow_late_check_out)}</DetailRow>
      <DetailRow label="Weekday check-in only">
        {formatCheckDays(s.weekday_checkin_only as string[] | null | undefined)}
      </DetailRow>
      <DetailRow label="Weekday check-out only">
        {formatCheckDays(s.weekday_checkout_only as string[] | null | undefined)}
      </DetailRow>
    </SectionPanel>,
    <SectionPanel key="policies" title="Booking Policies" icon={Shield}>
      <DetailRow label="Instant book" status={s.instant_book ? "ok" : undefined}>
        {formatBool(s.instant_book)}
      </DetailRow>
      <DetailRow label="Cancellation policy">
        {formatPolicy(s.cancellation_policy as string)}
      </DetailRow>
      <DetailRow label="Default status">
        {(s.default_status as string) || BOOKING_STATUS.Pending}
      </DetailRow>
      <DetailRow label="Max occupancy" status={Number(s.max_occupancy) > 0 ? "ok" : "warn"}>
        {fmt(s.max_occupancy)}
      </DetailRow>
      <DetailRow label="Children allowed">{formatBool(s.children_allowed)}</DetailRow>
      <DetailRow label="Infants allowed">{formatBool(s.infants_allowed)}</DetailRow>
      <DetailRow label="Smoking policy">{formatSmoking(s.smoking_allowed as string)}</DetailRow>
      <DetailRow label="Min guest age">{fmt(s.min_guest_age)}</DetailRow>
    </SectionPanel>,
    <SectionPanel key="pets" title="Pets Policy" icon={PawPrint}>
      <DetailRow label="Pets allowed">{formatBool(s.pets_allowed)}</DetailRow>
      {Boolean(s.pets_allowed) && (
        <>
          <DetailRow label="Pet types">
            {Array.isArray(s.pet_types) && (s.pet_types as string[]).length > 0
              ? (s.pet_types as string[]).join(", ")
              : "\u{2014}"}
          </DetailRow>
          <DetailRow label="Max pets">{fmt(s.max_pets)}</DetailRow>
          <DetailRow label="Pet fee">{fmt(s.pet_fee)}</DetailRow>
          <DetailRow label="Pet deposit">{fmt(s.pet_deposit)}</DetailRow>
          {s.pet_size_restrictions && (
            <DetailRow label="Size restrictions" multiline>
              {fmt(s.pet_size_restrictions)}
            </DetailRow>
          )}
          {s.pet_rules && (
            <DetailRow label="Pet rules" multiline>
              {fmt(s.pet_rules)}
            </DetailRow>
          )}
        </>
      )}
    </SectionPanel>,
    <SectionPanel key="guests" title="Guest Requirements" icon={Users}>
      <DetailRow label="Unregistered guests allowed">
        {formatBool(s.allow_unregistered_guests)}
      </DetailRow>
      <DetailRow label="Max unregistered guests">{fmt(s.unregistered_guest_limit)}</DetailRow>
      {Boolean(s.unregistered_guest_rules) && (
        <DetailRow label="Visitor rules" multiline>
          {fmt(s.unregistered_guest_rules)}
        </DetailRow>
      )}
    </SectionPanel>,
    <SectionPanel key="safety" title="Safety & Security" icon={Shield}>
      <DetailRow label="Safety features">
        {Array.isArray(s.safety_features) && (s.safety_features as string[]).length > 0
          ? (s.safety_features as string[]).map((f: string) => f.replaceAll("_", " ")).join(", ")
          : "\u{2014}"}
      </DetailRow>
      <DetailRow label="Damage deposit required">{formatBool(s.require_damage_deposit)}</DetailRow>
      {Boolean(s.security_cameras_details) && (
        <DetailRow label="Camera details" multiline>
          {fmt(s.security_cameras_details)}
        </DetailRow>
      )}
      {Boolean(s.damage_deposit_policy) && (
        <DetailRow label="Deposit policy" multiline>
          {fmt(s.damage_deposit_policy)}
        </DetailRow>
      )}
    </SectionPanel>,
    <SectionPanel key="property" title="Property Info" icon={Home}>
      <DetailRow label="Parking spaces">{fmt(s.parking_spaces)}</DetailRow>
      {Boolean(s.parking_info) && (
        <DetailRow label="Parking info" multiline>
          {fmt(s.parking_info)}
        </DetailRow>
      )}
      {Boolean(s.transit_info) && (
        <DetailRow label="Transit info" multiline>
          {fmt(s.transit_info)}
        </DetailRow>
      )}
      {Boolean(s.neighborhood_description) && (
        <DetailRow label="Neighborhood" multiline>
          {fmt(s.neighborhood_description)}
        </DetailRow>
      )}
      {Boolean(s.local_recommendations) && (
        <DetailRow label="Local recs" multiline>
          {fmt(s.local_recommendations)}
        </DetailRow>
      )}
      {Boolean(s.check_in_instructions) && (
        <DetailRow label="Check-in instr." multiline>
          {fmt(s.check_in_instructions)}
        </DetailRow>
      )}
      {Boolean(s.wifi_password) && (
        <DetailRow label="WiFi password">{fmt(s.wifi_password)}</DetailRow>
      )}
      {Boolean(s.emergency_contact) && (
        <DetailRow label="Emergency contact">{fmt(s.emergency_contact)}</DetailRow>
      )}
    </SectionPanel>,
    <SectionPanel key="repeater" title="Advanced Config" icon={Info}>
      <DetailRow label="Seasonal rates">
        {s.seasonal_rates && Array.isArray(s.seasonal_rates)
          ? `${(s.seasonal_rates as Array<unknown>).length} entries`
          : "\u{2014}"}
      </DetailRow>
      <DetailRow label="Length-of-stay rates">
        {s.length_of_stay_rates && Array.isArray(s.length_of_stay_rates)
          ? `${(s.length_of_stay_rates as Array<unknown>).length} entries`
          : "\u{2014}"}
      </DetailRow>
      <DetailRow label="Length-of-stay tiers">
        {s.length_of_stay_tiers && Array.isArray(s.length_of_stay_tiers)
          ? `${(s.length_of_stay_tiers as Array<unknown>).length} entries`
          : "\u{2014}"}
      </DetailRow>
      <DetailRow label="Unit config">
        {s.unit_config && Array.isArray(s.unit_config)
          ? `${(s.unit_config as Array<unknown>).length} units`
          : "\u{2014}"}
      </DetailRow>
      <DetailRow label="House rules">
        {s.house_rules && Array.isArray(s.house_rules)
          ? `${(s.house_rules as Array<unknown>).length} rules`
          : "\u{2014}"}
      </DetailRow>
    </SectionPanel>,
  ];

  return <div className="space-y-2">{sections.filter(Boolean)}</div>;
}

interface DiagnosticCheck {
  label: string;
  key: string;
  status: "ok" | "error" | "warn" | "info";
  message: string;
  detail?: string;
}

function runBookingDiagnostics(s: Record<string, unknown>): DiagnosticCheck[] {
  const checks: DiagnosticCheck[] = [];

  const num = (v: unknown, defaultVal = 0): number => {
    if (v == null || v === "") return defaultVal;
    const n = Number(v);
    return Number.isNaN(n) ? defaultVal : n;
  };

  const nr = num(s.nightly_rate);
  if (nr > 0) {
    checks.push({
      label: "Nightly rate",
      key: "nightly_rate",
      status: "ok",
      message: `$${nr.toFixed(2)}/night configured`,
      detail: "Pricing will calculate correctly",
    });
  } else {
    checks.push({
      label: "Nightly rate",
      key: "nightly_rate",
      status: "error",
      message: "No nightly rate set",
      detail: "Guests cannot book without a price",
    });
  }

  const cf = num(s.cleaning_fee);
  if (cf > 0) {
    checks.push({
      label: "Cleaning fee",
      key: "cleaning_fee",
      status: "ok",
      message: `$${cf.toFixed(2)} per stay`,
      detail: "Cleaning fee charged on every booking",
    });
  } else {
    checks.push({
      label: "Cleaning fee",
      key: "cleaning_fee",
      status: "info",
      message: "Not set ($0)",
      detail: "No cleaning fee charged",
    });
  }

  const sf = num(s.service_fee);
  if (sf > 0) {
    checks.push({
      label: "Service fee",
      key: "service_fee",
      status: "ok",
      message: `$${sf.toFixed(2)} per stay`,
      detail: "Service fee charged on every booking",
    });
  } else {
    checks.push({
      label: "Service fee",
      key: "service_fee",
      status: "info",
      message: "Not set ($0)",
      detail: "No service fee charged",
    });
  }

  const minN = num(s.min_nights, 1);
  if (minN >= 1) {
    checks.push({
      label: "Min nights",
      key: "min_nights",
      status: "ok",
      message: `${minN} night${minN === 1 ? "" : "s"} minimum`,
      detail: "Minimum stay enforced",
    });
  } else {
    checks.push({
      label: "Min nights",
      key: "min_nights",
      status: "error",
      message: "Min nights not configured",
      detail: "Guests would see no minimum-stay limit",
    });
  }

  const maxN = num(s.max_nights, 30);
  if (maxN <= 0) {
    checks.push({
      label: "Max nights",
      key: "max_nights",
      status: "warn",
      message: "Unlimited (no maximum set)",
      detail: "Guests can book any length of stay",
    });
  } else if (maxN >= minN) {
    checks.push({
      label: "Max nights",
      key: "max_nights",
      status: "ok",
      message: `Up to ${maxN} nights`,
      detail: "Maximum stay enforced",
    });
  } else {
    checks.push({
      label: "Max nights",
      key: "max_nights",
      status: "error",
      message: `Max (${maxN}) < min (${minN}) — impossible to book`,
      detail: "No valid stay length exists",
    });
  }

  const adv = num(s.advance_notice_days, 1);
  if (adv === 0) {
    checks.push({
      label: "Advance notice",
      key: "advance_notice_days",
      status: "info",
      message: "Same-day booking allowed",
      detail: "Guests can book today",
    });
  } else {
    checks.push({
      label: "Advance notice",
      key: "advance_notice_days",
      status: "ok",
      message: `${adv} day${adv === 1 ? "" : "s"} notice required`,
      detail: "Guests must book in advance",
    });
  }

  const prep = num(s.preparation_days, 0);
  if (prep >= 0) {
    checks.push({
      label: "Preparation days",
      key: "preparation_days",
      status: "ok",
      message: `${prep} day${prep === 1 ? "" : "s"} buffer`,
      detail: prep === 0 ? "No gap between bookings" : "Time for cleaning between stays",
    });
  }

  const checkIn =
    s.check_in_time && String(s.check_in_time).trim() ? String(s.check_in_time) : "15:00";
  const checkOut =
    s.check_out_time && String(s.check_out_time).trim() ? String(s.check_out_time) : "11:00";
  checks.push(
    {
      label: "Check-in time",
      key: "check_in_time",
      status: "ok",
      message: checkIn,
      detail: "Check-in window",
    },
    {
      label: "Check-out time",
      key: "check_out_time",
      status: "ok",
      message: checkOut,
      detail: "Check-out window",
    },
  );

  const bg = num(s.base_guests);
  if (bg > 0) {
    checks.push({
      label: "Base guests",
      key: "base_guests",
      status: "ok",
      message: `${bg} guest${bg === 1 ? "" : "s"} included`,
      detail: "Extra guest charges apply beyond this",
    });
  } else {
    checks.push({
      label: "Base guests",
      key: "base_guests",
      status: "error",
      message: "Base guests not set",
      detail: "Extra guest calculation may be wrong",
    });
  }

  const mo = num(s.max_occupancy);
  if (mo > 0) {
    checks.push({
      label: "Max occupancy",
      key: "max_occupancy",
      status: "ok",
      message: `Up to ${mo} guests`,
      detail: "Maximum allowed guests enforced",
    });
  } else {
    checks.push({
      label: "Max occupancy",
      key: "max_occupancy",
      status: "warn",
      message: "Not set (unlimited)",
      detail: "No cap on guest count",
    });
  }

  const ear = num(s.earliest_bookable_date, 0);
  checks.push({
    label: "Earliest bookable",
    key: "earliest_bookable_date",
    status: "ok",
    message: ear === 0 ? "Today" : `${ear} days ahead`,
    detail: ear === 0 ? "Guests can book today" : "Earliest check-in window",
  });

  const lat = num(s.latest_bookable_date, 12);
  if (lat > 0) {
    checks.push({
      label: "Latest bookable",
      key: "latest_bookable_date",
      status: "ok",
      message: `${lat} month${lat === 1 ? "" : "s"} ahead`,
      detail: "Booking window limit",
    });
  } else {
    checks.push({
      label: "Latest bookable",
      key: "latest_bookable_date",
      status: "warn",
      message: "No limit",
      detail: "Guests can book indefinitely far ahead",
    });
  }

  checks.push({
    label: "Instant booking",
    key: "instant_book",
    status: s.instant_book ? "ok" : "info",
    message: s.instant_book ? "Enabled — auto-accept" : "Disabled — manual approval",
    detail: s.instant_book
      ? "Guests can book without host review"
      : "Each booking requires host confirmation",
  });

  const wcin = s.weekday_checkin_only;
  if (Array.isArray(wcin) && wcin.length > 0 && wcin.length < 7) {
    checks.push({
      label: "Check-in days restricted",
      key: "weekday_checkin_only",
      status: "info",
      message: `${wcin.length} day${wcin.length === 1 ? "" : "s"} allowed`,
      detail: wcin.map((d: string) => d.charAt(0).toUpperCase() + d.slice(1)).join(", "),
    });
  }

  const wcout = s.weekday_checkout_only;
  if (Array.isArray(wcout) && wcout.length > 0 && wcout.length < 7) {
    checks.push({
      label: "Check-out days restricted",
      key: "weekday_checkout_only",
      status: "info",
      message: `${wcout.length} day${wcout.length === 1 ? "" : "s"} allowed`,
      detail: wcout.map((d: string) => d.charAt(0).toUpperCase() + d.slice(1)).join(", "),
    });
  }

  if (s.cancellation_policy) {
    checks.push({
      label: "Cancellation policy",
      key: "cancellation_policy",
      status: "ok",
      message: String(s.cancellation_policy).replace("_", " "),
      detail: "Cancellation terms defined",
    });
  }

  if (s.require_damage_deposit) {
    checks.push({
      label: "Damage deposit",
      key: "require_damage_deposit",
      status: "info",
      message: `$${num(s.damage_deposit_amount).toFixed(2)} required`,
      detail: "Refundable deposit collected before check-in",
    });
  }

  const uc = s.unit_config;
  if (Array.isArray(uc) && uc.length > 0) {
    checks.push({
      label: "Unit configuration",
      key: "unit_config",
      status: "ok",
      message: `${uc.length} unit type${uc.length === 1 ? "" : "s"} configured`,
      detail: "Guests can select from available units",
    });
  } else {
    checks.push({
      label: "Unit configuration",
      key: "unit_config",
      status: "info",
      message: "No unit types — single-rate booking",
      detail: "Booking uses the base nightly rate",
    });
  }

  checks.push({
    label: "Checkout day pricing",
    key: "include_checkout_day",
    status: "info",
    message: s.include_checkout_day ? "Included in price" : "Not included",
    detail: s.include_checkout_day
      ? "Guest pays for checkout night too"
      : "Pricing stops night before checkout",
  });

  const wd = s.weekend_days;
  const wm = num(s.weekend_multiplier);
  if (Array.isArray(wd) && wd.length > 0 && wm > 1) {
    checks.push({
      label: "Weekend pricing",
      key: "weekend_multiplier",
      status: "info",
      message: `${wm}x on ${wd.map((d: string) => d.charAt(0).toUpperCase() + d.slice(1)).join(", ")}`,
      detail: "Higher rates on select days",
    });
  } else if (Array.isArray(wd) && wd.length > 0) {
    checks.push({
      label: "Weekend pricing",
      key: "weekend_multiplier",
      status: "info",
      message: "No weekend markup",
      detail: "Same rate all week",
    });
  }

  return checks;
}

interface BookingDiagnosticCardProps {
  settings: Record<string, unknown>;
  dates: DateSelection | null;
  property: DemoProperty | undefined;
  logs: LogEntryData[];
}

function BookingDiagnosticCard({ settings, dates, property, logs }: BookingDiagnosticCardProps) {
  const checks = React.useMemo(() => runBookingDiagnostics(settings), [settings]);

  const errors = checks.filter((c) => c.status === "error").length;
  const warnings = checks.filter((c) => c.status === "warn").length;
  const ok = checks.filter((c) => c.status === "ok").length;

  const copyFullReport = () => {
    const allChecks = runBookingDiagnostics(settings);
    const errCount = allChecks.filter((c) => c.status === "error").length;
    const warnCount = allChecks.filter((c) => c.status === "warn").length;
    const infoCount = allChecks.filter((c) => c.status === "info").length;
    const okCount = allChecks.filter((c) => c.status === "ok").length;

    const report = {
      generated_at: (() => {
        const now = new Date();
        return now.toISOString();
      })(),
      timezone: Intl.DateTimeFormat().resolvedOptions().timeZone,
      property: property
        ? {
            id: property.id,
            title: property.title,
            bookable: property.bookable,
            city: property.city,
          }
        : null,
      calendar_dates: dates
        ? {
            start: dates.start,
            end: dates.end,
            nights:
              dates.start && dates.end
                ? (() => {
                    const end = new Date(dates.end);
                    const start = new Date(dates.start);
                    return Math.round((end.getTime() - start.getTime()) / (1000 * 60 * 60 * 24));
                  })()
                : null,
            localStorage_key: dates.start && dates.end ? `crm_booking_dates_${property?.id}` : null,
          }
        : null,
      settings,
      diagnostics: {
        total_checks: allChecks.length,
        blockers: errCount,
        warnings: warnCount,
        info: infoCount,
        ok: okCount,
        all_checks: allChecks,
      },
      api_logs: logs.map((l) => ({
        timestamp: l.timestamp,
        method: l.method,
        url: l.url,
        status: l.status,
        duration: `${l.duration}ms`,
        server_ms: l.serverMs ?? null,
        timing: l.timing ?? null,
        summary: l.summary,
        pipeline_stage: l.pipelineStage ?? null,
        request_body: l.requestBody?.replaceAll(/_wpnonce=\*{3}/g, "_wpnonce=***"),
        response_body: l.responseBody,
        debug: l.debug ?? [],
      })),
    };
    const text = JSON.stringify(report, null, 2);
    navigator.clipboard.writeText(text);
    toast.success("Full report copied to clipboard");
  };

  return (
    <Card className="border-amber-200/50 dark:border-amber-800/50">
      <CardHeader className="pb-3">
        <div className="flex items-center justify-between">
          <CardTitle className="flex items-center gap-2 text-base">
            <Activity className="h-4 w-4" /> Booking Diagnostics
          </CardTitle>
          <div className="flex items-center gap-2">
            {errors > 0 && (
              <Badge variant="destructive" className="text-[10px]">
                {errors} blocker{errors !== 1 ? "s" : ""}
              </Badge>
            )}
            {warnings > 0 && (
              <Badge
                variant="outline"
                className="text-[10px] text-amber-600 dark:text-amber-400 border-amber-300 dark:border-amber-700"
              >
                {warnings} warning{warnings !== 1 ? "s" : ""}
              </Badge>
            )}
            <Badge variant="secondary" className="text-[10px]">
              {ok} ok
            </Badge>
            <button
              type="button"
              onClick={copyFullReport}
              className="text-[10px] text-muted-foreground hover:text-foreground underline ml-1"
            >
              Copy Full Report
            </button>
          </div>
        </div>
        <CardDescription>
          {errors > 0
            ? `${errors} issue${errors !== 1 ? "s" : ""} blocking bookings — fix before going live`
            : "All critical settings are configured correctly"}
        </CardDescription>
      </CardHeader>
      <CardContent className="space-y-4">
        {dates && (
          <div className="rounded-md border border-blue-200 dark:border-blue-800 bg-blue-50/50 dark:bg-blue-950/20 px-3 py-2 text-xs space-y-1">
            <div className="flex items-center gap-2 font-medium text-foreground">
              <Calendar className="h-3.5 w-3.5 text-blue-600 dark:text-blue-400" />
              Calendar Date State
            </div>
            <div className="grid grid-cols-2 sm:grid-cols-4 gap-2 text-muted-foreground text-[11px]">
              <div>
                <span className="block text-[10px] uppercase tracking-wider">Check-in</span>
                <span className="font-mono text-foreground">{dates.start ?? "— not set —"}</span>
              </div>
              <div>
                <span className="block text-[10px] uppercase tracking-wider">Check-out</span>
                <span className="font-mono text-foreground">{dates.end ?? "— not set —"}</span>
              </div>
              <div>
                <span className="block text-[10px] uppercase tracking-wider">Nights</span>
                <span className="font-mono text-foreground">
                  {dates.start && dates.end
                    ? (() => {
                        const end = new Date(dates.end);
                        const start = new Date(dates.start);
                        return Math.round(
                          (end.getTime() - start.getTime()) / (1000 * 60 * 60 * 24),
                        );
                      })()
                    : "—"}
                </span>
              </div>
              <div>
                <span className="block text-[10px] uppercase tracking-wider">Source</span>
                <span className="font-mono text-foreground">
                  {dates.start && dates.end
                    ? `localStorage (crm_booking_dates_${property?.id})`
                    : "none persisted"}
                </span>
              </div>
            </div>
            {dates.start && new Date(dates.start) <= new Date() && (
              <div className="flex items-center gap-1.5 text-amber-600 dark:text-amber-400">
                <AlertTriangle className="h-3 w-3" />
                <span>Check-in date is today or in the past — may be blocked by same-day gate</span>
              </div>
            )}
          </div>
        )}
        {!dates && (
          <div className="rounded-md border border-muted bg-muted/20 px-3 py-2 text-xs text-muted-foreground flex items-center gap-2">
            <Info className="h-3.5 w-3.5" />
            No dates selected yet. Interact with the calendar to see date state here.
          </div>
        )}

        <div className="grid grid-cols-1 sm:grid-cols-2 gap-1.5">
          {checks.map((c) => (
            <div
              key={c.key}
              className={`flex items-start gap-2 rounded-md px-2.5 py-1.5 text-xs border ${
                c.status === "error"
                  ? "bg-red-50 dark:bg-red-950/20 border-red-200 dark:border-red-800"
                  : c.status === "warn"
                    ? "bg-amber-50 dark:bg-amber-950/20 border-amber-200 dark:border-amber-800"
                    : c.status === "info"
                      ? "bg-muted/30 border-border"
                      : "bg-transparent border-transparent"
              }`}
            >
              {c.status === "ok" ? (
                <CheckCircle2 className="h-3.5 w-3.5 shrink-0 mt-0.5 text-green-600 dark:text-green-400" />
              ) : c.status === "error" ? (
                <XCircle className="h-3.5 w-3.5 shrink-0 mt-0.5 text-red-600 dark:text-red-400" />
              ) : c.status === "warn" ? (
                <AlertTriangle className="h-3.5 w-3.5 shrink-0 mt-0.5 text-amber-500 dark:text-amber-400" />
              ) : (
                <Info className="h-3.5 w-3.5 shrink-0 mt-0.5 text-muted-foreground" />
              )}
              <div className="min-w-0">
                <span className="font-medium text-foreground">{c.label}</span>
                <span className="text-muted-foreground"> — {c.message}</span>
                {c.detail && (
                  <p className="text-[10px] text-muted-foreground/70 mt-0.5 leading-tight">
                    {c.detail}
                  </p>
                )}
              </div>
            </div>
          ))}
        </div>
      </CardContent>
    </Card>
  );
}

interface DemoProperty {
  id: number;
  title?: string;
  bookable?: boolean;
  thumbnail_url?: string;
  price?: string | number;
  rental_period?: string;
  city?: string;
  status_label?: string;
  booking?: {
    enabled?: boolean;
    settings?: Record<string, unknown>;
  };
}

interface BookingDemoProps {
  properties?: DemoProperty[];
  nonce?: string;
  cleanupNonce?: string;
  seedNonce?: string;
}

export default function BookingDemo({ properties = [], cleanupNonce }: BookingDemoProps) {
  const { props: pageProps } = usePage();
  const siteProps = pageProps?.site as Record<string, unknown> | undefined;
  const weekStartsOn = (siteProps?.start_of_week as number | undefined) ?? 1;

  const [demoDates, setDemoDates] = useState<DateSelection | null>(null);
  const [selectedPropertyId, setSelectedPropertyId] = useState("");
  const [activeBookingId, setActiveBookingId] = useState<number | null>(null);
  const [logs, setLogs] = useState<LogEntryData[]>([]);
  const [cleaningUp, setCleaningUp] = useState(false);
  const [clearingOpcache, setClearingOpcache] = useState(false);
  const [showCleanupConfirm, setShowCleanupConfirm] = useState(false);
  const [refreshKey, setRefreshKey] = useState(0);
  const [magicLinkData, setMagicLinkData] = useState<null | {
    magic_link?: string;
    raw_token?: string;
    guest_email?: string;
    token_table_exists?: boolean;
  }>(null);
  const [tokenStatusData, setTokenStatusData] = useState<null | {
    tokens?: Array<Record<string, unknown>>;
    table_exists?: boolean;
  }>(null);
  const [emailStatusData, setEmailStatusData] = useState<null | {
    emails?: Array<{ key: string; label: string; sent: boolean; sent_at: string | null }>;
  }>(null);
  const [verifyData, setVerifyData] = useState<null | {
    checks?: Array<{ key: string; label: string; passed: boolean; detail: string }>;
    summary?: string;
    all_passed?: boolean;
  }>(null);
  const [pipelineTraceData, setPipelineTraceData] = useState<Array<{
    at: string;
    stage: string;
    message: string;
    context?: Record<string, unknown>;
  }> | null>(null);
  const [traceLoading, setTraceLoading] = useState(false);
  const [transitioning, setTransitioning] = useState(false);
  const [magicLinkLoading, setMagicLinkLoading] = useState(false);
  const logEndRef = useRef<HTMLDivElement | null>(null);

  const selectedProperty = properties.find(
    (p: DemoProperty) => String(p.id) === selectedPropertyId,
  );

  const addLog = useCallback(
    (
      method: string,
      url: string,
      status: number,
      duration: number,
      summary: string,
      serverMs?: number,
      debug?: string[],
      requestBody?: string,
      responseBody?: string,
      timing?: Record<string, number>,
    ) => {
      const now = new Date();
      const timestamp = now.toLocaleTimeString("en-US", { hour12: false });
      setLogs((prev: LogEntryData[]) => [
        ...prev,
        {
          method,
          url,
          status,
          duration,
          summary,
          timestamp,
          serverMs,
          debug,
          requestBody,
          responseBody,
          timing,
        },
      ]);
    },
    [],
  );

  const trackedFetch = useCallback(
    async (
      url: string,
      method: string,
      body: URLSearchParams,
      label: string,
    ): Promise<{ res: Response | null; serverMs?: number }> => {
      const start = performance.now();
      const requestBody = body
        .toString()
        .replace(/_wpnonce=[^&]*&?/, "_wpnonce=***")
        .replace(/&?$/, "");
      try {
        const res = await fetch(url, {
          method,
          headers: { "Content-Type": "application/x-www-form-urlencoded" },
          body,
        });
        const elapsed = Math.round(performance.now() - start);
        let serverMs: number | undefined;
        let debug: string[] | undefined;
        let timing: Record<string, number> | undefined;
        let responseBody: string | undefined;
        const cloned = res.clone();
        try {
          const text = await cloned.text();
          if (!res.ok) {
            responseBody = text;
          } else {
            responseBody =
              text.length > 2000
                ? text.slice(0, 2000) + "\n... (truncated, " + text.length + " chars)"
                : text;
          }
          const data = JSON.parse(text);
          serverMs = data._server_time_ms;
          debug = data._debug;
          timing = data._timing;
        } catch {
          /* ignore parse errors */
        }
        const summary = res.ok ? `OK (${label})` : `FAILED (${label})`;
        addLog(
          method,
          url,
          res.status,
          elapsed,
          summary,
          serverMs,
          debug,
          requestBody,
          responseBody,
          timing,
        );
        return { res, serverMs };
      } catch {
        const elapsed = Math.round(performance.now() - start);
        addLog(method, url, 0, elapsed, "Network error", undefined, undefined, requestBody);
        toast.error("Network error");
        return { res: null };
      }
    },
    [addLog],
  );

  const handleWidgetLog = useCallback(
    (entry: LogEntry) => {
      addLog(
        entry.method,
        entry.url,
        typeof entry.status === "number" ? entry.status : 0,
        entry.duration,
        entry.summary,
        entry.serverMs,
        undefined,
        undefined,
        undefined,
        entry.timing,
      );
    },
    [addLog],
  );

  const handleBookingCreated = useCallback(async (bookingId: number) => {
    setActiveBookingId(bookingId);
    setRefreshKey((prev) => prev + 1);
    toast.success(`Booking #${bookingId} created!`);

    setLogs((prev: LogEntryData[]) => {
      if (prev.length === 0) return prev;
      const updated = [...prev];
      updated[updated.length - 1] = {
        ...updated.at(-1),
        pipelineStage: "Booking created",
      } as LogEntryData;
      return updated;
    });

    const bid = bookingId;
    (async () => {
      setMagicLinkLoading(true);
      try {
        const r = await fetch("/dashboard/bookings/demo/generate-magic-link/" + bid, {
          method: "POST",
          headers: { "Content-Type": "application/x-www-form-urlencoded" },
          body: new URLSearchParams({ _wpnonce: "" }),
        });
        if (r.ok) setMagicLinkData(await r.json());
      } catch {
        /* ignore */
      }
      setMagicLinkLoading(false);
    })();
    (async () => {
      try {
        const r = await fetch("/dashboard/bookings/demo/verify/" + bid);
        if (r.ok) setVerifyData(await r.json());
      } catch {
        /* ignore */
      }
    })();
    (async () => {
      try {
        const r = await fetch("/dashboard/bookings/demo/email-status/" + bid);
        if (r.ok) setEmailStatusData(await r.json());
      } catch {
        /* ignore */
      }
    })();
    (async () => {
      try {
        const r = await fetch("/dashboard/bookings/demo/token-status/" + bid);
        if (r.ok) setTokenStatusData(await r.json());
      } catch {
        /* ignore */
      }
    })();
    (async () => {
      try {
        const r = await fetch("/dashboard/bookings/demo/pipeline-trace/" + bid);
        if (r.ok) {
          const data: {
            trace?: Array<{
              at: string;
              stage: string;
              message: string;
              context?: Record<string, unknown>;
            }>;
          } = await r.json();
          const trace = data.trace || [];
          setPipelineTraceData(trace);
          for (const t of trace) {
            const now = new Date();
            const timestamp = now.toLocaleTimeString("en-US", { hour12: false });
            setLogs((prev: LogEntryData[]) => [
              ...prev,
              {
                type: "trace",
                method: "TRACE",
                url: t.message,
                status: 0,
                duration: 0,
                summary: t.message,
                timestamp: t.at || timestamp,
                pipelineStage: t.stage,
                responseBody: t.context ? JSON.stringify(t.context, null, 2) : undefined,
              },
            ]);
          }
        }
      } catch {
        /* ignore */
      }
    })();
  }, []);

  const resetDemo = useCallback(() => {
    setSelectedPropertyId("");
    setActiveBookingId(null);
    setLogs([]);
    setMagicLinkData(null);
    setTokenStatusData(null);
    setEmailStatusData(null);
    setVerifyData(null);
    setPipelineTraceData(null);
  }, []);

  const handleCleanupAll = useCallback(async () => {
    setShowCleanupConfirm(true);
  }, []);

  const executeCleanupAll = useCallback(async () => {
    setShowCleanupConfirm(false);
    setCleaningUp(true);
    const { res } = await trackedFetch(
      "/dashboard/bookings/demo/cleanup",
      "POST",
      new URLSearchParams({ _wpnonce: cleanupNonce ?? "" }),
      "cleanup",
    );
    if (res) {
      const data: { success?: boolean; message?: string } = await res.json();
      if (data.success) {
        toast.success(data.message);
        setDemoDates(null);
        for (const k of Object.keys(localStorage)) {
          if (k.startsWith("crm_booking_dates_")) localStorage.removeItem(k);
        }
        setRefreshKey((prev) => prev + 1);
      } else {
        toast.error("Cleanup failed");
      }
    }
    setCleaningUp(false);
  }, [trackedFetch, cleanupNonce]);

  const handleClearOpcache = useCallback(async () => {
    setClearingOpcache(true);
    try {
      const res = await fetch("/api/booking/clear-opcache", { method: "POST" });
      const data = await res.json();
      if (data.success) {
        toast.success(`OPcache cleared (${data.method}) — reloading page...`);
        setTimeout(() => location.reload(), 1000);
      } else {
        toast.error("Failed to clear OPcache");
      }
    } catch {
      toast.error("OPcache endpoint not available");
    }
    setClearingOpcache(false);
  }, []);

  const bookableCount = properties.filter((p) => p.bookable).length;

  return (
    <AuthenticatedLayout header="Booking Demo">
      <div className="container mx-auto py-6">
        <Breadcrumb>
          <BreadcrumbList>
            <BreadcrumbItem>
              <BreadcrumbLink href="/dashboard">Dashboard</BreadcrumbLink>
            </BreadcrumbItem>
            <BreadcrumbSeparator />
            <BreadcrumbItem>
              <BreadcrumbLink href="/dashboard/manage-bookings">Bookings</BreadcrumbLink>
            </BreadcrumbItem>
            <BreadcrumbSeparator />
            <BreadcrumbItem>
              <BreadcrumbPage>Booking Demo</BreadcrumbPage>
            </BreadcrumbItem>
          </BreadcrumbList>
        </Breadcrumb>

        <div className="flex items-start justify-between gap-4 mt-4 mb-6">
          <div className="flex items-start gap-3 min-w-0">
            <Beaker className="h-6 w-6 text-primary shrink-0 mt-0.5" />
            <div className="min-w-0">
              <h1 className="text-2xl font-bold text-foreground">Booking System Demo</h1>
              <p className="text-sm text-muted-foreground">
                Test the real Booking Widget — the same component used on property pages.
                {bookableCount === 0 && (
                  <span className="text-amber-500 dark:text-amber-400 ml-2">
                    No bookable properties found. Enable booking on a property first.
                  </span>
                )}
              </p>
            </div>
          </div>
          <div className="flex items-center gap-2 shrink-0">
            <Button variant="outline" size="sm" onClick={resetDemo}>
              <RotateCcw className="h-3 w-3 mr-1" /> Reset
            </Button>
            <Button
              variant="outline"
              size="sm"
              onClick={handleClearOpcache}
              disabled={clearingOpcache}
            >
              <RefreshCw className={`h-3 w-3 mr-1 ${clearingOpcache ? "animate-spin" : ""}`} />
              {clearingOpcache ? "Clearing..." : "Clear OPcache"}
            </Button>
            <Button
              variant="destructive"
              size="sm"
              onClick={handleCleanupAll}
              disabled={cleaningUp}
              className="hidden md:inline-flex"
            >
              <Trash2 className="h-3 w-3 mr-1" />
              {cleaningUp ? "Cleaning..." : "Clear All Bookings"}
            </Button>
          </div>
        </div>

        <div className="space-y-6">
          <Card>
            <CardHeader className="pb-3">
              <CardTitle className="flex items-center gap-2 text-base">
                <Building2 className="h-4 w-4" /> 1. Select Property
              </CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
              <Select
                value={selectedPropertyId}
                onValueChange={(v) => {
                  setSelectedPropertyId(v);
                  setActiveBookingId(null);
                  setDemoDates(null);
                  setMagicLinkData(null);
                  setTokenStatusData(null);
                  setEmailStatusData(null);
                  setVerifyData(null);
                  setPipelineTraceData(null);
                }}
              >
                <SelectTrigger className="w-full truncate">
                  <SelectValue placeholder="Choose a bookable property..." />
                </SelectTrigger>
                <SelectContent>
                  {properties.map((p) => (
                    <SelectItem key={p.id} value={String(p.id)} disabled={!p.bookable}>
                      <div className="flex items-center gap-2 min-w-0">
                        <span className="truncate">{p.title}</span>
                        {p.bookable ? (
                          <Badge
                            variant="outline"
                            className="text-[10px] text-green-600 dark:text-green-400"
                          >
                            bookable
                          </Badge>
                        ) : (
                          <Badge variant="outline" className="text-[10px] text-muted-foreground">
                            no booking
                          </Badge>
                        )}
                      </div>
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>

              {selectedProperty && (
                <div className="space-y-3">
                  <div className="flex gap-4">
                    {selectedProperty.thumbnail_url ? (
                      <img
                        src={selectedProperty.thumbnail_url}
                        alt={selectedProperty.title}
                        loading="lazy"
                        className="w-20 h-20 rounded-lg object-cover border shrink-0"
                      />
                    ) : (
                      <div className="w-20 h-20 rounded-lg bg-muted flex items-center justify-center shrink-0">
                        <Building2 className="h-8 w-8 text-muted-foreground/50" />
                      </div>
                    )}
                    <div className="flex-1 min-w-0">
                      <p className="font-semibold truncate">{selectedProperty.title}</p>
                      {(() => {
                        const propPrice = Number(selectedProperty.price);
                        const nightlyRate = selectedProperty.booking?.settings?.nightly_rate;
                        if (!propPrice && !nightlyRate) return null;
                        return (
                          <p className="text-sm text-muted-foreground flex items-center gap-1">
                            <DollarSign className="h-3 w-3" />
                            {propPrice > 0
                              ? `${propPrice.toLocaleString()}${selectedProperty.rental_period ? `/${selectedProperty.rental_period}` : ""}`
                              : `${Number(nightlyRate).toFixed(2)}/night (booking)`}
                          </p>
                        );
                      })()}
                      <div className="flex flex-wrap gap-1 mt-1">
                        {selectedProperty.city && (
                          <span className="text-xs text-muted-foreground flex items-center gap-1">
                            <MapPin className="h-3 w-3" /> {selectedProperty.city}
                          </span>
                        )}
                      </div>
                    </div>
                  </div>
                  {selectedProperty.booking?.settings && (
                    <BookingConfigPanel settings={selectedProperty.booking.settings} />
                  )}
                </div>
              )}
            </CardContent>
          </Card>

          {selectedProperty?.booking?.settings && (
            <BookingDiagnosticCard
              settings={selectedProperty.booking.settings}
              dates={demoDates}
              property={selectedProperty}
              logs={logs}
            />
          )}

          {selectedPropertyId && selectedProperty?.bookable && (
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
              <Card className="border-primary/20">
                <CardHeader className="pb-3">
                  <CardTitle className="flex items-center gap-2 text-base">
                    <Calendar className="h-4 w-4" /> Availability Calendar
                  </CardTitle>
                  <CardDescription>Select your dates to check availability</CardDescription>
                </CardHeader>
                <CardContent>
                  <BookingWidget
                    key={`${selectedPropertyId}-cal-${refreshKey}`}
                    propertyId={Number(selectedPropertyId)}
                    mode="calendar"
                    onLog={handleWidgetLog}
                    onDatesChange={setDemoDates}
                    minNights={Number(selectedProperty?.booking?.settings?.min_nights) || 1}
                    weekStartsOn={weekStartsOn}
                  />
                </CardContent>
              </Card>
              <Card className="border-primary/20">
                <CardHeader className="pb-3">
                  <CardTitle className="flex items-center gap-2 text-base">
                    <CalendarCheck className="h-4 w-4" /> Book Now
                  </CardTitle>
                  <CardDescription>
                    Pick dates, review pricing, and complete your booking
                  </CardDescription>
                </CardHeader>
                <CardContent>
                  <BookingForm
                    key={`${selectedPropertyId}-form-${refreshKey}`}
                    propertyId={Number(selectedPropertyId)}
                    initialDates={demoDates ?? undefined}
                    buttonText="Book Now"
                    baseGuests={Number(selectedProperty?.booking?.settings?.base_guests) || 1}
                    minNights={Number(selectedProperty?.booking?.settings?.min_nights) || 1}
                    onLog={handleWidgetLog}
                    onBookingCreated={handleBookingCreated}
                  />
                </CardContent>
              </Card>
            </div>
          )}

          {!selectedPropertyId && (
            <Card>
              <CardContent className="py-12 text-center">
                <Building2 className="h-12 w-12 mx-auto mb-3 opacity-30" />
                <p className="text-muted-foreground">
                  Select a bookable property to test the booking widget
                </p>
              </CardContent>
            </Card>
          )}

          <Card>
            <CardHeader className="pb-3">
              <div className="flex items-center justify-between">
                <CardTitle className="flex items-center gap-2 text-base">
                  <Activity className="h-4 w-4" /> API Log
                </CardTitle>
                {logs.length > 0 && (
                  <div className="flex items-center gap-2">
                    {(() => {
                      const total = logs.reduce((s, l) => s + l.duration, 0);
                      const avg = Math.round(total / logs.length);
                      const slowest = Math.max(...logs.map((l) => l.duration));
                      return (
                        <span className="text-[10px] text-muted-foreground font-mono tabular-nums">
                          {logs.length} call{logs.length !== 1 ? "s" : ""} · avg {avg}ms · max{" "}
                          {slowest}ms
                        </span>
                      );
                    })()}
                    <button
                      type="button"
                      onClick={() => {
                        const sanitized = logs.map((l) => ({
                          ...l,
                          requestBody: l.requestBody?.replaceAll(/_wpnonce=\*{3}/g, "_wpnonce=***"),
                        }));
                        navigator.clipboard.writeText(JSON.stringify(sanitized, null, 2));
                        toast.success("Log copied to clipboard");
                      }}
                      className="text-[10px] text-muted-foreground hover:text-foreground underline"
                    >
                      Copy Log
                    </button>
                    <button
                      type="button"
                      onClick={() => setLogs([])}
                      className="text-[10px] text-muted-foreground hover:text-foreground underline"
                    >
                      Clear
                    </button>
                  </div>
                )}
              </div>
              <CardDescription>
                All API calls from the widget and demo — click any entry to expand full details
              </CardDescription>

              {logs.length > 1 && (
                <div className="mt-3 space-y-0.5">
                  {(() => {
                    const maxDur = Math.max(...logs.map((l) => l.duration), 1);
                    return logs.map((entry, i) => (
                      <div key={i} className="flex items-center gap-2 text-[10px] font-mono">
                        <span className="w-12 text-right shrink-0 tabular-nums text-muted-foreground">
                          {entry.duration}ms
                        </span>
                        <div className="flex-1 h-3 bg-muted rounded-sm overflow-hidden relative">
                          <div
                            className={`h-full rounded-sm transition-all ${
                              entry.status === 0
                                ? "bg-red-500"
                                : entry.duration > 1000
                                  ? "bg-orange-500"
                                  : entry.duration > 500
                                    ? "bg-amber-400"
                                    : "bg-green-500"
                            }`}
                            style={{ width: `${(entry.duration / maxDur) * 100}%` }}
                          />
                        </div>
                        <span className="w-16 truncate text-right shrink-0 text-muted-foreground">
                          {entry.url.split("/").pop()}
                        </span>
                      </div>
                    ));
                  })()}
                </div>
              )}
            </CardHeader>
            <CardContent className="max-h-[500px] overflow-y-auto space-y-2">
              {logs.length === 0 && (
                <p className="text-xs text-muted-foreground text-center py-8">
                  No API calls yet. Interact with the widget to see logs.
                </p>
              )}
              {logs.map((entry, i) => (
                <LogEntryComponent key={i} entry={entry} />
              ))}
              <div ref={logEndRef} />
            </CardContent>
          </Card>
        </div>

        {activeBookingId && (
          <div className="space-y-6 mt-6">
            <Separator />
            <h2 className="text-lg font-semibold flex items-center gap-2">
              <Beaker className="h-5 w-5 text-primary" />
              Demo Debug Results — Booking #{activeBookingId}
            </h2>

            <Tabs defaultValue="debug" className="w-full">
              <TabsList className="grid w-full grid-cols-3">
                <TabsTrigger value="debug">Debug & Trace</TabsTrigger>
                <TabsTrigger value="guest">Guest Experience</TabsTrigger>
                <TabsTrigger value="actions">Actions & Transitions</TabsTrigger>
              </TabsList>

              <TabsContent value="debug" className="space-y-4 mt-6">
                <Card>
                  <CardHeader className="pb-3">
                    <CardTitle className="text-sm flex items-center gap-2">
                      <ExternalLink className="h-4 w-4" /> Magic Link
                    </CardTitle>
                    <CardDescription>
                      Guest access link — sent via email in the real flow
                    </CardDescription>
                  </CardHeader>
                  <CardContent>
                    {magicLinkLoading ? (
                      <div className="flex items-center gap-2 text-sm text-muted-foreground">
                        <Loader2 className="h-4 w-4 animate-spin" /> Generating...
                      </div>
                    ) : magicLinkData?.magic_link ? (
                      <div className="space-y-3">
                        <code className="block text-xs bg-muted p-2 rounded border break-all">
                          {magicLinkData.magic_link}
                        </code>
                        <div className="flex flex-wrap gap-2">
                          <Button
                            size="sm"
                            onClick={() => open(magicLinkData.magic_link!, "_blank")}
                          >
                            <ExternalLink className="h-3 w-3 mr-1" /> Open Guest View
                          </Button>
                          <Button
                            variant="outline"
                            size="sm"
                            onClick={() => {
                              navigator.clipboard.writeText(magicLinkData.magic_link!);
                              toast.success("Copied");
                            }}
                          >
                            Copy
                          </Button>
                        </div>
                      </div>
                    ) : (
                      <p className="text-sm text-muted-foreground">No magic link available</p>
                    )}
                  </CardContent>
                </Card>

                <Card>
                  <CardHeader className="pb-3">
                    <div className="flex items-center justify-between">
                      <CardTitle className="text-sm flex items-center gap-2">
                        <Activity className="h-4 w-4" /> Pipeline Trace
                      </CardTitle>
                      <Button
                        variant="outline"
                        size="sm"
                        onClick={async () => {
                          setTraceLoading(true);
                          try {
                            const r = await fetch(
                              "/dashboard/bookings/demo/pipeline-trace/" + activeBookingId,
                            );
                            if (r.ok) {
                              const data = await r.json();
                              setPipelineTraceData(data.trace || []);
                            }
                          } catch {
                            /* ignore */
                          }
                          setTraceLoading(false);
                        }}
                        disabled={traceLoading}
                      >
                        <RefreshCw className="h-3 w-3 mr-1" /> Refresh
                      </Button>
                    </div>
                    <CardDescription>
                      Persistent trace of pipeline stages (transitions, emails, notifications)
                    </CardDescription>
                  </CardHeader>
                  <CardContent>
                    {traceLoading ? (
                      <div className="flex items-center gap-2 text-sm text-muted-foreground">
                        <Loader2 className="h-4 w-4 animate-spin" /> Loading...
                      </div>
                    ) : pipelineTraceData && pipelineTraceData.length > 0 ? (
                      <div className="space-y-1 max-h-[400px] overflow-y-auto">
                        {pipelineTraceData.map((entry, i) => (
                          <div
                            key={i}
                            className="flex items-start gap-3 p-2 rounded text-sm bg-muted/30 border-l-4 border-blue-400"
                          >
                            <div className="flex-1 min-w-0">
                              <div className="flex items-center gap-2">
                                <span className="text-[10px] font-mono text-muted-foreground">
                                  {entry.at}
                                </span>
                                <Badge variant="secondary" className="text-[10px] font-mono">
                                  {entry.stage}
                                </Badge>
                              </div>
                              <p className="text-xs mt-0.5">{entry.message}</p>
                              {entry.context && Object.keys(entry.context).length > 0 && (
                                <details className="mt-1">
                                  <summary className="text-[10px] text-muted-foreground cursor-pointer hover:text-foreground">
                                    Context ({Object.keys(entry.context).length} keys)
                                  </summary>
                                  <pre className="text-[10px] bg-muted/50 p-2 rounded mt-1 overflow-x-auto">
                                    {JSON.stringify(entry.context, null, 2)}
                                  </pre>
                                </details>
                              )}
                            </div>
                          </div>
                        ))}
                      </div>
                    ) : (
                      <p className="text-sm text-muted-foreground">No trace entries yet</p>
                    )}
                  </CardContent>
                </Card>

                <Card>
                  <CardHeader className="pb-3">
                    <CardTitle className="text-sm flex items-center gap-2">
                      <Mail className="h-4 w-4" /> Email Delivery Status
                    </CardTitle>
                    <CardDescription>
                      Transactional emails for this booking lifecycle
                    </CardDescription>
                  </CardHeader>
                  <CardContent>
                    {emailStatusData?.emails ? (
                      <div className="space-y-2">
                        {emailStatusData.emails.map((e) => (
                          <div
                            key={e.key}
                            className={`flex items-center justify-between p-3 rounded-lg border-l-4 ${e.sent ? "border-green-500 bg-green-500/5" : "border-muted-foreground/20 bg-muted/20"}`}
                          >
                            <div className="flex items-center gap-2">
                              {e.sent ? (
                                <CheckCircle2 className="h-4 w-4 text-green-500" />
                              ) : (
                                <div className="h-4 w-4 rounded-full border-2 border-muted-foreground/30" />
                              )}
                              <div>
                                <span
                                  className={`text-sm font-medium ${e.sent ? "" : "text-muted-foreground"}`}
                                >
                                  {e.label}
                                </span>
                                {e.sent_at && (
                                  <p className="text-[10px] text-muted-foreground font-mono">
                                    Sent: {e.sent_at}
                                  </p>
                                )}
                              </div>
                            </div>
                            <Badge variant={e.sent ? "default" : "outline"} className="text-[10px]">
                              {e.sent ? "Sent" : "Pending"}
                            </Badge>
                          </div>
                        ))}
                      </div>
                    ) : (
                      <div className="flex items-center gap-2 text-sm text-muted-foreground">
                        <Loader2 className="h-4 w-4 animate-spin" /> Loading...
                      </div>
                    )}
                  </CardContent>
                </Card>

                <Card>
                  <CardHeader className="pb-3">
                    <div className="flex items-center justify-between">
                      <CardTitle className="text-sm flex items-center gap-2">
                        <CheckCircle2 className="h-4 w-4" /> Integration Verification
                      </CardTitle>
                      {verifyData && (
                        <Badge
                          variant={verifyData.all_passed ? "default" : "destructive"}
                          className="text-xs"
                        >
                          {verifyData.summary}
                        </Badge>
                      )}
                    </div>
                    <CardDescription>
                      Automated checks verifying booking module integration
                    </CardDescription>
                  </CardHeader>
                  <CardContent>
                    {verifyData?.checks ? (
                      <div className="space-y-1">
                        {verifyData.checks.map((c) => (
                          <div
                            key={c.key}
                            className={`flex items-start gap-3 p-2 rounded text-sm ${c.passed ? "bg-green-500/5" : "bg-red-500/5"}`}
                          >
                            {c.passed ? (
                              <CheckCircle2 className="h-4 w-4 text-green-500 shrink-0 mt-0.5" />
                            ) : (
                              <XCircle className="h-4 w-4 text-red-500 shrink-0 mt-0.5" />
                            )}
                            <div className="flex-1 min-w-0">
                              <span
                                className={`font-medium ${c.passed ? "text-green-700 dark:text-green-300" : "text-red-700 dark:text-red-300"}`}
                              >
                                {c.label}
                              </span>
                              {c.detail && (
                                <p className="text-[11px] text-muted-foreground font-mono truncate">
                                  {c.detail}
                                </p>
                              )}
                            </div>
                          </div>
                        ))}
                      </div>
                    ) : (
                      <div className="flex items-center gap-2 text-sm text-muted-foreground">
                        <Loader2 className="h-4 w-4 animate-spin" /> Running...
                      </div>
                    )}
                  </CardContent>
                </Card>
              </TabsContent>

              <TabsContent value="guest" className="space-y-4 mt-6">
                <Card>
                  <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                      <Users className="h-4 w-4" /> Guest / Client Perspective
                    </CardTitle>
                    <CardDescription>
                      The widget above shows exactly what a guest sees. The booking result card
                      (shown by the widget on success) is the real UI.
                    </CardDescription>
                  </CardHeader>
                  <CardContent>
                    <ul className="text-sm text-muted-foreground space-y-2 list-disc list-inside">
                      <li>
                        Magic link above opens the <strong>GuestView</strong> page
                      </li>
                      <li>Guest can view booking details, cancel, or request changes</li>
                      <li>No login required — magic link provides secure access</li>
                    </ul>
                  </CardContent>
                </Card>
              </TabsContent>

              <TabsContent value="actions" className="space-y-4 mt-6">
                <Card>
                  <CardHeader className="pb-3">
                    <CardTitle className="text-sm flex items-center gap-2">
                      <Key className="h-4 w-4" /> Token Table
                    </CardTitle>
                  </CardHeader>
                  <CardContent>
                    {tokenStatusData?.tokens && tokenStatusData.tokens.length > 0 ? (
                      <div className="space-y-2">
                        {tokenStatusData.tokens.map((t, i) => (
                          <div
                            key={i}
                            className="bg-muted/30 rounded p-3 font-mono text-xs space-y-1 border"
                          >
                            <div className="flex justify-between">
                              <span className="text-muted-foreground">Token #{String(t.id)}</span>
                              <Badge
                                variant={t.is_expired ? "destructive" : "default"}
                                className="text-[10px]"
                              >
                                {t.is_expired ? "Expired" : "Active"}
                              </Badge>
                            </div>
                            <div className="flex justify-between">
                              <span className="text-muted-foreground">Hash:</span>
                              <span>{(t.token_hash_short as string) || "\u{2014}"}</span>
                            </div>
                            <div className="flex justify-between">
                              <span className="text-muted-foreground">Email:</span>
                              <span>{(t.guest_email as string) || "\u{2014}"}</span>
                            </div>
                            <div className="flex justify-between">
                              <span className="text-muted-foreground">Expires:</span>
                              <span>{(t.expires_at as string) || "\u{2014}"}</span>
                            </div>
                          </div>
                        ))}
                      </div>
                    ) : (
                      <p className="text-sm text-muted-foreground">No tokens</p>
                    )}
                  </CardContent>
                </Card>

                <Card>
                  <CardHeader className="pb-3">
                    <CardTitle className="text-sm flex items-center gap-2">
                      <Activity className="h-4 w-4" /> Trigger Status Transition
                    </CardTitle>
                    <CardDescription>
                      Simulate a host status change and check the pipeline trace
                    </CardDescription>
                  </CardHeader>
                  <CardContent>
                    <div className="flex flex-wrap gap-2">
                      <Button
                        variant="outline"
                        size="sm"
                        onClick={async () => {
                          setTransitioning(true);
                          const body = new URLSearchParams({
                            status: BOOKING_STATUS.Confirmed,
                            _wpnonce: "",
                          });
                          const start = performance.now();
                          try {
                            const r = await fetch("/dashboard/manage-bookings/" + activeBookingId, {
                              method: "POST",
                              headers: { "Content-Type": "application/x-www-form-urlencoded" },
                              body,
                            });
                            addLog(
                              "POST",
                              "/dashboard/manage-bookings/" + activeBookingId,
                              r.status,
                              Math.round(performance.now() - start),
                              r.ok ? "Transition to confirmed" : "Transition failed",
                            );
                            if (r.ok) {
                              toast.success("Transitioned to confirmed");
                              setTimeout(async () => {
                                const tr = await fetch(
                                  "/dashboard/bookings/demo/pipeline-trace/" + activeBookingId,
                                );
                                if (tr.ok) {
                                  const data = await tr.json();
                                  setPipelineTraceData(data.trace || []);
                                }
                                const er = await fetch(
                                  "/dashboard/bookings/demo/email-status/" + activeBookingId,
                                );
                                if (er.ok) setEmailStatusData(await er.json());
                              }, 500);
                            }
                          } catch {
                            /* ignore */
                          }
                          setTransitioning(false);
                        }}
                        disabled={transitioning}
                      >
                        {transitioning ? (
                          <Loader2 className="h-3 w-3 mr-1 animate-spin" />
                        ) : (
                          <Activity className="h-3 w-3 mr-1" />
                        )}
                        {transitioning ? "Transitioning..." : "Trigger \u{2192} Confirmed"}
                      </Button>
                    </div>
                  </CardContent>
                </Card>
              </TabsContent>
            </Tabs>
          </div>
        )}

        <AlertDialog open={showCleanupConfirm} onOpenChange={setShowCleanupConfirm}>
          <AlertDialogContent>
            <AlertDialogHeader>
              <AlertDialogTitle>Clear All Demo Bookings?</AlertDialogTitle>
              <AlertDialogDescription>
                This will permanently delete all bookings created via the demo. This action cannot
                be undone.
              </AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
              <AlertDialogCancel>Cancel</AlertDialogCancel>
              <AlertDialogAction
                onClick={executeCleanupAll}
                className="bg-destructive text-destructive-foreground"
              >
                Delete All
              </AlertDialogAction>
            </AlertDialogFooter>
          </AlertDialogContent>
        </AlertDialog>
      </div>
    </AuthenticatedLayout>
  );
}
