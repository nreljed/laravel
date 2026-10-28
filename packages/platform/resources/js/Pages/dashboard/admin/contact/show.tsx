import { Head, router, usePage } from "@inertiajs/react";
import AdminLayout from "../../../../Layouts/AdminLayout";
import { Card, CardContent, CardHeader, CardTitle } from "../../../../Components/ui/card";
import { StatusBadge } from "../../../../Components/ui/status-badge";
import { Button } from "../../../../Components/ui/button";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "../../../../Components/ui/select";
import { Separator } from "../../../../Components/ui/separator";
import {
  ArrowLeft,
  Mail,
  Trash2,
  Clock,
  Globe,
  Monitor,
  MessageSquare,
  User,
} from "lucide-react";

interface Submission {
  id: number;
  name: string;
  email: string;
  subject: string | null;
  message: string;
  status: string;
  ip: string | null;
  user_agent: string | null;
  read_at: string | null;
  created_at: string;
}

interface PageProps {
  submission: Submission;
  statuses?: string[];
}

const statusMeta: Record<string, { label: string; variant: "warning" | "success" | "accentCyan" }> = {
  new: { label: "New", variant: "warning" },
  read: { label: "Read", variant: "success" },
  archived: { label: "Archived", variant: "accentCyan" },
};

export default function AdminContactShow() {
  const { props } = usePage();
  const { submission, statuses = ["new", "read", "archived"] } = props as unknown as PageProps;

  const meta = statusMeta[submission.status] ?? {
    label: submission.status,
    variant: "info" as const,
  };

  const updateStatus = (status: string) => {
    router.patch(
      route("dashboard.admin.contact.update", submission.id),
      { status },
      { preserveScroll: true },
    );
  };

  const destroy = () => {
    if (!window.confirm("Delete this contact message permanently?")) return;
    router.delete(route("dashboard.admin.contact.destroy", submission.id));
  };

  return (
    <AdminLayout title="Contact Message">
      <Head title={`Contact — ${submission.name}`} />

      <div className="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <Button variant="ghost" size="sm" onClick={() => router.get(route("dashboard.admin.contact.index"))}>
          <ArrowLeft className="size-4" />
          Back to messages
        </Button>

        <div className="flex items-center gap-2">
          <StatusBadge variant={meta.variant}>{meta.label}</StatusBadge>

          <Select value={submission.status} onValueChange={updateStatus}>
            <SelectTrigger className="w-[140px]">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              {statuses.map((value) => (
                <SelectItem key={value} value={value}>
                  {statusMeta[value]?.label ?? value}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>

          <Button variant="destructive" size="sm" onClick={destroy}>
            <Trash2 className="size-4" />
            Delete
          </Button>
        </div>
      </div>

      <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_280px]">
        <div className="space-y-6">
          <Card>
            <CardHeader>
              <CardTitle className="flex items-center gap-2 text-base">
                <MessageSquare className="size-4" />
                {submission.subject ?? "No subject"}
              </CardTitle>
            </CardHeader>
            <CardContent>
              <p className="text-sm leading-relaxed whitespace-pre-wrap">{submission.message}</p>
            </CardContent>
          </Card>
        </div>

        <div className="space-y-6">
          <Card>
            <CardHeader>
              <CardTitle className="flex items-center gap-2 text-base">
                <User className="size-4" />
                Sender
              </CardTitle>
            </CardHeader>
            <CardContent className="space-y-3 text-sm">
              <div>
                <p className="text-xs text-muted-foreground">Name</p>
                <p className="font-medium">{submission.name}</p>
              </div>
              <Separator />
              <div>
                <p className="text-xs text-muted-foreground">Email</p>
                <a
                  href={`mailto:${submission.email}`}
                  className="inline-flex items-center gap-1.5 font-medium text-primary hover:underline"
                >
                  <Mail className="size-3.5" />
                  {submission.email}
                </a>
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle className="flex items-center gap-2 text-base">
                <Clock className="size-4" />
                Details
              </CardTitle>
            </CardHeader>
            <CardContent className="space-y-3 text-sm">
              <div>
                <p className="text-xs text-muted-foreground">Received</p>
                <p>{new Date(submission.created_at).toLocaleString()}</p>
              </div>
              {submission.read_at && (
                <>
                  <Separator />
                  <div>
                    <p className="text-xs text-muted-foreground">First read</p>
                    <p>{new Date(submission.read_at).toLocaleString()}</p>
                  </div>
                </>
              )}
              <Separator />
              <div>
                <p className="text-xs text-muted-foreground">IP address</p>
                <p className="flex items-center gap-1.5 font-mono text-xs">
                  <Globe className="size-3.5" />
                  {submission.ip ?? "—"}
                </p>
              </div>
              {submission.user_agent && (
                <>
                  <Separator />
                  <div>
                    <p className="text-xs text-muted-foreground">Browser</p>
                    <p className="flex items-start gap-1.5 text-xs text-muted-foreground">
                      <Monitor className="size-3.5 shrink-0 mt-0.5" />
                      {submission.user_agent}
                    </p>
                  </div>
                </>
              )}
            </CardContent>
          </Card>
        </div>
      </div>
    </AdminLayout>
  );
}
