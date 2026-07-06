import { Head, usePage } from "@inertiajs/react";
import {
  Database,
  Cpu,
  Globe,
  HardDrive,
  CheckCircle2,
  XCircle,
  Users,
  Puzzle,
  Boxes,
  Lightbulb,
  Activity,
  Layers,
  Terminal,
  FileText,
  AlertTriangle,
  Info,
} from "lucide-react";
import AdminLayout from "@/Layouts/AdminLayout";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/Components/ui/card";
import { Badge } from "@/Components/ui/badge";

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
  ini_path: string;
}

interface LaravelInfo {
  version: string;
  environment: string;
  debug: boolean;
  url: string;
  timezone: string;
  locale: string;
}

interface OpcacheInfo {
  extension_loaded: boolean;
  enabled: boolean;
  config: Record<string, string>;
  status: OpcacheStatus | null;
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
  size: string;
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
}

interface EnvironmentInfo {
  type: string;
  detected: string;
}

interface Recommendation {
  severity: "critical" | "high" | "info";
  title: string;
  message: string;
  fix: string;
}

interface CacheInfo {
  driver: string;
  ttl: number;
}

interface QueueInfo {
  driver: string;
  connection: string;
}

interface MailInfo {
  driver: string;
}

interface SessionInfo {
  driver: string;
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

interface LogInfo {
  channel: string;
  level: string;
  path: string | null;
}

interface UsersInfo {
  total: number;
  verified: number;
  unverified: number;
  admin: number;
}

interface BuildInfo {
  exists: boolean;
  modified_at?: string;
  modified_timestamp?: number;
  age?: string;
}

interface ModuleDebugItem {
  name: string;
  slug: string;
  enabled: boolean;
  booted: boolean;
  dependencies: string[];
  nav: {
    parent?: { title: string; url: string | null };
    children?: { title: string; url: string | null }[];
  };
}

interface SystemHealthProps {
  php: PhpInfo;
  laravel: LaravelInfo;
  opcache: OpcacheInfo;
  extensions: Extension[];
  all_loaded_extensions: string[];
  database: DatabaseInfo;
  server: ServerInfo;
  environment: EnvironmentInfo;
  recommendations: Recommendation[];
  cache: CacheInfo;
  queue: QueueInfo;
  mail: MailInfo;
  session: SessionInfo;
  filesystem: FilesystemInfo;
  modules: ModuleItem[];
  cron: CronItem[];
  logs: LogInfo;
  users: UsersInfo;
  build: BuildInfo;
}

function severityIcon(severity: string) {
  switch (severity) {
    case "critical": {
      return AlertTriangle;
    }
    case "high": {
      return AlertTriangle;
    }
    default: {
      return Info;
    }
  }
}

function severityVariant(severity: string): "destructive" | "secondary" | "outline" {
  switch (severity) {
    case "critical": {
      return "destructive";
    }
    case "high": {
      return "secondary";
    }
    default: {
      return "outline";
    }
  }
}

export default function SystemHealth(props: SystemHealthProps) {
  const pageProps = usePage().props as Record<string, unknown>;
  const moduleDebugData = pageProps.moduleDebugData as ModuleDebugItem[] | undefined;

  const sections = [
    {
      title: "Application",
      icon: Globe,
      items: [
        { label: "Environment", value: props.laravel.environment },
        {
          label: "Debug Mode",
          value: props.laravel.debug ? "Enabled" : "Disabled",
          highlight: props.laravel.debug,
        },
        { label: "Laravel Version", value: props.laravel.version },
        { label: "PHP Version", value: props.php.version },
        { label: "Server Software", value: props.server.software },
        { label: "Server OS", value: props.php.sapi },
      ],
    },
    {
      title: "Database",
      icon: Database,
      items: [
        { label: "Connection", value: props.database.connection },
        { label: "Driver", value: props.database.driver },
        { label: "Database", value: props.database.name },
        { label: "Version", value: props.database.version },
        { label: "Size", value: props.database.size },
      ],
    },
    {
      title: "System",
      icon: Cpu,
      items: [
        { label: "Cache Driver", value: props.cache.driver },
        { label: "Session Driver", value: props.session.driver },
        { label: "Queue Driver", value: props.queue.driver },
        { label: "Mail Driver", value: props.mail.driver },
        { label: "Filesystem", value: props.filesystem.default },
      ],
    },
    {
      title: "PHP Configuration",
      icon: HardDrive,
      items: [
        {
          label: "Memory Limit",
          value: props.php.memory_limit,
          highlight: !props.php.memory_limit_ok,
        },
        { label: "Max Upload Size", value: props.php.upload_max_filesize },
        { label: "Max Post Size", value: props.php.post_max_size },
        { label: "Max Execution Time", value: `${props.php.max_execution_time}s` },
      ],
    },
    {
      title: "Statistics",
      icon: Users,
      items: [
        { label: "Total Users", value: String(props.users.total) },
        { label: "Verified", value: String(props.users.verified) },
        { label: "Unverified", value: String(props.users.unverified) },
        { label: "Admins", value: String(props.users.admin) },
        { label: "Database Modules", value: String(props.modules.length) },
      ],
    },
    {
      title: "Logging",
      icon: FileText,
      items: [
        { label: "Channel", value: props.logs.channel },
        { label: "Level", value: props.logs.level },
        { label: "Path", value: props.logs.path ?? "stack driver" },
      ],
    },
  ];

  return (
    <AdminLayout title="System Health">
      <Head title="System Health" />

      <div className="space-y-6">
        <div>
          <h2 className="text-2xl font-bold tracking-tight">System Health</h2>
          <p className="text-muted-foreground">Server configuration and diagnostics.</p>
        </div>

        {props.recommendations.length > 0 && (
          <Card>
            <CardHeader className="flex flex-row items-center gap-2">
              <Lightbulb className="h-5 w-5 text-muted-foreground" />
              <CardTitle className="text-lg">Recommendations</CardTitle>
              <CardDescription>
                {props.recommendations.filter((r) => r.severity === "critical").length} critical
              </CardDescription>
            </CardHeader>
            <CardContent>
              <div className="space-y-3">
                {props.recommendations.map((rec, i) => {
                  const RecIcon = severityIcon(rec.severity);
                  return (
                    <div key={i} className="flex gap-3 rounded-lg border p-3">
                      <RecIcon
                        className={`mt-0.5 h-5 w-5 shrink-0 ${
                          rec.severity === "critical"
                            ? "text-destructive"
                            : rec.severity === "high"
                              ? "text-orange-500"
                              : "text-muted-foreground"
                        }`}
                      />
                      <div className="min-w-0 flex-1 space-y-1">
                        <div className="flex items-center gap-2">
                          <span className="text-sm font-medium">{rec.title}</span>
                          <Badge
                            variant={severityVariant(rec.severity)}
                            className="text-[10px] px-1.5 py-0"
                          >
                            {rec.severity}
                          </Badge>
                        </div>
                        <p className="text-sm text-muted-foreground">{rec.message}</p>
                        {rec.fix && (
                          <p className="text-xs text-muted-foreground">
                            <span className="font-medium">Fix:</span> {rec.fix}
                          </p>
                        )}
                      </div>
                    </div>
                  );
                })}
              </div>
            </CardContent>
          </Card>
        )}

        <div className="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
          {sections.map((section) => {
            const Icon = section.icon;
            return (
              <Card key={section.title}>
                <CardHeader className="flex flex-row items-center gap-2">
                  <Icon className="h-5 w-5 text-muted-foreground" />
                  <CardTitle className="text-lg">{section.title}</CardTitle>
                </CardHeader>
                <CardContent>
                  <dl className="space-y-3">
                    {section.items.map((item) => (
                      <div key={item.label} className="flex items-center justify-between gap-2">
                        <dt className="text-sm text-muted-foreground">{item.label}</dt>
                        <dd
                          className={`text-sm font-medium truncate ${item.highlight ? "text-destructive" : ""}`}
                        >
                          {item.value}
                        </dd>
                      </div>
                    ))}
                  </dl>
                </CardContent>
              </Card>
            );
          })}
        </div>

        {props.opcache.extension_loaded && (
          <Card>
            <CardHeader className="flex flex-row items-center gap-2">
              <Activity className="h-5 w-5 text-muted-foreground" />
              <CardTitle className="text-lg">OPcache</CardTitle>
              <CardDescription>{props.opcache.enabled ? "Enabled" : "Disabled"}</CardDescription>
            </CardHeader>
            <CardContent>
              <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                {props.opcache.config && Object.entries(props.opcache.config).length > 0 && (
                  <div>
                    <h4 className="mb-2 text-sm font-medium">Configuration</h4>
                    <dl className="space-y-1">
                      {Object.entries(props.opcache.config).map(([key, val]) => (
                        <div key={key} className="flex justify-between gap-2 text-xs">
                          <dt className="text-muted-foreground">{key}</dt>
                          <dd className="font-medium">{val}</dd>
                        </div>
                      ))}
                    </dl>
                  </div>
                )}
                {props.opcache.status && (
                  <>
                    <div>
                      <h4 className="mb-2 text-sm font-medium">Memory</h4>
                      <dl className="space-y-1">
                        <div className="flex justify-between gap-2 text-xs">
                          <dt className="text-muted-foreground">Used</dt>
                          <dd className="font-medium">{props.opcache.status.used_memory}</dd>
                        </div>
                        <div className="flex justify-between gap-2 text-xs">
                          <dt className="text-muted-foreground">Free</dt>
                          <dd className="font-medium">{props.opcache.status.free_memory}</dd>
                        </div>
                        <div className="flex justify-between gap-2 text-xs">
                          <dt className="text-muted-foreground">Wasted</dt>
                          <dd className="font-medium">{props.opcache.status.wasted_memory}</dd>
                        </div>
                        <div className="flex justify-between gap-2 text-xs">
                          <dt className="text-muted-foreground">Usage</dt>
                          <dd className="font-medium">
                            {props.opcache.status.memory_usage_percent}%
                          </dd>
                        </div>
                      </dl>
                    </div>
                    <div>
                      <h4 className="mb-2 text-sm font-medium">Statistics</h4>
                      <dl className="space-y-1">
                        <div className="flex justify-between gap-2 text-xs">
                          <dt className="text-muted-foreground">Cached Files</dt>
                          <dd className="font-medium">
                            {props.opcache.status.cached_files} /{" "}
                            {props.opcache.status.max_cached_files}
                          </dd>
                        </div>
                        <div className="flex justify-between gap-2 text-xs">
                          <dt className="text-muted-foreground">Hit Rate</dt>
                          <dd
                            className={`font-medium ${!props.opcache.status.hit_rate_ok ? "text-destructive" : ""}`}
                          >
                            {props.opcache.status.hit_rate}%
                          </dd>
                        </div>
                        <div className="flex justify-between gap-2 text-xs">
                          <dt className="text-muted-foreground">Hits</dt>
                          <dd className="font-medium">
                            {props.opcache.status.hits.toLocaleString()}
                          </dd>
                        </div>
                        <div className="flex justify-between gap-2 text-xs">
                          <dt className="text-muted-foreground">Misses</dt>
                          <dd className="font-medium">
                            {props.opcache.status.misses.toLocaleString()}
                          </dd>
                        </div>
                      </dl>
                    </div>
                  </>
                )}
              </div>
            </CardContent>
          </Card>
        )}

        <div className="grid gap-6 md:grid-cols-2">
          <Card>
            <CardHeader className="flex flex-row items-center gap-2">
              <Puzzle className="h-5 w-5 text-muted-foreground" />
              <CardTitle className="text-lg">PHP Extensions</CardTitle>
              <CardDescription>
                {props.extensions.filter((e) => e.loaded).length} / {props.extensions.length}{" "}
                required loaded
              </CardDescription>
            </CardHeader>
            <CardContent>
              <div className="flex flex-wrap gap-2">
                {props.extensions.map((ext) => (
                  <Badge
                    key={ext.name}
                    variant={ext.loaded ? "default" : "destructive"}
                    className="gap-1"
                  >
                    {ext.loaded ? (
                      <CheckCircle2 className="h-3 w-3" />
                    ) : (
                      <XCircle className="h-3 w-3" />
                    )}
                    {ext.name}
                  </Badge>
                ))}
              </div>
            </CardContent>
          </Card>

          {props.build.exists && (
            <Card>
              <CardHeader className="flex flex-row items-center gap-2">
                <Layers className="h-5 w-5 text-muted-foreground" />
                <CardTitle className="text-lg">Build</CardTitle>
                <CardDescription>Vite build manifest</CardDescription>
              </CardHeader>
              <CardContent>
                <dl className="space-y-3">
                  <div className="flex items-center justify-between gap-2">
                    <dt className="text-sm text-muted-foreground">Status</dt>
                    <dd className="text-sm font-medium text-green-600">Built</dd>
                  </div>
                  <div className="flex items-center justify-between gap-2">
                    <dt className="text-sm text-muted-foreground">Last Built</dt>
                    <dd className="text-sm font-medium">{props.build.modified_at ?? "N/A"}</dd>
                  </div>
                  <div className="flex items-center justify-between gap-2">
                    <dt className="text-sm text-muted-foreground">Age</dt>
                    <dd className="text-sm font-medium">{props.build.age ?? "N/A"}</dd>
                  </div>
                </dl>
              </CardContent>
            </Card>
          )}
        </div>

        {props.cron.length > 0 && (
          <Card>
            <CardHeader className="flex flex-row items-center gap-2">
              <Terminal className="h-5 w-5 text-muted-foreground" />
              <CardTitle className="text-lg">Scheduled Commands</CardTitle>
              <CardDescription>{props.cron.length} app-namespaced artisan commands</CardDescription>
            </CardHeader>
            <CardContent>
              <div className="overflow-x-auto">
                <table className="w-full text-sm">
                  <thead>
                    <tr className="border-b text-left text-muted-foreground">
                      <th className="pb-2 pr-4 font-medium">Command</th>
                      <th className="pb-2 font-medium">Description</th>
                    </tr>
                  </thead>
                  <tbody>
                    {props.cron.map((cmd) => (
                      <tr key={cmd.name} className="border-b last:border-0">
                        <td className="py-2 pr-4 font-mono text-xs">{cmd.name}</td>
                        <td className="py-2 text-muted-foreground">{cmd.description}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </CardContent>
          </Card>
        )}

        {moduleDebugData && moduleDebugData.length > 0 && (
          <Card>
            <CardHeader className="flex flex-row items-center gap-2">
              <Boxes className="h-5 w-5 text-muted-foreground" />
              <CardTitle className="text-lg">Modules</CardTitle>
              <CardDescription>
                {moduleDebugData.filter((m) => m.enabled).length} enabled / {moduleDebugData.length}{" "}
                total
              </CardDescription>
            </CardHeader>
            <CardContent>
              <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                {moduleDebugData.map((mod) => (
                  <div key={mod.slug} className="flex items-start gap-3 rounded-lg border p-3">
                    {mod.enabled && mod.booted ? (
                      <CheckCircle2 className="mt-0.5 h-4 w-4 shrink-0 text-green-500" />
                    ) : (
                      <XCircle className="mt-0.5 h-4 w-4 shrink-0 text-destructive" />
                    )}
                    <div className="min-w-0 flex-1">
                      <div className="flex items-center gap-1.5">
                        <span className="text-sm font-medium leading-none">{mod.name}</span>
                        <span className="text-xs text-muted-foreground">({mod.slug})</span>
                      </div>
                      {mod.dependencies.length > 0 && (
                        <p className="mt-1 text-xs text-muted-foreground">
                          Depends on: {mod.dependencies.join(", ")}
                        </p>
                      )}
                      <div className="mt-1 flex flex-wrap gap-1">
                        <Badge
                          variant={mod.enabled ? "default" : "secondary"}
                          className="text-[10px] px-1.5 py-0"
                        >
                          {mod.enabled ? "enabled" : "disabled"}
                        </Badge>
                        <Badge
                          variant={mod.booted ? "default" : "secondary"}
                          className="text-[10px] px-1.5 py-0"
                        >
                          {mod.booted ? "booted" : "not booted"}
                        </Badge>
                      </div>
                    </div>
                  </div>
                ))}
              </div>
            </CardContent>
          </Card>
        )}
      </div>
    </AdminLayout>
  );
}
