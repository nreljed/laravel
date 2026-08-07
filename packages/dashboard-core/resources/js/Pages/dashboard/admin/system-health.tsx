import { Head, router } from "@inertiajs/react";
import React from "react";
import {
  Activity,
  CheckCircle2,
  AlertTriangle,
  XCircle,
  RefreshCw,
  ClipboardList,
  Check,
  ChevronDown,
  ChevronRight,
  Cpu,
  Database,
  Globe,
  Server,
  Settings,
  Plug,
  Timer,
  FileText,
  Users,
  Bug,
  Layers,
  GitCompareArrows,
  FolderOpen,
} from "lucide-react";
import AdminLayout from "@/Layouts/AdminLayout";
import { jsonHeaders } from "@/lib/gallery-utils";
import { Card, CardContent, CardHeader, CardTitle } from "@/Components/ui/card";
import { Badge } from "@/Components/ui/badge";
import { Button } from "@/Components/ui/button";
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from "@/Components/ui/collapsible";
import { Separator } from "@/Components/ui/separator";

/*
─── Type Definitions ───
*/

interface PhpInfo {
  version: string;
  version_ok: boolean;
  sapi: string;
  memory_limit: string;
  memory_limit_mb: number;
  memory_limit_ok: boolean;
  max_execution_time: string;
  max_input_time: string;
  max_input_vars: string;
  upload_max_filesize: string;
  post_max_size: string;
  display_errors: string;
  error_reporting: number;
  ini_path: string;
  scanned_ini_dirs: string;
  loaded_extensions_count: number;
  precision: string;
  default_charset: string;
}

interface LaravelInfo {
  version: string;
  environment: string;
  debug: boolean;
  url: string;
  timezone: string;
  locale: string;
  name: string;
}

interface OpcacheConfig {
  [key: string]: string;
}
interface OpcacheStatus {
  used_memory: string;
  free_memory: string;
  wasted_memory: string;
  cached_files: number;
  max_cached_files: number;
  hits: number;
  misses: number;
  hit_rate: number;
  hit_rate_ok: boolean;
  memory_usage_percent: number;
}
interface OpcacheInfo {
  extension_loaded: boolean;
  enabled: boolean;
  config: OpcacheConfig;
  status: OpcacheStatus | null;
}

interface Extension {
  name: string;
  loaded: boolean;
  required: boolean;
}

interface DatabaseInfo {
  connection: string;
  driver: string;
  name: string;
  version: string;
  version_ok: boolean;
  size: string;
  table_count: number;
  charset: string;
  collation: string;
}

interface ServerInfo {
  software: string;
  is_https: boolean;
  document_root: string;
  server_ip: string;
  server_protocol: string;
  http_host: string;
  server_name: string;
  remote_addr: string;
}

interface OsInfo {
  os: string;
  os_family: string;
  arch: string;
  hostname: string;
  kernel: string;
  full: string;
}

interface EnvironmentInfo {
  type: string;
  detected: string;
  ini_path: string;
  is_local: boolean;
  is_production: boolean;
}

interface Recommendation {
  severity: "critical" | "high" | "info";
  title: string;
  message: string;
  fix: string;
}

interface MailerConfig {
  transport?: string;
  host?: string;
  port?: string;
  username?: string;
  encryption?: string;
}
interface MailInfo {
  driver: string;
  from_address: string;
  from_name: string;
  mailer: MailerConfig;
}
interface SessionInfo {
  driver: string;
  lifetime: number;
  secure: unknown;
  same_site: string;
}
interface CacheInfo {
  driver: string;
  ttl: number;
  stores: string[];
}
interface QueueInfo {
  driver: string;
  connection: string;
}
interface FilesystemInfo {
  default: string;
  disks: string[];
}
interface ModuleItem {
  name: string;
  slug: string;
  enabled: boolean;
  version: string | null;
}
interface CronItem {
  name: string;
  description: string;
  class: string;
}
interface UsersInfo {
  total: number;
  verified: number;
  unverified: number;
  admin: number;
}
interface DeployedVersion {
  exists: boolean;
  version?: string;
  file_path?: string;
}
interface LatestVersion {
  found: boolean;
  version?: string;
  url?: string;
  published_at?: string;
  name?: string;
  body?: string;
  error?: string;
  skipped?: boolean;
  message?: string;
}
interface BuildInfo {
  exists: boolean;
  modified_at?: string;
  modified_timestamp?: number;
  age?: string;
  age_hours?: number;
}
interface CorePackage {
  name: string;
  sha: string | null;
  short: string | null;
  url: string | null;
  version: string | null;
}

interface LogFile {
  name: string;
  size: string;
  modified: string;
}
interface LogInfo {
  channel: string;
  level: string;
  driver: string;
  path: string | null;
  size: string | null;
  recent_errors: number;
  files: LogFile[];
}

interface StorageDir {
  name: string;
  exists: boolean;
  writable: boolean;
  file_count: number;
  size: string;
}
interface DiskInfo {
  total: string;
  free: string;
  used: string;
  percent: number;
  writable: boolean;
}
interface StorageInfo {
  dirs: StorageDir[];
  total_size: string;
  disk: DiskInfo | null;
}

