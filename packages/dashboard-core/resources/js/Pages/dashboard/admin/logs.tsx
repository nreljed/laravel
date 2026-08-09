import { Head, Link, router } from "@inertiajs/react";
import { useMemo, useState } from "react";
import {
  Check,
  ChevronLeft,
  ChevronRight,
  ClipboardCopy,
  Download,
  FileText,
  Search,
} from "lucide-react";
import AdminLayout from "@/Layouts/AdminLayout";
import {
  Breadcrumb,
  BreadcrumbItem,
  BreadcrumbList,
  BreadcrumbPage,
  BreadcrumbSeparator,
} from "@/Components/ui/breadcrumb";
import { Badge } from "@/Components/ui/badge";
import { Button } from "@/Components/ui/button";
import { Card, CardContent } from "@/Components/ui/card";
import { Input } from "@/Components/ui/input";
import { NativeSelect, NativeSelectOption } from "@/Components/ui/native-select";
import { cn } from "@/lib/utils";

/*
─── Types ───
*/

interface LogFileEntry {
  name: string;
  size: string;
  modified: string;
}

interface LogPage {
  name: string | null;
  size: string | null;
  modified: string | null;
  lines: string[];
  page: number;
  per_page: number;
  total_lines: number;
  total_pages: number;
  truncated: boolean;
}

interface LogViewerProps {
  channel: string;
  level: string;
  driver: string;
  path: string | null;
  files: LogFileEntry[];
  selected: string | null;
  log: LogPage;
}

const LEVELS = ["all", "CRITICAL", "ERROR", "WARNING", "NOTICE", "INFO", "DEBUG"] as const;

type Level = (typeof LEVELS)[number];

const PER_PAGE_OPTIONS = ["100", "200", "500", "1000"];

function lineClass(line: string) {
  if (/\.(ALERT|CRITICAL|EMERGENCY)\b/.test(line)) {
    return "text-red-600 dark:text-red-400 font-semibold";
  }
  if (/\.ERROR\b/.test(line)) {
    return "text-rose-500 dark:text-rose-400";
  }
  if (/\.WARNING\b/.test(line)) {
    return "text-amber-600 dark:text-amber-400";
  }
  if (/\.(NOTICE|INFO|DEBUG)\b/.test(line)) {
    return "text-muted-foreground";
  }
  return "text-foreground";
}

/*
─── Page ───
*/

