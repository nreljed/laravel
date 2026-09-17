import { History } from "lucide-react";
import { Head } from "@inertiajs/react";
import AdminLayout from "../../../Layouts/AdminLayout";
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "../../../Components/ui/card";
import { Badge } from "../../../Components/ui/badge";

interface DeployRelease {
  id: number;
  tag: string;
  status: "active" | "rolled-back" | "failed";
  error: string | null;
  deployed_by: number | null;
  deployed_at: string | null;
}

interface DeployHistoryProps {
  releases: DeployRelease[];
}

const statusBadge: Record<string, { label: string; variant: "default" | "secondary" | "destructive" }> = {
  active: { label: "Active", variant: "default" },
  "rolled-back": { label: "Rolled back", variant: "secondary" },
  failed: { label: "Failed", variant: "destructive" },
};

export default function DeployHistory({ releases }: DeployHistoryProps) {
  return (
    <AdminLayout title="Deploy History">
      <Head title="Deploy History" />
      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <History className="h-5 w-5" />
            Deploy History
          </CardTitle>
          <CardDescription>
            Released versions. Rollbacks are performed by the deploy runner
            (GitHub Actions &rsaquo; Deploy Runner (Auto-Repair) &rsaquo;
            release-rollback) — this page is read-only.
          </CardDescription>
        </CardHeader>
        <CardContent>
          <div className="space-y-3">
            {releases.length === 0 && <p className="text-sm text-muted-foreground">No releases recorded.</p>}
            {releases.map((release) => {
              const badge = statusBadge[release.status] ?? statusBadge.failed;
              return (
                <div
                  key={release.id}
                  className="flex items-center justify-between rounded-md border p-3"
                >
                  <div>
                    <div className="flex items-center gap-2">
                      <span className="font-mono text-sm font-medium">{release.tag}</span>
                      <Badge variant={badge.variant}>{badge.label}</Badge>
                    </div>
                    {release.error && <p className="mt-1 text-xs text-destructive">{release.error}</p>}
                  </div>
                </div>
              );
            })}
          </div>
        </CardContent>
      </Card>
    </AdminLayout>
  );
}