interface LaravelCacheStatus {
  config_cached: boolean;
  routes_cached: boolean;
  events_cached: boolean;
  config_cache_path: string;
  routes_cache_path: string;
  is_down_for_maintenance: boolean;
}

interface SystemHealthProps {
  php: PhpInfo;
  laravel: LaravelInfo;
  opcache: OpcacheInfo;
  extensions: Extension[];
  all_loaded_extensions: string[];
  database: DatabaseInfo;
  server: ServerInfo;
  os: OsInfo;
  environment: EnvironmentInfo;
  recommendations: Recommendation[];
  cache: CacheInfo;
  queue: QueueInfo;
  mail: MailInfo;
  session: SessionInfo;
  filesystem: FilesystemInfo;
  modules: ModuleItem[];
  schedule: CronItem[];
  logs: LogInfo;
  users: UsersInfo;
  build: BuildInfo;
  storage: StorageInfo;
  deployed_version: DeployedVersion;
  latest_version: LatestVersion;
  core_package: CorePackage;
  laravel_cache: LaravelCacheStatus;
  debug_access_url: string | null;
}

/*
─── Helper Components ───
*/

function StatusDot({ ok }: { ok: boolean }) {
  return (
    <span
      className={`inline-block h-2.5 w-2.5 rounded-full shrink-0 ${
        ok ? "bg-emerald-500 dark:bg-emerald-400" : "bg-red-500 dark:bg-red-400"
      }`}
    />
  );
}

function SeverityIcon({ severity }: { severity: string }) {
  switch (severity) {
    case "critical": {
      return <XCircle className="h-4 w-4 shrink-0 text-red-500 dark:text-red-400" />;
    }
    case "high": {
      return <AlertTriangle className="h-4 w-4 shrink-0 text-amber-500 dark:text-amber-400" />;
    }
    default: {
      return <CheckCircle2 className="h-4 w-4 shrink-0 text-emerald-500 dark:text-emerald-400" />;
    }
  }
}

function InfoRow({
  label,
  value,
  ok,
}: {
  label: string;
  value: string | number | boolean | null | undefined;
  ok?: boolean;
}) {
  return (
    <div className="flex items-center justify-between gap-2 py-1.5 text-sm">
      <span className="text-muted-foreground shrink-0">{label}</span>
      <span className="flex items-center gap-2 font-mono text-xs text-right max-w-[60%] truncate">
        {ok !== undefined && <StatusDot ok={ok} />}
        {String(value ?? "—")}
      </span>
    </div>
  );
}

function MiniCard({
  value,
  label,
  color,
}: {
  value: string | number;
  label: string;
  color?: string;
}) {
  return (
    <Card className="border-border/50">
      <CardContent className="p-3">
        <p className="text-lg font-bold" style={color ? { color } : undefined}>
          {value}
        </p>
        <p className="text-[10px] text-muted-foreground">{label}</p>
      </CardContent>
    </Card>
  );
}

function CollapsibleSection({
  defaultOpen = false,
  title,
  icon,
  status,
  children,
}: {
  defaultOpen?: boolean;
  title: string;
  icon: React.ReactNode;
  status: "good" | "warning" | "critical" | "info";
  children: React.ReactNode;
}) {
  const [open, setOpen] = React.useState(defaultOpen);

  const borderColor =
    status === "good"
      ? "border-emerald-500/30 dark:border-emerald-400/30"
      : status === "warning"
        ? "border-amber-500/30 dark:border-amber-400/30"
        : status === "critical"
          ? "border-red-500/30 dark:border-red-400/30"
          : "border-border/50";

  const badgeVariant =
    status === "good"
      ? "success"
      : status === "warning"
        ? "warning"
        : status === "critical"
          ? "error"
          : "info";

  const badgeLabel =
    status === "good"
      ? "Good"
      : status === "warning"
        ? "Warning"
        : status === "critical"
          ? "Critical"
          : "Info";

  return (
    <Collapsible
      open={open}
      onOpenChange={setOpen}
      className={`rounded-lg border ${borderColor} overflow-hidden`}
    >
      <CollapsibleTrigger className="flex w-full items-center gap-3 px-4 py-3 text-sm font-medium text-foreground hover:bg-accent/50 transition-colors cursor-pointer">
        {open ? (
          <ChevronDown className="h-4 w-4 text-muted-foreground shrink-0" />
        ) : (
          <ChevronRight className="h-4 w-4 text-muted-foreground shrink-0" />
        )}
        {icon}
        <span className="flex-1 text-left">{title}</span>
        <Badge
          variant={badgeVariant as "success" | "warning" | "error" | "info"}
          className="text-[10px] px-2 py-0.5"
        >
          {badgeLabel}
        </Badge>
      </CollapsibleTrigger>
      <CollapsibleContent>
        <div className="px-4 pb-4 pt-1 border-t border-border/50">{children}</div>
      </CollapsibleContent>
    </Collapsible>
  );
}

/*
─── Main Page ───
*/