export default function LogViewer({
  channel,
  level,
  driver,
  path,
  files,
  selected,
  log,
}: LogViewerProps) {
  const [query, setQuery] = useState("");
  const [levelFilter, setLevelFilter] = useState<Level>("all");
  const [copied, setCopied] = useState(false);

  const navigate = (params: Record<string, string | number>) => {
    router.get(route("dashboard.admin.logs", { file: selected ?? undefined }), params, {
      preserveState: true,
      preserveScroll: true,
    });
  };

  const changeFile = (name: string) => {
    router.get(route("dashboard.admin.logs", { file: name }), {
      per_page: log.per_page,
      page: 1,
    });
  };

  const filteredLines = useMemo(() => {
    const q = query.trim().toLowerCase();

    return log.lines.filter((line) => {
      if (q && !line.toLowerCase().includes(q)) {
        return false;
      }
      if (levelFilter !== "all" && !new RegExp(String.raw`\.${levelFilter}\b`).test(line)) {
        return false;
      }
      return true;
    });
  }, [log.lines, query, levelFilter]);

  const handleCopy = async () => {
    const text = filteredLines.join("\n");
    if (!text) {
      return;
    }
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
    setCopied(true);
    setTimeout(() => setCopied(false), 2500);
  };

  const firstIndex = log.total_lines - (log.page - 1) * log.per_page - filteredLines.length + 1;

  return (
    <AdminLayout title="Log Viewer">
      <Head title="Log Viewer" />

      <div className="mx-auto max-w-6xl space-y-6 px-4">
        <Breadcrumb>
          <BreadcrumbList>
            <BreadcrumbItem>
              <Link
                href={route("dashboard.index")}
                className="transition-colors hover:text-foreground"
              >
                Dashboard
              </Link>
            </BreadcrumbItem>
            <BreadcrumbSeparator />
            <BreadcrumbItem>
              <Link
                href={route("dashboard.admin.system-health")}
                className="transition-colors hover:text-foreground"
              >
                System Health
              </Link>
            </BreadcrumbItem>
            <BreadcrumbSeparator />
            <BreadcrumbItem>
              <BreadcrumbPage>Log Viewer</BreadcrumbPage>
            </BreadcrumbItem>
          </BreadcrumbList>
        </Breadcrumb>

        {/* ── Header ── */}
        <div className="flex flex-wrap items-start justify-between gap-3">
          <div className="flex items-center gap-3 min-w-0">
            <FileText className="mt-0.5 h-6 w-6 text-muted-foreground shrink-0" />
            <div className="min-w-0">
              <div className="flex items-center gap-2 flex-wrap">
                <h2 className="text-lg font-semibold text-foreground">Log Viewer</h2>
                <Badge variant="secondary" className="font-mono">
                  {selected ?? "No log file"}
                </Badge>
              </div>
              <div className="flex items-center gap-2 flex-wrap text-xs text-muted-foreground mt-0.5">
                <span>
                  channel <code className="font-mono">{channel}</code>
                </span>
                <span>·</span>
                <span>
                  level <code className="font-mono">{level}</code>
                </span>
                <span>·</span>
                <span>
                  driver <code className="font-mono">{driver}</code>
                </span>
                {path && (
                  <>
                    <span>·</span>
                    <code className="font-mono">{path}</code>
                  </>
                )}
              </div>
            </div>
          </div>
          <div className="flex items-center gap-2 shrink-0">
            <Button
              variant="outline"
              size="sm"
              onClick={handleCopy}
              disabled={filteredLines.length === 0}
            >
              {copied ? (
                <>
                  <Check className="mr-1.5 h-3.5 w-3.5" /> Copied
                </>
              ) : (
                <>
                  <ClipboardCopy className="mr-1.5 h-3.5 w-3.5" /> Copy
                </>
              )}
            </Button>
            {selected && (
              <Button asChild variant="outline" size="sm">
                <a href={route("dashboard.admin.logs.download", { file: selected })}>
                  <Download className="mr-1.5 h-3.5 w-3.5" /> Download
                </a>
              </Button>
            )}
          </div>
        </div>

        {/* ── Controls ── */}
        <div className="flex flex-wrap items-center gap-3">
          <NativeSelect
            value={selected ?? ""}
            onChange={(e) => {
              const name = e.target.value;
              if (name) changeFile(name);
            }}
            className="min-w-52"
            size="sm"
            aria-label="Log file"
          >
            {files.length === 0 && <NativeSelectOption value="">No log files</NativeSelectOption>}
            {files.map((f) => (
              <NativeSelectOption key={f.name} value={f.name}>
                {f.name} ({f.size})
              </NativeSelectOption>
            ))}
          </NativeSelect>

          <div className="relative">
            <Search className="pointer-events-none absolute top-1/2 left-2.5 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
            <Input
              value={query}
              onChange={(e) => setQuery(e.target.value)}
              placeholder="Search lines…"
              className="h-8 w-56 pl-8 text-sm"
            />
          </div>

          <NativeSelect
            value={levelFilter}
            onChange={(e) => setLevelFilter(e.target.value as Level)}
            size="sm"
            className="w-36"
            aria-label="Minimum log level"
          >
            {LEVELS.map((l) => (
              <NativeSelectOption key={l} value={l}>
                {l === "all" ? "All levels" : l}
              </NativeSelectOption>
            ))}
          </NativeSelect>

          <NativeSelect
            value={String(log.per_page)}
            onChange={(e) => navigate({ per_page: Number(e.target.value), page: 1 })}
            size="sm"
            className="w-36"
            aria-label="Lines per page"
          >
            {PER_PAGE_OPTIONS.map((n) => (
              <NativeSelectOption key={n} value={n}>
                {n} lines
              </NativeSelectOption>
            ))}
          </NativeSelect>

          <div className="ml-auto flex items-center gap-2 text-sm text-muted-foreground">
            <Button
              variant="outline"
              size="sm"
              onClick={() => navigate({ page: log.page - 1 })}
              disabled={log.page <= 1}
            >
              <ChevronLeft className="h-3.5 w-3.5" />
              Newer
            </Button>
            <span className="whitespace-nowrap">
              Page {log.page} / {log.total_pages}
            </span>
            <Button
              variant="outline"
              size="sm"
              onClick={() => navigate({ page: log.page + 1 })}
              disabled={log.page >= log.total_pages}
            >
              Older
              <ChevronRight className="h-3.5 w-3.5" />
            </Button>
          </div>
        </div>

        {/* ── Lines ── */}
        <Card className="overflow-hidden">
          <CardContent className="p-0">
            <div className="flex items-center justify-between gap-3 border-b border-border/50 px-4 py-2 text-xs text-muted-foreground">
              <span>
                {selected ? (
                  <>
                    {log.size} · modified {log.modified} · {log.total_lines.toLocaleString()} lines
                    total
                    {log.truncated && (
                      <span className="ml-2 text-amber-500">tail may be truncated</span>
                    )}
                  </>
                ) : (
                  "No log file selected."
                )}
              </span>
              {filteredLines.length !== log.lines.length && (
                <span>
                  showing {filteredLines.length} of {log.lines.length} lines
                </span>
              )}
            </div>
            <pre className="max-h-[65vh] overflow-auto bg-muted/40 p-3 text-[11px] font-mono leading-relaxed">
              {selected === null ? (
                <span className="text-muted-foreground">
                  Create a log file under <code>storage/logs</code> to start inspecting entries.
                </span>
              ) : filteredLines.length === 0 ? (
                <span className="text-muted-foreground">
                  {log.lines.length === 0
                    ? "Log is empty."
                    : "No lines match the current search and level filters."}
                </span>
              ) : (
                filteredLines.map((line, i) => (
                  <div key={`${firstIndex + i}:${line}`} className="flex gap-3">
                    <span className="w-10 shrink-0 text-right text-muted-foreground/60 select-none">
                      {firstIndex + i}
                    </span>
                    <span
                      className={cn("min-w-0 whitespace-pre-wrap break-words", lineClass(line))}
                    >
                      {line}
                    </span>
                  </div>
                ))
              )}
            </pre>
          </CardContent>
        </Card>

        {/* ── Footer note ── */}
        <p className="text-xs text-muted-foreground">
          Lines are read from the tail of the file, newest first. Use <strong>Older</strong> to walk
          back through previous pages, the level filter to focus on failures, and{" "}
          <strong>Copy</strong> to grab the filtered view for a ticket or AI assistant.
        </p>
      </div>
    </AdminLayout>
  );
}
