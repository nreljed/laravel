import { useCallback, useEffect, useRef, useState } from "react";
import { Head } from "@inertiajs/react";
import { Activity } from "lucide-react";
import AdminLayout from "../../../Layouts/AdminLayout";
import { Button } from "../../../Components/ui/button";
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "../../../Components/ui/card";
import { Badge } from "../../../Components/ui/badge";
import { toast } from "../../../Components/ui/toast";
import { jsonHeaders } from "../../../lib/gallery-utils";

interface DeployOutputRow {
  level: string;
  message: string;
  at?: string;
}

interface DeployCheckPayload {
  status: string;
  tag: string | null;
  output: DeployOutputRow[];
  core?: boolean;
}

interface DeployStatusProps {
  deploy: DeployCheckPayload | null;
}

const statusBadge: Record<string, { label: string; variant: "default" | "secondary" | "destructive" }> = {
  idle: { label: "Idle", variant: "secondary" },
  running: { label: "Deploying", variant: "default" },
  failed: { label: "Failed", variant: "destructive" },
};

const POLL_INTERVAL = 2000;

export default function DeployStatus({ deploy }: DeployStatusProps) {
  const [check, setCheck] = useState<DeployCheckPayload | null>(deploy);
  const failuresRef = useRef(0);

  const poll = useCallback(async () => {
    if (document.visibilityState !== "visible") {
      return;
    }

    try {
      const response = await fetch(route("dashboard.admin.deploy.status.data") as string, {
        headers: jsonHeaders(),
      });
      if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
      }
      const data = (await response.json()) as DeployCheckPayload;
      failuresRef.current = 0;
      setCheck(data);
    } catch {
      failuresRef.current += 1;
      if (failuresRef.current === 3) {
        toast.add({ title: "Status poll failed", type: "error" });
      }
    }
  }, []);

  useEffect(() => {
    const id = setInterval(poll, POLL_INTERVAL);
    return () => clearInterval(id);
  }, [poll]);

  const badge = statusBadge[check?.status ?? "idle"] ?? statusBadge.failed;

  return (
    <AdminLayout title="Deploy Status">
      <Head title="Deploy Status" />
      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <Activity className="h-5 w-5" />
            Deploy Status
          </CardTitle>
          <CardDescription>Current release health and deploy progress</CardDescription>
        </CardHeader>
        <CardContent>
          <div className="space-y-3">
            <div className="flex items-center gap-2">
              <Badge variant={badge.variant}>{badge.label}</Badge>
              {check?.tag && <span className="font-mono text-sm">{check.tag}</span>}
            </div>
            {check?.output && check.output.length > 0 && (
              <div className="max-h-40 space-y-1 overflow-y-auto rounded-md border p-2 font-mono text-xs">
                {check.output.slice(-8).map((row, i) => (
                  <div key={i} className="flex gap-2">
                    <span className="text-muted-foreground">{row.level}</span>
                    <span>{row.message}</span>
                  </div>
                ))}
              </div>
            )}
            <Button
              variant="outline"
              size="sm"
              onClick={() => {
                failuresRef.current = 0;
                void poll();
              }}
            >
              Refresh
            </Button>
          </div>
        </CardContent>
      </Card>
    </AdminLayout>
  );
}