export default function SystemHealth(props: SystemHealthProps) {
  const [isRefreshing, setIsRefreshing] = React.useState(false);
  const [reportCopied, setReportCopied] = React.useState(false);

  const handleRefresh = async () => {
    setIsRefreshing(true);
    try {
      const response = await fetch(route("dashboard.admin.system-health.refresh"), {
        method: "POST",
        headers: jsonHeaders(),
        credentials: "same-origin",
      });
      if (response.ok) {
        router.reload({ preserveUrl: true });
      } else {
        location.reload();
      }
    } catch {
      location.reload();
    } finally {
      setIsRefreshing(false);
    }
  };

  const formatReportText = () => {
    const L = (label: string, val: string) => `  ${label}: ${val}`;
    const d = new Date();
    const lines = [
      "Cost Breaker 360 — System Health Report",
      `Generated: ${d.toISOString().slice(0, 19).replace("T", " ")}`,
      `Environment: ${props.laravel.environment} (${props.environment.detected})`,
      "",
    ];

    if (props.recommendations.length > 0) {
      lines.push("=== RECOMMENDATIONS ===");
      for (const r of props.recommendations) {
        lines.push(`  [${r.severity}] ${r.title}`, `  ${r.message}`);
        if (r.fix) lines.push(`  Fix: ${r.fix.replaceAll("\n", " | ")}`);
        lines.push("");
      }
    }

    lines.push(
      "=== APPLICATION ===",
      L("Laravel", props.laravel.version),
      L("Environment", props.laravel.environment),
      L("Debug", String(props.laravel.debug)),
      L("URL", props.laravel.url),
      "",
      "=== PHP ===",
      L("Version", props.php.version),
      L("SAPI", props.php.sapi),
      L("Memory Limit", props.php.memory_limit),
      L("Max Execution", `${props.php.max_execution_time}s`),
      L("Upload Max", props.php.upload_max_filesize),
      L("Post Max", props.php.post_max_size),
      L("ini Path", props.php.ini_path),
      "",
    );
    if (props.database.version)
      lines.push(
        "=== DATABASE ===",
        L("Driver", props.database.driver),
        L("Version", props.database.version),
        L("Size", props.database.size),
        L("Tables", String(props.database.table_count)),
        "",
      );
    lines.push(
      "=== SERVER ===",
      L("Software", props.server.software),
      L("OS", props.os.os),
      L("Hostname", props.os.hostname),
      L("HTTPS", String(props.server.is_https)),
      "",
    );
    if (props.deployed_version.exists || props.latest_version.found || props.core_package.sha)
      lines.push(
        "=== VERSION ===",
        ...(props.deployed_version.exists
          ? [L("Deployed", props.deployed_version.version ?? "?")]
          : []),
        L("Latest", props.latest_version.found ? (props.latest_version.version ?? "?") : "Unknown"),
        L(
          "Core Package",
          props.core_package.short
            ? `${props.core_package.name}@${props.core_package.short}`
            : `${props.core_package.name} (unrecorded)`,
        ),
        "",
      );
    lines.push(
      "=== USERS ===",
      L("Total", String(props.users.total)),
      L("Verified", String(props.users.verified)),
      L("Admins", String(props.users.admin)),
      "",
    );

    return lines.join("\n");
  };

  const handleCopyReport = async () => {
    const text = formatReportText();
    try {
      await navigator.clipboard.writeText(text);
    } catch {
      const ta = document.createElement("textarea");
      ta.value = text;
      document.body.appendChild(ta);
      ta.select();
      document.execCommand("copy");
      document.body.removeChild(ta);
    }
    setReportCopied(true);
    setTimeout(() => setReportCopied(false), 2500);
  };

  const criticalCount = props.recommendations.filter((r) => r.severity === "critical").length;
  const highCount = props.recommendations.filter((r) => r.severity === "high").length;
  const totalIssues = criticalCount + highCount;
  const hasIssues = totalIssues > 0;

  const showVersionCard =
    props.deployed_version.exists ||
    props.latest_version.found ||
    Boolean(props.core_package.sha) ||
    Boolean(props.core_package.version);

  const canCompareVersions = props.deployed_version.exists && props.latest_version.found;

  const isUpToDate =
    canCompareVersions && props.deployed_version.version === props.latest_version.version;

  const all_extensions_loaded = props.extensions.every((e) => !e.required || e.loaded);

  return (
    <AdminLayout title="System Health">
      <Head title="System Health" />

      <div className="mx-auto max-w-4xl space-y-6 px-4">
        {/* ── Header ── */}
        <div className="flex items-start justify-between gap-3 sm:items-center sm:flex-nowrap flex-wrap">
          <div className="flex items-center gap-3 min-w-0">
            <Activity className="mt-0.5 h-6 w-6 text-muted-foreground shrink-0" />
            <div className="min-w-0">
              <h2 className="text-lg font-semibold text-foreground">System Health</h2>
              <div className="flex items-center gap-2 flex-wrap">
                <p className="text-xs sm:text-sm text-muted-foreground">
                  Server configuration and diagnostics for {props.laravel.name}
                </p>
                <Badge
                  variant={props.environment.is_production ? "success" : "info"}
                  className="text-[10px] px-1.5 py-0 shrink-0"
                >
                  {props.environment.is_production ? "Production" : props.environment.detected}
                </Badge>
              </div>
            </div>
          </div>
          <div className="flex items-center gap-2 shrink-0">
            <Button variant="outline" size="sm" onClick={handleCopyReport}>
              {reportCopied ? (
                <>
                  <Check className="mr-1.5 h-3.5 w-3.5" /> Copied
                </>
              ) : (
                <>
                  <ClipboardList className="mr-1.5 h-3.5 w-3.5" /> Copy Report
                </>
              )}
            </Button>
            <Button variant="outline" size="sm" onClick={handleRefresh} disabled={isRefreshing}>
              <RefreshCw className={`mr-1.5 h-3.5 w-3.5 ${isRefreshing ? "animate-spin" : ""}`} />
              Refresh
            </Button>
          </div>
        </div>

        {/* ── Summary Cards ── */}
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <Card
            className={
              !hasIssues
                ? "border-emerald-500/30 dark:border-emerald-400/30"
                : criticalCount > 0
                  ? "border-red-500/30 dark:border-red-400/30"
                  : "border-amber-500/30 dark:border-amber-400/30"
            }
          >
            <CardContent className="flex items-center gap-3 p-4">
              {!hasIssues ? (
                <CheckCircle2 className="h-8 w-8 shrink-0 text-emerald-500 dark:text-emerald-400" />
              ) : criticalCount > 0 ? (
                <XCircle className="h-8 w-8 shrink-0 text-red-500 dark:text-red-400" />
              ) : (
                <AlertTriangle className="h-8 w-8 shrink-0 text-amber-500 dark:text-amber-400" />
              )}
              <div>
                <p className="text-2xl font-bold">{!hasIssues ? "Good" : totalIssues}</p>
                <p className="text-xs text-muted-foreground">
                  {!hasIssues
                    ? "All systems good"
                    : `${criticalCount} critical, ${highCount} warnings`}
                </p>
              </div>
            </CardContent>
          </Card>
          <Card>
            <CardContent className="flex items-center gap-3 p-4">
              <Globe className="h-8 w-8 shrink-0 text-muted-foreground" />
              <div>
                <p className="text-2xl font-bold">v{props.laravel.version}</p>
                <p className="text-xs text-muted-foreground">Laravel {props.laravel.environment}</p>
              </div>
            </CardContent>
          </Card>
          <Card>
            <CardContent className="flex items-center gap-3 p-4">
              <Cpu className="h-8 w-8 shrink-0 text-muted-foreground" />
              <div>
                <p className="text-2xl font-bold">{props.php.version}</p>
                <p className="text-xs text-muted-foreground">PHP {props.php.sapi}</p>
              </div>
            </CardContent>
          </Card>
          <Card>
            <CardContent className="flex items-center gap-3 p-4">
              <Server className="h-8 w-8 shrink-0 text-muted-foreground" />
              <div>
                <p className="text-2xl font-bold">{props.server.software.split("/", 1)[0]}</p>
                <p className="text-xs text-muted-foreground">
                  {props.os.os_family} / {props.os.arch}
                </p>
              </div>
            </CardContent>
          </Card>
        </div>

        {/* ── Version Comparison ── */}
        {showVersionCard && (
          <Card
            className={
              canCompareVersions
                ? isUpToDate
                  ? "border-emerald-500/30 dark:border-emerald-400/30"
                  : "border-amber-500/30 dark:border-amber-400/30"
                : undefined
            }
          >
            <CardContent className="flex items-center justify-between gap-4 p-4">
              <div className="flex items-center gap-3 min-w-0">
                <GitCompareArrows
                  className={`h-6 w-6 shrink-0 ${canCompareVersions ? (isUpToDate ? "text-emerald-500 dark:text-emerald-400" : "text-amber-500 dark:text-amber-400") : "text-muted-foreground"}`}
                />
                <div className="min-w-0">
                  <p className="text-sm font-medium">
                    Deployed:{" "}
                    {props.deployed_version.exists ? (
                      <span className="font-mono">{props.deployed_version.version}</span>
                    ) : (
                      <span className="text-muted-foreground">local (not recorded)</span>
                    )}
                  </p>
                  {props.latest_version.found ? (
                    <p className="text-xs text-muted-foreground">
                      Latest release:{" "}
                      <span className="font-mono">{props.latest_version.version}</span>
                      {props.latest_version.published_at && (
                        <>
                          {" "}
                          &middot;{" "}
                          {new Date(props.latest_version.published_at).toLocaleDateString()}
                        </>
                      )}
                    </p>
                  ) : (
                    <p className="text-xs text-muted-foreground">
                      Latest release:{" "}
                      <span className="text-muted-foreground">
                        {props.latest_version.message ?? "Unreachable"}
                      </span>
                    </p>
                  )}
                  <p className="text-xs text-muted-foreground">
                    Dashboard Core:{" "}
                    {props.core_package.url && props.core_package.short ? (
                      <a
                        href={props.core_package.url}
                        target="_blank"
                        rel="noreferrer"
                        className="font-mono text-primary hover:underline"
                      >
                        {props.core_package.short}
                      </a>
                    ) : (
                      <span className="font-mono">unrecorded</span>
                    )}
                    {props.core_package.version && <> &middot; v{props.core_package.version}</>}
                  </p>
                </div>
              </div>
              {canCompareVersions ? (
                <Badge variant={isUpToDate ? "success" : "warning"} className="shrink-0">
                  {isUpToDate ? "Up to date" : "Update available"}
                </Badge>
              ) : (
                <Badge variant="info" className="shrink-0">
                  Local build
                </Badge>
              )}
            </CardContent>
          </Card>
        )}

        {/* ── Recommendations ── */}
        {hasIssues && (
          <Card
            className={
              criticalCount > 0
                ? "border-red-500/30 dark:border-red-400/30"
                : "border-amber-500/30 dark:border-amber-400/30"
            }
          >
            <CardHeader className="pb-3">
              <CardTitle className="flex items-center gap-2 text-base">
                {criticalCount > 0 ? (
                  <XCircle className="h-5 w-5 text-red-500 dark:text-red-400" />
                ) : (
                  <AlertTriangle className="h-5 w-5 text-amber-500 dark:text-amber-400" />
                )}
                Recommendations
              </CardTitle>
            </CardHeader>
            <CardContent className="space-y-3">
              {props.recommendations.map((rec, i) => (
                <div
                  key={i}
                  className={`rounded-lg border p-3 ${
                    rec.severity === "critical"
                      ? "border-red-500/30 bg-red-500/5 dark:border-red-400/30 dark:bg-red-400/10"
                      : rec.severity === "high"
                        ? "border-amber-500/30 bg-amber-500/5 dark:border-amber-400/30 dark:bg-amber-400/10"
                        : "border-border/50 bg-muted/30"
                  }`}
                >
                  <div className="flex items-start gap-2">
                    <SeverityIcon severity={rec.severity} />
                    <div className="min-w-0 flex-1">
                      <p className="text-sm font-medium">{rec.title}</p>
                      <p className="text-xs text-muted-foreground mt-0.5">{rec.message}</p>
                      {rec.fix && (
                        <pre className="mt-2 rounded bg-muted/50 p-2 text-xs font-mono whitespace-pre-wrap overflow-x-auto">
                          {rec.fix}
                        </pre>
                      )}
                    </div>
                  </div>
                </div>
              ))}
            </CardContent>
          </Card>
        )}

        {/* ── Collapsible Sections ── */}
        <div className="space-y-3">
          {/* Application */}
          <CollapsibleSection
            title="Application"
            icon={<Globe className="h-4 w-4 text-sky-500 dark:text-sky-400" />}
            status={
              props.laravel.debug && props.laravel.environment === "production"
                ? "critical"
                : "good"
            }
          >
            <div className="space-y-1">
              <InfoRow label="Name" value={props.laravel.name} />
              <InfoRow label="Laravel Version" value={props.laravel.version} />
              <InfoRow label="Environment" value={props.laravel.environment} />
              <InfoRow label="Detected Tooling" value={props.environment.detected} />
              <InfoRow label="php.ini" value={props.environment.ini_path} />
              <InfoRow
                label="Debug Mode"
                value={props.laravel.debug ? "Enabled" : "Disabled"}
                ok={!props.laravel.debug || props.laravel.environment !== "production"}
              />
              <InfoRow label="URL" value={props.laravel.url} />
              <InfoRow label="Timezone" value={props.laravel.timezone} />
              <InfoRow label="Locale" value={props.laravel.locale} />
              <Separator className="my-2" />
              <InfoRow
                label="Config Cached"
                value={props.laravel_cache.config_cached ? "Yes" : "No"}
                ok={props.laravel_cache.config_cached || !props.environment.is_production}
              />
              <InfoRow
                label="Routes Cached"
                value={props.laravel_cache.routes_cached ? "Yes" : "No"}
                ok={props.laravel_cache.routes_cached || !props.environment.is_production}
              />
              <InfoRow
                label="Events Cached"
                value={props.laravel_cache.events_cached ? "Yes" : "No"}
              />
              <InfoRow
                label="Maintenance Mode"
                value={props.laravel_cache.is_down_for_maintenance ? "Active" : "Off"}
                ok={!props.laravel_cache.is_down_for_maintenance}
              />
            </div>
          </CollapsibleSection>

          {/* PHP Configuration */}
          <CollapsibleSection
            title="PHP Configuration"
            icon={<Cpu className="h-4 w-4 text-sky-500 dark:text-sky-400" />}
            status={props.php.memory_limit_ok && props.php.version_ok ? "good" : "warning"}
          >
            <div className="space-y-1">
              <InfoRow label="Version" value={props.php.version} ok={props.php.version_ok} />
              <InfoRow label="SAPI" value={props.php.sapi} />
              <InfoRow
                label="Memory Limit"
                value={props.php.memory_limit}
                ok={props.php.memory_limit_ok}
              />
              <InfoRow label="Max Execution Time" value={`${props.php.max_execution_time}s`} />
              <InfoRow label="Max Input Time" value={`${props.php.max_input_time}s`} />
              <InfoRow label="Max Input Vars" value={props.php.max_input_vars} />
              <InfoRow label="Upload Max" value={props.php.upload_max_filesize} />
              <InfoRow label="Post Max Size" value={props.php.post_max_size} />
              <InfoRow
                label="Display Errors"
                value={props.php.display_errors}
                ok={
                  props.php.display_errors === "0" ||
                  props.php.display_errors === "off" ||
                  props.php.display_errors === "" ||
                  props.environment.is_local
                }
              />
              <InfoRow label="Loaded Extensions" value={props.php.loaded_extensions_count} />
              <Separator className="my-2" />
              <InfoRow label="php.ini Path" value={props.php.ini_path} />
              <InfoRow label="Scanned INI" value={props.php.scanned_ini_dirs} />
            </div>
          </CollapsibleSection>

          {/* OPcache */}
          <CollapsibleSection
            title="OPcache"
            icon={<Activity className="h-4 w-4 text-purple-500 dark:text-purple-400" />}
            status={
              props.opcache.enabled ? "good" : props.opcache.extension_loaded ? "warning" : "info"
            }
          >
            <div className="space-y-1">
              <InfoRow
                label="Extension"
                value={props.opcache.extension_loaded ? "Loaded" : "Not loaded"}
                ok={props.opcache.extension_loaded}
              />
              <InfoRow
                label="Enabled"
                value={props.opcache.enabled ? "Yes" : "No"}
                ok={props.opcache.enabled}
              />
              {props.opcache.enabled && props.opcache.status && (
                <>
                  <Separator className="my-2" />
                  <InfoRow
                    label="Hit Rate"
                    value={`${props.opcache.status.hit_rate}%`}
                    ok={props.opcache.status.hit_rate_ok}
                  />
                  <InfoRow label="Memory Used" value={props.opcache.status.used_memory} />
                  <InfoRow label="Memory Free" value={props.opcache.status.free_memory} />
                  <InfoRow label="Usage" value={`${props.opcache.status.memory_usage_percent}%`} />
                  <InfoRow
                    label="Cached Files"
                    value={`${props.opcache.status.cached_files} / ${props.opcache.status.max_cached_files}`}
                  />
                  <InfoRow
                    label="Hits / Misses"
                    value={`${props.opcache.status.hits.toLocaleString()} / ${props.opcache.status.misses.toLocaleString()}`}
                  />
                  <Separator className="my-2" />
                  <p className="text-xs font-medium text-muted-foreground mt-2 mb-1">Directives</p>
                  {Object.entries(props.opcache.config).map(([key, val]) => (
                    <InfoRow key={key} label={key} value={val} />
                  ))}
                </>
              )}
            </div>
          </CollapsibleSection>

          {/* PHP Extensions */}
          <CollapsibleSection
            title="PHP Extensions"
            icon={<Plug className="h-4 w-4 text-emerald-500 dark:text-emerald-400" />}
            status={all_extensions_loaded ? "good" : "critical"}
          >
            <div className="space-y-4">
              <div>
                <p className="text-xs font-medium text-muted-foreground mb-2">
                  Required & Recommended
                </p>
                <div className="grid grid-cols-2 gap-1 sm:grid-cols-3">
                  {props.extensions.map((ext) => (
                    <div key={ext.name} className="flex items-center gap-2 py-1 text-sm">
                      {ext.loaded ? (
                        <CheckCircle2 className="h-3.5 w-3.5 shrink-0 text-emerald-500 dark:text-emerald-400" />
                      ) : (
                        <XCircle className="h-3.5 w-3.5 shrink-0 text-red-500 dark:text-red-400" />
                      )}
                      <span className={ext.required ? "font-medium" : "text-muted-foreground"}>
                        {ext.name}
                      </span>
                      {ext.required && !ext.loaded && (
                        <Badge variant="destructive" className="text-[9px] px-1 py-0">
                          enable
                        </Badge>
                      )}
                      {!ext.required && !ext.loaded && (
                        <Badge variant="secondary" className="text-[9px] px-1 py-0">
                          optional
                        </Badge>
                      )}
                    </div>
                  ))}
                </div>
              </div>
              {props.all_loaded_extensions.length > 0 && (
                <>
                  <Separator />
                  <div>
                    <p className="text-xs font-medium text-muted-foreground mb-2">
                      All Loaded ({props.all_loaded_extensions.length})
                    </p>
                    <div className="flex flex-wrap gap-1.5">
                      {props.all_loaded_extensions.map((ext) => (
                        <span
                          key={ext}
                          className="inline-flex items-center gap-1 rounded-md bg-muted px-2 py-0.5 text-[11px] font-mono"
                        >
                          {ext}
                        </span>
                      ))}
                    </div>
                  </div>
                </>
              )}
            </div>
          </CollapsibleSection>

          {/* Database */}
          <CollapsibleSection
            title="Database"
            icon={<Database className="h-4 w-4 text-cyan-500 dark:text-cyan-400" />}
            status={props.database.version_ok ? "good" : "critical"}
          >
            <div className="space-y-1">
              <InfoRow label="Connection" value={props.database.connection} />
              <InfoRow label="Driver" value={props.database.driver} />
              <InfoRow label="Database" value={props.database.name} />
              <InfoRow
                label="Version"
                value={props.database.version}
                ok={props.database.version_ok}
              />
              <InfoRow label="Size" value={props.database.size} />
              <InfoRow label="Tables" value={props.database.table_count} />
              <InfoRow label="Charset" value={props.database.charset} />
              <InfoRow label="Collation" value={props.database.collation} />
            </div>
          </CollapsibleSection>

          {/* Server & OS */}
          <CollapsibleSection
            title="Server & OS"
            icon={<Server className="h-4 w-4 text-purple-500 dark:text-purple-400" />}
            status={props.server.is_https ? "good" : "warning"}
          >
            <div className="space-y-1">
              <InfoRow label="Software" value={props.server.software} />
              <InfoRow
                label="HTTPS"
                value={props.server.is_https ? "Yes" : "No"}
                ok={props.server.is_https}
              />
              <InfoRow label="Protocol" value={props.server.server_protocol} />
              <InfoRow label="Host" value={props.server.http_host} />
              <InfoRow label="IP" value={props.server.server_ip} />
              <InfoRow label="Document Root" value={props.server.document_root} />
              <Separator className="my-2" />
              <InfoRow label="OS" value={props.os.os} />
              <InfoRow label="OS Family" value={props.os.os_family} />
              <InfoRow label="Architecture" value={props.os.arch} />
              <InfoRow label="Hostname" value={props.os.hostname} />
              <InfoRow label="Kernel" value={props.os.kernel} />
            </div>
          </CollapsibleSection>

          {/* Storage / Cache */}
          <CollapsibleSection
            title="Storage & Cache"
            icon={<FolderOpen className="h-4 w-4 text-rose-500 dark:text-rose-400" />}
            status={props.storage.dirs.every((d) => d.writable) ? "good" : "critical"}
          >
            <div className="space-y-1">
              {props.storage.disk && (
                <>
                  <div className="grid grid-cols-2 gap-3 mb-3">
                    <MiniCard value={props.storage.disk.free} label="Disk Free" />
                    <MiniCard
                      value={`${props.storage.disk.percent}%`}
                      label="Disk Used"
                      color={props.storage.disk.percent >= 90 ? "var(--red-500)" : undefined}
                    />
                  </div>
                  <InfoRow
                    label="Disk Usage"
                    value={`${props.storage.disk.used} / ${props.storage.disk.total}`}
                    ok={props.storage.disk.percent < 90}
                  />
                  <Separator className="my-2" />
                </>
              )}
              <div className="grid grid-cols-2 gap-3 mb-3">
                <MiniCard
                  value={props.storage.dirs.reduce((s, d) => s + d.file_count, 0)}
                  label="Total Files"
                />
                <MiniCard value={props.storage.total_size} label="Total Size" />
              </div>
              {props.storage.dirs.map((dir) => (
                <div key={dir.name} className="space-y-1 mb-3">
                  <p className="text-xs font-medium text-muted-foreground">{dir.name}</p>
                  <InfoRow label="Exists" value={dir.exists ? "Yes" : "No"} ok={dir.exists} />
                  <InfoRow label="Writable" value={dir.writable ? "Yes" : "No"} ok={dir.writable} />
                  <InfoRow label="Files" value={dir.file_count} />
                  <InfoRow label="Size" value={dir.size} />
                </div>
              ))}
            </div>
          </CollapsibleSection>

          {/* Build Cache */}
          <CollapsibleSection
            title="Build Cache (Vite)"
            icon={<Layers className="h-4 w-4 text-sky-500 dark:text-sky-400" />}
            status={props.build.exists ? "good" : "critical"}
          >
            <div className="space-y-1">
              <InfoRow
                label="Manifest"
                value={props.build.exists ? "Exists" : "Missing"}
                ok={props.build.exists}
              />
              {props.build.exists && (
                <>
                  <InfoRow label="Last Built" value={props.build.modified_at ?? "?"} />
                  <InfoRow label="Age" value={props.build.age ?? "?"} />
                  {props.build.age_hours !== undefined && props.build.age_hours > 24 && (
                    <p className="text-[10px] text-amber-500 dark:text-amber-400 mt-1">
                      Build is over 24 hours old. Run{" "}
                      <code className="text-[10px]">npm run build</code> after code changes.
                    </p>
                  )}
                </>
              )}
            </div>
          </CollapsibleSection>

          {/* Services Config */}
          <CollapsibleSection
            title="Services Configuration"
            icon={<Settings className="h-4 w-4 text-cyan-500 dark:text-cyan-400" />}
            status={
              props.queue.driver !== "sync" || props.cache.driver !== "file" ? "good" : "info"
            }
          >
            <div className="space-y-1">
              <p className="text-xs font-medium text-muted-foreground mb-1">Cache</p>
              <InfoRow label="Driver" value={props.cache.driver} />
              <InfoRow label="Stores" value={props.cache.stores.join(", ")} />
              <InfoRow label="TTL" value={`${props.cache.ttl}s`} />
              <Separator className="my-2" />
              <p className="text-xs font-medium text-muted-foreground mb-1">Queue</p>
              <InfoRow label="Driver" value={props.queue.driver} />
              <InfoRow label="Connection" value={props.queue.connection} />
              <Separator className="my-2" />
              <p className="text-xs font-medium text-muted-foreground mb-1">Session</p>
              <InfoRow label="Driver" value={props.session.driver} />
              <InfoRow label="Lifetime" value={`${props.session.lifetime} min`} />
              <InfoRow label="Secure" value={String(props.session.secure ?? "auto")} />
              <Separator className="my-2" />
              <p className="text-xs font-medium text-muted-foreground mb-1">Mail</p>
              <InfoRow label="Driver" value={props.mail.driver} />
              {props.mail.mailer.transport && (
                <InfoRow label="Transport" value={props.mail.mailer.transport} />
              )}
              {props.mail.mailer.host && <InfoRow label="Host" value={props.mail.mailer.host} />}
              {props.mail.mailer.port && <InfoRow label="Port" value={props.mail.mailer.port} />}
              {props.mail.mailer.username && (
                <InfoRow label="Username" value={props.mail.mailer.username} />
              )}
              {props.mail.mailer.encryption && (
                <InfoRow label="Encryption" value={props.mail.mailer.encryption} />
              )}
              <InfoRow
                label="From"
                value={
                  props.mail.from_address
                    ? `${props.mail.from_name} <${props.mail.from_address}>`
                    : "Not set"
                }
                ok={!!props.mail.from_address}
              />
              <Separator className="my-2" />
              <p className="text-xs font-medium text-muted-foreground mb-1">Filesystem</p>
              <InfoRow label="Default" value={props.filesystem.default} />
              <InfoRow label="Disks" value={props.filesystem.disks.join(", ")} />
            </div>
          </CollapsibleSection>

          {/* Logging */}
          <CollapsibleSection
            title="Logging"
            icon={<FileText className="h-4 w-4 text-rose-500 dark:text-rose-400" />}
            status="info"
          >
            <div className="space-y-1">
              <InfoRow label="Channel" value={props.logs.channel} />
              <InfoRow label="Driver" value={props.logs.driver} />
              <InfoRow label="Level" value={props.logs.level} />
              <InfoRow label="Path" value={props.logs.path ?? "Stack driver"} />
              {props.logs.path ? <InfoRow label="Size" value={props.logs.size} /> : null}
              {props.logs.recent_errors > 0 && (
                <InfoRow
                  label="Recent Errors"
                  value={props.logs.recent_errors}
                  ok={props.logs.recent_errors === 0}
                />
              )}
              {props.logs.files.length > 1 && (
                <>
                  <Separator className="my-2" />
                  <p className="text-xs font-medium text-muted-foreground mb-1">
                    Log Files ({props.logs.files.length})
                  </p>
                  {props.logs.files.map((f) => (
                    <InfoRow key={f.name} label={f.name} value={`${f.size} — ${f.modified}`} />
                  ))}
                </>
              )}
            </div>
          </CollapsibleSection>

          {/* Scheduled Commands */}
          {props.schedule.length > 0 && (
            <CollapsibleSection
              title="Scheduled Commands"
              icon={<Timer className="h-4 w-4 text-amber-500 dark:text-amber-400" />}
              status="info"
            >
              <div className="space-y-1">
                <p className="text-xs text-muted-foreground mb-3">
                  {props.schedule.length} app-namespaced artisan commands
                </p>
                <div className="overflow-x-auto">
                  <table className="w-full text-sm">
                    <thead>
                      <tr className="border-b text-left text-muted-foreground">
                        <th className="pb-2 pr-4 font-medium text-xs">Command</th>
                        <th className="pb-2 font-medium text-xs">Description</th>
                      </tr>
                    </thead>
                    <tbody>
                      {props.schedule.map((cmd) => (
                        <tr key={cmd.name} className="border-b last:border-0">
                          <td className="py-1.5 pr-4 font-mono text-xs">{cmd.name}</td>
                          <td className="py-1.5 text-xs text-muted-foreground">
                            {cmd.description}
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              </div>
            </CollapsibleSection>
          )}

          {/* Statistics */}
          <CollapsibleSection
            title="Statistics"
            icon={<Users className="h-4 w-4 text-cyan-500 dark:text-cyan-400" />}
            status="info"
          >
            <div className="space-y-1">
              <div className="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-3">
                <MiniCard value={props.users.total} label="Total Users" />
                <MiniCard value={props.users.verified} label="Verified" />
                <MiniCard value={props.users.unverified} label="Unverified" />
                <MiniCard value={props.users.admin} label="Admins" />
              </div>
              <InfoRow label="Total Users" value={props.users.total} />
              <InfoRow
                label="Verified"
                value={props.users.verified}
                ok={props.users.verified > 0}
              />
              <InfoRow label="Unverified" value={props.users.unverified} />
              <InfoRow label="Admins" value={props.users.admin} />
              <InfoRow label="Feature Modules" value={props.modules.length} />
            </div>
          </CollapsibleSection>

          {/* Diagnostics */}
          {props.debug_access_url && (
            <CollapsibleSection
              title="Diagnostics"
              icon={<Bug className="h-4 w-4 text-rose-500 dark:text-rose-400" />}
              status="info"
            >
              <div className="space-y-1">
                <p className="text-xs text-muted-foreground mb-2">
                  Access the{" "}
                  <code className="rounded bg-muted px-1 py-0.5 text-xs font-mono">debug.php</code>{" "}
                  diagnostic tool with your unique access key:
                </p>
                <div className="rounded-md bg-muted p-3">
                  <code className="break-all text-xs">{props.debug_access_url}</code>
                </div>
                <p className="mt-2 text-xs text-muted-foreground">
                  Bookmark this URL — unique per installation, stable unless APP_KEY changes.
                </p>
              </div>
            </CollapsibleSection>
          )}
        </div>
      </div>
    </AdminLayout>
  );
}
